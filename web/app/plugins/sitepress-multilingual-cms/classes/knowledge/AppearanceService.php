<?php

namespace WPML\Knowledge;

use WPML\TM\ATE\TranslateEverything\UntranslatedPackages;
use WPML\TM\ATE\TranslateEverything\UntranslatedPosts;
use function WPML\Container\make;

class AppearanceService implements \IWPML_Backend_Action, \IWPML_Frontend_Action, \IWPML_DIC_Action {

	const CACHE_GROUP = AppearanceRepository::CACHE_GROUP;

	const TTL = AppearanceRepository::TTL;

	const MODE_ADMIN    = 'admin';
	const MODE_VISITORS = 'visitors';
	const MODE_OFF      = 'off';

	const ACTION_STRINGS_RECORDED = 'wpml_appearance_strings_recorded';

	const OPTION_FAILURES = 'wpml_appearance_write_failures';

	const FAILURE_KINDS = [ 'read', 'write', 'reopen' ];

	const NOTICE_GROUP     = 'wpml-appearances';
	const NOTICE_ID_PREFIX = 'wpml-appearances-failed-';

	const ST_SETTING  = 'st';
	const ST_MODE_KEY = 'autoregister_strings';

	const ST_MODE_DISABLED = 2;

	const ST_ACTION_MODE_CHANGED = 'wpml_st_autoregister_strings_mode_changed';

	const ST_MODE_STARTED_KEY = 'autoregister_strings_enabled_time';

	const ST_MODE_VISITORS = 1;

	const ST_MODE_HORIZON = 7200;

	const OPTION_LAST_MODE = 'wpml_appearance_last_mode';

	const OPTION_SURFACES = 'wpml_appearance_surfaces';

	const SURFACE_ON  = 'on';
	const SURFACE_OFF = 'off';

	const READ_ONLY_SURFACES = [ 'string' ];

	private $repository;

	private $pending = [];

	private $flushArmed = false;

	public function __construct( AppearanceRepository $repository ) {
		$this->repository = $repository;
	}

	public function add_hooks() {
		add_action( 'wpml_appearance_mark', [ $this, 'mark' ], 10, 2 );
		add_action( 'wpml_appearance_record', [ $this, 'record' ], 10, 3 );
		add_action( 'template_redirect', [ $this, 'markSingular' ] );

		add_filter( 'wpml_appearance_has', [ $this, 'has' ], 10, 3 );
		add_filter( 'wpml_appearance_filter_ids', [ $this, 'filterIds' ], 10, 3 );
		add_filter( 'wpml_appearance_count_missing', [ $this, 'countMissing' ], 10, 2 );
		add_filter( 'wpml_appearance_exists_sql', [ $this, 'existsSql' ], 10, 3 );
		add_filter( 'wpml_appearance_detection_mode', [ $this, 'detectionModeFilter' ], 10, 2 );
		add_filter( 'wpml_appearance_available', [ $this, 'available' ], 10, 1 );
		add_filter( 'wpml_appearance_import_strings', [ $this, 'importStrings' ], 10, 1 );
		add_filter( 'wpml_appearance_surfaces', [ $this, 'surfaces' ], 10, 1 );
		add_action( 'wpml_appearance_set_surfaces', [ $this, 'setSurfaces' ], 10, 1 );

		add_action( 'delete_post', [ $this, 'deletePost' ], 10, 2 );
		add_action( 'delete_term', [ $this, 'deleteTerm' ], 10, 3 );
		add_action( 'icl_st_unregister_string', [ $this, 'deleteString' ], 10, 1 );
		add_action( 'icl_st_unregister_string_multi', [ $this, 'deleteStrings' ], 10, 1 );
		add_action( 'wpml_st_delete_all_string_data', [ $this, 'deleteString' ], 10, 1 );

		add_action( 'admin_init', [ $this, 'refreshNotices' ] );

		add_action( self::ST_ACTION_MODE_CHANGED, [ $this, 'reopenWhenDetectionTurnsOff' ], 10, 1 );
		add_action( 'wp_loaded', [ $this, 'reopenWhenDetectionStopped' ], PHP_INT_MAX );
	}

