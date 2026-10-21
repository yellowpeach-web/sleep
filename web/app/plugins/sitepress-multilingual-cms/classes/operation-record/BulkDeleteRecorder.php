<?php

namespace WPML\OperationRecord;

use WPML\ContentDeletion\CoreNoticeParams;
use WPML\ContentDeletion\ItemDeleteNotice;

class BulkDeleteRecorder implements \IWPML_Backend_Action, \IWPML_DIC_Action {

	const NOTICE_WINDOW = 60;

	private $records;

	private $markers;

	private $coreNotice;

	private $route = null;

	private $recordId = 0;

	private $pendingCounts = array();

	private $members = array();

	public function __construct( ?Repository $records = null, ?TrashMarkers $markers = null, ?CoreNoticeParams $coreNotice = null ) {
		$this->records    = $records ? $records : new Repository();
		$this->markers    = $markers ? $markers : new TrashMarkers();
		$this->coreNotice = $coreNotice ? $coreNotice : new CoreNoticeParams();
	}

	public function add_hooks() {
		add_action( 'load-edit.php', array( $this, 'engage' ) );
		add_action( 'load-upload.php', array( $this, 'engage' ) );

		add_action( 'admin_notices', array( $this, 'renderNotice' ) );
	}

	public function engage() {
		$this->route = $this->detectRoute();

		if ( null === $this->route ) {
			return;
		}

		$this->members = $this->groupMembersOf( $this->selectionIds() );

		add_action( 'deleted_post', array( $this, 'countDeleted' ), 10, 2 );
		add_action( 'trashed_post', array( $this, 'countTrashed' ), 10, 1 );

		add_filter( 'wp_redirect', array( $this, 'carryRecord' ) );

		add_action( 'shutdown', array( $this, 'finalizeRun' ) );
	}

	public function detectRoute() {
		if ( ! is_admin() ) {
			return null;
		}

		$action = $this->requestedAction();

		if ( null === $action ) {
			return null;
		}

		if ( ! $this->selectionIds() ) {
			return null;
		}

		return 'trash' === $action ? Repository::ROUTE_TRASH : Repository::ROUTE_PERMANENT;
	}

	public function countDeleted( $post_id, $post = null ) {
		if ( null === $this->route ) {
			return;
		}

		if ( ! $this->isMember( $post_id ) ) {
			return;
		}

		$type = $post instanceof \WP_Post ? $post->post_type : $this->screenPostType();

		if ( 'revision' === $type ) {
			return;
		}

		$this->countRemoved( $type );
	}

	public function countTrashed( $post_id ) {
		if ( null === $this->route ) {
			return;
		}

		$post_id = (int) $post_id;

		if ( ! $this->isMember( $post_id ) ) {
			return;
		}

		$post = get_post( $post_id );
		$type = $post instanceof \WP_Post ? $post->post_type : $this->screenPostType();

		if ( 'revision' === $type ) {
			return;
		}

		$this->countRemoved( $type );
		$this->markers->mark( $post_id, $this->ensureRecord() );
	}

	private function isMember( $post_id ) {
		return in_array( (int) $post_id, $this->members, true );
	}

	private function groupMembersOf( array $selection ) {
		global $wpml_post_translations;

		$members = array_map( 'intval', $selection );

		if ( ! is_object( $wpml_post_translations ) || ! method_exists( $wpml_post_translations, 'get_element_translations' ) ) {
			return array_values( array_unique( $members ) );
		}

		if ( method_exists( $wpml_post_translations, 'prefetch_ids' ) ) {
			$wpml_post_translations->prefetch_ids( $members );
		}

		foreach ( $selection as $id ) {
			$group = $wpml_post_translations->get_element_translations( (int) $id, false, false );

			foreach ( (array) $group as $member ) {
				$members[] = (int) $member;
			}
		}

		return array_values( array_unique( $members ) );
	}

	public function finalizeRun() {
		if ( ! $this->recordId ) {
			return;
		}

		$patch = array();

		if ( $this->pendingCounts ) {
			$patch['counts_add'] = $this->pendingCounts;
		}

		$this->pendingCounts = array();

		$this->records->finalize( $this->recordId, $patch );
	}

	public function carryRecord( $location ) {
		return ItemDeleteNotice::carry( $location, $this->recordId, $this->route );
	}

	public function renderNotice() {
		global $pagenow;

		if ( 'edit.php' !== $pagenow && 'upload.php' !== $pagenow ) {
			return;
		}

		if ( ! $this->carriesCoreCount() ) {
			return;
		}

		$record = $this->records->latest( Repository::KIND_BULK_DELETE );

		if ( ! is_array( $record ) || Repository::STATUS_FINALIZED !== ( isset( $record['status'] ) ? $record['status'] : '' ) ) {
			return;
		}

		if ( ! isset( $record['actor'] ) || (int) $record['actor'] !== (int) get_current_user_id() ) {
			return;
		}

		if ( empty( $record['finished'] ) || ( time() - (int) $record['finished'] ) > self::NOTICE_WINDOW ) {
			return;
		}

		if ( ! $this->isNewestRecord( $record ) ) {
			return;
		}

		if ( ! $this->namesRecord( $record ) ) {
			return;
		}

		$reported = $this->reportedCount();

		if ( null === $reported ) {
			return;
		}

		$counts  = isset( $record['counts'] ) ? (array) $record['counts'] : array();
		$removed = $this->sumBucket( $counts, 'removed' );

		if ( $removed <= 0 || $removed === $reported ) {
			return;
		}

		$this->coreNotice->replaced();

		$this->renderText(
			$this->noticeText( isset( $record['route'] ) ? $record['route'] : null, $counts, $removed )
		);
	}

