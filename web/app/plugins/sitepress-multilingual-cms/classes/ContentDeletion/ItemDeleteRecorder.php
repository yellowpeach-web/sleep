<?php

namespace WPML\ContentDeletion;

use WPML\LanguageEditor\Save\Phase\SyncSuspension;
use WPML\OperationRecord\Repository;
use WPML\OperationRecord\TrashMarkers;
use WPML\OperationRecord\UndoWindow;

class ItemDeleteRecorder implements \IWPML_Backend_Action, \IWPML_Frontend_Action {

	const HOOK_PROMOTED = 'wpml_content_deletion_promoted';

	const HOOK_JOBS_CANCELLED = 'wpml_content_deletion_jobs_cancelled';

	private $records;

	private $markers;

	private $route = null;

	private $recordId = 0;

	private $startedAt = 0;

	private $group = array();

	private $sourceLang = null;

	private $groupType = null;

	private $removed = array();

	private $pendingCounts = array();

	private $pendingJobs = null;

	private $promotedTo = null;

	private $finalizeArmed = false;

	private $answer;

	private $undoWindow;

	public function __construct( ?Repository $records = null, ?TrashMarkers $markers = null, ?UndoWindow $undoWindow = null ) {
		$this->records    = $records ? $records : new Repository();
		$this->markers    = $markers ? $markers : new TrashMarkers();
		$this->undoWindow = $undoWindow ? $undoWindow : new UndoWindow();
	}

	public function add_hooks() {
		add_action( 'wp_trash_post', array( $this, 'beforeTrash' ), 1 );
		add_action( 'delete_post', array( $this, 'beforeDelete' ), 1 );

		add_action( 'trashed_post', array( $this, 'countTrashed' ), 10, 1 );
		add_action( 'deleted_post', array( $this, 'countDeleted' ), 10, 2 );

		add_action( 'pre_delete_term', array( $this, 'beforeTermDelete' ), 1, 2 );
		add_action( 'delete_term', array( $this, 'countDeletedTerm' ), 1, 3 );

		add_action( self::HOOK_PROMOTED, array( $this, 'notePromote' ), 10, 2 );
		add_action( self::HOOK_JOBS_CANCELLED, array( $this, 'noteJobs' ), 10, 2 );

		add_filter( 'wp_redirect', array( $this, 'carryRecord' ) );
	}

	public function carryRecord( $location ) {
		if ( ! $this->recordId && $this->isBulkRequest() ) {
			return $location;
		}

		return ItemDeleteNotice::carry( $location, $this->recordId, $this->route );
	}

	public function beforeTrash( $post_id ) {
		$this->engage( Repository::ROUTE_TRASH, $post_id );
	}

	public function beforeDelete( $post_id ) {
		$this->engage( Repository::ROUTE_PERMANENT, $post_id );
	}

	public function beforeTermDelete( $term_id, $taxonomy ) {
		if ( null !== $this->route ) {
			return;
		}

		if ( $this->isBulkRequest() || SyncSuspension::isSuspended() ) {
			return;
		}

		$translations = $this->termTranslations();

		if ( ! $translations ) {
			return;
		}

		$tt_id = (int) $translations->adjust_ttid_for_term_id( (int) $term_id );

		if ( ! $tt_id ) {
			return;
		}

		$group = $translations->get_element_translations( $tt_id, false, false );
		$group = is_array( $group ) ? array_map( 'intval', $group ) : array();

		if ( count( $group ) < 2 ) {
			return;
		}

		$this->route      = Repository::ROUTE_PERMANENT;
		$this->group      = $group;
		$this->groupType  = 'tax_' . (string) $taxonomy;
		$this->sourceLang = $this->sourceLangOf( $translations, $tt_id, $group );
	}

	public function countDeletedTerm( $term, $tt_id, $taxonomy ) {
		if ( null === $this->route || null === $this->groupType ) {
			return;
		}

		if ( ! $this->isMember( $tt_id ) ) {
			return;
		}

		$this->countRemoved( 'tax_' . (string) $taxonomy, (int) $tt_id );
	}

