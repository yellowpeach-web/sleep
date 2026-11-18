<?php

namespace WPML\TM\ATE\Handshake\Corrections;

use WPML\TM\ATE\Handshake\Notice;
use WPML\TM\ATE\Handshake\Routes;
use WPML\TM\ATE\Handshake\State;
use WPML\TM\ATE\Handshake\Support\Box;
use WPML\TM\ATE\Handshake\Support\Texts;

class Page {

	const ID = 'wpml-updated-translations';

	const OP_APPLY = 'apply';
	const OP_SKIP  = 'skip';

	const REASON_SHOWN = 120;

	private $offer;

	private $decision;

	private $formatDate;

	public function __construct( ?Offer $offer = null, ?Decision $decision = null, $formatDate = null ) {
		$this->offer      = $offer ?: new Offer();
		$this->decision   = $decision ?: new Decision();
		$this->formatDate = $formatDate ?: function ( $time ) {
			return (string) wp_date( (string) get_option( 'date_format', 'F j, Y' ), (int) $time );
		};
	}

	public static function tabDefinition() {
		$mayAct = Notice::userMayAct();
		$count  = Offer::count();

		return [
			'label'     => Texts::correctionsTab( $count ),
			'tooltip'   => Texts::correctionsPromise(),
			'visible'   => $mayAct && $count > 0,
			'reachable' => $mayAct,
		];
	}

	public static function printPage() {
		try {
			$html = ( new self() )->render();
		} catch ( \Throwable $e ) {
			return;
		}

		if ( '' === $html ) {
			return;
		}

		echo wp_kses( $html, self::allowedHtml() );
		wp_print_inline_script_tag( self::script() );
	}

	public function render() {
		if ( ! Notice::userMayAct() ) {
			return '';
		}

		$html  = '<section id="' . esc_attr( self::ID ) . '" class="wpml-uc" aria-labelledby="' . esc_attr( self::ID ) . '-title"';
		$html .= ' data-wpml-uc-url="' . esc_url( Routes::url( Routes::CORRECTIONS_DECIDE ) ) . '" data-wpml-uc-nonce="' . esc_attr( wp_create_nonce( 'wp_rest' ) ) . '">';
		$html .= '<style>' . Box::css() . self::css() . '</style>';
		$html .= '<div data-wpml-uc-inner="1">' . $this->inner( $this->offer->read() ) . '</div>';
		$html .= '<div hidden data-wpml-uc-failed="1">' . self::errorCard() . '</div>';

		return $html . '</section>';
	}

	public static function answer( $op, array $ids ) {
		return wp_kses( ( new self() )->respond( (string) $op, $ids ), self::allowedHtml() );
	}

	public function respond( $op, array $ids ) {
		$read = $this->offer->read();

		if ( ! $read['ok'] ) {
			return $this->inner( $read );
		}

		if ( self::OP_APPLY === $op ) {
			$result = $this->decision->apply( $ids, $read['rows'] );

			if ( ! $result['ok'] ) {
				return $this->inner( $read, self::errorCard(), $ids );
			}

			if ( $result['accepted'] < 1 ) {
				return $this->inner( $read, '', $ids );
			}

			$accepted = array_map( 'intval', $ids );
			$left     = array_values(
				array_filter(
					$read['rows'],
					function ( $row ) use ( $accepted ) {
						return ! empty( $row['deleted'] ) || ! in_array( (int) $row['ate_id'], $accepted, true );
					}
				)
			);

			return $this->afterDecision( $left, Box::card( 'ok', Texts::correctionsAccepted( $result['accepted'] ), [], '', '', 'accepted' ) );
		}

		if ( self::OP_SKIP === $op ) {
			$result = $this->decision->skipAll( $read['rows'] );

			if ( ! $result['ok'] ) {
				return $this->inner( $read, self::errorCard(), $ids );
			}

			return $this->afterDecision( [], Box::card( 'ok', Texts::correctionsSkipped( $result['skipped'] ), [], '', '', 'skipped' ) );
		}

		return $this->inner( $read );
	}

	private function afterDecision( array $left, $card ) {
		State::update( Offer::summaryChanges( $left ) );

		return $this->inner(
			[
				'ok'    => true,
				'rows'  => $left,
				'error' => '',
			],
			$card
		);
	}