	public function reopenWhenDetectionTurnsOff( $newMode ) {
		if ( self::ST_MODE_DISABLED !== (int) $newMode ) {
			return;
		}
		if ( ! did_action( 'wp_loaded' ) ) {
			return;
		}
		if ( self::MODE_OFF === get_option( self::OPTION_LAST_MODE, null ) ) {
			return;
		}

		try {
			$this->reopenDeclaredTypes();
		} catch ( \Throwable $e ) {
			$this->reopenFailed( $e );

			return;
		}
		update_option( self::OPTION_LAST_MODE, self::MODE_OFF, true );
		$this->succeeded( 'reopen' );
	}

	public function reopenWhenDetectionStopped() {
		$mode = $this->detectionMode();
		if ( self::MODE_OFF !== $mode ) {
			$this->succeeded( 'reopen' );
		}
		$last = get_option( self::OPTION_LAST_MODE, null );
		if ( $mode === $last ) {
			return;
		}

		$reopen = self::MODE_OFF === $mode && in_array( $last, [ self::MODE_ADMIN, self::MODE_VISITORS ], true );
		if ( $reopen ) {
			try {
				$this->reopenDeclaredTypes();
			} catch ( \Throwable $e ) {
				$this->reopenFailed( $e );

				return;
			}
		}
		update_option( self::OPTION_LAST_MODE, $mode, true );
		if ( $reopen ) {
			$this->succeeded( 'reopen' );
		}
	}

	public function reopenDeclaredTypes() {
		$this->reopenTypes( $this->declaredTypes() );
	}

	public function mark( $type, $id ) {
		$id = (int) $id;
		if ( $id <= 0 || ! self::isKnownType( $type ) || ! $this->isFrontEndView() ) {
			return;
		}

		$mode = $this->detectionMode( $type );
		if ( self::MODE_OFF === $mode ) {
			return;
		}

		if ( self::MODE_ADMIN === $mode && ! current_user_can( 'manage_options' ) ) {
			return;
		}

		$this->add( $type, $id, self::MODE_ADMIN === $mode ? 'admin' : 'visitor' );
	}

	public function record( $type, $ids, $seenBy ) {
		if ( ! is_array( $ids ) || ! in_array( $seenBy, AppearanceRepository::SEEN_BY, true ) || ! self::isKnownType( $type ) ) {
			return;
		}
		if ( self::MODE_OFF === $this->detectionMode( $type ) ) {
			return;
		}

		foreach ( $ids as $id ) {
			$id = is_numeric( $id ) ? (int) $id : 0;
			if ( $id > 0 ) {
				$this->add( $type, $id, $seenBy );
			}
		}
	}

	public function markSingular() {
		if ( ! is_singular() ) {
			return;
		}

		$id   = (int) get_queried_object_id();
		$type = $id > 0 ? get_post_type( $id ) : false;
		if ( ! $type ) {
			return;
		}

		$this->mark( 'post_' . $type, $id );
	}

	public function pending() {
		return array_keys( $this->pending );
	}

	public function flush() {
		$pending          = $this->pending;
		$this->pending    = [];
		$this->flushArmed = false;
		if ( ! $pending ) {
			return;
		}

		foreach ( wp_cache_get_multiple( array_keys( $pending ), self::CACHE_GROUP ) as $key => $note ) {
			if ( false !== $note ) {
				unset( $pending[ $key ] );
			}
		}
		if ( ! $pending ) {
			return;
		}

		$keys = [];
		foreach ( $pending as $entry ) {
			$keys[] = [ $entry[0], $entry[1] ];
		}
		$lastSeen = $this->repository->findLastSeen( $keys );
		if ( null === $lastSeen ) {
			$this->failed( 'read' );

			return;
		}
		$this->succeeded( 'read' );

		$now      = time();
		$new      = [];
		$bySeenBy = [];
		foreach ( $pending as $key => $entry ) {
			if ( isset( $lastSeen[ $key ] ) ) {
				$seenAt = strtotime( $lastSeen[ $key ] . ' UTC' );
				if ( false !== $seenAt && $seenAt > $now - self::TTL ) {
					wp_cache_add( $key, 1, self::CACHE_GROUP, self::TTL );
					continue;
				}
			} else {
				$new[ $key ] = $entry;
			}
			$bySeenBy[ $entry[2] ][ $key ] = $entry;
		}
		if ( ! $bySeenBy ) {
			return;
		}

		$stamp = gmdate( 'Y-m-d H:i:s', $now );
		foreach ( $bySeenBy as $seenBy => $entries ) {
			$rows = [];
			foreach ( $entries as $entry ) {
				$rows[] = [ $entry[0], $entry[1], $stamp, $stamp ];
			}
			if ( null === $this->repository->upsert( $rows, $seenBy ) ) {
				$this->failed( 'write' );

				return;
			}
			foreach ( array_keys( $entries ) as $key ) {
				wp_cache_add( $key, 1, self::CACHE_GROUP, self::TTL );
			}
		}
		$this->succeeded( 'write' );

		$this->reopen( $new );
	}

