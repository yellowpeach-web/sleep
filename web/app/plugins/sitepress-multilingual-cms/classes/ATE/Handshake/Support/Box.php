<?php

namespace WPML\TM\ATE\Handshake\Support;

use WPML\TM\ATE\Handshake\Marks;
use WPML\TM\ATE\Sync\SyncChunk;

class Box {

	const ID = 'wpml-translation-delivery';

	const CONDITIONS = [ 'failed_on_site', 'unreachable', 'blocked', 'signature', 'hears_but_silent' ];

	const SKIPPED_ALL_BLOCKED = 'all_recipients_blocked';

	const PROBE_PROBLEMS = [ Check::UNREACHABLE, Check::BLOCKED, Check::SIGNATURE, Check::KEYS_REFUSED, Check::SITE_MOVED ];

	const KEYS_REFUSED = 'keys_refused';

	const CLOSED_WHILE_CHECKING = 'problem_closed';

	private $formatTime;

	private $wakePath;

	public function __construct( $formatTime = null, $wakePath = null ) {
		$this->formatTime = $formatTime ?: [ self::class, 'siteTime' ];
		$this->wakePath   = $wakePath ?: [ Check::class, 'siteWakePath' ];
	}


	public function render( array $facts, array $system, array $blocks, $condition, $ajaxUrl, $nonce ) {
		$condition = in_array( $condition, self::CONDITIONS, true ) ? $condition : '';

		$html  = '<section id="' . esc_attr( self::ID ) . '" class="wpml-td" aria-labelledby="' . esc_attr( self::ID ) . '-title"';
		$html .= ' data-wpml-td-ajax="' . esc_url( $ajaxUrl ) . '" data-wpml-td-nonce="' . esc_attr( $nonce ) . '"';
		$html .= ' data-wpml-td-autorun="' . esc_attr( $condition ) . '">';
		$html .= '<style>' . self::css() . '</style>';
		$html .= '<div class="wpml-td-inner" data-wpml-td-inner="1">' . $this->inner( $facts, $system, $blocks, $condition, '' ) . '</div>';
		$html .= '<div class="wpml-td-busy" hidden data-wpml-td-busy="1">' . self::checkingCard() . '</div>';

		return $html . '</section>';
	}

	public function inner( array $facts, array $system, array $blocks, $condition, $card, $found = '', $written = null ) {
		$s    = $this->situation( $facts, $system, $found );
		$html = '';

		if ( ! empty( $blocks['all_blocked'] ) ) {
			$html .= '<div class="wpml-td-allblocked" role="note" data-wpml-td-all-blocked="1">' . self::mark( 'bad' ) . self::bold( Texts::allBlocked() ) . '</div>';
		}

		list( $kind, $status ) = $this->statusLine( $s );

		$html .= '<div class="wpml-td-head"><div><h2 id="' . esc_attr( self::ID ) . '-title">' . esc_html( Texts::title() ) . '</h2><p>' . esc_html( Texts::intro() ) . '</p></div>';
		$html .= '<span class="wpml-td-status s-' . esc_attr( $kind ) . '" data-wpml-td-situation="' . esc_attr( $s['key'] ) . '">' . self::mark( $kind ) . '<span>' . esc_html( $status ) . '</span></span></div>';

		if ( in_array( $condition, self::CONDITIONS, true ) ) {
			$html .= '<div class="wpml-td-mailnote">' . esc_html( Texts::mailNote( $condition, $written ) ) . '</div>';
		}

		$html .= '<div class="wpml-td-cardslot" data-wpml-td-card="1" aria-live="polite">' . $card . '</div>';
		$html .= $this->situationHtml( $s );
		$html .= '<div class="wpml-td-cols"><div class="wpml-td-col" data-wpml-td-column="site"><h3>' . esc_html( Texts::siteColumn() ) . '</h3>' . self::factsHtml( $this->siteFacts( $facts, $s ) ) . '</div>';
		$html .= '<div class="wpml-td-col" data-wpml-td-column="system"><h3>' . esc_html( Texts::systemColumn() ) . '</h3>' . $this->systemHtml( $system, $blocks, $s ) . '</div></div>';

		$together = $this->together( $s );

		if ( '' !== $together ) {
			$html .= '<p class="wpml-td-together"><b>' . esc_html( Texts::readTogether() ) . '</b> ' . esc_html( $together ) . '</p>';
		}

		$html .= $this->eventsHtml( $facts, $system );
		$html .= $this->settingsHtml( $facts );
		$html .= $this->actionsHtml( $s, $facts, $system );

		return $html;
	}


	public function settingsHtml( array $facts ) {
		if ( ! isset( $facts['chunk'] ) || ! is_array( $facts['chunk'] ) ) {
			return '';
		}

		$chunk     = $facts['chunk'];
		$value     = (int) $chunk['value'];
		$isDefault = ! empty( $chunk['is_default'] );
		$field     = self::ID . '-chunk';

		$html  = '<details class="wpml-td-events wpml-td-settings" data-wpml-td-settings="1"><summary>' . esc_html( Texts::supportSettings() ) . '</summary>';
		$html .= '<div class="wpml-td-setting">';
		$html .= '<label for="' . esc_attr( $field ) . '">' . esc_html( Texts::chunkLabel() ) . '</label>';
		$html .= '<p class="wpml-td-line" data-wpml-td-chunk-readout="' . esc_attr( $isDefault ? 'default' : 'set' ) . '">' . esc_html( Texts::chunkReadout( $value, $isDefault, (int) $chunk['default'] ) ) . '</p>';
		$html .= '<div class="wpml-td-setrow"><input type="number" id="' . esc_attr( $field ) . '" min="' . esc_attr( (string) SyncChunk::MIN ) . '" max="' . esc_attr( (string) SyncChunk::MAX ) . '" step="1" inputmode="numeric" value="' . esc_attr( (string) $value ) . '" data-wpml-td-chunk="1">';
		$html .= '<button type="button" class="button" data-wpml-td-action="' . esc_attr( Loader::OP_CHUNK ) . '">' . esc_html( Texts::chunkButton( 'save' ) ) . '</button>';

		if ( ! $isDefault ) {
			$html .= '<button type="button" class="button" data-wpml-td-action="' . esc_attr( Loader::OP_CHUNK_DEFAULT ) . '">' . esc_html( Texts::chunkButton( 'default' ) ) . '</button>';
		}

		$html .= '</div><p class="wpml-td-sethelp">' . esc_html( Texts::chunkHelp() ) . '</p></div></details>';

		return $html;
	}

	public function chunkCard( array $saved ) {
		if ( empty( $saved['ok'] ) ) {
			return self::card( 'warn', Texts::chunkNotSavedTitle(), [ Texts::chunkInvalid() ], '', '', Loader::OP_CHUNK );
		}

		if ( ! empty( $saved['default'] ) ) {
			return self::card( 'ok', Texts::chunkDefaultTitle( (int) $saved['chunk'] ), [], '', '', Loader::OP_CHUNK_DEFAULT );
		}

		return self::card(
			! empty( $saved['clamped'] ) ? 'warn' : 'ok',
			Texts::chunkSavedTitle( (int) $saved['chunk'] ),
			! empty( $saved['clamped'] ) ? [ Texts::chunkClamped( (int) $saved['chunk'] ) ] : [],
			'',
			'',
			Loader::OP_CHUNK
		);
	}


