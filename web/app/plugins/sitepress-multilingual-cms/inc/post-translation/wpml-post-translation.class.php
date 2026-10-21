<?php

abstract class WPML_Post_Translation extends WPML_Element_Translation {

	protected $settings;
	protected $post_translation_sync;
	public static $defer_term_counting = false;

	private static $language_assignment_changed = array();

	private $debug_backtrace;

	private $changed_post_meta = array();

	private $post_meta_write_sequence = 0;

	private $post_save_stack = array();

	private $post_meta_tracking_incomplete = array();

	private $post_was_auto_draft = array();

	private $synced_post_meta = array();

	private $late_sync_running = array();

	public function __construct( &$settings, &$wpdb ) {
		parent::__construct( $wpdb );
		$this->settings = $settings;
	}

	protected function is_setup_complete( ) {
		return isset( $this->settings[ 'setup_complete' ]) && $this->settings[ 'setup_complete' ];
	}

	public function init() {
		if ( $this->is_setup_complete() ) {
			add_action( 'added_post_meta', [ $this, 'record_changed_post_meta' ], 0, 3 );
			add_action( 'updated_post_meta', [ $this, 'record_changed_post_meta' ], 0, 3 );
			add_action( 'deleted_post_meta', [ $this, 'record_changed_post_meta' ], 0, 3 );
			add_action( 'wpml_after_batch_copy_custom_fields', [ $this, 'record_batch_changed_post_meta' ], 0, 3 );
			add_filter( 'update_post_metadata_by_mid', [ $this, 'record_post_meta_key_update_by_mid' ], 0, 4 );
			add_action( 'transition_post_status', [ $this, 'record_post_status_transition' ], 0, 3 );
			add_action( 'save_post', [ $this, 'begin_post_save' ], 1, 3 );
			add_action( 'save_post', [ $this, 'save_post_actions' ], 100, 2 );
			add_action( 'save_post', [ $this, 'finish_post_save' ], PHP_INT_MAX, 3 );
			add_action( 'wp_insert_post', [ $this, 'sync_late_post_meta' ], PHP_INT_MAX, 1 );
			if ( function_exists( 'did_action' ) && did_action( 'rest_api_init' ) ) {
				$this->add_rest_late_sync_actions();
			} else {
				add_action( 'rest_api_init', [ $this, 'add_rest_late_sync_actions' ] );
			}
			add_action( 'shutdown', [ $this, 'shutdown_action' ], PHP_INT_MAX );
			add_action( 'edit_attachment', [ $this, 'attachment_actions' ], 100 );
			add_action( 'add_attachment', [ $this, 'attachment_actions' ], 100 );
		}
	}

	public function record_changed_post_meta( $meta_id, $post_id, $meta_key ) {
		$blog_id = $this->get_current_blog_id();
		$post_id = (int) $post_id;

		if ( ! $post_id ) {
			$this->post_meta_tracking_incomplete[ $blog_id ] = true;
			return;
		}

		$this->changed_post_meta[ $blog_id ][ $post_id ][ (string) $meta_key ] = ++$this->post_meta_write_sequence;
	}

	public function record_batch_changed_post_meta( $unused_post_id_from, $post_id_to, array $meta_keys ) {
		foreach ( $meta_keys as $meta_key ) {
			$this->record_changed_post_meta( 0, $post_id_to, $meta_key );
		}
	}

	public function record_post_meta_key_update_by_mid( $check, $meta_id, $meta_value, $meta_key ) {
		if ( false !== $meta_key ) {
			$this->post_meta_tracking_incomplete[ $this->get_current_blog_id() ] = true;
		}

		return $check;
	}

	public function record_post_status_transition( $new_status, $old_status, $post ) {
		if ( 'auto-draft' === $old_status && 'auto-draft' !== $new_status && $post instanceof WP_Post ) {
			$this->post_was_auto_draft[ $this->get_current_blog_id() ][ (int) $post->ID ] = true;
		}
	}