	public function detectionMode( $type = '' ) {
		$mode = $this->globalMode();
		if ( self::MODE_OFF !== $mode && is_string( $type ) && '' !== $type && ! $this->surfaceEnabled( $type ) ) {
			return self::MODE_OFF;
		}

		return $mode;
	}

	public function surfaceEnabled( $type ) {
		if ( in_array( $type, self::READ_ONLY_SURFACES, true ) ) {
			return true;
		}

		$stored = $this->storedSurfaces();

		return ! ( is_string( $type ) && isset( $stored[ $type ] ) && self::SURFACE_OFF === $stored[ $type ] );
	}

	public function setSurfaces( $switches ) {
		if ( ! is_array( $switches ) ) {
			return;
		}

		$declared = $this->declaredTypes();
		$before   = $this->storedSurfaces();
		$after    = $before;
		foreach ( $switches as $type => $value ) {
			$value = self::surfaceValue( $value );
			if ( null === $value || ! in_array( $type, $declared, true ) || in_array( $type, self::READ_ONLY_SURFACES, true ) ) {
				continue;
			}
			$after[ $type ] = $value;
		}

		if ( $after === $before ) {
			return;
		}

		update_option( self::OPTION_SURFACES, $after, true );

		if ( self::MODE_OFF === $this->globalMode() ) {
			return;
		}

		$switchedOff = [];
		foreach ( $after as $type => $value ) {
			if ( self::SURFACE_OFF === $value && ( ! isset( $before[ $type ] ) || self::SURFACE_OFF !== $before[ $type ] ) ) {
				$switchedOff[] = $type;
			}
		}
		$this->reopenTypes( $switchedOff );
	}

	public function surfaces( $surfaces = [] ) {
		if ( ! defined( 'WPML_ST_VERSION' ) ) {
			return [];
		}

		$declared = $this->declaredTypes();
		if ( ! $declared ) {
			return [];
		}

		$labels = apply_filters( 'wpml_appearance_surface_labels', [] );
		$labels = is_array( $labels ) ? $labels : [];

		$globalOn = self::MODE_OFF !== $this->globalMode();

		$rows = [];
		foreach ( $declared as $type ) {
			$label = isset( $labels[ $type ] ) && is_string( $labels[ $type ] ) && '' !== $labels[ $type ] ? $labels[ $type ] : self::unlabeledName( $type );
			$notShown   = apply_filters( 'wpml_appearance_count_missing', null, $type );
			$switchable = ! in_array( $type, self::READ_ONLY_SURFACES, true );

			$rows[ $type ] = [
				'label'      => $label,
				'enabled'    => $switchable ? $this->surfaceEnabled( $type ) : $globalOn,
				'switchable' => $switchable,
				'not_shown'  => is_int( $notShown ) ? $notShown : null,
			];
		}

		return $rows;
	}

	private static function unlabeledName( $type ) {
		if ( 0 === strpos( $type, 'post_' ) ) {
			$object = get_post_type_object( substr( $type, 5 ) );
			if ( is_object( $object ) && isset( $object->labels->name ) && is_string( $object->labels->name ) && '' !== $object->labels->name ) {
				return $object->labels->name;
			}
		}

		return $type;
	}

	private function globalMode() {
		if ( ! defined( 'WPML_ST_VERSION' ) ) {
			return self::MODE_OFF;
		}

		global $sitepress;
		if ( ! is_object( $sitepress ) ) {
			return self::MODE_OFF;
		}

		$settings = $sitepress->get_setting( self::ST_SETTING );
		if ( ! is_array( $settings ) || ! isset( $settings[ self::ST_MODE_KEY ] ) ) {
			return self::MODE_OFF;
		}

		switch ( (int) $settings[ self::ST_MODE_KEY ] ) {
			case 0:
				return self::MODE_ADMIN;
			case self::ST_MODE_VISITORS:
				return $this->horizonPassed( $settings ) ? self::MODE_OFF : self::MODE_VISITORS;
			default:
				return self::MODE_OFF;
		}
	}