	public function situation( array $facts, array $system, $found = '' ) {
		$state   = SystemView::OK === ( isset( $system['status'] ) ? $system['status'] : '' ) ? (array) $system['state'] : [];
		$episode = isset( $state['episode'] ) && is_array( $state['episode'] ) ? $state['episode'] : [];
		$failed  = isset( $facts['marks'][ Marks::FAILED_ON_SITE ] ) ? count( $facts['marks'][ Marks::FAILED_ON_SITE ] ) : 0;
		$self    = [
			'loopback'      => $facts['loopback']['ok'],
			'cron_disabled' => $facts['cron']['disabled'],
			'cron_overdue'  => $facts['cron']['overdue'],
		];
		$silent  = Check::silentReason( Check::before( $facts ), $self );
		$running = ! empty( $facts['running'] );

		$episodeNamesNothing = isset( $episode['template'] ) && 'failed_on_site' === $episode['template'] && $failed < 1;

		if ( isset( $episode['template'] ) && in_array( $episode['template'], self::CONDITIONS, true ) && ! ( $running && 'hears_but_silent' === $episode['template'] ) && ! $episodeNamesNothing ) {
			$key = (string) $episode['template'];
		} elseif ( $failed > 0 ) {
			$key = 'failed_on_site';
		} elseif ( (int) $facts['acceptance']['refused_at'] > 0 ) {
			$key = 'not_confirmed';
		} elseif ( '' !== $silent ) {
			$key = 'hears_but_silent';
		} elseif ( (int) $facts['in_flight'] > 0 || $running ) {
			$key = 'collecting';
		} else {
			$key = 'all_good';
		}

		$byCheck = in_array( $found, self::PROBE_PROBLEMS, true ) && in_array( $key, [ 'not_confirmed', 'all_good', 'collecting' ], true );

		if ( $byCheck ) {
			$key = (string) $found;
		}

		if ( Check::HEARS_BUT_SILENT === $found && in_array( $key, [ 'all_good', 'collecting' ], true ) && ! $running ) {
			$key = 'hears_but_silent';
		}

		$keysRefused = self::keysRefused( $facts, $system, $found );

		if ( $keysRefused ) {
			$key = self::KEYS_REFUSED;
		}

		if ( 'hears_but_silent' === $key && '' === $silent ) {
			$silent = false === $self['loopback'] ? 'loopback' : ( $self['cron_overdue'] ? 'cron' : 'host' );
		}

		$t       = $this->formatTime;
		$iso     = function ( $value ) {
			return is_string( $value ) && '' !== $value && false !== strtotime( $value ) ? (int) strtotime( $value ) : 0;
		};
		$waiting = $state ? ( isset( $state['pending_count'] ) ? (int) $state['pending_count'] : 0 ) : (int) $facts['in_flight'];
		$sinceAt = $state ? $iso( isset( $state['pending_since'] ) ? $state['pending_since'] : '' ) : (int) $facts['in_flight_since'];
		$callAt  = $iso( isset( $state['last_wakeup_at'] ) ? $state['last_wakeup_at'] : '' );
		$dueAt   = $iso( isset( $episode['due_at'] ) ? $episode['due_at'] : '' );
		$reg     = $facts['registration'];
		$status  = isset( $state['last_wakeup_status'] ) && is_numeric( $state['last_wakeup_status'] ) && (int) $state['last_wakeup_status'] > 0 ? (int) $state['last_wakeup_status'] : null;

		if ( $byCheck ) {
			$callAt = 0;
			$status = null;
		}

		if ( 'signature' === $key && null === $status && (int) $facts['acceptance']['refused_status'] > 0 ) {
			$status = (int) $facts['acceptance']['refused_status'];
		}

		if ( $dueAt < 1 && (int) $facts['acceptance']['refused_at'] > 0 ) {
			$dueAt = (int) $facts['acceptance']['refused_at'];
		}

		return [
			'key'        => $key,
			'failed'     => $failed,
			'waiting'    => $waiting,
			'since'      => $sinceAt > 0 ? $t( $sinceAt ) : '',
			'at'         => $callAt > 0 ? $t( $callAt ) : '',
			'opened'     => $dueAt > 0 ? $t( $dueAt ) : '',
			'status'     => $status,
			'path'       => (string) call_user_func( $this->wakePath ),
			'last_call'  => $facts['last_wake'] ? $t( (int) $facts['last_wake']['at'] ) : '',
			'last_run'   => $facts['last_run'] ? $t( (int) $facts['last_run']['at'] ) : '',
			'plan'       => $facts['last_wake'] ? Texts::planWords( (string) $facts['last_wake']['plan'] ) : '',
			'registered' => false === $reg['matches'] ? (string) $reg['registered'] : '',
			'current'    => '' !== (string) $reg['current'] ? (string) $reg['current'] : (string) $reg['registered'],
			'reason'     => $silent,
			'system'     => $state,
			'episode'    => $episode,
			'issue_link' => Actions::issueLink( isset( $system['state'] ) ? (array) $system['state'] : [] ),
			'stalled'    => isset( $facts['stalled']['cause'] ) && Stalled::NONE !== $facts['stalled']['cause'] && isset( Stalled::ATTRIBUTE[ $facts['stalled']['cause'] ] ),
			'stalled_cause' => isset( $facts['stalled']['cause'] ) ? (string) $facts['stalled']['cause'] : '',
			'keys_since'    => $keysRefused ? self::keysRefusedSince( $facts, $t ) : '',
			'keys_answer'   => $keysRefused && isset( $facts['keys_refused']['answer'] ) ? (string) $facts['keys_refused']['answer'] : '',
		];
	}

	public static function keysRefused( array $facts, array $system, $found = '' ) {
		$status = isset( $system['status'] ) ? (string) $system['status'] : '';

		if ( SystemView::REFUSED === $status || Check::KEYS_REFUSED === $found ) {
			return true;
		}

		return isset( $facts['keys_refused']['at'] ) && (int) $facts['keys_refused']['at'] > 0 && SystemView::OK !== $status;
	}

	private static function keysRefusedSince( array $facts, $t ) {
		$at = isset( $facts['keys_refused']['at'] ) ? (int) $facts['keys_refused']['at'] : 0;

		if ( $at < 1 && 403 === (int) $facts['acceptance']['refused_status'] ) {
			$at = (int) $facts['acceptance']['refused_at'];
		}

		return $at > 0 ? $t( $at ) : '';
	}

	private function statusLine( array $s ) {
		$kinds = [
			'all_good'         => 'ok',
			'collecting'       => 'info',
			'hears_but_silent' => 'warn',
		];

		return [ isset( $kinds[ $s['key'] ] ) ? $kinds[ $s['key'] ] : 'bad', Texts::status( $s['key'], $s['failed'] ) ];
	}

	private function situationHtml( array $s ) {
		$headline = 'collecting' === $s['key'] && ! empty( $s['stalled'] ) ? Texts::headlineStalled( isset( $s['stalled_cause'] ) ? (string) $s['stalled_cause'] : '' ) : Texts::headline( $s['key'], $s['failed'] );
		$html     = '<div class="wpml-td-sit"><p class="wpml-td-headline">' . esc_html( $headline ) . '</p>';
		$rows = $this->situationRows( $s );

		if ( $rows ) {
			$labels = Texts::situationLabels();
			$html  .= '<dl>';

			foreach ( $rows as $i => $text ) {
				if ( ! is_string( $text ) ) {
					continue;
				}

				$rec   = 3 === $i;
				$html .= '<dt>' . esc_html( $labels[ $i ] ) . '</dt><dd' . ( $rec ? ' class="rec"' : '' ) . '>';
				$html .= ( $rec ? '<span class="wpml-td-rectag">' . esc_html( Texts::recommended() ) . '</span> ' : '' ) . self::bold( $text ) . '</dd>';
			}

			$html .= '</dl>';
		}

		return $html . '</div>';
	}

