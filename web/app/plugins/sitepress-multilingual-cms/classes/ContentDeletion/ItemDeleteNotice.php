<?php

namespace WPML\ContentDeletion;

use WPML\LanguageEditor\RemovedLanguages\DisplayNames;
use WPML\OperationRecord\BulkDeleteRecorder;
use WPML\OperationRecord\Repository;

class ItemDeleteNotice implements \IWPML_Backend_Action, \IWPML_DIC_Action {

	const NOTICE_WINDOW = BulkDeleteRecorder::NOTICE_WINDOW;

	const RESULT_SCREENS = array( 'edit.php', 'upload.php', 'edit-tags.php' );

	const TERM_SCREEN = 'edit-tags.php';

	const POST_SCREENS = array( 'edit.php', 'upload.php' );

	const TERM_DELETED_MESSAGE = 2;

	const TERM_TYPE_PREFIX = 'tax_';

	const UNDONE_PARAM = 'wpml_undone';

	const REFUSED_PARAM = 'wpml_undo_refused';

	const REMAINING_PARAM = 'wpml_undo_remaining';

	const RECORD_PARAM = 'wpml_delete_record';

	const REPORTED_PARAM = 'wpml_delete_reported';

	const OWN_PARAMS = array(
		self::UNDONE_PARAM,
		self::REFUSED_PARAM,
		self::REMAINING_PARAM,
		self::RECORD_PARAM,
		self::REPORTED_PARAM,
	);

	const NOTICE_CLASS = 'wpml-item-delete-notice';

	private $records;

	private $coreNotice;

	public function __construct( ?Repository $records = null, ?CoreNoticeParams $coreNotice = null ) {
		$this->records    = $records ? $records : new Repository();
		$this->coreNotice = $coreNotice ? $coreNotice : new CoreNoticeParams();
	}

	public function add_hooks() {
		add_action( 'admin_notices', array( $this, 'renderNotice' ) );

		add_filter( 'removable_query_args', array( $this, 'removeOutcomeParams' ) );

		add_action( 'admin_init', array( $this, 'stripOutcomeParams' ), 1 );
	}

	public function stripOutcomeParams() {
		if ( ! isset( $_SERVER['REQUEST_URI'] ) || ! is_string( $_SERVER['REQUEST_URI'] ) ) {
			return;
		}

		$query = strstr( sanitize_text_field( wp_unslash( $_SERVER['REQUEST_URI'] ) ), '?' );
		parse_str( $query ? substr( $query, 1 ) : '', $carried );
		if ( ! array_intersect_key( $carried, array_flip( self::OWN_PARAMS ) ) ) {
			return;
		}

		$_SERVER['REQUEST_URI'] = remove_query_arg( self::OWN_PARAMS, esc_url_raw( wp_unslash( $_SERVER['REQUEST_URI'] ) ) );
	}

	public function removeOutcomeParams( $args ) {
		if ( ! is_array( $args ) ) {
			return $args;
		}

		return array_merge( $args, self::OWN_PARAMS );
	}

	public static function carry( $location, $recordId, $route ) {
		if ( ! is_string( $location ) || '' === $location ) {
			return $location;
		}

		if ( ! $recordId ) {
			return self::withoutDeleteParams( $location );
		}

		$location = add_query_arg( self::RECORD_PARAM, (int) $recordId, $location );
		$reported = self::coreCountOn( $location, $route );

		if ( null !== $reported ) {
			$location = add_query_arg( self::REPORTED_PARAM, $reported, $location );
		}

		return $location;
	}

	public static function carriedRecordId() {
		return self::carriedInt( self::RECORD_PARAM );
	}

	public static function carriedCount() {
		return self::carriedInt( self::REPORTED_PARAM );
	}

	private static function carriedInt( $key ) {
		$value = self::requestParam( $key );

		return null === $value ? null : (int) $value;
	}

	public static function requestParam( $key ) {
		if ( ! isset( $_GET[ $key ] ) || ! is_scalar( $_GET[ $key ] ) ) {
			return null;
		}

		return sanitize_text_field( wp_unslash( $_GET[ $key ] ) );
	}

	private static function withoutDeleteParams( $location ) {
		if ( false === strpos( $location, self::RECORD_PARAM ) && false === strpos( $location, self::REPORTED_PARAM ) ) {
			return $location;
		}

		return remove_query_arg( array( self::RECORD_PARAM, self::REPORTED_PARAM ), $location );
	}

	private static function coreCountOn( $location, $route ) {
		$query = wp_parse_url( $location, PHP_URL_QUERY );

		if ( ! is_string( $query ) || '' === $query ) {
			return null;
		}

		parse_str( $query, $params );

		$key = Repository::ROUTE_TRASH === $route ? 'trashed' : 'deleted';

		if ( ! isset( $params[ $key ] ) || ! is_scalar( $params[ $key ] ) ) {
			return null;
		}

		return (int) $params[ $key ];
	}

