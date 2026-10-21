<?php

namespace WPML\ContentDeletion;

class RestoreCascadeNotice implements \IWPML_Backend_Action, \IWPML_DIC_Action {

	const RESTORED_PARAM = 'wpml_restored_with_set';

	const REMAINING_PARAM = 'wpml_restore_remaining';

	const REACHABLE_PARAM = 'wpml_restore_reachable';

	const REFUSED_PARAM = 'wpml_restore_refused';

	const MISSING_PARAM = 'wpml_restore_missing';

	const TYPE_PARAM = 'wpml_restore_type';

	const NOTICE_CLASS = 'wpml-restore-cascade-notice';

	private $coreNotice;

	private static $restored = 0;

	private static $remaining = 0;

	private static $remaining_floor = 0;

	private static $refused = 0;

	private static $missing = 0;

	private static $per_record = array();

	private static $post_type = '';

	public static function record( $restored, $remaining, $post_type = '', $refused = 0, $missing = 0, $record_id = 0 ) {
		$restored  = max( (int) $restored, 0 );
		$refused   = max( (int) $refused, 0 );
		$missing   = max( (int) $missing, 0 );
		$remaining = max( (int) $remaining, 0 );

		if ( $restored < 1 && $refused < 1 ) {
			self::$missing        += $missing;
			self::$remaining_floor = max( self::$remaining_floor, $remaining );

			self::recordBatch( $record_id, $remaining, $refused, false );

			return;
		}

		self::$restored += $restored;
		self::$refused  += $refused;
		self::$missing  += $missing;
		self::$remaining = $remaining;

		self::recordBatch( $record_id, $remaining, $refused, true );

		$post_type = (string) $post_type;

		if ( '' === self::$post_type ) {
			self::$post_type = $post_type;
		} elseif ( self::$post_type !== $post_type ) {
			self::$post_type = '';
		}
	}

	private static function recordBatch( $record_id, $remaining, $refused, $walked ) {
		$record_id = max( (int) $record_id, 0 );

		if ( ! isset( self::$per_record[ $record_id ] ) ) {
			self::$per_record[ $record_id ] = array(
				'remaining' => 0,
				'floor'     => 0,
				'refused'   => 0,
			);
		}

		self::$per_record[ $record_id ]['refused'] += $refused;

		if ( $walked ) {
			self::$per_record[ $record_id ]['remaining'] = $remaining;

			return;
		}

		self::$per_record[ $record_id ]['floor'] = max( self::$per_record[ $record_id ]['floor'], $remaining );
	}

	public static function forget() {
		self::$restored        = 0;
		self::$remaining       = 0;
		self::$remaining_floor = 0;
		self::$refused         = 0;
		self::$missing         = 0;
		self::$post_type       = '';
		self::$per_record      = array();
	}

	public function __construct( ?CoreNoticeParams $coreNotice = null ) {
		$this->coreNotice = $coreNotice ? $coreNotice : new CoreNoticeParams();
	}

	public function add_hooks() {
		add_filter( 'wp_redirect', array( $this, 'carryOutcome' ) );
		add_action( 'admin_notices', array( $this, 'renderNotice' ) );

		add_filter( 'removable_query_args', array( $this, 'removeOutcomeParams' ) );

		add_action( 'admin_init', array( $this, 'stripOutcomeParams' ), 1 );
	}

	public function stripOutcomeParams() {
		if ( ! isset( $_SERVER['REQUEST_URI'] ) || ! is_string( $_SERVER['REQUEST_URI'] ) ) {
			return;
		}

		$own = array(
			self::RESTORED_PARAM,
			self::REMAINING_PARAM,
			self::REACHABLE_PARAM,
			self::REFUSED_PARAM,
			self::MISSING_PARAM,
			self::TYPE_PARAM,
		);

		$query = strstr( sanitize_text_field( wp_unslash( $_SERVER['REQUEST_URI'] ) ), '?' );
		parse_str( $query ? substr( $query, 1 ) : '', $carried );
		if ( ! array_intersect_key( $carried, array_flip( $own ) ) ) {
			return;
		}

		$_SERVER['REQUEST_URI'] = remove_query_arg( $own, esc_url_raw( wp_unslash( $_SERVER['REQUEST_URI'] ) ) );
	}

	public function removeOutcomeParams( $args ) {
		if ( ! is_array( $args ) ) {
			return $args;
		}

		return array_merge(
			$args,
			array(
				self::RESTORED_PARAM,
				self::REMAINING_PARAM,
				self::REACHABLE_PARAM,
				self::REFUSED_PARAM,
				self::MISSING_PARAM,
				self::TYPE_PARAM,
			)
		);
	}

	public function carryOutcome( $location ) {
		if ( ( self::$restored < 1 && self::$refused < 1 ) || ! is_string( $location ) || '' === $location ) {
			return $location;
		}

		$location = add_query_arg( self::RESTORED_PARAM, (string) self::$restored, $location );
		$location = add_query_arg( self::REMAINING_PARAM, (string) self::remainingOfTheRequest(), $location );
		$location = add_query_arg( self::REACHABLE_PARAM, (string) self::reachableOfTheRequest(), $location );

		if ( self::$refused > 0 ) {
			$location = add_query_arg( self::REFUSED_PARAM, (string) self::$refused, $location );
		}

		if ( self::$missing > 0 ) {
			$location = add_query_arg( self::MISSING_PARAM, (string) self::$missing, $location );
		}

		if ( '' !== self::$post_type ) {
			$location = add_query_arg( self::TYPE_PARAM, rawurlencode( self::$post_type ), $location );
		}

		return $location;
	}