	public function situationRows( array $s ) {
		$means = Texts::means( $s['key'], (int) $s['waiting'], (string) $s['since'] );
		$issue = isset( $s['issue_link'] ) && '' !== (string) $s['issue_link'];

		switch ( $s['key'] ) {
			case 'failed_on_site':
				return [ Texts::wrongFailed( $s['failed'] ), Texts::meansFailed(), Texts::firstFailed(), $issue ? Texts::recFailed() : Texts::recFailedPlain() ];
			case 'unreachable':
				return [ Texts::wrongUnreachable( $s['at'], $s['last_call'] ), $means, Texts::firstUnreachable(), Texts::recUnreachable( $s['path'] ) ];
			case 'blocked':
				return [ Texts::wrongBlocked( '' !== $s['at'] ? $s['at'] : $s['opened'], $s['path'], $s['status'], $s['last_call'] ), $means, Texts::firstBlocked( $s['path'] ), Texts::recBlocked( $s['path'] ) ];
			case 'signature':
				return [ Texts::wrongSignature( $s['opened'], $s['status'], $s['registered'], $s['current'] ), $means, Texts::firstSignature(), Texts::recSignature( $s['current'] ) ];
			case 'hears_but_silent':
				return [ Texts::wrongSilent( $s['at'], $s['plan'], $s['last_run'], $s['reason'] ), $means, Texts::firstSilent(), Texts::recSilent( $s['reason'] ) ];
			case 'not_confirmed':
				return [ null, null, null, $issue ? Texts::recNotConfirmed() : Texts::recNotConfirmedPlain() ];
			case self::KEYS_REFUSED:
				return [ Texts::wrongKeysRefused( $s['keys_since'], $s['keys_answer'] ), $means, Texts::firstKeysRefused(), Texts::recKeysRefused() ];
			case Check::SITE_MOVED:
				return [ Texts::wrongSiteMoved( $s['registered'], $s['current'] ), $means, Texts::firstSiteMoved(), Texts::recSiteMoved() ];
			default:
				return [];
		}
	}

	public function together( array $s ) {
		switch ( $s['key'] ) {
			case 'failed_on_site':
				return Texts::togetherFailed( $s['failed'] );
			case 'unreachable':
				return '' === $s['at'] ? '' : Texts::togetherUnreachable( $s['at'], $s['last_call'] );
			case 'blocked':
				return '' === $s['at'] ? '' : Texts::togetherBlocked( $s['at'], $s['last_call'] );
			case 'signature':
				return Texts::togetherSignature( $s['registered'], $s['current'] );
			case 'hears_but_silent':
				return '' === $s['at'] ? '' : Texts::togetherSilent( $s['at'], $s['last_run'], $s['reason'] );
			case self::KEYS_REFUSED:
				return Texts::togetherKeysRefused();
			case Check::SITE_MOVED:
				return Texts::togetherSiteMoved();
			default:
				return '';
		}
	}


	public function siteFacts( array $facts, array $s ) {
		$t       = $this->formatTime;
		$problem = in_array( $s['key'], self::CONDITIONS, true ) || in_array( $s['key'], [ self::KEYS_REFUSED, Check::SITE_MOVED ], true );
		$rows    = [];
		$parked  = isset( $facts['parked'] ) ? max( 0, (int) $facts['parked'] ) : 0;
		$in      = max( 0, (int) $facts['in_flight'] - $parked );

		$progress = $in < 1 && ( $parked > 0 || ! empty( $facts['marks'][ Marks::FAILED_ON_SITE ] ) )
			? Texts::noOtherWaiting()
			: Texts::inProgress( $in, (int) $facts['in_flight_since'] > 0 ? $t( (int) $facts['in_flight_since'] ) : '' );
		$rows[]   = [ Texts::label( 'in_progress' ), esc_html( $progress ), $in < 1 ? 'ok' : ( $problem ? 'warn' : 'info' ) ];

		$stalled = Stalled::view( isset( $facts['stalled'] ) ? (array) $facts['stalled'] : [], $t );

		if ( Stalled::NONE !== $stalled['cause'] ) {
			$value = '<span data-wpml-delivery-stalled="' . esc_attr( $stalled['attribute'] ) . '">' . esc_html( '' !== $stalled['hold'] ? $stalled['hold'] : Texts::stalledReason( $stalled['cause'] ) ) . '</span>';

			if ( '' !== $stalled['actionUrl'] ) {
				$value .= ' <a href="' . esc_url( $stalled['actionUrl'] ) . '" target="_blank" rel="noopener">' . esc_html( $stalled['actionLabel'] ) . '</a>';
			}

			$rows[] = [ $stalled['label'], $value, 'warn' ];
		}

		$rows[] = [
			Texts::label( 'last_collected' ),
			esc_html( $facts['last_run'] ? Texts::lastCollected( $t( (int) $facts['last_run']['at'] ), (string) $facts['last_run']['trigger'], $facts['last_run']['applied'] ) : Texts::neverCollected() ),
			$facts['last_run'] ? ( $problem ? 'warn' : 'ok' ) : 'none',
		];

		$rows[] = [
			Texts::label( 'last_call_received' ),
			esc_html( Texts::lastCallReceived( $facts['last_wake'] ? $t( (int) $facts['last_wake']['at'] ) : '' ) ),
			in_array( $s['key'], [ 'unreachable', 'blocked' ], true ) ? 'bad' : ( $facts['last_wake'] ? 'ok' : 'none' ),
		];

		if ( '' !== (string) $facts['shape'] ) {
			$rows[] = [ Texts::label( 'how_collects' ), esc_html( Texts::shape( (string) $facts['shape'] ) ), 'hears_but_silent' === $s['key'] ? 'warn' : 'ok' ];
		}

		$loop   = $facts['loopback'];
		$rows[] = [ Texts::label( 'by_itself' ), esc_html( Texts::byItself( $loop['ok'], (int) $loop['at'] > 0 ? $t( (int) $loop['at'] ) : '' ) ), null === $loop['ok'] ? 'none' : ( $loop['ok'] ? 'ok' : 'bad' ) ];

		$cron   = $facts['cron'];
		$rows[] = [
			Texts::label( 'wp_cron' ),
			esc_html( Texts::wpCron( $cron, (int) $cron['last_ran'] > 0 ? $t( (int) $cron['last_ran'] ) : '' ) ),
			$cron['overdue'] ? 'warn' : ( (int) $cron['next'] > 0 ? 'ok' : 'none' ),
		];

		$acc    = $facts['acceptance'];
		$rows[] = [
			Texts::label( 'accepted' ),
			esc_html(
				self::KEYS_REFUSED === $s['key'] && (int) $acc['refused_at'] > 0
					? Texts::acceptedKeysRefused( $t( (int) $acc['refused_at'] ), (int) $acc['refused_status'] )
					: Texts::acceptedValue( (int) $acc['accepted_at'] > 0 ? $t( (int) $acc['accepted_at'] ) : '', (int) $acc['refused_at'] > 0 ? $t( (int) $acc['refused_at'] ) : '', (int) $acc['refused_status'] )
			),
			(int) $acc['refused_at'] > 0 ? 'bad' : ( (int) $acc['accepted_at'] > 0 ? 'ok' : 'none' ),
		];

		$failed = isset( $facts['marks'][ Marks::FAILED_ON_SITE ] ) ? $facts['marks'][ Marks::FAILED_ON_SITE ] : [];
		$rows[] = [ Texts::label( 'not_applied' ), $failed ? esc_html( Texts::translations( count( $failed ) ) ) . $this->jobList( $failed ) : esc_html( Texts::none() ), $failed ? 'bad' : 'ok' ];

		$resend = isset( $facts['marks'][ Marks::NEEDS_RESEND ] ) ? $facts['marks'][ Marks::NEEDS_RESEND ] : [];
		$rows[] = [ Texts::label( 'resend' ), esc_html( $resend ? Texts::resendValue( count( $resend ) ) : Texts::none() ), $resend ? 'warn' : 'ok' ];

		$reg = $facts['registration'];

		if ( true === $reg['matches'] ) {
			$rows[] = [ Texts::label( 'registration' ), esc_html( Texts::registeredFor( (string) $reg['registered'] ) ), 'ok' ];
		} elseif ( false === $reg['matches'] ) {
			$rows[] = [
				Texts::label( 'registration' ),
				'<div class="wpml-td-addr"><span>' . esc_html( Texts::registeredForLabel() ) . '</span> <b>' . esc_html( (string) $reg['registered'] ) . '</b> <span>' . esc_html( Texts::runsAtLabel() ) . '</span> <b>' . esc_html( (string) $reg['current'] ) . '</b></div> ' . esc_html( Texts::addressesDiffer() ),
				'bad',
			];
		}

		return $rows;
	}