	public function renderNotice() {
		if ( ! $this->isResultScreen() ) {
			return;
		}

		if ( $this->renderUndoOutcome() ) {
			return;
		}

		if ( ! $this->carriesCoreCount() ) {
			return;
		}

		$record = $this->records->latest( Repository::KIND_ITEM_DELETE );

		if ( ! is_array( $record ) || Repository::STATUS_FINALIZED !== ( isset( $record['status'] ) ? $record['status'] : '' ) ) {
			return;
		}

		if ( ! isset( $record['actor'] ) || (int) $record['actor'] !== (int) get_current_user_id() ) {
			return;
		}

		if ( empty( $record['finished'] ) || ( time() - (int) $record['finished'] ) > self::NOTICE_WINDOW ) {
			return;
		}

		if ( 'undone' === ( isset( $record['note'] ) ? (string) $record['note'] : '' ) ) {
			return;
		}

		if ( ! $this->isNewestRecord( $record ) ) {
			return;
		}

		if ( ! $this->namesRecord( $record ) ) {
			return;
		}

		$route    = isset( $record['route'] ) ? $record['route'] : null;
		$counts   = isset( $record['counts'] ) ? (array) $record['counts'] : array();
		$reported = $this->reportedCount( $counts );

		if ( null === $reported ) {
			return;
		}

		$removed = $this->sumBucket( $counts, 'removed' );

		if ( $removed <= 0 ) {
			return;
		}

		$sentences = array();

		if ( $removed !== $reported ) {
			$sentences[] = $this->countSentence( $route, $counts, $removed, $record );

			$this->coreNotice->replaced();
		}

		$promote = $this->promoteSentence( $counts, $record );

		if ( '' !== $promote ) {
			$sentences[] = $promote;
		}

		if ( ! $sentences ) {
			return;
		}

		$this->renderText( implode( ' ', $sentences ), $this->undoableId( $record ) );
	}

	private function countSentence( $route, array $counts, $removed, array $record ) {
		$label  = $this->typeLabel( $counts, $removed );
		$source = $this->sourceName( $record );
		$others = $removed - 1;

		if ( '' !== $source && $others >= 1 ) {
			if ( Repository::ROUTE_TRASH === $route ) {
				/* translators: Notice shown after content was moved to the Trash. %1$d: how many items were moved, %2$s: the name of the content type, %3$s: the language the original is in, %4$d: how many translations went with it. */
				$template = _n(
					'%1$d %2$s moved to the Trash — %3$s and its %4$d translation.',
					'%1$d %2$s moved to the Trash — %3$s and its %4$d translations.',
					$others,
					'sitepress'
				);
			} else {
				/* translators: Notice shown after content was deleted for good. %1$d: how many items were deleted, %2$s: the name of the content type, %3$s: the language the original is in, %4$d: how many translations went with it. */
				$template = _n(
					'%1$d %2$s deleted — %3$s and its %4$d translation.',
					'%1$d %2$s deleted — %3$s and its %4$d translations.',
					$others,
					'sitepress'
				);
			}

			return sprintf( $template, $removed, $label, $source, $others );
		}

		if ( Repository::ROUTE_TRASH === $route ) {
			/* translators: Notice shown after content was moved to the Trash together with its translations. %1$d: how many items were moved, %2$s: the name of the content type. */
			$template = _n(
				'%1$d %2$s moved to the Trash — including its translations.',
				'%1$d %2$s moved to the Trash — including their translations.',
				$removed,
				'sitepress'
			);
		} else {
			/* translators: Notice shown after content was deleted for good together with its translations. %1$d: how many items were deleted, %2$s: the name of the content type. */
			$template = _n(
				'%1$d %2$s deleted — including its translations.',
				'%1$d %2$s deleted — including their translations.',
				$removed,
				'sitepress'
			);
		}

		return sprintf( $template, $removed, $label );
	}

	private function promoteSentence( array $counts, array $record ) {
		$code = isset( $record['promoted_to'] ) ? (string) $record['promoted_to'] : '';

		if ( '' === $code ) {
			return '';
		}

		$name = $this->languageName( $code );

		return sprintf(
			/* translators: Notice shown after the original of a set of translations changed to another language. %1$s: the name of that language, %2$s: the name of the content type in the singular, for example page. */
			__( 'The %1$s %2$s is now the one the others are translated from.', 'sitepress' ),
			'' === $name ? $code : $name,
			$this->typeLabel( $counts, 1 )
		);
	}