	private function horizonPassed( array $settings ) {
		$startedAt = isset( $settings[ self::ST_MODE_STARTED_KEY ] ) ? (int) $settings[ self::ST_MODE_STARTED_KEY ] : 0;

		return $startedAt > 0 && time() - $startedAt >= self::ST_MODE_HORIZON;
	}

	public function detectionModeFilter( $mode, $type = '' ) {
		return $this->detectionMode( is_string( $type ) ? $type : '' );
	}

	public function heldTypes() {
		if ( self::MODE_OFF === $this->globalMode() ) {
			return [];
		}

		return array_values( array_filter( $this->declaredTypes(), [ $this, 'surfaceEnabled' ] ) );
	}

	private function declaredTypes() {
		$declared = apply_filters( 'wpml_appearance_part_of_types', [] );
		if ( ! is_array( $declared ) ) {
			return [];
		}

		return array_values( array_unique( array_filter( $declared, [ self::class, 'isKnownType' ] ) ) );
	}

	private function stringDeclared() {
		$declared = apply_filters( 'wpml_appearance_part_of_types', [] );

		return is_array( $declared ) && in_array( 'string', $declared, true );
	}

	private function isUndeclaredString( $type ) {
		return 'string' === $type && ! $this->stringDeclared();
	}

	private static function literalType( $typeExpr ) {
		$expr = trim( $typeExpr );
		if ( strlen( $expr ) < 2 ) {
			return '';
		}
		$quote = $expr[0];
		if ( ( "'" !== $quote && '"' !== $quote ) || substr( $expr, -1 ) !== $quote ) {
			return '';
		}

		return substr( $expr, 1, -1 );
	}

	private function storedSurfaces() {
		$stored = get_option( self::OPTION_SURFACES, [] );

		return is_array( $stored ) ? $stored : [];
	}

	private static function surfaceValue( $value ) {
		if ( true === $value || self::SURFACE_ON === $value ) {
			return self::SURFACE_ON;
		}
		if ( false === $value || self::SURFACE_OFF === $value ) {
			return self::SURFACE_OFF;
		}

		return null;
	}

	public function has( $answer, $type, $id ) {
		$id = (int) $id;
		if ( $id <= 0 || ! self::isKnownType( $type ) ) {
			return $answer;
		}
		if ( $this->isUndeclaredString( $type ) ) {
			return null;
		}

		$key = $type . ':' . $id;
		if ( false !== wp_cache_get( $key, self::CACHE_GROUP ) ) {
			return true;
		}

		$has = $this->repository->has( $type, $id );
		if ( true === $has ) {
			wp_cache_add( $key, 1, self::CACHE_GROUP, self::TTL );
		}

		return $has;
	}

	public function filterIds( $answer, $type, $ids ) {
		if ( ! is_array( $ids ) || ! self::isKnownType( $type ) ) {
			return $answer;
		}
		if ( $this->isUndeclaredString( $type ) ) {
			return null;
		}

		return $this->repository->filterIds( $type, $ids );
	}

	public function countMissing( $answer, $type ) {
		if ( ! self::isKnownType( $type ) ) {
			return $answer;
		}
		if ( 'string' === $type && ( ! defined( 'WPML_ST_VERSION' ) || ! $this->stringDeclared() ) ) {
			return null;
		}

		return $this->repository->countMissing( $type );
	}

	public function existsSql( $answer, $typeExpr, $idExpr ) {
		if ( ! is_string( $typeExpr ) || ! is_string( $idExpr ) || '' === $typeExpr || '' === $idExpr ) {
			return is_string( $answer ) ? $answer : '';
		}
		if ( $this->isUndeclaredString( self::literalType( $typeExpr ) ) ) {
			return '';
		}

		return $this->repository->existsSql( $typeExpr, $idExpr );
	}

	public function available( $available ) {
		return (bool) $this->repository->isAvailable();
	}

	public function importStrings( $answer ) {
		return $this->repository->importStringsFromPositions();
	}

	public function typeStats() {
		return $this->repository->stats();
	}

