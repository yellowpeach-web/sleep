<?php

class WPML_Pre_Option_Page extends WPML_WPDB_And_SP_User {

	const CACHE_GROUP = 'wpml_pre_option_page';

	const PAGE_OPTIONS = [ 'page_on_front', 'page_for_posts', 'wp_page_for_privacy_policy' ];

	private static $hooks_added = false;

	private static $remembered = [];

	private static $remembered_for_cache = null;

	private static function add_cache_clearing_hooks() {
		if ( self::$hooks_added ) {
			return;
		}
		self::$hooks_added = true;
		foreach ( self::PAGE_OPTIONS as $option ) {
			add_action( "add_option_{$option}", [ self::class, 'clear_cache' ], 10, 0 );
			add_action( "update_option_{$option}", [ self::class, 'clear_cache' ], 10, 0 );
			add_action( "delete_option_{$option}", [ self::class, 'clear_cache' ], 10, 0 );
		}
		add_action( 'wpml_translation_update', [ self::class, 'clear_on_translation_update' ] );
		add_action( 'transition_post_status', [ self::class, 'clear_on_page_status_change' ], 10, 3 );
	}

	private static $core_reset_language = null;

	private $switched;
	private $lang;

	public function __construct( &$wpdb, &$sitepress, $switched, $lang ) {
		parent::__construct( $wpdb, $sitepress );

		$this->switched = $switched;
		$this->lang     = $lang;

		self::add_cache_clearing_hooks();
	}

	public function get( $type, $from_language = null ) {
		$fresh   = $this->switched && $this->sitepress->get_setting( 'setup_complete' );
		$results = $fresh ? null : $this->remembered( $type );

		if ( null === $results && ( $fresh || ! $this->switched ) ) {
			$read    = $this->read_page_options( ! $fresh );
			$results = isset( $read[ $type ] ) ? $read[ $type ] : [];
		}

		$target_language = $from_language ? $from_language : ( self::$core_reset_language ? self::$core_reset_language : $this->lang );

		return isset( $results[ $target_language ] ) ? $results[ $target_language ] : false;
	}

	private function remembered( $type ) {
		$cache  = new WPML_WP_Cache( self::CACHE_GROUP );
		$found  = false;
		$cached = $cache->get( $type, $found );
		$memo   = &$this->request_memo();

		if ( $found && isset( $cached[ $type ] ) && is_array( $cached[ $type ] ) ) {
			if ( ! isset( $memo['types'][ $type ] ) ) {
				$memo['types'][ $type ] = $cached[ $type ];
			}

			return $cached[ $type ];
		}

		return isset( $memo['types'][ $type ] ) ? $memo['types'][ $type ] : null;
	}