	private function undoableId( array $record ) {
		if ( Repository::ROUTE_TRASH !== ( isset( $record['route'] ) ? $record['route'] : null ) ) {
			return 0;
		}

		$until = isset( $record['undo_until'] ) ? (int) $record['undo_until'] : 0;

		if ( $until <= 0 || $until < time() ) {
			return 0;
		}

		return isset( $record['id'] ) ? (int) $record['id'] : 0;
	}

	private function renderUndoOutcome() {
		if ( $this->landedFromDelete() ) {
			return false;
		}

		$restored = self::carriedInt( self::UNDONE_PARAM );

		if ( null === $restored || $restored < 0 ) {
			return false;
		}

		$refused   = max( (int) self::carriedInt( self::REFUSED_PARAM ), 0 );
		$remaining = max( (int) self::carriedInt( self::REMAINING_PARAM ), 0 );

		if ( 0 === $restored && 0 === $refused ) {
			return false;
		}

		$record = $this->undoRecord();
		$set    = null === $record ? 0 : $this->sumBucket( (array) $record['counts'], 'removed' );
		$back   = null !== $record && isset( $record['restored'] ) ? (int) $record['restored'] : $restored;
		$back   = max( $back, 0 );

		$sentences = array();

		if ( $restored > 0 && $remaining > 0 ) {
			$total = $set > 0 ? max( $set, $back + $remaining ) : $back + $remaining;

			$sentences[] = sprintf(
				/* translators: Notice shown while content is being brought back from the Trash a step at a time. %1$d: how many have come back so far, %2$d: how many there are in all. "Undo" is the wording of the control that takes the deletion back. */
				_n(
					'%1$d of %2$d item restored from the Trash — run Undo again for the rest.',
					'%1$d of %2$d items restored from the Trash — run Undo again for the rest.',
					$total,
					'sitepress'
				),
				$back,
				$total
			);
		} elseif ( $restored > 0 && $set > 0 && $back >= $set ) {
			$sentences[] = sprintf(
				/* translators: %d: how many documents the undo brought back in total. */
				_n(
					'All %d item is back out of the Trash.',
					'All %d items are back out of the Trash.',
					$set,
					'sitepress'
				),
				$set
			);
		} elseif ( $restored > 0 && $set > 0 ) {
			$sentences[] = sprintf(
				/* translators: Notice shown after content was brought back from the Trash. %1$d: how many came back, %2$d: how many there were in all. */
				_n(
					'%1$d of %2$d item restored from the Trash.',
					'%1$d of %2$d items restored from the Trash.',
					$set,
					'sitepress'
				),
				$back,
				$set
			);
		} elseif ( $restored > 0 ) {
			$sentences[] = sprintf(
				/* translators: %d: how many documents came back out of the Trash. */
				_n(
					'%d item restored from the Trash.',
					'%d items restored from the Trash.',
					$back,
					'sitepress'
				),
				$back
			);
		}

		if ( $refused > 0 ) {
			$sentences[] = sprintf(
				/* translators: %d: how many documents the viewer may not restore. */
				_n(
					'%d could not be restored — you do not have permission to restore it.',
					'%d could not be restored — you do not have permission to restore them.',
					$refused,
					'sitepress'
				),
				$refused
			);
		}

		$this->renderText( implode( ' ', $sentences ), $remaining > 0 ? $this->undoableAgain() : 0, $refused > 0 );

		return true;
	}

	private function undoRecord() {
		$record = $this->records->latest( Repository::KIND_ITEM_DELETE );

		if ( ! is_array( $record ) || ! isset( $record['counts'] ) ) {
			return null;
		}

		if ( Repository::STATUS_FINALIZED !== ( isset( $record['status'] ) ? $record['status'] : '' ) ) {
			return null;
		}

		if ( ! isset( $record['actor'] ) || (int) $record['actor'] !== (int) get_current_user_id() ) {
			return null;
		}

		if ( ! $this->isNewestRecord( $record ) ) {
			return null;
		}

		return $record;
	}

	private function undoableAgain() {
		$record = $this->undoRecord();

		if ( null === $record ) {
			return 0;
		}

		if ( 'undone' === ( isset( $record['note'] ) ? (string) $record['note'] : '' ) ) {
			return 0;
		}

		return $this->undoableId( $record );
	}

	private function isNewestRecord( array $record ) {
		$newest = $this->records->latest();

		if ( ! is_array( $newest ) || ! isset( $newest['id'] ) ) {
			return true;
		}

		return (int) $newest['id'] === ( isset( $record['id'] ) ? (int) $record['id'] : 0 );
	}

	private function reportedCount( array $counts ) {
		global $pagenow;

		$terms = $this->describesTerms( $counts );

		if ( self::TERM_SCREEN === $pagenow ) {
			return $terms ? $this->reportedTermCount() : null;
		}

		if ( ! in_array( $pagenow, self::POST_SCREENS, true ) ) {
			return null;
		}

		if ( $terms ) {
			return null;
		}

		return self::carriedCount();
	}