	private static function remainingOfTheRequest() {
		return max( self::$remaining, self::$remaining_floor );
	}

	private static function reachableOfTheRequest() {
		$total = 0;

		foreach ( self::$per_record as $batch ) {
			$total += max( max( $batch['remaining'], $batch['floor'] ) - $batch['refused'], 0 );
		}

		return $total;
	}

	private function reachable( $remaining, $refused ) {
		return max( $remaining - $refused, 0 );
	}

	public function renderNotice() {
		global $pagenow;

		if ( 'edit.php' !== $pagenow && 'upload.php' !== $pagenow ) {
			return;
		}

		$extra   = $this->number( self::RESTORED_PARAM );
		$refused = $this->number( self::REFUSED_PARAM );

		if ( $extra < 1 && $refused < 1 ) {
			return;
		}

		$counted   = $this->number( 'untrashed' );
		$missing   = $this->number( self::MISSING_PARAM );
		$total     = $counted + $extra;
		$label     = $this->typeLabel( $total );
		$sentences = array();

		$reachable = $this->reachableOnTheUrl( $refused );

		$brought_back = $extra >= 1;

		if ( $reachable > 0 && $brought_back ) {
			$sentences[] = sprintf(
				/* translators: Notice shown while content is being brought back from the Trash a step at a time. %1$d: how many came back, %2$s: the name of the content type, %3$d: how many are still in the Trash. "Restore" is the wording of the control that brings content back. */
				_n(
					'%1$d %2$s restored from the Trash, with the set it went there with. %3$d is still in the Trash — run Restore again for the rest.',
					'%1$d %2$s restored from the Trash, with the set they went there with. %3$d are still in the Trash — run Restore again for the rest.',
					$reachable,
					'sitepress'
				),
				$total,
				$label,
				$reachable
			);
		} elseif ( $refused > 0 || $missing > 0 ) {
			$sentences[] = sprintf(
				/* translators: Notice shown after content was brought back from the Trash. %1$d: how many came back, %2$s: the name of the content type. */
				__( '%1$d %2$s restored from the Trash.', 'sitepress' ),
				$total,
				$label
			);
		} else {
			$sentences[] = sprintf(
				/* translators: Notice shown after content was brought back from the Trash. %1$d: how many came back, %2$s: the name of the content type. */
				_n(
					'%1$d %2$s restored from the Trash.',
					'%1$d %2$s restored from the Trash — the whole set that went there together.',
					$total,
					'sitepress'
				),
				$total,
				$label
			);
		}

		if ( $refused > 0 ) {
			$sentences[] = sprintf(
				/* translators: %d: how many documents the viewer may not restore. */
				_n(
					'%d is still in the Trash — you do not have permission to restore it.',
					'%d are still in the Trash — you do not have permission to restore them.',
					$refused,
					'sitepress'
				),
				$refused
			);
		}

		if ( $reachable > 0 && ! $brought_back ) {
			$sentences[] = sprintf(
				/* translators: %d: how many members of the set no pass has reached yet. */
				_n(
					'Another %d of the set is still in the Trash — run Restore again for the rest.',
					'Another %d of the set are still in the Trash — run Restore again for the rest.',
					$reachable,
					'sitepress'
				),
				$reachable
			);
		}

		$this->coreNotice->replaced();

		echo wp_kses(
			'<div class="notice notice-info is-dismissible ' . esc_attr( self::NOTICE_CLASS ) . '"><p>'
				. esc_html( implode( ' ', $sentences ) ) . '</p></div>',
			array(
				'div' => array( 'class' => array() ),
				'p'   => array(),
			)
		);
	}

	private function reachableOnTheUrl( $refused ) {
		if ( isset( $_GET[ self::REACHABLE_PARAM ] ) && is_scalar( $_GET[ self::REACHABLE_PARAM ] ) ) {
			return $this->number( self::REACHABLE_PARAM );
		}

		return $this->reachable( $this->number( self::REMAINING_PARAM ), $refused );
	}

	private function number( $key ) {
		if ( in_array( $key, CoreNoticeParams::KEYS, true ) ) {
			$value = $this->coreNotice->get( $key );

			return null === $value ? 0 : max( (int) $value, 0 );
		}

		if ( ! isset( $_GET[ $key ] ) || ! is_scalar( $_GET[ $key ] ) ) {
			return 0;
		}

		return max( (int) $_GET[ $key ], 0 );
	}

	private function typeLabel( $number ) {
		$type = isset( $_GET[ self::TYPE_PARAM ] ) && is_scalar( $_GET[ self::TYPE_PARAM ] )
			? sanitize_key( wp_unslash( (string) $_GET[ self::TYPE_PARAM ] ) )
			: '';

		if ( '' !== $type ) {
			$object = get_post_type_object( $type );

			if ( is_object( $object ) && isset( $object->labels ) ) {
				$label = 1 === (int) $number ? $object->labels->singular_name : $object->labels->name;

				if ( is_string( $label ) && '' !== $label ) {
					return $label;
				}
			}
		}

		/* translators: The word for one piece of content, used inside sentences about deleting and restoring, as in "1 item restored". Singular, in lower case. */
		return _n( 'item', 'items', $number, 'sitepress' );
	}
}