	public function begin_post_save( $post_id, $post, $update = null ) {
		$blog_id = $this->get_current_blog_id();
		$this->post_save_stack[ $blog_id ][ (int) $post_id ][] = null === $update ? null : (bool) $update;
	}

	public function finish_post_save( $post_id, $post, $update = null ) {
		$blog_id = $this->get_current_blog_id();
		$post_id = (int) $post_id;

		if ( empty( $this->post_save_stack[ $blog_id ][ $post_id ] ) ) {
			return;
		}

		array_pop( $this->post_save_stack[ $blog_id ][ $post_id ] );
		if ( $this->post_save_stack[ $blog_id ][ $post_id ] ) {
			return;
		}

		unset( $this->post_save_stack[ $blog_id ][ $post_id ] );
		if ( ! isset( $this->synced_post_meta[ $blog_id ][ $post_id ] ) ) {
			unset( $this->changed_post_meta[ $blog_id ][ $post_id ] );
		}
		unset( $this->post_was_auto_draft[ $blog_id ][ $post_id ] );
	}

	public function add_rest_late_sync_actions() {
		foreach ( get_post_types( array( 'show_in_rest' => true ), 'names' ) as $post_type ) {
			add_action( 'rest_after_insert_' . $post_type, [ $this, 'sync_late_post_meta' ], PHP_INT_MAX, 1 );
		}
	}

	public function sync_late_post_meta( $post ) {
		$blog_id = $this->get_current_blog_id();
		$post_id = $post instanceof WP_Post ? (int) $post->ID : (int) $post;

		if (
			! $post_id
			|| ! isset( $this->synced_post_meta[ $blog_id ][ $post_id ] )
			|| ! empty( $this->post_save_stack[ $blog_id ][ $post_id ] )
			|| ! empty( $this->late_sync_running[ $blog_id ][ $post_id ] )
		) {
			return;
		}

		$late_post_meta = $this->get_post_meta_written_since_sync( $post_id );
		if ( ! $late_post_meta ) {
			return;
		}

		$original_id = (int) $this->get_original_element( $post_id );
		if ( $original_id && $original_id !== $post_id ) {
			$this->remember_synced_post_meta( $post_id );
			return;
		}
		if ( WPML_Post_Duplication::is_duplication_in_progress( $post_id ) ) {
			return;
		}

		$this->late_sync_running[ $blog_id ][ $post_id ] = true;
		try {
			$this->get_sync_helper()->copy_custom_fields_after_save( $post_id, $late_post_meta );
		} finally {
			unset( $this->late_sync_running[ $blog_id ][ $post_id ] );
			$this->remember_synced_post_meta( $post_id );
		}
	}

	private function remember_synced_post_meta( $post_id ) {
		$this->synced_post_meta[ $this->get_current_blog_id() ][ (int) $post_id ] = $this->post_meta_write_sequence;
	}

	private function get_post_meta_written_since_sync( $post_id ) {
		$blog_id = $this->get_current_blog_id();
		$post_id = (int) $post_id;

		$changed_post_meta = isset( $this->changed_post_meta[ $blog_id ][ $post_id ] )
			? $this->changed_post_meta[ $blog_id ][ $post_id ]
			: array();
		if ( isset( $this->synced_post_meta[ $blog_id ][ $post_id ] ) ) {
			$synced_at         = $this->synced_post_meta[ $blog_id ][ $post_id ];
			$changed_post_meta = array_filter(
				$changed_post_meta,
				function ( $written_at ) use ( $synced_at ) {
					return $written_at > $synced_at;
				}
			);
		}

		return array_keys( $changed_post_meta );
	}

	public function get_original_post_status( $trid, $source_lang_code = null ) {

		return $this->get_original_post_attr ( $trid, 'post_status', $source_lang_code );
	}

	public function get_original_post_ID( $trid, $source_lang_code = null ) {

		return $this->get_original_post_attr ( $trid, 'ID', $source_lang_code );
	}

	public function get_original_menu_order
	( $trid, $source_lang_code = null ) {

		return $this->get_original_post_attr ( $trid, 'menu_order', $source_lang_code );
	}