	public function jobList( array $jobs ) {
		$html = '<ul class="wpml-td-jobs">';

		foreach ( $jobs as $job ) {
			$title  = '' !== (string) $job['title'] ? (string) $job['title'] : Texts::untitled();
			$reason = Texts::reason( (string) $job['reason'] );
			$html  .= '<li data-wpml-td-job="' . esc_attr( (string) (int) $job['job_id'] ) . '"><span class="jt">' . esc_html( $title ) . '</span>';

			if ( '' !== (string) $job['language'] ) {
				$html .= ' <span class="jm">' . esc_html( Texts::jobPair( (string) $job['language'] ) ) . '</span>';
			}

			if ( '' !== $reason ) {
				$html .= '<br>' . esc_html( Texts::recorded( $reason ) );
			}

			if ( '' !== (string) $job['details'] ) {
				$html .= '<details class="wpml-td-forsupport"><summary>' . esc_html( Texts::detailsForSupport() ) . '</summary><code>' . esc_html( (string) $job['details'] ) . '</code></details>';
			}

			$html .= '</li>';
		}

		return $html . '</ul>';
	}

	private function systemHtml( array $system, array $blocks, array $s ) {
		$status = isset( $system['status'] ) ? $system['status'] : '';
		$lines  = [
			SystemView::NO_RECORD      => Texts::systemNoRecord(),
			SystemView::NOT_REGISTERED => Texts::systemNotRegistered(),
			SystemView::REFUSED        => Texts::systemRefused(),
		];

		if ( SystemView::OK === $status ) {
			$html = self::factsHtml( $this->systemFacts( $s, $blocks ) );
		} else {
			$html = '<p class="wpml-td-line">' . esc_html( isset( $lines[ $status ] ) ? $lines[ $status ] : Texts::systemUnreachable() ) . '</p>';
		}

		return $html . $this->recipientsHtml( $blocks );
	}

	public function systemFacts( array $s, array $blocks ) {
		$t     = $this->formatTime;
		$state = $s['system'];
		$iso   = function ( $value ) use ( $t ) {
			return is_string( $value ) && '' !== $value && false !== strtotime( $value ) ? $t( (int) strtotime( $value ) ) : '';
		};
		$rows  = [];
		$count = isset( $state['pending_count'] ) ? (int) $state['pending_count'] : 0;

		$rows[] = [ Texts::label( 'waiting' ), esc_html( Texts::finishedWaiting( $count, $iso( isset( $state['pending_since'] ) ? $state['pending_since'] : '' ) ) ), $count < 1 ? 'ok' : ( 'collecting' === $s['key'] ? 'info' : 'warn' ) ];

		$stopped = $iso( isset( $state['horizon_flagged_at'] ) ? $state['horizon_flagged_at'] : '' );
		$class   = isset( $state['last_wakeup_class'] ) ? (string) $state['last_wakeup_class'] : '';

		if ( '' !== $stopped ) {
			$rows[] = [ Texts::label( 'last_call' ), esc_html( Texts::stoppedCalling( $stopped ) ), 'bad' ];
		} else {
			$at     = $iso( isset( $state['last_wakeup_at'] ) ? $state['last_wakeup_at'] : '' );
			$rows[] = [ Texts::label( 'last_call' ), esc_html( Texts::lastCall( $at, $class, $s['status'], $s['path'] ) ), '' === $at ? 'none' : ( 'reached' === $class ? 'ok' : 'bad' ) ];
		}

		$said = Texts::saidItCollects( isset( $state['capability'] ) ? (string) $state['capability'] : '' );

		if ( '' !== $said ) {
			$rows[] = [ Texts::label( 'how_collects' ), esc_html( $said ), 'none' ];
		}

		$episode = $s['episode'];

		if ( isset( $episode['template'] ) && '' !== (string) $episode['template'] ) {
			$rows[] = [
				Texts::label( 'open_problem' ),
				esc_html( Texts::openProblem( (string) $episode['template'], $iso( isset( $episode['due_at'] ) ? $episode['due_at'] : '' ), $this->mailWent( $episode, $iso ) ) ),
				'hears_but_silent' === $episode['template'] ? 'warn' : 'bad',
			];
		} else {
			$rows[] = [ Texts::label( 'open_problems' ), esc_html( Texts::none() ), 'ok' ];
		}

		$closures = isset( $state['closures'] ) && is_array( $state['closures'] ) ? $state['closures'] : [];

		if ( $closures ) {
			$words = null;

			foreach ( $closures as $closure ) {
				if ( isset( $closure['words_returned'] ) && null !== $closure['words_returned'] ) {
					$words = (int) $words + (int) $closure['words_returned'];
				}
			}

			$rows[] = [ Texts::label( 'recently_closed' ), esc_html( Texts::recentlyClosed( count( $closures ), $words ) ), 'none' ];
		}

		return $rows;
	}

	private function mailWent( array $episode, $iso ) {
		$skipped = isset( $episode['skipped'] ) ? (string) $episode['skipped'] : '';

		if ( self::SKIPPED_ALL_BLOCKED === $skipped ) {
			return Texts::mailNotSentAllStopped();
		}

		if ( '' !== $skipped ) {
			return Texts::mailNotSent();
		}

		$at = $iso( isset( $episode['client_message_sent_at'] ) ? $episode['client_message_sent_at'] : '' );

		if ( '' === $at ) {
			return '';
		}

		$sentTo = isset( $episode['sent_to'] ) && is_array( $episode['sent_to'] ) ? count( $episode['sent_to'] ) : 0;

		return Texts::mailWent( $at, $sentTo );
	}

	public function recipientsHtml( array $blocks ) {
		$rows = isset( $blocks['recipients'] ) ? (array) $blocks['recipients'] : [];

		if ( ! $rows ) {
			return '';
		}

		$html = '<div class="wpml-td-recips"><h4>' . esc_html( Texts::label( 'recipients' ) ) . '</h4><ul>';

		foreach ( $rows as $row ) {
			$state = (string) $row['notifications_blocked'];

			if ( Blocks::NONE === $state ) {
				$html .= '<li>' . self::mark( 'ok' ) . '<span>' . esc_html( Texts::getsThem( (string) $row['address'] ) ) . '</span></li>';
				continue;
			}

			$since = '' !== (string) $row['blocked_since'] && false !== strtotime( (string) $row['blocked_since'] )
				? call_user_func( $this->formatTime, (int) strtotime( (string) $row['blocked_since'] ) )
				: '';
			$kind  = ! empty( $blocks['all_blocked'] ) ? 'bad' : 'warn';

			$html .= '<li data-wpml-td-blocked="' . esc_attr( (string) $row['address'] ) . '">' . self::mark( $kind ) . '<span>' . esc_html( Texts::stopped( (string) $row['address'], $state, $since ) );

			if ( '' !== (string) $row['resume_url'] ) {
				$html .= ' <a href="' . esc_url( (string) $row['resume_url'] ) . '" target="_blank" rel="noopener">' . esc_html( Texts::resumeThem() ) . '</a>';
			}

			$html .= '</span></li>';
		}

		return $html . '</ul></div>';
	}

	private static function factsHtml( array $rows ) {
		$html = '<dl class="wpml-td-facts">';

		foreach ( $rows as $row ) {
			$html .= '<div class="wpml-td-fact"><dt>' . self::mark( $row[2] ) . '<span>' . esc_html( $row[0] ) . '</span></dt><dd>' . $row[1] . '</dd></div>';
		}

		return $html . '</dl>';
	}