	public function reset( $type = null ) {
		if ( null !== $type && ! self::isKnownType( $type ) ) {
			return null;
		}

		$deleted = $this->repository->deleteByType( $type );
		if ( null === $deleted ) {
			return null;
		}

		$this->forgetNotes();

		$reopened = [];
		if ( $deleted > 0 ) {
			$held     = $this->heldTypes();
			$affected = null === $type ? $held : array_values( array_intersect( $held, [ $type ] ) );
			$reopened = $this->reopenTypes( $affected );
		}

		return [
			'deleted'  => $deleted,
			'reopened' => $reopened,
		];
	}

	public function deletePost( $postId, $post = null ) {
		$postId   = (int) $postId;
		$postType = is_object( $post ) && isset( $post->post_type ) ? $post->post_type : get_post_type( $postId );
		if ( $postId <= 0 || ! is_string( $postType ) || '' === $postType ) {
			return;
		}

		$this->repository->delete( 'post_' . $postType, $postId );
	}

	public function deleteTerm( $termId, $termTaxonomyId, $taxonomy ) {
		$termTaxonomyId = (int) $termTaxonomyId;
		if ( $termTaxonomyId <= 0 || ! is_string( $taxonomy ) || '' === $taxonomy ) {
			return;
		}

		$this->repository->delete( 'tax_' . $taxonomy, $termTaxonomyId );
	}

	public function deleteString( $stringId ) {
		$stringId = is_numeric( $stringId ) ? (int) $stringId : 0;
		if ( $stringId > 0 ) {
			$this->repository->delete( 'string', $stringId );
		}
	}

	public function deleteStrings( $stringIds ) {
		if ( ! is_array( $stringIds ) ) {
			return;
		}

		foreach ( $stringIds as $stringId ) {
			$this->deleteString( $stringId );
		}
	}

	public function refreshNotices() {
		if ( wp_doing_ajax() ) {
			return;
		}

		$failures = $this->failures();
		$notices  = wpml_get_admin_notices();

		foreach ( self::FAILURE_KINDS as $kind ) {
			$id = self::NOTICE_ID_PREFIX . $kind;

			if ( ! isset( $failures[ $kind ] ) ) {
				if ( $notices->get_notice( $id, self::NOTICE_GROUP ) ) {
					$notices->remove_notice( self::NOTICE_GROUP, $id );
				}
				continue;
			}

			$notice = $notices->create_notice( $id, self::noticeText( $kind, $failures[ $kind ]['error'] ), self::NOTICE_GROUP );
			$notice->set_css_class_types( [ 'warning' ] );
			$notice->set_dismissible( false );
			$notice->set_restrict_to_page_prefixes( [ 'sitepress-multilingual-cms', 'wpml' ] );
			$notices->add_notice( $notice );
		}
	}

	public static function noticeText( $kind, $error ) {
		if ( 'reopen' === $kind ) {
			return self::reopenNoticeText( $error );
		}

		$title = 'read' === $kind
			? esc_html__( 'WPML could not read which content appeared on your site.', 'sitepress' )
			: esc_html__( 'WPML could not save which content appeared on your site.', 'sitepress' );

		$error = '' !== (string) $error ? (string) $error : __( 'The database gave no error message.', 'sitepress' );

		return '<p><strong>' . $title . '</strong></p>'
			. '<p>' . esc_html__( 'The database refused the statement with this error:', 'sitepress' ) . ' <code>' . esc_html( $error ) . '</code></p>'
			. '<p>' . esc_html__( 'Until this is fixed, templates and other content shown inside your pages may wait longer for automatic translation. Ask your host to check the database, or send this error to WPML support.', 'sitepress' ) . '</p>'
			. '<p>' . ( 'read' === $kind
				? esc_html__( 'This message goes away after the next successful read.', 'sitepress' )
				: esc_html__( 'This message goes away after the next successful save.', 'sitepress' ) ) . '</p>';
	}

	private static function reopenNoticeText( $error ) {
		$error = '' !== (string) $error ? (string) $error : __( 'The error gave no message.', 'sitepress' );

		return '<p><strong>' . esc_html__( 'WPML could not mark content that appeared on your site for automatic translation.', 'sitepress' ) . '</strong></p>'
			. '<p>' . esc_html__( 'The error was:', 'sitepress' ) . ' <code>' . esc_html( $error ) . '</code></p>'
			. '<p>' . esc_html__( 'Templates and other content that appeared on your site wait for automatic translation until this is fixed. WPML tries again on every page load. If this message stays, send the error to WPML support.', 'sitepress' ) . '</p>';
	}