	public function inner( array $read, $card = '', $ticked = null ) {
		if ( ! $read['ok'] && '' === $card ) {
			$card = self::errorCard();
		}

		$html  = '<h2 id="' . esc_attr( self::ID ) . '-title">' . esc_html( Texts::correctionsTitle() ) . '</h2>';
		$html .= '<div class="wpml-uc-cardslot" data-wpml-uc-card="1" aria-live="polite">' . $card . '</div>';

		if ( ! $read['ok'] ) {
			return $html;
		}

		$rows = $read['rows'];

		if ( ! $rows ) {
			return $html . '<p class="wpml-uc-empty" data-wpml-uc-empty="1">' . esc_html( Texts::correctionsEmpty() ) . '</p>';
		}

		$html .= $this->intro( $rows );
		$html .= '<details class="wpml-uc-q"><summary>' . esc_html( Texts::correctionsAcceptQuestion() ) . '</summary><p>' . esc_html( Texts::correctionsAcceptAnswer() ) . '</p></details>';
		$html .= '<details class="wpml-uc-q"><summary>' . esc_html( Texts::correctionsSkipQuestion() ) . '</summary><p>' . esc_html( Texts::correctionsSkipAnswer() ) . '</p></details>';

		return $html . $this->table( $rows, $ticked );
	}

	private function intro( array $rows ) {
		$batches = [];

		foreach ( $rows as $row ) {
			$key = (string) $row['batch_id'];

			if ( ! isset( $batches[ $key ] ) ) {
				$batches[ $key ] = [
					'count'        => 0,
					'corrected_at' => 0,
					'reason'       => '',
				];
			}

			$batches[ $key ]['count']++;
			$batches[ $key ]['corrected_at'] = max( $batches[ $key ]['corrected_at'], (int) $row['corrected_at'] );

			if ( '' === $batches[ $key ]['reason'] && '' !== (string) $row['reason'] ) {
				$batches[ $key ]['reason'] = (string) $row['reason'];
			}
		}

		uasort(
			$batches,
			function ( $a, $b ) {
				return $b['corrected_at'] - $a['corrected_at'];
			}
		);

		$html = '<div class="wpml-uc-intro">';

		foreach ( $batches as $batch ) {
			$date   = call_user_func( $this->formatDate, $batch['corrected_at'] > 0 ? $batch['corrected_at'] : time() );
			$reason = '' !== $batch['reason'] ? $batch['reason'] : Texts::correctionsNoReasonIntro();
			$html  .= '<p data-wpml-uc-batch="1">' . esc_html( Texts::correctionsBatchIntro( $batch['count'], $date ) ) . ' ' . esc_html( $reason ) . '</p>';
		}

		return $html . '<p class="wpml-uc-promise">' . esc_html( Texts::correctionsPromise() ) . '</p></div>';
	}

	private function table( array $rows, $ticked ) {
		usort(
			$rows,
			function ( $a, $b ) {
				$byDeleted = (int) ! empty( $a['deleted'] ) - (int) ! empty( $b['deleted'] );

				if ( 0 !== $byDeleted ) {
					return $byDeleted;
				}

				$byTitle = strcasecmp( (string) $a['title'], (string) $b['title'] );

				return 0 !== $byTitle ? $byTitle : strcasecmp( (string) $a['language_name'], (string) $b['language_name'] );
			}
		);

		$ticked     = null === $ticked ? null : array_map( 'intval', $ticked );
		$selectable = 0;
		$checked    = 0;
		$body       = '';

		foreach ( $rows as $row ) {
			$deleted = ! empty( $row['deleted'] );
			$isOn    = ! $deleted && ( null === $ticked || in_array( (int) $row['ate_id'], $ticked, true ) );

			if ( ! $deleted ) {
				$selectable++;
			}

			if ( $isOn ) {
				$checked++;
			}

			$body .= $this->row( $row, $deleted, $isOn );
		}

		$html  = '<table class="widefat wpml-uc-table"><thead><tr>';
		$html .= '<td class="check-column"><input type="checkbox" data-wpml-uc-all="1" aria-label="' . esc_attr( Texts::correctionsSelectAll() ) . '"' . ( $selectable > 0 && $checked === $selectable ? ' checked="checked"' : '' ) . ( $selectable < 1 ? ' disabled="disabled"' : '' ) . '></td>';
		$html .= '<th scope="col">' . esc_html( Texts::correctionsPageColumn() ) . '</th>';
		$html .= '<th scope="col">' . esc_html( Texts::correctionsLanguageColumn() ) . '</th>';
		$html .= '<th scope="col">' . esc_html( Texts::correctionsWhyColumn() ) . '</th>';
		$html .= '<th scope="col">' . esc_html( Texts::correctionsNoteColumn() ) . '</th>';
		$html .= '</tr></thead><tbody>' . $body . '</tbody></table>';

		$labels = [];

		for ( $n = 0; $n <= $selectable; $n++ ) {
			$labels[ $n ] = Texts::correctionsApply( $n );
		}

		$html .= '<div class="wpml-uc-actions">';
		$html .= '<button type="button" class="button button-primary" data-wpml-uc-op="' . esc_attr( self::OP_APPLY ) . '" data-wpml-uc-labels="' . esc_attr( (string) wp_json_encode( $labels ) ) . '"' . ( $checked < 1 ? ' disabled="disabled"' : '' ) . '>' . esc_html( Texts::correctionsApply( $checked ) ) . '</button>';
		$html .= '<button type="button" class="button" data-wpml-uc-op="' . esc_attr( self::OP_SKIP ) . '">' . esc_html( Texts::correctionsSkipAll() ) . '</button>';
		$html .= '<a class="wpml-uc-later" href="' . esc_url( admin_url( 'admin.php?page=tm/menu/main.php' ) ) . '">' . esc_html( Texts::correctionsDecideLater() ) . '</a>';

		return $html . '</div>';
	}