	private function termTranslations() {
		global $wpml_term_translations;

		return is_object( $wpml_term_translations )
			&& method_exists( $wpml_term_translations, 'adjust_ttid_for_term_id' )
			&& method_exists( $wpml_term_translations, 'get_element_translations' )
			? $wpml_term_translations
			: null;
	}

	private function engage( $route, $post_id ) {
		if ( null !== $this->route ) {
			return;
		}

		if ( $this->isBulkRequest() || SyncSuspension::isSuspended() ) {
			return;
		}

		$post_id = (int) $post_id;
		$type    = get_post_type( $post_id );

		if ( ! $post_id || ! $type || 'revision' === $type ) {
			return;
		}

		if ( 'nav_menu_item' === $type ) {
			return;
		}

		$group = $this->groupOf( $post_id );

		if ( count( $group ) < 2 ) {
			return;
		}

		$this->route      = $route;
		$this->group      = $group;
		$this->sourceLang = $this->sourceLangOf( $this->postTranslations(), $post_id, $group );
	}

	private function sourceLangOf( $translations, $element_id, array $group ) {
		if ( is_object( $translations ) && method_exists( $translations, 'get_source_lang_code' ) ) {
			$code = $translations->get_source_lang_code( (int) $element_id );

			if ( is_string( $code ) && '' !== $code ) {
				return $code;
			}
		}

		$code = array_search( (int) $element_id, $group, true );

		return is_string( $code ) && '' !== $code ? $code : null;
	}

	private function postTranslations() {
		global $wpml_post_translations;

		return is_object( $wpml_post_translations ) ? $wpml_post_translations : null;
	}

	public function countTrashed( $post_id ) {
		if ( null === $this->route || null !== $this->groupType ) {
			return;
		}

		$post_id = (int) $post_id;

		if ( ! $this->isMember( $post_id ) ) {
			return;
		}

		$post = get_post( $post_id );
		$type = $post instanceof \WP_Post ? $post->post_type : get_post_type( $post_id );

		if ( 'revision' === $type ) {
			return;
		}

		$this->countRemoved( (string) $type, $post_id );
		$this->markers->mark( $post_id, $this->ensureRecord() );
	}

	private function isMember( $element_id ) {
		return in_array( (int) $element_id, array_map( 'intval', $this->group ), true );
	}

	public function countDeleted( $post_id, $post = null ) {
		if ( null === $this->route || null !== $this->groupType ) {
			return;
		}

		if ( ! $this->isMember( $post_id ) ) {
			return;
		}

		$type = $post instanceof \WP_Post ? $post->post_type : get_post_type( $post_id );

		if ( 'revision' === $type ) {
			return;
		}

		$this->countRemoved( (string) $type, (int) $post_id );
	}

	public function notePromote( $trid, $language ) {
		if ( null === $this->route ) {
			return;
		}

		$this->promotedTo = (string) $language;
	}

	public function noteJobs( $cancelled, $stopped_in_progress ) {
		if ( null === $this->route ) {
			return;
		}

		if ( null === $this->pendingJobs ) {
			$this->pendingJobs = array(
				'cancelled'           => 0,
				'stopped_in_progress' => 0,
			);
		}

		$this->pendingJobs['cancelled']           += (int) $cancelled;
		$this->pendingJobs['stopped_in_progress'] += (int) $stopped_in_progress;
	}