	private function read_page_options( $remember ) {
		$wpdb = $this->wpdb;
		$rows = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT o.option_name, t.element_id, t.language_code, p.post_status
				FROM {$wpdb->options} o
				INNER JOIN {$wpdb->prefix}icl_translations s
					ON s.element_type = 'post_page' AND s.element_id = CAST( o.option_value AS UNSIGNED )
				INNER JOIN {$wpdb->prefix}icl_translations t ON t.trid = s.trid
				LEFT JOIN {$wpdb->posts} p ON p.ID = t.element_id
				WHERE o.option_name IN ( %s, %s, %s )",
				self::PAGE_OPTIONS[0],
				self::PAGE_OPTIONS[1],
				self::PAGE_OPTIONS[2]
			)
		);

		$types  = array_fill_keys( self::PAGE_OPTIONS, [] );
		$status = [];
		if ( ! empty( $wpdb->last_error ) ) {
			return $types;
		}

		foreach ( is_array( $rows ) ? $rows : [] as $row ) {
			if ( null === $row->element_id ) {
				continue;
			}
			$types[ $row->option_name ][ $row->language_code ] = $row->element_id;
			if ( null !== $row->post_status ) {
				$status[ (int) $row->element_id ] = $row->post_status;
			}
		}

		$cache = new WPML_WP_Cache( self::CACHE_GROUP );
		foreach ( $types as $option => $per_language ) {
			$cache->set( $option, [ $option => $per_language ] );
		}

		if ( $remember ) {
			$memo = &$this->request_memo();
			$memo = [
				'types'  => $types,
				'status' => $status,
			];
		}

		return $types;
	}

	private function &request_memo() {
		$object_cache = isset( $GLOBALS['wp_object_cache'] ) && is_object( $GLOBALS['wp_object_cache'] ) ? $GLOBALS['wp_object_cache'] : null;
		if ( self::$remembered_for_cache !== $object_cache ) {
			self::$remembered           = [];
			self::$remembered_for_cache = $object_cache;
		}

		$prefix = isset( $this->wpdb->prefix ) ? (string) $this->wpdb->prefix : '';
		if ( ! isset( self::$remembered[ $prefix ] ) ) {
			self::$remembered[ $prefix ] = [
				'types'  => [],
				'status' => [],
			];
		}

		return self::$remembered[ $prefix ];
	}

	private function post_status( $page_id ) {
		$page_id = (int) $page_id;
		$memo    = &$this->request_memo();
		if ( isset( $memo['status'][ $page_id ] ) ) {
			return $memo['status'][ $page_id ];
		}

		$status = get_post_status( $page_id );
		if ( is_string( $status ) ) {
			$memo['status'][ $page_id ] = $status;
		}

		return $status;
	}

	public function get_skipping_trashed( $type ) {
		$page_id = $this->get( $type );
		if ( ! $page_id || 'trash' !== $this->post_status( $page_id ) ) {
			return $page_id;
		}

		$default_page_id = $this->get( $type, $this->sitepress->get_default_language() );

		return $default_page_id ? $default_page_id : $page_id;
	}

	public static function answer_in_language_during_core_reset( $language ) {
		self::$core_reset_language = $language;
	}

	public static function end_core_reset() {
		self::$core_reset_language = null;
	}


	public static function clear_cache() {
		$cache = new WPML_WP_Cache( self::CACHE_GROUP );
		$cache->flush_group_cache();
		self::forget_request_reads();
	}

	public static function clear_page_on_front_cache() {
		self::clear_cache_key( 'page_on_front' );
	}

	public static function clear_privacy_policy_cache() {
		self::clear_cache_key( 'wp_page_for_privacy_policy' );
	}

	public static function clear_on_translation_update( $args ) {
		$element_type = is_array( $args ) && isset( $args['element_type'] ) ? (string) $args['element_type'] : '';
		if ( '' === $element_type || 'post_page' === $element_type ) {
			self::clear_cache();
		}
	}

	public static function clear_on_page_status_change( $new_status, $old_status, $post ) {
		if ( $new_status !== $old_status && $post instanceof WP_Post && 'page' === $post->post_type ) {
			self::forget_request_reads();
		}
	}

	public static function forget_request_reads() {
		self::$remembered = [];
	}

	public static function maybe_clear_privacy_policy_cache( $page_id, $post_type ) {
		if ( 'page' !== $post_type ) {
			return;
		}

		$policy_page_id = (int) get_option( 'wp_page_for_privacy_policy' );
		
		if ( $policy_page_id === $page_id ) {
			self::clear_privacy_policy_cache();
		}
	}

	public static function clear_cache_key( $cache_key ) {
		wp_cache_delete( $cache_key, self::CACHE_GROUP );
		self::forget_request_reads();
	}

	function fix_trashed_front_or_posts_page_settings( $post_id ) {
		if ( 'page' !== get_post_type( $post_id ) ) {
			return;
		}

		$post_id = (int) $post_id;
		$page_on_front_current  = (int) $this->get( 'page_on_front' );
		$page_for_posts_current = (int) $this->get( 'page_for_posts' );

		$page_on_front_default  = (int) $this->get( 'page_on_front', $this->sitepress->get_default_language() );
		$page_for_posts_default = (int) $this->get( 'page_for_posts', $this->sitepress->get_default_language() );

		if ( $page_on_front_current === $post_id && $page_on_front_current !== $page_on_front_default ) {
			remove_filter( 'pre_option_page_on_front', array( $this->sitepress, 'pre_option_page_on_front' ) );
			update_option( 'page_on_front', $page_on_front_default );
			add_filter( 'pre_option_page_on_front', array( $this->sitepress, 'pre_option_page_on_front' ) );
		}
		if ( $page_for_posts_current === $post_id && $page_for_posts_current !== $page_for_posts_default ) {
			remove_filter( 'pre_option_page_for_posts', array( $this->sitepress, 'pre_option_page_for_posts' ) );
			update_option( 'page_for_posts', $page_for_posts_default );
			add_filter( 'pre_option_page_for_posts', array( $this->sitepress, 'pre_option_page_for_posts' ) );
		}
	}

}