	public function get_original_comment_status( $trid, $source_lang_code = null ) {

		return $this->get_original_post_attr ( $trid, 'comment_status', $source_lang_code );
	}

	public function get_original_ping_status( $trid, $source_lang_code = null ) {

		return $this->get_original_post_attr ( $trid, 'ping_status', $source_lang_code );
	}

	public function get_original_post_format( $trid, $source_lang_code = null ) {

		return get_post_format ( $this->get_original_post_ID ( $trid, $source_lang_code ) );
	}

	public abstract function save_post_actions( $pidd, $post );

	public function attachment_actions( $post_id ) {
		if ( apply_filters( 'wpml_apply_save_attachment_actions', false, $post_id ) ) {
			$post = get_post( $post_id );

			if ( $post ) {
				$this->save_post_actions( $post_id, $post );
			}
		}
	}

	public function shutdown_action() {
		if ( self::$defer_term_counting ) {
			self::$defer_term_counting = false;
			wp_defer_term_counting( false );
		}
	}

	public function trash_translation ( $trans_id ) {
		if ( !WPML_WordPress_Actions::is_bulk_trash( $trans_id ) ) {
			wp_trash_post( $trans_id );
		}
	}

	public function untrash_translation( $trans_id ) {
		if ( WPML_WordPress_Actions::is_bulk_untrash( $trans_id ) ) {
			return;
		}

		add_filter( 'wp_untrash_post_status', array( $this, 'restore_own_trashed_status' ), 10, 2 );

		try {
			wp_untrash_post( $trans_id );
		} finally {
			remove_filter( 'wp_untrash_post_status', array( $this, 'restore_own_trashed_status' ), 10 );
		}
	}

	public function restore_own_trashed_status( $new_status, $post_id ) {
		$previous_status = get_post_meta( $post_id, '_wp_trash_meta_status', true );

		return $previous_status ? $previous_status : $new_status;
	}

	function untrashed_post_actions( $post_id ) {
		$translation_sync = $this->get_sync_helper ();
		$translation_sync->untrashed_post_actions ( $post_id );
	}

	public function delete_post_translation_entry( $post_id ) {

		$update_args = array( 'context' => 'post', 'element_id' => $post_id );
		do_action( 'wpml_translation_update', array_merge( $update_args, array( 'type' => 'before_delete' ) ) );

		$res = WPML_Translation_Records_Delete::translations_where(
			"element_id = %d AND element_type LIKE 'post%%'",
			array( $post_id ),
			1
		);

		do_action( 'wpml_translation_update', array_merge( $update_args, array( 'type' => 'after_delete' ) ) );

		return $res;
	}

	public function trashed_post_actions( $post_id ) {
		$this->delete_post_actions( $post_id, true );
	}

	public function delete_post_actions( $post_id, $keep_db_entries = false ) {
		$translation_sync = $this->get_sync_helper ();
		$translation_sync->delete_post_actions ( $post_id, $keep_db_entries );
	}

	abstract function get_save_post_trid( $post_id, $post_status );

	protected function current_user_can_join_translation_group( $trid, $element_type, $target_language = null ) {
		return \WPML\Translation\TranslationGroupAuthorization::currentUserCanJoinGroup(
			$trid,
			$element_type,
			$target_language,
			$this->wpdb
		);
	}

	public function get_save_post_lang( $post_id, $sitepress ) {
		$language_code = $this->get_element_lang_code ( $post_id );
		if ( $language_code ) {
			return apply_filters ( 'wpml_save_post_lang', $language_code );
		}
		$language_code = $sitepress->get_current_language ();
		$language_code = $sitepress->is_active_language ( $language_code ) ? $language_code
			: $sitepress->get_default_language ();

		return apply_filters ( 'wpml_save_post_lang', $language_code );
	}

	protected abstract function get_save_post_source_lang( $trid, $language_code, $default_language );