	private function reportedTermCount() {
		$value = $this->coreNotice->get( 'message' );

		if ( null === $value ) {
			return null;
		}

		return self::TERM_DELETED_MESSAGE === (int) $value ? 1 : null;
	}

	private function describesTerms( array $counts ) {
		if ( ! $counts ) {
			return false;
		}

		foreach ( array_keys( $counts ) as $type ) {
			$type = (string) $type;

			if ( 0 !== strpos( $type, self::TERM_TYPE_PREFIX ) ) {
				return false;
			}

			if ( function_exists( 'post_type_exists' ) && post_type_exists( $type ) ) {
				return false;
			}
		}

		return true;
	}

	private function namesRecord( array $record ) {
		$named = self::carriedRecordId();

		return null !== $named && isset( $record['id'] ) && $named === (int) $record['id'];
	}

	private function isResultScreen() {
		global $pagenow;

		return is_string( $pagenow ) && in_array( $pagenow, self::RESULT_SCREENS, true );
	}

	private function carriesCoreCount() {
		global $pagenow;

		if ( self::TERM_SCREEN === $pagenow ) {
			return null !== $this->reportedTermCount();
		}

		return null !== self::carriedCount();
	}

	private function landedFromDelete() {
		global $pagenow;

		if ( self::TERM_SCREEN === $pagenow ) {
			return null !== $this->reportedTermCount();
		}

		foreach ( array( 'deleted', 'trashed' ) as $key ) {
			if ( null !== $this->coreNotice->get( $key ) ) {
				return true;
			}
		}

		return false;
	}

	private function sumBucket( array $counts, $bucket ) {
		$total = 0;

		foreach ( $counts as $buckets ) {
			$total += isset( $buckets[ $bucket ] ) ? (int) $buckets[ $bucket ] : 0;
		}

		return $total;
	}

	private function typeLabel( array $counts, $number ) {
		$types = array_keys( $counts );

		if ( 1 === count( $types ) ) {
			$type = (string) $types[0];

			$object = 0 === strpos( $type, self::TERM_TYPE_PREFIX ) && function_exists( 'get_taxonomy' )
				? get_taxonomy( substr( $type, strlen( self::TERM_TYPE_PREFIX ) ) )
				: get_post_type_object( $type );

			if ( $object && isset( $object->labels ) ) {
				$label = 1 === (int) $number ? $object->labels->singular_name : $object->labels->name;

				if ( is_string( $label ) && '' !== $label ) {
					return $label;
				}
			}
		}

		/* translators: The word for one piece of content, used inside sentences about deleting and restoring, as in "1 item restored". Singular, in lower case. */
		return _n( 'item', 'items', $number, 'sitepress' );
	}

	private function sourceName( array $record ) {
		$code = isset( $record['source_lang'] ) ? (string) $record['source_lang'] : '';

		return '' === $code ? '' : $this->languageName( $code );
	}

	private function languageName( $code ) {
		global $sitepress;

		if ( is_object( $sitepress ) && method_exists( $sitepress, 'get_active_languages' ) ) {
			foreach ( (array) $sitepress->get_active_languages() as $active => $language ) {
				if ( (string) $active !== $code ) {
					continue;
				}

				$language = (array) $language;

				if ( ! empty( $language['display_name'] ) ) {
					return (string) $language['display_name'];
				}
			}
		}

		if ( class_exists( DisplayNames::class ) ) {
			$names = DisplayNames::forCodes( array( $code ) );

			if ( isset( $names[ $code ] ) && '' !== $names[ $code ] ) {
				return (string) $names[ $code ];
			}
		}

		return $code;
	}

	private function renderText( $text, $undoable, $warning = false ) {
		$attributes = 'class="notice ' . ( $warning ? 'notice-warning' : 'notice-info' )
			. ' is-dismissible ' . esc_attr( self::NOTICE_CLASS ) . '"';

		if ( $undoable > 0 ) {
			$attributes .= ' data-record="' . esc_attr( (string) $undoable ) . '"'
				/* translators: Label of the control that takes the last deletion back. Verb, imperative. */
				. ' data-undo-label="' . esc_attr( __( 'Undo', 'sitepress' ) ) . '"'
				. ' data-undo-aria="' . esc_attr(
					__( 'Undo — bring the whole set back out of the Trash', 'sitepress' )
				) . '"';
		}

		echo wp_kses(
			'<div ' . $attributes . '><p>' . esc_html( $text ) . '</p></div>',
			array(
				'div' => array(
					'class'           => array(),
					'data-record'     => array(),
					'data-undo-label' => array(),
					'data-undo-aria'  => array(),
				),
				'p'   => array(),
			)
		);
	}
}