	private function eventsHtml( array $facts, array $system ) {
		$t    = $this->formatTime;
		$site = [];

		foreach ( (array) $facts['events'] as $event ) {
			$what = Texts::siteEvent( (string) $event['kind'], (array) $event['data'] );

			if ( '' !== $what ) {
				$site[] = [ $t( (int) $event['t'] ), $what ];
			}
		}

		$ate   = [];
		$state = SystemView::OK === ( isset( $system['status'] ) ? $system['status'] : '' ) ? (array) $system['state'] : [];
		$path  = (string) call_user_func( $this->wakePath );

		foreach ( isset( $state['events'] ) && is_array( $state['events'] ) ? $state['events'] : [] as $event ) {
			$what = Texts::systemEvent( isset( $event['kind'] ) ? (string) $event['kind'] : '', isset( $event['data'] ) && is_array( $event['data'] ) ? $event['data'] : [], $path );
			$at   = isset( $event['at'] ) && is_string( $event['at'] ) ? strtotime( $event['at'] ) : false;

			if ( '' !== $what && false !== $at ) {
				$ate[] = [ $t( (int) $at ), $what ];
			}
		}

		$list = function ( array $items ) {
			if ( ! $items ) {
				return '<p class="wpml-td-line">' . esc_html( Texts::noEvents() ) . '</p>';
			}

			$html = '<ol>';

			foreach ( $items as $item ) {
				$html .= '<li><time>' . esc_html( $item[0] ) . '</time>' . esc_html( $item[1] ) . '</li>';
			}

			return $html . '</ol>';
		};

		return '<details class="wpml-td-events"><summary>' . esc_html( Texts::recentEvents() ) . '</summary><div class="wpml-td-evcols">'
			. '<div><h5>' . esc_html( Texts::eventsOnThisSite() ) . '</h5>' . $list( $site ) . '</div>'
			. '<div><h5>' . esc_html( Texts::eventsInTheSystem() ) . '</h5>' . $list( $ate ) . '</div></div></details>';
	}


	private function actionsHtml( array $s, array $facts, array $system ) {
		$waiting = (int) $facts['in_flight'] > 0;

		switch ( $s['key'] ) {
			case 'failed_on_site':
				$acts = [ [ 'retry', true ], [ 'report', false ], [ 'check', false ] ];
				break;
			case 'unreachable':
			case 'blocked':
				$acts = [ [ 'check', true ], [ 'collect', false ] ];
				break;
			case 'signature':
				$acts = [ [ 'rereg', true ], [ 'check', false ] ];
				break;
			case 'not_confirmed':
				$acts = [ [ 'check', true ], [ 'report', false ] ];
				break;
			case 'hears_but_silent':
				$acts = $waiting ? [ [ 'collect', true ], [ 'check', false ] ] : [ [ 'check', true ] ];
				break;
			case self::KEYS_REFUSED:
			case Check::SITE_MOVED:
				$acts = [ [ 'register', true ], [ 'check', false ] ];
				break;
			default:
				$acts = [ [ 'check', false ] ];
				break;
		}

		if ( $waiting && in_array( $s['key'], [ 'unreachable', 'blocked', 'signature', 'hears_but_silent' ], true ) ) {
			$acts[] = [ 'cancel', false ];
		}

		if ( ! empty( $facts['marks'][ Marks::NEEDS_RESEND ] ) ) {
			$acts[] = [ 'resend', false ];
		}

		$html = '<div class="wpml-td-actions">';

		foreach ( $acts as $act ) {
			$html .= $this->button( $act[0], $act[1], $system );
		}

		$html .= '<span class="grow"></span><a href="' . esc_url( Actions::reportUrl( isset( $system['state'] ) ? (array) $system['state'] : [] ) ) . '" class="wpml-td-help" target="_blank" rel="noopener">' . esc_html( Texts::action( 'contact' ) ) . '</a></div>';

		return $html;
	}

	public function button( $action, $recommended, array $system = [], $label = '' ) {
		$ops   = [
			'check'      => 'check',
			'checkagain' => 'check',
			'collect'    => Actions::COLLECT,
			'retry'      => Actions::RETRY,
			'resend'     => Actions::RESEND,
			'cancel'     => Actions::CANCEL_PREVIEW,
			'register'   => Actions::REGISTER,
		];
		$label = '' !== $label ? $label : Texts::action( $action );
		$class = $recommended ? 'button button-primary' : 'button';

		if ( isset( $ops[ $action ] ) ) {
			$control = '<button type="button" class="' . esc_attr( $class ) . '" data-wpml-td-action="' . esc_attr( $ops[ $action ] ) . '">' . esc_html( $label ) . '</button>';
		} elseif ( 'rereg' === $action ) {
			$control = '<a class="' . esc_attr( $class ) . '" href="' . esc_url( Actions::reregisterUrl() ) . '">' . esc_html( $label ) . '</a>';
		} else {
			$control = '<a class="' . esc_attr( $class ) . '" href="' . esc_url( Actions::reportUrl( isset( $system['state'] ) ? (array) $system['state'] : [] ) ) . '" target="_blank" rel="noopener">' . esc_html( $label ) . '</a>';
		}

		return '<span class="wpml-td-act">' . ( $recommended ? '<span class="wpml-td-rectag">' . esc_html( Texts::recommended() ) . '</span>' : '' ) . $control . '</span>';
	}


	public static function card( $kind, $title, array $paragraphs, $extra, $buttons, $result, $attrs = '' ) {
		$role = '' === $attrs ? 'status' : 'alertdialog';
		$html = '<div class="wpml-td-card c-' . esc_attr( $kind ) . '" role="' . $role . '" data-wpml-td-result="' . esc_attr( $result ) . '"' . $attrs . '><div class="ct">' . self::mark( $kind ) . '<span>' . esc_html( $title ) . '</span></div>';

		foreach ( $paragraphs as $paragraph ) {
			$html .= '<p>' . self::bold( $paragraph ) . '</p>';
		}

		$html .= $extra;

		return $html . ( '' !== $buttons ? '<div class="cbtns">' . $buttons . '</div>' : '' ) . '</div>';
	}

	public static function checkingCard() {
		$steps = '<ul class="wpml-td-steps">';

		foreach ( Texts::checkingSteps() as $step ) {
			$steps .= '<li><span class="wpml-td-spin" aria-hidden="true"></span>' . esc_html( $step ) . '</li>';
		}

		$steps .= '</ul><p class="note">' . esc_html( Texts::checkingNote() ) . '</p>';

		return self::card( 'info', Texts::checkingTitle(), [], $steps, '', 'checking' );
	}

	public static function hasOpenProblem( array $system ) {
		$state = isset( $system['status'] ) && SystemView::OK === $system['status'] && isset( $system['state'] ) ? (array) $system['state'] : [];

		return isset( $state['episode']['template'] ) && in_array( $state['episode']['template'], self::CONDITIONS, true );
	}

	public static function closedWhileChecking( array $result, array $before, array $after ) {
		return in_array( isset( $result['card'] ) ? $result['card'] : '', self::PROBE_PROBLEMS, true )
			&& self::hasOpenProblem( $before )
			&& isset( $after['status'] ) && SystemView::OK === $after['status']
			&& ! self::hasOpenProblem( $after );
	}

	public static function foundByCheck( array $result, array $before, array $after ) {
		$card = isset( $result['card'] ) ? (string) $result['card'] : '';

		if ( in_array( $card, self::PROBE_PROBLEMS, true ) ) {
			return self::closedWhileChecking( $result, $before, $after ) ? '' : $card;
		}

		$collect = isset( $result['collect'] ) && is_array( $result['collect'] ) ? $result['collect'] : [];

		return Check::FAILED !== $card && ! empty( $collect['ran'] ) && self::notCollected( $collect, $after ) ? Check::HEARS_BUT_SILENT : '';
	}

	public static function notCollected( array $counts, array $system ) {
		if ( ! empty( $counts['busy'] ) || ( isset( $counts['applied'] ) ? (int) $counts['applied'] : 0 ) > 0 || ( isset( $counts['failed'] ) ? (int) $counts['failed'] : 0 ) > 0 ) {
			return false;
		}

		return self::waitingInSystem( $system ) > 0;
	}

	public static function waitingInSystem( array $system ) {
		if ( ! isset( $system['status'] ) || SystemView::OK !== $system['status'] || ! isset( $system['state']['pending_count'] ) ) {
			return 0;
		}

		return max( 0, (int) $system['state']['pending_count'] );
	}