	private function row( array $row, $deleted, $isOn ) {
		$title    = (string) $row['title'];
		$language = (string) $row['language_name'];
		$label    = $deleted ? Texts::correctionsPageDeleted() : $title . ', ' . $language;

		$html  = '<tr data-wpml-uc-row="' . esc_attr( (string) (int) $row['ate_id'] ) . '"' . ( $deleted ? ' data-wpml-uc-deleted="1"' : '' ) . '>';
		$html .= '<th scope="row" class="check-column"><input type="checkbox" name="ids[]" value="' . esc_attr( (string) (int) $row['ate_id'] ) . '" data-wpml-uc-pick="1" aria-label="' . esc_attr( $label ) . '"'
			. ( $isOn ? ' checked="checked"' : '' ) . ( $deleted ? ' disabled="disabled"' : '' ) . '></th>';

		if ( $deleted ) {
			$page = esc_html( Texts::correctionsPageDeleted() );
		} elseif ( '' !== (string) $row['edit_link'] ) {
			$page = '<a href="' . esc_url( (string) $row['edit_link'] ) . '">' . esc_html( $title ) . '</a>';
		} else {
			$page = esc_html( $title );
		}

		$html .= '<td>' . $page . '</td>';
		$html .= '<td>' . esc_html( $language ) . '</td>';
		$html .= '<td>' . self::why( (string) $row['reason'] ) . '</td>';
		$html .= '<td>' . ( ! empty( $row['edited'] ) ? esc_html( Texts::correctionsEdited() ) : '' ) . '</td>';

		return $html . '</tr>';
	}

	private static function why( $reason ) {
		if ( '' === $reason ) {
			return esc_html( Texts::correctionsNoReason() );
		}

		$length = function_exists( 'mb_strlen' ) ? mb_strlen( $reason ) : strlen( $reason );

		if ( $length <= self::REASON_SHOWN ) {
			return esc_html( $reason );
		}

		$shown = function_exists( 'mb_substr' ) ? mb_substr( $reason, 0, self::REASON_SHOWN ) : substr( $reason, 0, self::REASON_SHOWN );
		$rest  = function_exists( 'mb_substr' ) ? mb_substr( $reason, self::REASON_SHOWN ) : substr( $reason, self::REASON_SHOWN );

		return esc_html( $shown ) . '<details class="wpml-uc-more"><summary>' . esc_html( Texts::correctionsMore() ) . '</summary>' . esc_html( $rest ) . '</details>';
	}

	public static function errorCard() {
		return Box::card( 'bad', Texts::correctionsNoAnswer(), [], '', '', 'error' );
	}