	private function isNewestRecord( array $record ) {
		$newest = $this->records->latest();

		if ( ! is_array( $newest ) || ! isset( $newest['id'] ) ) {
			return true;
		}

		return (int) $newest['id'] === ( isset( $record['id'] ) ? (int) $record['id'] : 0 );
	}

	private function countRemoved( $type ) {
		$this->ensureRecord();

		if ( ! isset( $this->pendingCounts[ $type ] ) ) {
			$this->pendingCounts[ $type ] = array();
		}

		$current                                   = isset( $this->pendingCounts[ $type ]['removed'] ) ? (int) $this->pendingCounts[ $type ]['removed'] : 0;
		$this->pendingCounts[ $type ]['removed'] = $current + 1;
	}

	private function ensureRecord() {
		if ( ! $this->recordId ) {
			$this->recordId = $this->records->start(
				Repository::KIND_BULK_DELETE,
				array(),
				(int) get_current_user_id(),
				$this->route
			);
		}

		return $this->recordId;
	}

	private function requestedAction() {
		foreach ( array( 'action', 'action2' ) as $key ) {
			if ( ! isset( $_REQUEST[ $key ] ) || ! is_scalar( $_REQUEST[ $key ] ) ) {
				continue;
			}

			$action = sanitize_text_field( wp_unslash( $_REQUEST[ $key ] ) );

			if ( 'delete' === $action || 'trash' === $action ) {
				return $action;
			}
		}

		return null;
	}

	private function selectionIds() {
		$ids = array();

		foreach ( array( 'post', 'media', 'ids' ) as $key ) {
			if ( ! isset( $_REQUEST[ $key ] ) || ! is_array( $_REQUEST[ $key ] ) ) {
				continue;
			}

			foreach ( array_map( 'intval', (array) wp_unslash( $_REQUEST[ $key ] ) ) as $id ) {
				if ( $id > 0 ) {
					$ids[] = $id;
				}
			}
		}

		return $ids;
	}

	private function screenPostType() {
		if ( isset( $_REQUEST['post_type'] ) && is_scalar( $_REQUEST['post_type'] ) ) {
			$type = sanitize_key( wp_unslash( $_REQUEST['post_type'] ) );

			if ( '' !== $type ) {
				return $type;
			}
		}

		return isset( $_REQUEST['media'] ) ? 'attachment' : 'post';
	}

	private function reportedCount() {
		return ItemDeleteNotice::carriedCount();
	}

	private function carriesCoreCount() {
		return null !== ItemDeleteNotice::carriedCount();
	}

	private function namesRecord( array $record ) {
		$named = ItemDeleteNotice::carriedRecordId();

		return null !== $named && isset( $record['id'] ) && $named === (int) $record['id'];
	}

	private function sumBucket( array $counts, $bucket ) {
		$total = 0;

		foreach ( $counts as $buckets ) {
			$total += isset( $buckets[ $bucket ] ) ? (int) $buckets[ $bucket ] : 0;
		}

		return $total;
	}

	private function noticeText( $route, array $counts, $removed ) {
		$label   = $this->typeLabel( $counts, $removed );
		$skipped = $this->sumBucket( $counts, 'skipped' );

		if ( $skipped > 0 ) {
			if ( Repository::ROUTE_TRASH === $route ) {
				/* translators: Notice shown after content was moved to the Trash. %1$d: how many were moved, %2$s: the name of the content type, %3$d: how many were already gone because their original had been deleted. */
				$template = _n(
					'%1$d %2$s moved to the Trash — %3$d was already removed with its original.',
					'%1$d %2$s moved to the Trash — %3$d were already removed with their originals.',
					$skipped,
					'sitepress'
				);
			} else {
				/* translators: Notice shown after content was deleted for good. %1$d: how many were deleted, %2$s: the name of the content type, %3$d: how many were already gone because their original had been deleted. */
				$template = _n(
					'%1$d %2$s deleted — %3$d was already removed with its original.',
					'%1$d %2$s deleted — %3$d were already removed with their originals.',
					$skipped,
					'sitepress'
				);
			}

			return sprintf( $template, $removed, $label, $skipped );
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

	private function typeLabel( array $counts, $number ) {
		$types = array_keys( $counts );

		if ( 1 === count( $types ) ) {
			$object = get_post_type_object( (string) $types[0] );

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

	private function renderText( $text ) {
		echo wp_kses(
			'<div class="notice notice-info is-dismissible wpml-bulk-delete-notice"><p>'
				. esc_html( $text )
				. '</p></div>',
			array(
				'div' => array( 'class' => array() ),
				'p'   => array(),
			)
		);
	}
}