	public function checkCard( array $result, array $system, array $before = [] ) {
		if ( self::closedWhileChecking( $result, $before, $system ) ) {
			return $this->closedCard( $result, $system );
		}

		$probe   = $result['probe'];
		$self    = $result['self'];
		$path    = (string) $probe['path'];
		$card    = (string) $result['card'];
		$collect = $result['collect'];
		$lead    = [];
		$extra   = '';
		$buttons = '';

		if ( $collect['ran'] ) {
			$lead[] = $collect['busy'] ? Texts::collectBusy() : Texts::collectedWhileChecking( (int) $collect['applied'] );
		}

		if ( Check::ALL_GOOD === $card && $collect['ran'] && self::notCollected( $collect, $system ) ) {
			return self::card( 'warn', Texts::cardTitle( Check::HEARS_BUT_SILENT ), [ Texts::collectedNothingWhileChecking( self::waitingInSystem( $system ) ) ], '', '', Check::HEARS_BUT_SILENT );
		}

		switch ( $card ) {
			case Check::ALL_GOOD:
				$kind = 'ok';
				$body = [ Texts::cardAllGood( (int) $collect['applied'] ) ];
				if ( 'reached' === $probe['class'] ) {
					$body[] = Texts::cardReached();
				}
				$lead = [];
				break;
			case Check::FAILED:
				$kind    = 'bad';
				$body    = [ Texts::cardFailed( count( $result['failed_jobs'] ) ) ];
				$extra   = $this->jobList( (array) $result['failed_jobs'] ) . '<p>' . esc_html( Texts::cardFailedFix() ) . '</p>';
				$buttons = $this->button( 'retry', true, $system ) . $this->button( 'report', false, $system );
				break;
			case Check::UNREACHABLE:
				$kind    = 'bad';
				$body    = Texts::cardUnreachable( $probe['status'], $path );
				$buttons = $this->button( 'checkagain', true, $system );
				break;
			case Check::BLOCKED:
				$kind    = 'bad';
				$body    = Texts::cardBlocked( $path, $probe['status'], (string) $probe['what'] );
				$buttons = $this->button( 'checkagain', true, $system );
				break;
			case Check::SIGNATURE:
				$kind    = 'bad';
				$body    = Texts::cardSignature( false === $self['url_matches'] ? (string) $self['registered'] : '', (string) $self['current'] );
				$buttons = $this->button( 'rereg', true, $system );
				break;
			case Check::HEARS_BUT_SILENT:
				$kind    = 'warn';
				$body    = Texts::cardSilent( (string) $result['reason'] );
				$buttons = $this->button( 'collect', true, $system );
				break;
			case Check::NO_HANDSHAKE:
				$kind = 'info';
				$body = [ Texts::cardNoHandshake() ];
				break;
			case Check::KEYS_REFUSED:
				$kind    = 'bad';
				$body    = [ Texts::cardKeysRefused() ];
				$buttons = $this->button( 'register', true, $system );
				break;
			case Check::SITE_MOVED:
				$kind    = 'bad';
				$body    = [ Texts::cardSiteMoved() ];
				$buttons = $this->button( 'register', true, $system );
				break;
			case Check::THROTTLED:
				return self::card( 'info', Texts::action( 'check' ), [ Texts::cardThrottled() ], '', '', $card );
			case Check::NO_RECORD:
				$kind = 'info';
				$body = [ Texts::cardNoRecord() ];
				break;
			default:
				$kind    = 'warn';
				$body    = [ Texts::cardSystemUnreachable() ];
				$buttons = $this->button( 'report', true, $system, Texts::action( 'contact' ) );
				break;
		}

		$paragraphs = array_merge( ! empty( $result['throttled'] ) ? [ Texts::cardThrottled() ] : [], $lead, $body );

		return self::card( $kind, Texts::cardTitle( $card ), $paragraphs, $extra, $buttons, $card );
	}

	private function closedCard( array $result, array $system ) {
		$collect = $result['collect'];
		$lead    = Texts::problemClosed();

		if ( $collect['ran'] ) {
			$lead = ( $collect['busy'] ? Texts::collectBusy() : Texts::collectedWhileChecking( (int) $collect['applied'] ) ) . ' ' . $lead;
		}

		$button = Check::SIGNATURE === $result['card'] ? $this->button( 'rereg', false, $system ) : $this->button( 'checkagain', false, $system );

		$cronRuns = empty( $result['self']['cron_overdue'] );

		return self::card( 'info', Texts::action( 'check' ), [ $lead, Texts::stillCannotCall( $cronRuns ) ], '', $button, self::CLOSED_WHILE_CHECKING );
	}

	public function actionCard( array $result, array $system = [] ) {
		if ( isset( $result['ok'], $result['error'] ) && false === $result['ok'] ) {
			return $this->errorCard( (string) $result['error'] );
		}

		switch ( $result['action'] ) {
			case Actions::COLLECT:
				if ( ! empty( $result['busy'] ) ) {
					return self::card( 'info', Texts::collectedTitle( 0 ), [ Texts::collectBusy() ], '', '', Actions::COLLECT );
				}
				if ( self::notCollected( $result, $system ) ) {
					return self::card( 'warn', Texts::cardTitle( Check::HEARS_BUT_SILENT ), [ Texts::collectedNothingWhileWaiting( self::waitingInSystem( $system ) ) ], '', '', Actions::COLLECT );
				}
				return self::card( (int) $result['failed'] > 0 ? 'warn' : 'ok', Texts::collectedTitle( (int) $result['applied'] ), [ Texts::collectedBody( (int) $result['applied'], (int) $result['failed'] ) ], '', '', Actions::COLLECT );
			case Actions::RETRY:
				return self::retryCard( $result );
			case Actions::RESEND:
				return self::card( 'ok', Texts::resentTitle( (int) $result['resent'] ), [ Texts::panelNote() ], '', '', Actions::RESEND );
			case Actions::CANCEL_PREVIEW:
				return $this->cancelConfirmation( (int) $result['count'] );
			case Actions::REGISTER:
				return empty( $result['ok'] )
					? self::card( 'bad', Texts::registerFailedTitle(), [ Texts::registerFailedBody() ], '', '', Actions::REGISTER )
					: self::card( 'ok', Texts::registeredTitle(), [ Texts::registeredBody() ], '', '', Actions::REGISTER );
			case Actions::CANCEL:
				if ( (int) $result['cancelled'] < 1 ) {
					return self::card( 'bad', Texts::errorTitle(), [ Texts::outcomeError() ], '', '', Actions::CANCEL );
				}
				return self::card( (int) $result['failed'] > 0 ? 'warn' : 'ok', Texts::cancelledTitle( (int) $result['cancelled'] ), [ Texts::cancelledBody( $result['words'], (int) $result['failed'], isset( $result['unconfirmed'] ) ? (int) $result['unconfirmed'] : 0 ), Texts::panelNote() ], '', '', Actions::CANCEL );
			default:
				return $this->errorCard( isset( $result['error'] ) ? (string) $result['error'] : '' );
		}
	}

	private static function retryCard( array $result ) {
		$retried    = (int) $result['retried'];
		$failed     = (int) $result['failed'];
		$inProgress = isset( $result['in_progress'] ) ? (int) $result['in_progress'] : 0;
		$refused    = isset( $result['refused'] ) && is_array( $result['refused'] ) ? $result['refused'] : [];

		if ( ! $refused && $inProgress < 1 ) {
			return self::card( $failed > 0 ? 'warn' : 'info', Texts::retriedTitle( $retried ), [ Texts::retriedBody( $retried, $failed ) ], '', '', Actions::RETRY );
		}

		$refusedJobs = 0;
		$lines       = '';

		foreach ( $refused as $reason => $refusal ) {
			$refusedJobs += (int) $refusal['jobs'];
			$text         = Texts::retryRefused(
				(string) $reason,
				(int) $refusal['words'],
				array_key_exists( 'balance_words', $result ) && null !== $result['balance_words'] ? (int) $result['balance_words'] : null,
				isset( $refusal['left'] ) ? (int) $refusal['left'] : null
			);
			$lines       .= '<p data-wpml-td-refused="' . esc_attr( (string) $reason ) . '">' . self::linked( $text, Actions::refusalUrl( (string) $reason ) ) . '</p>';
		}

		$paragraphs = [];

		if ( $retried > 0 ) {
			$paragraphs[] = Texts::retriedBody( $retried, $failed );
		} elseif ( $failed > 0 ) {
			$paragraphs[] = Texts::retryFailed( $failed );
		}

		if ( $inProgress > 0 ) {
			$paragraphs[] = Texts::retryInProgress( $inProgress );
		}

		$title = $retried + $inProgress > 0 ? Texts::retriedTitle( $retried + $inProgress ) : Texts::retryRefusedTitle( $refusedJobs );
		$kind  = $refusedJobs > 0 || $failed > 0 ? 'warn' : 'info';

		return self::card( $kind, $title, $paragraphs, $lines, '', Actions::RETRY );
	}