	public function finalizeRun() {
		if ( ! $this->recordId ) {
			return;
		}

		$counts = $this->pendingCounts;

		foreach ( $this->keptCounts() as $type => $kept ) {
			if ( ! isset( $counts[ $type ] ) ) {
				$counts[ $type ] = array();
			}

			$counts[ $type ]['kept'] = ( isset( $counts[ $type ]['kept'] ) ? (int) $counts[ $type ]['kept'] : 0 ) + $kept;
		}

		$patch = array();

		if ( $counts ) {
			$patch['counts_add'] = $counts;
		}

		if ( null !== $this->pendingJobs ) {
			$patch['jobs_add'] = $this->pendingJobs;
		}

		if ( null !== $this->promotedTo ) {
			$patch['promoted_to'] = $this->promotedTo;
		}

		if ( $this->cameFromTranslation() ) {
			$patch['entry_point'] = 'translation';
		}

		if ( null !== $this->sourceLang ) {
			$patch['source_lang'] = $this->sourceLang;
		}

		if ( Repository::ROUTE_TRASH === $this->route && $this->startedAt ) {
			$until = $this->undoWindow->until( $this->startedAt );

			if ( null !== $until ) {
				$patch['undo_until'] = $until;
			}
		}

		$this->pendingCounts = array();

		$this->records->finalize( $this->recordId, $patch );
	}

	public function isBulkRequest() {
		if ( isset( $_GET['delete_all'] ) || isset( $_GET['delete_all2'] ) ) {
			return true;
		}

		$isDeleteAction = false;

		foreach ( array( 'action', 'action2' ) as $key ) {
			if ( ! isset( $_REQUEST[ $key ] ) || ! is_scalar( $_REQUEST[ $key ] ) ) {
				continue;
			}

			$action = sanitize_text_field( wp_unslash( (string) $_REQUEST[ $key ] ) );

			if ( 'delete' === $action || 'trash' === $action || 'bulk-delete' === $action ) {
				$isDeleteAction = true;
			}
		}

		if ( ! $isDeleteAction ) {
			return false;
		}

		foreach ( array( 'post', 'media', 'ids', 'delete_tags' ) as $key ) {
			if ( ! isset( $_REQUEST[ $key ] ) || ! is_array( $_REQUEST[ $key ] ) ) {
				continue;
			}

			if ( array_filter( array_map( 'intval', (array) wp_unslash( $_REQUEST[ $key ] ) ) ) ) {
				return true;
			}
		}

		return false;
	}

	private function cameFromTranslation() {
		if ( ! $this->answer ) {
			$this->answer = new DialogAnswer();
		}

		return $this->answer->cameFromTranslation();
	}

	private function groupOf( $post_id ) {
		global $wpml_post_translations;

		if ( ! is_object( $wpml_post_translations ) || ! method_exists( $wpml_post_translations, 'get_element_translations' ) ) {
			return array();
		}

		$group = $wpml_post_translations->get_element_translations( $post_id, false, false );

		return is_array( $group ) ? array_map( 'intval', $group ) : array();
	}

	private function keptCounts() {
		$kept = array();

		foreach ( $this->group as $element_id ) {
			$element_id = (int) $element_id;

			if ( in_array( $element_id, $this->removed, true ) ) {
				continue;
			}

			$type = null !== $this->groupType ? $this->groupType : get_post_type( $element_id );

			if ( ! $type ) {
				continue;
			}

			$kept[ $type ] = isset( $kept[ $type ] ) ? $kept[ $type ] + 1 : 1;
		}

		return $kept;
	}

	private function countRemoved( $type, $post_id ) {
		$this->ensureRecord();

		if ( ! in_array( $post_id, $this->removed, true ) ) {
			$this->removed[] = $post_id;
		}

		if ( ! isset( $this->pendingCounts[ $type ] ) ) {
			$this->pendingCounts[ $type ] = array();
		}

		$current                                 = isset( $this->pendingCounts[ $type ]['removed'] ) ? (int) $this->pendingCounts[ $type ]['removed'] : 0;
		$this->pendingCounts[ $type ]['removed'] = $current + 1;
	}

	private function ensureRecord() {
		if ( $this->recordId ) {
			return $this->recordId;
		}

		$this->startedAt = time();
		$this->recordId  = $this->records->start(
			Repository::KIND_ITEM_DELETE,
			array_keys( $this->group ),
			(int) get_current_user_id(),
			$this->route
		);

		if ( ! $this->finalizeArmed ) {
			$this->finalizeArmed = true;
			add_action( 'shutdown', array( $this, 'finalizeRun' ) );
		}

		return $this->recordId;
	}
}