	public static function isKnownType( $type ) {
		if ( ! is_string( $type ) ) {
			return false;
		}
		if ( 'string' === $type ) {
			return true;
		}

		return (bool) preg_match( '/^(post|tax|package)_.+$/', $type );
	}

	private function add( $type, $id, $seenBy ) {
		if ( 0 === strpos( $type, 'post_' ) ) {
			$id = $this->originalOf( $id );
		}

		$this->pending[ $type . ':' . $id ] = [ $type, $id, $seenBy ];

		if ( ! $this->flushArmed ) {
			$this->flushArmed = true;
			add_action( 'shutdown', [ $this, 'flush' ], 20 );
		}
	}

	private function reopen( array $new ) {
		if ( ! $new ) {
			return;
		}

		$held = $this->heldTypes();
		if ( ! $held ) {
			return;
		}

		$types   = [];
		$strings = [];
		foreach ( $new as $entry ) {
			if ( ! in_array( $entry[0], $held, true ) ) {
				continue;
			}
			if ( 'string' === $entry[0] ) {
				$strings[] = $entry[1];
				continue;
			}
			$types[ $entry[0] ] = true;
		}

		$this->reopenTypes( array_keys( $types ) );

		if ( $strings ) {
			do_action( self::ACTION_STRINGS_RECORDED, $strings );
		}
	}

	private function reopenTypes( array $types ) {
		$reopened = [];
		foreach ( array_unique( $types ) as $type ) {
			if ( 'string' === $type || 0 === strpos( $type, 'tax_' ) ) {
				continue;
			}
			if ( 0 === strpos( $type, 'post_' ) ) {
				make( UntranslatedPosts::class )->markPostTypeAsUncompleted( substr( $type, strlen( 'post_' ) ) );
			} elseif ( 0 === strpos( $type, 'package_' ) ) {
				make( UntranslatedPackages::class )->markKindAsUncompleted( substr( $type, strlen( 'package_' ) ) );
			} else {
				continue;
			}
			$reopened[] = $type;
		}

		return $reopened;
	}

	private function forgetNotes() {
		if ( function_exists( 'wp_cache_supports' ) && wp_cache_supports( 'flush_group' ) ) {
			wp_cache_flush_group( self::CACHE_GROUP );
		} else {
			wp_cache_flush();
		}
	}

	private function failed( $kind ) {
		if ( ! $this->repository->isAvailable() ) {
			return;
		}

		global $wpdb;
		$this->recordFailure( $kind, is_object( $wpdb ) && isset( $wpdb->last_error ) ? (string) $wpdb->last_error : '' );
	}

	private function reopenFailed( \Throwable $e ) {
		$this->recordFailure( 'reopen', $e->getMessage() );
	}

	private function recordFailure( $kind, $error ) {
		$failures = $this->failures();
		if ( isset( $failures[ $kind ] ) ) {
			return;
		}

		$failures[ $kind ] = [
			'error' => $error,
			'time'  => time(),
		];
		update_option( self::OPTION_FAILURES, $failures, true );
	}

	private function succeeded( $kind ) {
		$failures = $this->failures();
		if ( ! isset( $failures[ $kind ] ) ) {
			return;
		}

		unset( $failures[ $kind ] );
		update_option( self::OPTION_FAILURES, $failures, true );
	}

	private function failures() {
		$failures = get_option( self::OPTION_FAILURES, [] );

		return is_array( $failures ) ? $failures : [];
	}

	private function isFrontEndView() {
		return ! is_admin()
			&& ! wp_doing_ajax()
			&& ! wp_doing_cron()
			&& ! wpml_is_cli()
			&& ! wpml_is_rest_request()
			&& ! is_preview()
			&& ! is_customize_preview();
	}

	private function originalOf( $postId ) {
		global $wpml_post_translations;

		if ( $wpml_post_translations instanceof \WPML_Post_Translation ) {
			$original = $wpml_post_translations->get_original_element( $postId, true );
			if ( $original ) {
				return (int) $original;
			}
		}

		return $postId;
	}
}