	protected function after_save_post( $trid, $post_vars, $language_code, $source_language ) {
		list( $language_code, $source_language ) = $this->keep_recorded_language_if_slot_is_taken(
			(int) $post_vars['ID'],
			$trid,
			$language_code,
			$source_language
		);
		$custom_fields_to_sync   = $this->get_changed_post_meta_for_save( $post_vars['ID'] );
		$has_same_assignment     = $this->has_same_language_assignment( $post_vars['ID'], $trid, $language_code, $source_language );
		self::$language_assignment_changed[ (int) $post_vars['ID'] ] = ! $has_same_assignment;
		if ( ! $has_same_assignment ) {
			$custom_fields_to_sync = null;
		}

		$custom_fields_to_sync = apply_filters(
			'wpml_custom_fields_to_sync_on_post_save',
			$custom_fields_to_sync,
			$post_vars['ID'],
			$post_vars
		);
		$custom_fields_to_sync = $this->normalize_custom_fields_to_sync( $custom_fields_to_sync );

		WPML_Post_Translation_Metadata_Initializer::suspend();

		try {
			$this->maybe_set_elid( $trid, $post_vars['post_type'], $language_code, $post_vars['ID'], $source_language );
		} finally {
			WPML_Post_Translation_Metadata_Initializer::resume();
		}
		if ( ! $has_same_assignment ) {
			$this->reload();
		}
		$translation_sync = $this->get_sync_helper();
		$original_id      = $this->get_original_element( $post_vars['ID'] );
		$sync_with_translations = function () use ( $translation_sync, $original_id, $post_vars, $custom_fields_to_sync ) {
			$translation_sync->sync_with_translations(
				$original_id ? $original_id : $post_vars['ID'],
				$post_vars,
				$custom_fields_to_sync
			);
		};
		if ( $original_id && (int) $original_id !== (int) $post_vars['ID'] ) {
			\WPML\CustomFieldTranslation\CopiedFieldResets::watch( $post_vars['ID'], $original_id, $sync_with_translations );
		} else {
			$sync_with_translations();
		}
		$this->remember_synced_post_meta( $post_vars['ID'] );
		$translation_sync->sync_with_duplicates( $post_vars['ID'] );
		if ( ! function_exists( 'icl_cache_clear' ) ) {
			require_once WPML_PLUGIN_PATH . '/inc/cache.php';
		}
		icl_cache_clear( $post_vars['post_type'] . 's_per_language', true );
		if ( ! in_array( $post_vars['post_type'], array( 'nav_menu_item', 'attachment' ), true ) ) {
			do_action( 'wpml_tm_save_post', $post_vars['ID'], get_post( $post_vars['ID'] ), false );
		}
		$this->flush_object_cache_for_groups( array( 'ls_languages', WPML_ELEMENT_TRANSLATIONS_CACHE_GROUP ) );

		WPML_Pre_Option_Page::maybe_clear_privacy_policy_cache( $original_id, $post_vars['post_type'] );

		do_action( 'wpml_after_save_post', $post_vars['ID'], $trid, $language_code, $source_language );
	}

	private function get_changed_post_meta_for_save( $post_id ) {
		$blog_id = $this->get_current_blog_id();
		$post_id = (int) $post_id;
		$stack   = isset( $this->post_save_stack[ $blog_id ][ $post_id ] )
			? $this->post_save_stack[ $blog_id ][ $post_id ]
			: array();

		if (
			empty( $stack )
			|| true !== end( $stack )
			|| ! empty( $this->post_meta_tracking_incomplete[ $blog_id ] )
			|| ! empty( $this->post_was_auto_draft[ $blog_id ][ $post_id ] )
		) {
			return null;
		}

		return $this->get_post_meta_written_since_sync( $post_id );
	}

	public static function did_language_assignment_change( $post_id ) {
		return ! empty( self::$language_assignment_changed[ (int) $post_id ] );
	}

	public static function set_language_assignment_changed( $post_id, $changed ) {
		if ( null === $changed ) {
			unset( self::$language_assignment_changed[ (int) $post_id ] );
		} else {
			self::$language_assignment_changed[ (int) $post_id ] = (bool) $changed;
		}
	}