	public static function allowedHtml() {
		$out    = Box::allowedHtml();
		$common = [
			'class'      => true,
			'id'         => true,
			'scope'      => true,
			'aria-label' => true,
			'data-*'     => true,
		];

		foreach ( [ 'table', 'thead', 'tbody', 'tr', 'th', 'td' ] as $tag ) {
			$out[ $tag ] = $common;
		}

		$out['input']  = $common + [
			'type'     => true,
			'name'     => true,
			'value'    => true,
			'checked'  => true,
			'disabled' => true,
		];
		$out['button'] = $out['button'] + [ 'disabled' => true ];

		return $out;
	}

	public static function script() {
		return '(function(){var root=document.getElementById(' . wp_json_encode( self::ID ) . ');if(!root){return;}'
			. 'var inner=root.querySelector("[data-wpml-uc-inner]"),failed=root.querySelector("[data-wpml-uc-failed]");'
			. 'function picks(){return Array.prototype.slice.call(root.querySelectorAll("[data-wpml-uc-pick]:not(:disabled)"));}'
			. 'function sync(){var all=root.querySelector("[data-wpml-uc-all]"),apply=root.querySelector("[data-wpml-uc-op=\"apply\"]"),p=picks(),n=p.filter(function(c){return c.checked;}).length;'
			. 'if(all){all.checked=p.length>0&&n===p.length;all.indeterminate=n>0&&n<p.length;}'
			. 'if(apply){var l=JSON.parse(apply.getAttribute("data-wpml-uc-labels")||"{}");apply.textContent=l[n]||l[0]||apply.textContent;apply.disabled=n<1;}}'
			. 'function busy(on){root.querySelectorAll("[data-wpml-uc-op]").forEach(function(b){b.disabled=on;});if(!on){sync();}}'
			. 'root.addEventListener("change",function(e){var t=e.target;if(t.hasAttribute("data-wpml-uc-all")){picks().forEach(function(c){c.checked=t.checked;});}sync();});'
			. 'root.addEventListener("click",function(e){var b=e.target.closest("[data-wpml-uc-op]");if(!b||b.disabled){return;}var ids=picks().filter(function(c){return c.checked;}).map(function(c){return parseInt(c.value,10);});'
			. 'busy(true);fetch(root.getAttribute("data-wpml-uc-url"),{method:"POST",credentials:"same-origin",headers:{"Content-Type":"application/json","X-WP-Nonce":root.getAttribute("data-wpml-uc-nonce")},body:JSON.stringify({op:b.getAttribute("data-wpml-uc-op"),ids:ids})})'
			. '.then(function(r){if(!r.ok){throw new Error(String(r.status));}return r.json();}).then(function(d){if(d&&typeof d.html==="string"){inner.innerHTML=d.html;sync();}else{throw new Error("answer");}})'
			. '.catch(function(){var s=root.querySelector("[data-wpml-uc-card]");if(s&&failed){s.innerHTML=failed.innerHTML;}busy(false);});});'
			. 'sync();})();';
	}

	private static function css() {
		return '.wpml-uc{color:#374151;font-size:13px;line-height:1.5}.wpml-uc h2{margin:0 0 8px;font-size:16px;font-weight:600;color:#111827;padding:0}'
			. '.wpml-uc .wpml-td-card{margin:8px 0 12px}.wpml-uc-intro p{margin:4px 0;overflow-wrap:anywhere}.wpml-uc-promise{font-weight:600;color:#111827}'
			. '.wpml-uc-q{margin:6px 0}.wpml-uc-q summary,.wpml-uc-more summary{cursor:pointer;color:#2271b1}.wpml-uc-q p{margin:4px 0 0 16px;max-width:70em}'
			. '.wpml-uc-table{margin:12px 0 0}.wpml-uc-table td,.wpml-uc-table th{vertical-align:top;overflow-wrap:anywhere}.wpml-uc-table .check-column{width:2.2em}'
			. '.wpml-uc-more{display:inline}.wpml-uc-more[open]{display:block}'
			. '.wpml-uc-actions{display:flex;flex-wrap:wrap;gap:10px;align-items:center;margin:12px 0 0}.wpml-uc-empty{margin:8px 0;color:#374151}'
			. '@media (max-width:782px){.wpml-uc-table thead{display:none}.wpml-uc-table tr{display:block;border-top:1px solid #e5e7eb;padding:6px 0}.wpml-uc-table td,.wpml-uc-table th{display:block;padding:2px 8px}}';
	}
}