	private static function linked( $text, $url ) {
		$html = self::bold( $text );

		if ( '' === $url ) {
			return str_replace( [ '&lt;a&gt;', '&lt;/a&gt;' ], '', $html );
		}

		$external = 0 !== strpos( $url, admin_url() );
		$open     = '<a href="' . esc_url( $url ) . '"' . ( $external ? ' target="_blank" rel="noopener"' : '' ) . '>';

		return str_replace( [ '&lt;a&gt;', '&lt;/a&gt;' ], [ $open, '</a>' ], $html );
	}

	private function errorCard( $what ) {
		$details = '' === $what ? '' : '<details class="wpml-td-forsupport"><summary>' . esc_html( Texts::detailsForSupport() ) . '</summary><code>' . esc_html( $what ) . '</code></details>';

		return self::card( 'bad', Texts::errorTitle(), [ Texts::outcomeError() ], $details, '', 'error' );
	}

	private function cancelConfirmation( $count ) {
		if ( $count < 1 ) {
			return self::card( 'info', Texts::action( 'cancel' ), [ Texts::cancelNothing() ], '', '', Actions::CANCEL_PREVIEW );
		}

		$buttons = '<span class="wpml-td-act"><button type="button" class="button" data-wpml-td-dismiss="1">' . esc_html( Texts::cancelKeep() ) . '</button></span>'
			. '<span class="wpml-td-act"><button type="button" class="button button-primary wpml-td-danger" data-wpml-td-action="' . esc_attr( Actions::CANCEL ) . '">' . esc_html( Texts::cancelConfirm( $count ) ) . '</button></span>';
		$attrs   = ' data-wpml-td-count="' . esc_attr( (string) $count ) . '"';

		return self::card( 'warn', Texts::cancelQuestion( $count ), Texts::cancelExplain(), '', $buttons, Actions::CANCEL_PREVIEW, $attrs );
	}

	public static function refusedCard() {
		return self::card( 'bad', Texts::errorTitle(), [ Texts::notAllowed() ], '', '', 'refused' );
	}


	public static function mark( $kind ) {
		$glyphs = [
			'ok'   => '&#10003;',
			'info' => '&#8226;',
			'warn' => '!',
			'bad'  => '&#10005;',
			'none' => '&#8211;',
		];
		$kind   = isset( $glyphs[ $kind ] ) ? $kind : 'none';

		return '<span class="wpml-td-mk ' . esc_attr( $kind ) . '" aria-hidden="true">' . $glyphs[ $kind ] . '</span><span class="screen-reader-text">' . esc_html( Texts::markLabel( $kind ) ) . ': </span>';
	}

	private static function bold( $text ) {
		return str_replace( [ '&lt;b&gt;', '&lt;/b&gt;' ], [ '<b>', '</b>' ], esc_html( $text ) );
	}

	public static function siteTime( $time ) {
		$clock = (string) wp_date( (string) get_option( 'time_format', 'H:i' ), (int) $time );

		if ( wp_date( 'Y-m-d', (int) $time ) === wp_date( 'Y-m-d' ) ) {
			return $clock;
		}

		return Texts::dateAt( (string) wp_date( 'j M', (int) $time ), $clock );
	}

	public static function allowedHtml() {
		$common = [
			'class'           => true,
			'id'              => true,
			'role'            => true,
			'hidden'          => true,
			'aria-live'       => true,
			'aria-hidden'     => true,
			'aria-labelledby' => true,
			'data-*'          => true,
		];
		$out    = [];

		foreach ( [ 'section', 'style', 'div', 'span', 'p', 'h2', 'h3', 'h4', 'h5', 'dl', 'dt', 'dd', 'ul', 'ol', 'li', 'b', 'br', 'details', 'summary', 'code', 'time' ] as $tag ) {
			$out[ $tag ] = $common;
		}

		$out['button'] = $common + [ 'type' => true ];
		$out['label'] = $common + [ 'for' => true ];
		$out['input'] = $common + [
			'type'      => true,
			'min'       => true,
			'max'       => true,
			'step'      => true,
			'value'     => true,
			'inputmode' => true,
		];
		$out['a']      = $common + [
			'href'   => true,
			'target' => true,
			'rel'    => true,
		];

		return $out;
	}

	public static function script() {
		return '(function(){var box=document.getElementById(' . wp_json_encode( self::ID ) . ');if(!box){return;}'
			. 'var inner=box.querySelector("[data-wpml-td-inner]"),busy=box.querySelector("[data-wpml-td-busy]");'
			. 'function slot(){return box.querySelector("[data-wpml-td-card]");}'
			. 'function run(op){box.querySelectorAll("[data-wpml-td-action]").forEach(function(b){b.disabled=true;});'
			. 'if(op==="check"&&slot()&&busy){slot().innerHTML=busy.innerHTML;}var body=new URLSearchParams();body.append("action",' . wp_json_encode( Loader::AJAX_ACTION ) . ');'
			. 'body.append("op",op);body.append("nonce",box.getAttribute("data-wpml-td-nonce"));'
			. 'var chunk=box.querySelector("[data-wpml-td-chunk]");if(op===' . wp_json_encode( Loader::OP_CHUNK ) . '&&chunk){body.append("chunk",chunk.value);}'
			. 'if(box.getAttribute("data-wpml-td-autorun")){body.append("delivery",box.getAttribute("data-wpml-td-autorun"));}'
			. 'fetch(box.getAttribute("data-wpml-td-ajax"),{method:"POST",credentials:"same-origin",body:body}).then(function(r){return r.json();}).then(function(r){'
			. 'var d=r&&r.data?r.data:{};if(d.inner){inner.innerHTML=d.inner;}else if(d.card&&slot()){slot().innerHTML=d.card;}'
			. '}).catch(function(){if(slot()){slot().replaceChildren();}}).then(function(){box.querySelectorAll("[data-wpml-td-action]").forEach(function(b){b.disabled=false;});});}'
			. 'box.addEventListener("click",function(e){var t=e.target.closest("[data-wpml-td-action],[data-wpml-td-dismiss]");if(!t){return;}'
			. 'if(t.hasAttribute("data-wpml-td-dismiss")){if(slot()){slot().replaceChildren();}return;}run(t.getAttribute("data-wpml-td-action"));});'
			. 'if(box.getAttribute("data-wpml-td-autorun")){run("check");}})();';
	}