	private function has_same_language_assignment( $post_id, $trid, $language_code, $source_language ) {
		$details = $this->get_element_language_details( $post_id );
		if ( ! is_object( $details ) || ! isset( $details->trid, $details->language_code ) ) {
			return false;
		}

		$current_source_language = property_exists( $details, 'source_language_code' )
			? $details->source_language_code
			: null;

		return (int) $details->trid === (int) $trid
			&& (string) $details->language_code === (string) $language_code
			&& (string) $current_source_language === (string) $source_language;
	}

	protected function keep_recorded_language_if_slot_is_taken( $post_id, $trid, $language_code, $source_language ) {
		$recorded = $this->get_element_language_details( $post_id );
		if ( ! is_object( $recorded ) || ! $recorded->language_code || ! $trid
			|| (int) $recorded->trid !== (int) $trid
			|| (string) $recorded->language_code === (string) $language_code
		) {
			return array( $language_code, $source_language );
		}

		$holder = (int) $this->get_element_id( $language_code, $trid );
		if ( ! $holder || $holder === (int) $post_id ) {
			return array( $language_code, $source_language );
		}

		return array( $recorded->language_code, $recorded->source_language_code );
	}

	private function normalize_custom_fields_to_sync( $custom_fields_to_sync ) {
		if ( null === $custom_fields_to_sync ) {
			return null;
		}
		if ( ! is_array( $custom_fields_to_sync ) ) {
			return null;
		}

		$normalized = array();
		foreach ( $custom_fields_to_sync as $meta_key ) {
			if ( is_string( $meta_key ) || is_int( $meta_key ) ) {
				$normalized[ (string) $meta_key ] = true;
			}
		}

		return array_keys( $normalized );
	}

	protected function get_current_blog_id() {
		return function_exists( 'get_current_blog_id' ) ? (int) get_current_blog_id() : 0;
	}

	private function flush_object_cache_for_groups( $groups = array() ) {
		if ( ! empty( $groups ) ) {
			foreach ( $groups as $group ) {
				$cache            = new WPML_WP_Cache( $group );
				$cache->flush_group_cache();
			}
		}
	}

	private function get_original_post_attr( $trid, $attribute, $source_lang_code ) {
		$wpdb             = $this->wpdb;
		$legal_attributes = array(
			'post_status',
			'post_date',
			'menu_order',
			'comment_status',
			'ping_status',
			'ID'
		);
		$res              = false;
		if ( in_array ( $attribute, $legal_attributes, true ) ) {
			if ( null === $source_lang_code ) {
				$res = $wpdb->get_var(
					$wpdb->prepare(
						'SELECT p.' . esc_sql( $attribute ) . " FROM {$wpdb->prefix}icl_translations wpml_translations
						JOIN {$wpdb->posts} p
							ON wpml_translations.element_id = p.ID
								AND wpml_translations.element_type = CONCAT('post_', p.post_type)
						WHERE wpml_translations.trid=%d
							AND wpml_translations.source_language_code IS NULL
						LIMIT 1",
						$trid
					)
				);
			} else {
				$res = $wpdb->get_var(
					$wpdb->prepare(
						'SELECT p.' . esc_sql( $attribute ) . " FROM {$wpdb->prefix}icl_translations wpml_translations
						JOIN {$wpdb->posts} p
							ON wpml_translations.element_id = p.ID
								AND wpml_translations.element_type = CONCAT('post_', p.post_type)
						WHERE wpml_translations.trid=%d
							AND wpml_translations.language_code = %s
						LIMIT 1",
						$trid,
						$source_lang_code
					)
				);
			}
		}

		return $res;
	}

	public function has_save_post_action( $post ) {
		if ( ! $post ) {
			return false;
		}
		$is_auto_draft              = isset( $post->post_status ) && $post->post_status === 'auto-draft';
		$is_editing_different_post  = $this->is_editing_different_post( $post->ID );
		$is_saving_a_revision       = array_key_exists( 'post_type', $_POST ) && 'revision' === $_POST['post_type'];
		$is_untrashing              = array_key_exists( 'action', $_GET ) && 'untrash' === $_GET['action'];
		$is_auto_save               = array_key_exists( 'autosave', $_POST );
		$skip_sitepress_actions     = WPML_Save_Translation_Data_Action::is_delivery_window_open();
		$is_post_a_revision         = 'revision' === $post->post_type;
		$is_scheduled_to_be_trashed = get_post_meta( $post->ID, '_wp_trash_meta_status', true );
		$is_add_meta_action         = isset( $_POST['action'] ) && 'add-meta' === $_POST['action'];
		$is_inner_post_insertion    = $this->is_inner_post_insertion();

		return $this->is_translated_type( $post->post_type )
		       && ! ( $is_auto_draft
		              || $is_auto_save
		              || $skip_sitepress_actions
		              || ( $is_editing_different_post && ! $is_inner_post_insertion )
		              || $is_saving_a_revision
		              || $is_post_a_revision
		              || $is_scheduled_to_be_trashed
		              || $is_add_meta_action
		              || $is_untrashing );
	}

	protected function is_editing_different_post( $post_id ) {
		return array_key_exists( 'post_ID', $_POST ) && (int) $_POST['post_ID'] && $post_id != $_POST['post_ID'];
	}

	protected function get_element_join() {

		return "
				JOIN {$this->wpdb->posts} p
					ON wpml_translations.element_id = p.ID
						AND wpml_translations.element_type = CONCAT('post_', p.post_type)
		";
	}

	protected function get_type_prefix() {
		return 'post_';
	}


	public function is_translated_type( $post_type ) {
		global $sitepress;

		return $sitepress->is_translated_post_type ( $post_type );
	}

	public function get_allowed_target_langs( $post ) {
		global $sitepress;

		$active_languages = $sitepress->get_active_languages ();
		$can_translate    = array_keys ( $active_languages );
		$can_translate    = array_diff (
			$can_translate,
			array( $this->get_element_lang_code ( $post->ID ) )
		);

		return apply_filters ( 'wpml_allowed_target_langs', $can_translate, $post->ID, 'post' );
	}

	private function maybe_set_elid( $trid, $post_type, $language_code, $post_id, $source_language ) {
		global $sitepress;

		$element_type = 'post_' . $post_type;
		$sitepress->set_element_language_details (
			$post_id,
			$element_type,
			$trid,
			$language_code,
			$source_language
		);
	}

	private function get_sync_helper() {
		global $sitepress;

		$this->post_translation_sync = $this->post_translation_sync
			? $this->post_translation_sync : new WPML_Post_Synchronization( $this->settings, $this, $sitepress );

		return $this->post_translation_sync;
	}

	private function get_debug_backtrace() {
		if ( ! $this->debug_backtrace ) {
			$this->debug_backtrace = new WPML\Utils\DebugBackTrace( 20 );
		}

		return $this->debug_backtrace;
	}

	public function set_debug_backtrace( WPML_Debug_BackTrace $debug_backtrace ) {
		$this->debug_backtrace = $debug_backtrace;
	}

	protected function is_inner_post_insertion() {
		$debug_backtrace = $this->get_debug_backtrace();
		return 1 < $debug_backtrace->count_function_in_call_stack( 'wp_insert_post' );
	}

	protected function get_post_vars( $post ) {
		$post_vars = array();

		if ( ! $this->is_inner_post_insertion() ) {
			$post_vars = (array) $_POST;
		}

		foreach ( (array) $post as $k => $v ) {
			$post_vars[ $k ] = $v;
		}

		$post_vars['post_type'] = isset( $post_vars['post_type'] ) ? $post_vars['post_type'] : $post->post_type;

		return $post_vars;
	}

	protected function defer_term_counting() {
		if ( ! self::$defer_term_counting ) {
			self::$defer_term_counting = true;
			wp_defer_term_counting( true );
		}
	}

	public static function getGlobalInstance() {
		global $wpml_post_translations, $sitepress;

		if ( ! isset( $wpml_post_translations ) ) {
			wpml_load_post_translation( is_admin(), $sitepress->get_settings() );
		}

		return $wpml_post_translations;
	}
}