	public static function css() {
		return '.wpml-td{background:#fff;border:1px solid #e5e7eb;border-radius:6px;margin:0 0 24px;color:#374151;font-size:13px;line-height:1.5}'
			. '.wpml-td-allblocked{background:#fef2f2;border-bottom:1px solid #fecaca;color:#7f1d1d;padding:10px 20px}.wpml-td-allblocked b{color:#b91c1c}'
			. '.wpml-td-head{display:flex;flex-wrap:wrap;align-items:flex-start;justify-content:space-between;gap:8px 16px;padding:18px 20px 6px}'
			. '.wpml-td-head h2{margin:0 0 2px;font-size:16px;font-weight:600;color:#111827;padding:0}.wpml-td-head p{margin:0;font-size:12px;color:#6b7280}'
			. '.wpml-td-status{display:inline-flex;align-items:center;gap:6px;font-size:12px;font-weight:600;border:1px solid;border-radius:999px;padding:3px 10px 3px 6px}'
			. '.wpml-td-mk{display:inline-flex;align-items:center;justify-content:center;width:16px;height:16px;border-radius:50%;font-size:10px;font-weight:700;line-height:1;flex:none;color:#fff;margin-right:4px}'
			. '.wpml-td .s-ok{color:#1f7a66;background:#ecf8f4;border-color:#b9e3d6}.wpml-td-mk.ok{background:#27ad95}'
			. '.wpml-td .s-info{color:#0e5566;background:#ebf3f5;border-color:#c7dfe6}.wpml-td-mk.info{background:#2f7d92}'
			. '.wpml-td .s-warn{color:#92400e;background:#fffbeb;border-color:#fde68a}.wpml-td-mk.warn{background:#d97706}'
			. '.wpml-td .s-bad{color:#b91c1c;background:#fef2f2;border-color:#fecaca}.wpml-td-mk.bad{background:#dc2626}.wpml-td-mk.none{background:#d1d5db;color:#4b5563}'
			. '.wpml-td-mailnote{margin:8px 20px 0;padding:8px 12px;background:#ebf3f5;border-radius:6px;color:#0e4a59}'
			. '.wpml-td-sit{padding:8px 20px 4px}.wpml-td-headline{font-size:15px;font-weight:600;color:#111827;margin:6px 0 8px}'
			. '.wpml-td-sit dl{margin:0;display:grid;grid-template-columns:170px 1fr;gap:6px 14px}.wpml-td-sit dt{color:#6b7280;font-weight:500}.wpml-td-sit dd{margin:0;min-width:0;overflow-wrap:anywhere}.wpml-td-sit dd.rec{color:#111827}'
			. '.wpml-td-rectag{display:inline-block;font-size:10.5px;font-weight:700;text-transform:uppercase;letter-spacing:.04em;color:#0e5566;background:#d7ebf1;border-radius:3px;padding:1px 5px;margin-right:4px}'
			. '.wpml-td-card{margin:12px 20px 4px;border:1px solid;border-radius:6px;padding:12px 14px}.wpml-td-card .ct{display:flex;align-items:center;gap:8px;font-weight:600;margin-bottom:4px}.wpml-td-card p{margin:4px 0;overflow-wrap:anywhere}'
			. '.wpml-td-card .cbtns{display:flex;flex-wrap:wrap;gap:8px;align-items:flex-end;margin-top:10px}.wpml-td-card .note{font-size:12px;color:#6b7280}'
			. '.wpml-td-card.c-ok{border-color:#b9e3d6;background:#f6fbf9}.wpml-td-card.c-info{border-color:#c7dfe6;background:#f7fbfc}.wpml-td-card.c-warn{border-color:#fde68a;background:#fffdf5}.wpml-td-card.c-bad{border-color:#fecaca;background:#fffafa}'
			. '.wpml-td-steps{list-style:none;padding:0;margin:6px 0 0}.wpml-td-steps li{display:flex;gap:8px;align-items:center;padding:2px 0}'
			. '.wpml-td-spin{width:14px;height:14px;border:2px solid #c7dfe6;border-top-color:#2f7d92;border-radius:50%;animation:wpmltdsp .9s linear infinite;flex:none}@keyframes wpmltdsp{to{transform:rotate(360deg)}}@media (prefers-reduced-motion:reduce){.wpml-td-spin{animation:none}}'
			. '.wpml-td-cols{display:grid;grid-template-columns:1fr 1fr;margin:14px 0 0;border-top:1px solid #e5e7eb}.wpml-td-col{padding:14px 20px 10px;min-width:0}.wpml-td-col+.wpml-td-col{border-left:1px solid #e5e7eb;background:#fcfcfd}'
			. '.wpml-td-col h3{font-size:13px;font-weight:600;color:#111827;margin:0 0 8px}.wpml-td-facts{margin:0}.wpml-td-fact{padding:6px 0;border-top:1px solid #f3f4f6}.wpml-td-fact:first-child{border-top:0}'
			. '.wpml-td-fact dt{display:flex;align-items:center;gap:2px;font-size:11.5px;font-weight:600;color:#6b7280;text-transform:uppercase;letter-spacing:.03em}.wpml-td-fact dd{margin:2px 0 0 22px;overflow-wrap:anywhere}'
			. '.wpml-td-jobs{list-style:none;margin:4px 0 0;padding:0}.wpml-td-jobs li{padding:6px 0;border-top:1px dashed #e5e7eb}.wpml-td-jobs li:first-child{border-top:0}.wpml-td-jobs .jt{font-weight:600;color:#111827}.wpml-td-jobs .jm{font-size:12px;color:#6b7280}'
			. '.wpml-td-forsupport{margin-top:3px;font-size:12px}.wpml-td-forsupport summary{cursor:pointer;color:#2271b1}.wpml-td-forsupport code{display:block;white-space:pre-wrap;word-break:break-word;margin-top:4px}'
			. '.wpml-td-addr{display:grid;grid-template-columns:auto 1fr;gap:2px 10px;background:#f9fafb;border:1px solid #e5e7eb;border-radius:6px;padding:8px 10px;margin:8px 0}.wpml-td-addr span{color:#6b7280}'
			. '.wpml-td-recips{margin:10px 0 2px;padding-top:10px;border-top:1px solid #e5e7eb}.wpml-td-recips h4{font-size:11.5px;font-weight:600;color:#6b7280;text-transform:uppercase;letter-spacing:.03em;margin:0 0 4px}.wpml-td-recips ul{list-style:none;padding:0;margin:0}.wpml-td-recips li{display:flex;gap:2px;align-items:flex-start;padding:3px 0;overflow-wrap:anywhere}'
			. '.wpml-td-together{margin:0;padding:10px 20px;background:#f8fafc;border-top:1px solid #e5e7eb}.wpml-td-together b{color:#111827}'
			. '.wpml-td-events{border-top:1px solid #e5e7eb;padding:8px 20px;font-size:12.5px}.wpml-td-events summary{cursor:pointer;color:#2271b1;font-weight:500}'
			. '.wpml-td-evcols{display:grid;grid-template-columns:1fr 1fr;gap:6px 24px;margin-top:8px}.wpml-td-evcols h5{margin:0 0 4px;font-size:11.5px;color:#6b7280;text-transform:uppercase}.wpml-td-evcols ol{list-style:none;margin:0;padding:0}.wpml-td-evcols time{color:#6b7280;margin-right:6px}'
			. '.wpml-td-actions{display:flex;flex-wrap:wrap;gap:10px;align-items:flex-end;padding:12px 20px 16px;border-top:1px solid #e5e7eb}.wpml-td-act{display:inline-flex;flex-direction:column;align-items:flex-start;gap:3px}'
			. '.wpml-td-actions .grow{flex:1}.wpml-td-help{align-self:center}.wpml-td-note{padding:0 20px 12px;color:#6b7280}.wpml-td-line{margin:4px 0}'
			. '.wpml-td .button.wpml-td-danger{background:#b91c1c;border-color:#b91c1c}'
			. '.wpml-td-setting{margin-top:8px}.wpml-td-setting label{display:block;font-size:11.5px;font-weight:600;color:#6b7280;text-transform:uppercase;letter-spacing:.03em}'
			. '.wpml-td-setrow{display:flex;flex-wrap:wrap;gap:8px;align-items:center;margin:4px 0}.wpml-td-setrow input{width:90px}.wpml-td-sethelp{margin:4px 0 0;font-size:12px;color:#6b7280}'
			. '@media (max-width:782px){.wpml-td-cols,.wpml-td-evcols{grid-template-columns:1fr}.wpml-td-col+.wpml-td-col{border-left:0;border-top:1px solid #e5e7eb}.wpml-td-sit dl{grid-template-columns:1fr;gap:2px}'
			. '.wpml-td-head,.wpml-td-sit,.wpml-td-col,.wpml-td-together,.wpml-td-events,.wpml-td-actions{padding-left:14px;padding-right:14px}.wpml-td-card{margin-left:14px;margin-right:14px}}';
	}
}
