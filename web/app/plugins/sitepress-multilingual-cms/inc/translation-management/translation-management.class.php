<?php

require_once __DIR__ . '/../constants-since-5-0.php';

use WPML\Auryn\InjectionException;
use WPML\FP\Fns;
use WPML\FP\Logic;
use WPML\FP\Maybe;
use WPML\FP\Obj;
use WPML\FP\Lst;
use WPML\FP\Relation;
use WPML\LanguageEditor\TranslationPause;
use WPML\LIB\WP\User;
use WPML\TM\API\Batch;
use WPML\TM\API\Jobs;
use WPML\TM\ATE\Review\ReviewStatus;
use WPML\TM\ATE\Review\ReviewStatusLookup;
use WPML\TM\Editor\HeldOpenLock;
use WPML\TM\Editor\OpenLock;
use WPML\UIPage;
use WPML\TM\TranslationDashboard\FiltersStorage;
use WPML\Utilities\AdvisoryLockFactory;
use function WPML\Container\make;
use function WPML\FP\invoke;
use function WPML\FP\partialRight;
use function WPML\FP\pipe;
use WPML\TM\Jobs\JobLog;
use WPML\Upgrade\TranslationStatusSchema;
use WPML\Core\Component\PostHog\Application\Service\Event\EventInstanceService;

class TranslationManagement {

	const INIT_PRIORITY = 1500;

	const DUPLICATE_ELEMENT_ACTION = 2;
	const TRANSLATE_ELEMENT_ACTION = 1;

	const DUPLICATION_PAUSED = 'translation_paused';

	const DUPLICATION_REFUSED_TRANSLATION_EXISTS = 'content_already_translated';
	const DUPLICATION_REFUSED_IN_PROGRESS        = 'translation_in_progress';

	private $refused_duplications = [];

	const COMMENT_DUPLICATION_LOG_PREFIX = 'WPML comment duplication used:';

	private static $comment_duplication_usage_logged = false;

	private $supersededAteJobIdsAnnounced = [];

	private $selected_translator;
	private $current_translator;
	private $messages = array();
	public $admin_texts_to_translate = array();
	private $comment_duplicator;

	private $settings_factory;

	private $cache_factory;

	private $filters_and_actions;

	private $wpml_cookie;

	private static $send_jobs_added_for_types = [];

	private $sent_job_ids_per_type = [];

	private $send_deadline = null;

	private $send_was_truncated = false;

	private $send_processed_elements = 0;

	private $send_processed_pairs = 0;

	private $tp_errors_consumed = 0;

	private $failed_send_elements = [];

	private $last_status_write_failed = false;

	private $last_status_write_errno = 0;

	private $failed_create_job_ids = [];

	private $held_back_job_ids = [];

	private $settings_data;

	public function &__get( $name ) {
		if ( 'settings' === $name ) {
			$this->load_settings_if_required();

			return $this->settings_data;
		}
		$null = null;

		return $null;
	}

	public function __set( $name, $value ) {
		if ( 'settings' === $name ) {
			$this->settings_data = $value;

			return;
		}
		$this->{$name} = $value;
	}

	public function __isset( $name ) {
		if ( 'settings' === $name ) {
			$this->load_settings_if_required();

			return null !== $this->settings_data;
		}

		return isset( $this->{$name} );
	}

	public function __unset( $name ) {
		if ( 'settings' === $name ) {
			$this->settings_data = null;

			return;
		}
		unset( $this->{$name} );
	}


	function __construct( ?WPML_Cookie $wpml_cookie = null ) {

		global $sitepress, $wpml_cache_factory;

		$this->selected_translator     = new WPML_Translator();
		$this->selected_translator->ID = 0;
		$this->current_translator      = new WPML_Translator();
		$this->current_translator->ID  = 0;
		$this->cache_factory           = $wpml_cache_factory;

		add_action( 'init', array( $this, 'init' ), self::INIT_PRIORITY );

		add_action( 'admin_enqueue_scripts', array( $this, 'admin_enqueue_scripts' ), 10, 0 );
		add_action( 'delete_post', array( $this, 'delete_post_actions' ), 1, 1 );
		add_action( 'icl_ajx_custom_call', array( $this, 'ajax_calls' ), 10, 2 );
		\WPML\Request\Adapter\Ajax::register( 'save_notification_settings', \WPML\Request\Policy\Policy::capability( 'manage_translations', \WPML\Request\Policy\Authenticity::actionNonce( 'save_notification_settings_nonce', 'save_notification_settings_nonce' ) ), array( $this, 'ajax_save_notification_settings' ) );
		add_action( 'wpml_tm_add_message', array( $this, 'add_message_from_action' ), 10, 3 );
		add_action( 'wpml_tm_ate_create_failed', array( $this, 'add_failed_create_job_ids' ), 10, 2 );
		add_action( 'wpml_tm_ate_create_held_back', array( $this, 'add_held_back_job_ids' ), 10, 2 );
		add_action( 'wpml_translation_editor_stored', array( $this, 'sync_translation_editor_setting' ) );

		add_filter( 'icl_additional_translators', array( $this, 'icl_additional_translators' ), 99, 3 );

		Fns::each(
			function( $type ) {
				if ( ! in_array( $type, self::$send_jobs_added_for_types, true ) ) {
					add_action( "wpml_tm_send_{$type}_jobs", [ $this, 'action_send_jobs' ], 10, 3 );
					self::$send_jobs_added_for_types[] = $type;
				}
			},
			[ 'post', 'package', 'st-batch' ]
		);
		$this->init_comments_synchronization();
		add_action( 'wpml_loaded', array( $this, 'wpml_loaded_action' ) );

		add_filter( 'wpml_translation_job_id', array( $this, 'get_translation_job_id_filter' ), 10, 2 );

		$this->filters_and_actions = new WPML_Translation_Management_Filters_And_Actions( $this, $sitepress );

		$this->wpml_cookie = $wpml_cookie ?: new WPML_Cookie();
	}

	public function wpml_loaded_action() {
		if ( is_admin() ) {
			add_action( 'wpml_config', array( $this, 'wpml_config_action' ), 10, 1 );
		}
	}

	public function settings_loaded() {
		return (bool) $this->settings_data;
	}

	public function sync_translation_editor_setting( $stored_value ) {
		if ( ! $this->settings_loaded() ) {
			return;
		}
		$settings                           = $this->settings;
		$settings['doc_translation_method'] = $stored_value;
		$this->settings                     = $settings;
	}

	public function load_settings_if_required() {
		if ( ! $this->settings_data ) {
			$this->hydrate_settings();
		}
	}

	private function hydrate_settings() {
		$settings            = apply_filters( 'wpml_setting', null, 'translation-management' );
		$this->settings_data = is_array( $settings ) ? $settings : array();
		$this->init_default_settings();
		$this->settings_data = WPML_TM_Default_Settings::apply_notification_defaults( $this->settings_data );
	}

	public function wpml_config_action( $args ) {
		if ( current_user_can( 'manage_options' ) ) {
			$this->update_section_translation_setting( $args );
		}
	}

	public function settings_factory() {
		$this->settings_factory = $this->settings_factory
			? $this->settings_factory
			: new WPML_Custom_Field_Setting_Factory( $this );

		return $this->settings_factory;
	}

	private function init_translator_language_pairs( WP_User $current_user, WPML_Translator $current_translator ) {
		$languagePairRecords = make( WPML_Language_Pair_Records::class );

		$current_translator_language_pairs  = $languagePairRecords->get( $current_user->ID );
		$current_translator_language_pairs  = $languagePairRecords->convert_to_storage_format( $current_translator_language_pairs );

		$current_translator->language_pairs = $this->sanitize_language_pairs( $current_translator_language_pairs );

		if ( ! count( $current_translator->language_pairs ) ) {
			$current_translator->language_pairs = array();
		}

		return $current_translator;
	}

	private function is_valid_language_code_format( $code ) {
		return $code && is_string( $code ) && strlen( $code ) >= 2;
	}

	private function sanitize_language_pairs( $language_pairs ) {
		if ( ! $language_pairs || ! is_array( $language_pairs ) ) {
			$language_pairs = array();
		} else {
			$language_codes_from = array_keys( $language_pairs );
			foreach ( $language_codes_from as $code_from ) {
				$language_codes_to = array_keys( $language_pairs[ $code_from ] );

				foreach ( $language_codes_to as $code_to ) {
					if ( ! $this->is_valid_language_code_format( (string) $code_to ) ) {
						unset( $language_pairs[ $code_from ][ $code_to ] );
					}
				}

				if ( ! $this->is_valid_language_code_format( $code_from ) || ! count( $language_pairs[ $code_from ] ) ) {
					unset( $language_pairs[ $code_from ] );
				}
			}
		}
		return $language_pairs;
	}

	private function update_section_translation_setting( $args ) {
		$section   = $args['section'];
		$key       = $args['key'];
		$value     = $args['value'];
		$read_only = isset( $args['read_only'] ) ? $args['read_only'] : true;

		$section                        = preg_replace( '/-/', '_', $section );
		$config_section                 = $this->get_translation_setting_name( $section );
		$custom_config_readonly_section = $this->get_custom_readonly_translation_setting_name( $section );
		if ( isset( $this->settings[ $config_section ] ) ) {
			$this->settings[ $config_section ][ esc_sql( $key ) ] = esc_sql( $value );
			if ( ! isset( $this->settings[ $custom_config_readonly_section ] ) ) {
				$this->settings[ $custom_config_readonly_section ] = array();
			}
			if ( $read_only === true && ! in_array( $key, $this->settings[ $custom_config_readonly_section ] ) ) {
				$this->settings[ $custom_config_readonly_section ][] = esc_sql( $key );
			}
			$this->save_settings();
		}
	}

	public function init() {
		$this->init_comments_synchronization();

		WPML_Config::load_config();

		if ( is_admin() ) {
			$this->wpml_add_duplicate_check_actions();
		}
	}

	public function get_settings() {
		$this->load_settings_if_required();
		return $this->settings;
	}

	public function wpml_add_duplicate_check_actions() {
		global $pagenow;
		if (
			'post.php' === $pagenow
			||
			( isset( $_POST['action'] ) && 'check_duplicate' === $_POST['action'] && DOING_AJAX )
		) {
			return new WPML_Translate_Independently();
		}
	}

	public function admin_enqueue_scripts() {
		// Only with TM loaded: WPML_TM_FOLDER is defined by core on every license
		if ( ! \WPML\Plugins::isTMLoadedForRequest() || ! defined( 'WPML_TM_VERSION' ) || ! defined( 'WPML_TM_URL' ) ) {
			return;
		}

		if ( UIPage::isTMTranslators( $_GET ) ) {
			wp_register_style( 'translation-translators', WPML_TM_URL . '/res/css/translation-translators.css', array( 'otgs-icons' ), ICL_SITEPRESS_SCRIPT_VERSION );
			wp_enqueue_style( 'translation-translators' );
		} elseif ( UIPage::isSettings( $_GET ) ) {
			wp_register_style( 'sitepress-translation-options', ICL_PLUGIN_URL . '/res/css/translation-options.css', array(), ICL_SITEPRESS_SCRIPT_VERSION );
			wp_enqueue_style( 'sitepress-translation-options' );
		} elseif ( UIPage::isTMDashboard( $_GET ) ) {
			wp_register_style( 'translation-dashboard', WPML_TM_URL . '/res/css/translation-dashboard.css', array(), ICL_SITEPRESS_SCRIPT_VERSION );
			wp_enqueue_style( 'translation-dashboard' );
			wp_register_style( 'translation-translators', WPML_TM_URL . '/res/css/translation-translators.css', array( 'otgs-icons' ), ICL_SITEPRESS_SCRIPT_VERSION );
			wp_enqueue_style( 'translation-translators' );
		}
	}

	public static function get_batch_name( $batch_id ) {
		$batch_data = self::get_batch_data( $batch_id );
		if ( ! $batch_data || ! isset( $batch_data->batch_name ) ) {
			/* translators: Shown in place of a batch name when a translation job belongs to no named batch. */
			$batch_name = __( 'No Batch', 'sitepress' );
		} else {
			$batch_name = $batch_data->batch_name;
		}

		return $batch_name;
	}

	public static function get_batch_url( $batch_id ) {
		$batch_data = self::get_batch_data( $batch_id );
		$batch_url  = '';
		if ( $batch_data && isset( $batch_data->tp_id ) && $batch_data->tp_id != 0 ) {
			$batch_url = OTG_TRANSLATION_PROXY_URL . "/projects/{$batch_data->tp_id}/external";
		}

		return $batch_url;
	}

	public static function get_batch_last_update( $batch_id ) {
		$batch_data = self::get_batch_data( $batch_id );

		return $batch_data ? $batch_data->last_update : false;
	}

	public static function get_batch_tp_id( $batch_id ) {
		$batch_data = self::get_batch_data( $batch_id );

		return $batch_data ? $batch_data->tp_id : false;
	}

	public static function get_batch_data( $batch_id ) {
		$cache_key   = $batch_id;
		$cache_group = 'get_batch_data';
		$cache_found = false;

		$batch_data = wp_cache_get( $cache_key, $cache_group, false, $cache_found );

		if ( $cache_found ) {
			return $batch_data;
		}

		global $wpdb;
		$batch_data = $wpdb->get_row(
			$wpdb->prepare( "SELECT * FROM {$wpdb->prefix}icl_translation_batches WHERE id=%d", $batch_id )
		);

		wp_cache_set( $cache_key, $batch_data, $cache_group );

		return $batch_data;
	}

	function save_settings() {
		global $sitepress;

		$icl_settings['translation-management'] = $this->settings;
		$cpt_sync_option                        = $sitepress->get_setting( 'custom_posts_sync_option', array() );
		$cpt_sync_option                        = (bool) $cpt_sync_option === false ? $sitepress->get_setting( 'custom-types_sync_option', array() ) : $cpt_sync_option;
		$cpt_unlock_options                     = $sitepress->get_setting( 'custom_posts_unlocked_option', array() );

		if ( ! isset( $icl_settings['custom_posts_sync_option'] ) ) {
			$icl_settings['custom_posts_sync_option'] = array();
		}

		foreach ( $cpt_sync_option as $k => $v ) {
			$icl_settings['custom_posts_sync_option'][ $k ] = $v;
		}
		$icl_settings['translation-management']['custom-types_readonly_config'] = isset( $icl_settings['translation-management']['custom-types_readonly_config'] ) ? $icl_settings['translation-management']['custom-types_readonly_config'] : array();
		foreach ( $icl_settings['translation-management']['custom-types_readonly_config'] as $k => $v ) {
			if ( ! $this->is_unlocked_type( $k, $cpt_unlock_options ) ) {
				$icl_settings['custom_posts_sync_option'][ $k ] = $v;
			}
		}
		$sitepress->set_setting( 'translation-management', $icl_settings['translation-management'], true );
		$sitepress->set_setting( 'custom_posts_sync_option', $icl_settings['custom_posts_sync_option'], true );
		$this->hydrate_settings();
	}

	public function initial_custom_field_translate_states() {
		global $wpdb;

		$this->initial_term_custom_field_translate_states();

		return $this->initial_translation_states( $wpdb->postmeta );
	}

	public function initial_term_custom_field_translate_states() {
		global $wpdb;

		return ! empty( $wpdb->termmeta )
			? $this->initial_translation_states( $wpdb->termmeta )
			: array();
	}

	function ajax_calls( $call, $data ) {
		global $wpdb, $sitepress;
		switch ( $call ) {
			case 'icl_cf_translation':
			case 'icl_tcf_translation':
				foreach (
					array(
						'cf'          => $call === 'icl_tcf_translation' ? WPML_TERM_META_SETTING_INDEX_PLURAL : WPML_POST_META_SETTING_INDEX_PLURAL,
						'cf_unlocked' => $call === 'icl_tcf_translation' ? WPML_TERM_META_UNLOCKED_SETTING_INDEX : WPML_POST_META_UNLOCKED_SETTING_INDEX,
					) as $field => $setting
				) {
					if ( ! empty( $data[ $field ] ) && is_array( $data[ $field ] ) ) {
						$cft = array();
						foreach ( $data[ $field ] as $k => $v ) {
							$cft[ base64_decode( $k ) ] = $v;
						}

						if ( ! isset( $this->settings[ $setting ] ) || ! is_array( $this->settings[ $setting ] ) ) {
							$this->settings[ $setting ] = array();
						}

						$changed = array();
						foreach ( $cft as $key => $value ) {
							if ( ! array_key_exists( $key, $this->settings[ $setting ] )
								|| $this->has_setting_value_changed( $this->settings[ $setting ][ $key ], $value ) ) {
								$changed[ $key ] = $value;
							}
						}

						if ( ! empty( $changed ) ) {
							$this->settings[ $setting ] = array_merge( $this->settings[ $setting ], $changed );
							$this->save_settings();
							do_action( 'wpml_custom_fields_sync_option_updated', $changed );
						}
					}
				}
				echo '1|';
				break;
			case 'icl_doc_translation_method':
				$new_editor = Obj::prop( 't_method', $data );
				if ( $new_editor ) {
					$previous_editor = isset( $this->settings['doc_translation_method'] )
						? $this->settings['doc_translation_method']
						: ICL_TM_TMETHOD_ATE;

					$this->settings['doc_translation_method'] = $new_editor;
					$sitepress->set_setting( 'doc_translation_method', $this->settings['doc_translation_method'] );

					if ( (string) $previous_editor !== (string) $new_editor ) {
						$this->capture_translation_editor_switched_event( $previous_editor, $new_editor );
						$this->triggerCdtStatsResend();
					}
				}

				if ( isset( $data['translation_memory'] ) ) {
					$sitepress->set_setting( 'translation_memory', $data['translation_memory'] );
				}

				$this->save_settings();
				echo '1|';
				break;
			case 'reset_duplication':
				$this->reset_duplicate_flag( $_POST['post_id'] );
				break;
			case 'set_duplication':
				$new_id = $this->set_duplicate( $_POST['wpml_original_post_id'], $_POST['post_lang'] );
				if ( false === $new_id ) {
					wp_send_json_error( array( 'reason' => self::DUPLICATION_PAUSED ) );
					break;
				}
				wp_send_json_success( array( 'id' => $new_id ) );
				break;
		}
	}

	private function has_setting_value_changed( $stored, $posted ) {
		if ( is_scalar( $stored ) && is_scalar( $posted ) ) {
			return (string) $stored !== (string) $posted;
		}

		return $stored !== $posted;
	}

	public function get_element_prefix( $element_type_full ) {
		$element_type_parts = explode( '_', $element_type_full );
		$element_type       = $element_type_parts[0];

		return $element_type;
	}

	public function get_element_type_prefix_from_job_id( $job_id ) {
		$job = $this->get_translation_job( $job_id );

		if ( isset( $job->element_type_prefix ) ) {
			return $job->element_type_prefix;
		}

		return $job ? $this->get_element_type_prefix_from_job( $job ) : false;
	}

	public function get_element_type_prefix_from_job( $job ) {
		if ( is_object( $job ) ) {
			$element_type        = $this->get_element_type( $job->trid );
			$element_type_prefix = $this->get_element_prefix( $element_type );
		} else {
			$element_type_prefix = false;
		}

		return $element_type_prefix;
	}


	public function has_translators() {
		if ( function_exists( 'wpml_tm_load_blog_translators' ) ) {
			return wpml_tm_load_blog_translators()->has_translators();
		}

		return false;
	}

	public static function get_blog_translators( $args = array() ) {
		$translators = array();

		if ( function_exists( 'wpml_tm_load_blog_translators' ) ) {
			$translators = wpml_tm_load_blog_translators()->get_blog_translators( $args );
		}

		return $translators;
	}

	function get_selected_translator() {
		global $wpdb;
		if ( $this->selected_translator && $this->selected_translator->ID ) {
			$user                                      = new WP_User( $this->selected_translator->ID );
			$this->selected_translator->display_name   = $user->data->display_name;
			$this->selected_translator->user_login     = $user->data->user_login;
			$this->selected_translator->language_pairs = get_user_meta( $this->selected_translator->ID, $wpdb->prefix . 'language_pairs', true );
		} else {
			$this->selected_translator->ID = 0;
		}

		return $this->selected_translator;
	}

	function get_current_translator() {
		$current_translator        = $this->current_translator;
		$current_translator_is_set = $current_translator && $current_translator->ID > 0 && $current_translator->language_pairs;

		if ( ! $current_translator_is_set ) {
			$this->init_current_translator();
		}

		return $this->current_translator;
	}


	function make_duplicates( $data ) {
		$duplicate_to = TranslationPause::filterTranslatable( array_keys( (array) $data['duplicate_to'] ) );

		\WPML\Core\Compatibility\OperationContext::within(
			\WPML\Core\Compatibility\OperationContext::MAKE_DUPLICATES,
			function () use ( $data, $duplicate_to ) {
				foreach ( $data['iclpost'] as $master_post_id ) {
					foreach ( $duplicate_to as $lang ) {
						$this->make_duplicate( $master_post_id, $lang );
					}
				}
			}
		);
	}

	function make_duplicate( $master_post_id, $lang, $overwrite_translation = false ) {
		global $sitepress;

		return $sitepress->make_duplicate( $master_post_id, $lang, $overwrite_translation );
	}

	function make_duplicates_all( $master_post_id ) {
		global $sitepress;

		$master_post = get_post( $master_post_id );
		if ( $master_post->post_status == 'auto-draft' || $master_post->post_type == 'revision' ) {
			return;
		}

		$language_details_original = $sitepress->get_element_language_details( $master_post_id, 'post_' . $master_post->post_type );

		if ( ! $language_details_original ) {
			return;
		}

		$data['iclpost'] = array( $master_post_id );
		foreach ( $sitepress->get_active_languages() as $lang => $details ) {
			if ( $lang != $language_details_original->language_code ) {
				$data['duplicate_to'][ $lang ] = 1;
			}
		}

		$this->make_duplicates( $data );
	}

	function reset_duplicate_flag( $post_id ) {
		global $sitepress;

		$post = get_post( $post_id );

		$trid         = $sitepress->get_element_trid( $post_id, 'post_' . $post->post_type );
		$translations = $sitepress->get_element_translations( $trid, 'post_' . $post->post_type );

		foreach ( $translations as $tr ) {
			if ( $tr->element_id == $post_id ) {
				$this->update_translation_status(
					array(
						'translation_id' => $tr->translation_id,
						'status'         => ICL_TM_COMPLETE,
					)
				);
			}
		}

		delete_post_meta( $post_id, '_icl_lang_duplicate_of' );
	}

	function set_duplicate( $master_post_id, $post_lang ) {
		$new_id = 0;
		if ( $master_post_id && $post_lang ) {
			$new_id = \WPML\Core\Compatibility\OperationContext::within(
				\WPML\Core\Compatibility\OperationContext::SET_DUPLICATION,
				function () use ( $master_post_id, $post_lang ) {
					return $this->make_duplicate( $master_post_id, $post_lang, true );
				}
			);
		}

		return $new_id;
	}

	function duplication_delete_comment( $comment_id ) {
		global $wpdb;

		$original_comment = (bool) get_comment_meta( $comment_id, '_icl_duplicate_of', true ) === false;
		if ( $original_comment ) {
			$duplicates = $wpdb->get_col(
				$wpdb->prepare(
					"SELECT comment_id
														   FROM {$wpdb->commentmeta}
														   WHERE meta_key='_icl_duplicate_of'
														   AND meta_value=%d",
					$comment_id
				)
			);
			foreach ( $duplicates as $dup ) {
				wp_delete_comment( $dup, true );
			}
		}
	}

	function duplication_edit_comment( $comment_id ) {
		global $wpdb;

		$comment = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$wpdb->comments} WHERE comment_ID=%d", $comment_id ), ARRAY_A );
		unset( $comment['comment_ID'], $comment['comment_post_ID'] );

		$comment_meta = $wpdb->get_results( $wpdb->prepare( "SELECT meta_key, meta_value FROM {$wpdb->commentmeta} WHERE comment_id=%d AND meta_key <> '_icl_duplicate_of'", $comment_id ) );

		$original_comment = get_comment_meta( $comment_id, '_icl_duplicate_of', true );
		if ( $original_comment ) {
			$duplicates = $wpdb->get_col( $wpdb->prepare( "SELECT comment_id FROM {$wpdb->commentmeta} WHERE meta_key='_icl_duplicate_of' AND meta_value=%d", $original_comment ) );
			$duplicates = array( $original_comment ) + array_diff( $duplicates, array( $comment_id ) );
		} else {
			$duplicates = $wpdb->get_col( $wpdb->prepare( "SELECT comment_id FROM {$wpdb->commentmeta} WHERE meta_key='_icl_duplicate_of' AND meta_value=%d", $comment_id ) );
		}

		$updated = 0;
		if ( ! empty( $duplicates ) ) {
			foreach ( $duplicates as $dup ) {

				$updated += (int) $wpdb->update( $wpdb->comments, $comment, array( 'comment_ID' => $dup ) );

				$wpdb->query( $wpdb->prepare( "DELETE FROM {$wpdb->commentmeta} WHERE comment_id=%d AND meta_key <> '_icl_duplicate_of'", $dup ) );

				if ( $comment_meta ) {
					foreach ( $comment_meta as $key => $value ) {
						wp_cache_delete( $dup, 'comment_meta' );
						update_comment_meta( $dup, $value->meta_key, $value->meta_value );
					}
				}
			}
		}

		if ( $updated > 0 ) {
			do_action( 'wpml_comment_counts_mark_out_of_date' );
		}
	}

	function duplication_status_comment( $comment_id, $comment_status ) {
		global $wpdb;

		static $_avoid_8_loop;

		if ( isset( $_avoid_8_loop ) ) {
			return;
		}
		$_avoid_8_loop = true;

		$original_comment = get_comment_meta( $comment_id, '_icl_duplicate_of', true );
		if ( $original_comment ) {
			$duplicates = $wpdb->get_col( $wpdb->prepare( "SELECT comment_id FROM {$wpdb->commentmeta} WHERE meta_key='_icl_duplicate_of' AND meta_value=%d", $original_comment ) );
			$duplicates = array( $original_comment ) + array_diff( $duplicates, array( $comment_id ) );
		} else {
			$duplicates = $wpdb->get_col( $wpdb->prepare( "SELECT comment_id FROM {$wpdb->commentmeta} WHERE meta_key='_icl_duplicate_of' AND meta_value=%d", $comment_id ) );
		}

		if ( ! empty( $duplicates ) ) {
			foreach ( $duplicates as $duplicate ) {
				wp_set_comment_status( $duplicate, $comment_status );
			}
		}

		unset( $_avoid_8_loop );
	}

	function duplication_insert_comment( $comment_id ) {
		global $wpdb, $sitepress;

		$duplicator = $this->get_comment_duplicator();

		$comment = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$wpdb->comments} WHERE comment_ID=%d", $comment_id ), ARRAY_A );

		$skip_duplication = apply_filters( 'wpml_skip_comment_duplication', false, $comment_id, $comment );

		if ( $skip_duplication ) {
			return;
		}

		$post_id = $comment['comment_post_ID'];

		$duplicate_of = get_post_meta( $post_id, '_icl_lang_duplicate_of', true );
		if ( $duplicate_of ) {
			$post_duplicates = $sitepress->get_duplicates( $duplicate_of );
			$duplicator->move_to_original( $duplicate_of, $post_duplicates, $comment );
			$this->duplication_insert_comment( $comment_id );

			return;
		} else {
			$post_duplicates = $sitepress->get_duplicates( $post_id );
		}
		if ( $post_duplicates ) {
			$this->maybe_log_comment_duplication_usage( $post_id, count( $post_duplicates ) );
		}

		unset( $comment['comment_ID'], $comment['comment_post_ID'] );
		foreach ( $post_duplicates as $lang => $dup_id ) {
			$comment['comment_post_ID'] = $dup_id;

			if ( $comment['comment_parent'] ) {
				$translated_parent = $duplicator->get_correct_parent( $comment, $dup_id );
				if ( ! $translated_parent ) {
					$this->duplication_insert_comment( $comment['comment_parent'] );
					$translated_parent = $duplicator->get_correct_parent( $comment, $dup_id );
				}
				$comment['comment_parent'] = $translated_parent;
			}

			$duplicator->insert_duplicated_comment( $comment, $dup_id, $comment_id );
		}
	}

	private function maybe_log_comment_duplication_usage( $post_id, $duplicate_count ) {
		if ( self::$comment_duplication_usage_logged ) {
			return;
		}

		self::$comment_duplication_usage_logged = true;

		$event = array(
			'setting'         => 'sync_comments_on_duplicates',
			'blog_id'         => (int) get_current_blog_id(),
			'source_post_id'  => (int) $post_id,
			'duplicate_count' => (int) $duplicate_count,
		);

		$wpml_debug_log_enabled = defined( 'WPML_DEBUG_LOG' ) && WPML_DEBUG_LOG;

		$should_log = apply_filters(
			'wpml_sync_comments_on_duplicates_log_usage',
			$wpml_debug_log_enabled,
			$event
		);

		if ( true !== $should_log ) {
			return;
		}

		$encoded_event = wp_json_encode( $event );
		if ( ! is_string( $encoded_event ) ) {
			$encoded_event = '{"setting":"sync_comments_on_duplicates"}';
		}

		error_log( self::COMMENT_DUPLICATION_LOG_PREFIX . ' ' . $encoded_event );
	}

	private function get_comment_duplicator() {

		if ( ! $this->comment_duplicator ) {
			$this->comment_duplicator = new WPML_Comment_Duplication();
		}

		return $this->comment_duplicator;
	}

	public function delete_post_actions( $post_id ) {
		global $wpdb;

		$post_type = $wpdb->get_var( $wpdb->prepare( "SELECT post_type FROM {$wpdb->posts} WHERE ID=%d", $post_id ) );

		if ( ! empty( $post_type ) ) {
			$trid_subquery = $wpdb->prepare(
				"SELECT trid FROM {$wpdb->prefix}icl_translations WHERE element_id=%d AND element_type=%s AND source_language_code IS NULL",
				$post_id,
				'post_' . $post_type
			);

			$translation_ids = $wpdb->get_col(
				"SELECT translation_id FROM {$wpdb->prefix}icl_translations WHERE trid = (" . $trid_subquery . ')'
			);

			if ( $translation_ids ) {
				$rids = $wpdb->get_col(
					"SELECT rid FROM {$wpdb->prefix}icl_translation_status WHERE translation_id IN (" . wpml_prepare_in( $translation_ids, '%d' ) . ')'
				);
				$wpdb->query(
					"DELETE FROM {$wpdb->prefix}icl_translation_status WHERE translation_id IN (" . wpml_prepare_in( $translation_ids, '%d' ) . ')'
				);

				if ( $rids ) {
					$job_ids = $wpdb->get_col(
						"SELECT job_id FROM {$wpdb->prefix}icl_translate_job WHERE rid IN (" . wpml_prepare_in( $rids, '%d' ) . ')'
					);
					$wpdb->query(
						"DELETE FROM {$wpdb->prefix}icl_translate_job WHERE rid IN (" . wpml_prepare_in( $rids, '%d' ) . ')'
					);
					if ( $job_ids ) {
						$wpdb->query(
							"DELETE FROM {$wpdb->prefix}icl_translate WHERE job_id IN (" . wpml_prepare_in( $job_ids, '%d' ) . ')'
						);
					}
				}
			}
		}
	}


	function post_md5( $post ) {

		return apply_filters( 'wpml_tm_element_md5', $post );
	}

	function get_element_translation( $element_id, $language, $element_type = 'post_post' ) {
		global $wpdb, $sitepress;
		$trid        = $sitepress->get_element_trid( $element_id, $element_type );
		$translation = array();
		if ( $trid ) {
			$translation = $wpdb->get_row(
				$wpdb->prepare(
					"
				SELECT *
				FROM {$wpdb->prefix}icl_translations tr
				JOIN {$wpdb->prefix}icl_translation_status ts ON tr.translation_id = ts.translation_id
				WHERE tr.trid=%d AND tr.language_code= %s
			",
					$trid,
					$language
				)
			);
		}

		return $translation;
	}

	function get_element_translations( $element_id, $element_type = 'post_post', $service = false ) {
		global $wpdb, $sitepress;
		$trid         = $sitepress->get_element_trid( $element_id, $element_type );
		$translations = array();
		if ( $trid ) {
			if ( $service ) {
				$translations = $wpdb->get_results(
					$wpdb->prepare(
						"
				SELECT *
				FROM {$wpdb->prefix}icl_translations tr
				JOIN {$wpdb->prefix}icl_translation_status ts ON tr.translation_id = ts.translation_id
				WHERE tr.trid=%d AND translation_service = %s
			",
						$trid,
						$service
					)
				);
			} else {
				$translations = $wpdb->get_results(
					$wpdb->prepare(
					"
				SELECT *
				FROM {$wpdb->prefix}icl_translations tr
				JOIN {$wpdb->prefix}icl_translation_status ts ON tr.translation_id = ts.translation_id
				WHERE tr.trid=%d
			",
						$trid
					)
				);
			}
			foreach ( $translations as $k => $v ) {
				$translations[ $v->language_code ] = $v;
				unset( $translations[ $k ] );
			}
		}

		return $translations;
	}

	public function status2icon_class( $status, $needs_update = 0, $needs_review = false ) {
		if ( $needs_update ) {
			$icon_class = 'otgs-ico-needs-update';
		} elseif ( $needs_review ) {
			$icon_class = 'otgs-ico-needs-review';
		} else {
			switch ( $status ) {
				case ICL_TM_NOT_TRANSLATED:
					$icon_class = 'otgs-ico-not-translated';
					break;
				case ICL_TM_WAITING_FOR_TRANSLATOR:
					$icon_class = 'otgs-ico-waiting';
					break;
				case ICL_TM_IN_PROGRESS:
				case ICL_TM_TRANSLATION_READY_TO_DOWNLOAD:
				case ICL_TM_ATE_NEEDS_RETRY:
					$icon_class = 'otgs-ico-in-progress';
					break;
				case ICL_TM_NEEDS_UPDATE:
					$icon_class = 'otgs-ico-needs-update';
					break;
				case ICL_TM_DUPLICATE:
					$icon_class = 'otgs-ico-duplicate';
					break;
				case ICL_TM_COMPLETE:
					$icon_class = 'otgs-ico-translated';
					break;
				case ICL_TM_ATE_UNSOLVABLE:
					$icon_class = 'otgs-ico-warning';
					break;
				default:
					$icon_class = 'otgs-ico-not-translated';
			}
		}

		return $icon_class;
	}

	public static function status2text( $status ) {
		switch ( $status ) {
			case ICL_TM_NOT_TRANSLATED:
				/* translators: Status of a piece of content: it has no translation in that language yet. */
				$text = __( 'Not translated', 'sitepress' );
				break;
			case ICL_TM_WAITING_FOR_TRANSLATOR:
				$text = __( 'Waiting for translator', 'sitepress' );
				break;
			case ICL_TM_IN_PROGRESS:
				/* translators: Status of a translation: it is being made right now. */
				$text = __( 'In progress', 'sitepress' );
				break;
			case ICL_TM_NEEDS_UPDATE:
				/* translators: Status of a translation: the original changed after it was translated, so the translation has to be gone over again. */
				$text = __( 'Needs update', 'sitepress' );
				break;
			case ICL_TM_DUPLICATE:
				/* translators: Status of a translation: it is a copy of the original that WPML keeps in step, not a translation of its own. Noun. */
				$text = _x( 'Duplicate', 'status of a translation', 'sitepress' );
				break;
			case ICL_TM_COMPLETE:
				/* translators: Status of a translation: it is finished. Adjective, not an instruction to finish it. */
				$text = __( 'Complete', 'sitepress' );
				break;
			case ICL_TM_TRANSLATION_READY_TO_DOWNLOAD:
				$text = __( 'Translation ready to download', 'sitepress' );
				break;
			case ICL_TM_ATE_UNSOLVABLE:
				$text = __( 'Failed - needs attention', 'sitepress' );
				break;
			case ICL_TM_ATE_NEEDS_RETRY:
				$text = __( 'In progress - needs retry', 'sitepress' );
				break;
			default:
				$text = '';
		}

		return $text;
	}

	public function decode_field_data( $data, $format ) {
		if ( $format == 'base64' ) {
			$data = base64_decode( $data );
		} elseif ( $format == 'csv_base64' ) {
			$exp = explode( ',', $data );
			foreach ( $exp as $k => $e ) {
				$exp[ $k ] = base64_decode( trim( $e, '"' ) );
			}
			$data = $exp;
		}

		return $data;
	}

	function create_translation_package( $post ) {
		$wpmlElementTranslationPackage = make( WPML_Element_Translation_Package::class );
		return $wpmlElementTranslationPackage->create_translation_package( $post, true ) ?: false;
	}

	function messages_by_type( $type ) {
		$messages = $this->messages;

		$result = [];
		foreach ( $messages as $message ) {
			if ( $type === false || ( ! empty( $message['type'] ) && $message['type'] == $type ) ) {
				$result[] = $message;
			}
		}

		return $result ?: false;
	}

	public function start_send_budget() {
		$this->send_deadline            = microtime( true ) + \WPML\TM\TranslationProxy\SendTuning::timeBudgetSeconds();
		$this->send_was_truncated       = false;
		$this->send_processed_elements  = 0;
		$this->send_processed_pairs     = 0;
		$this->failed_send_elements     = [];
	}

	public function send_was_truncated() {
		return $this->send_was_truncated;
	}

	public function get_failed_send_elements() {
		return $this->failed_send_elements;
	}

	public function add_failed_create_job_ids( $job_ids, $sent_from = null ) {
		$this->failed_create_job_ids = array_values(
			array_unique( array_merge( $this->failed_create_job_ids, array_map( 'intval', (array) $job_ids ) ) )
		);
	}

	public function get_failed_create_job_ids() {
		return $this->failed_create_job_ids;
	}

	public function add_held_back_job_ids( $job_ids, $sent_from = null ) {
		$this->held_back_job_ids = array_values(
			array_unique( array_merge( $this->held_back_job_ids, array_map( 'intval', (array) $job_ids ) ) )
		);
	}

	public function get_held_back_job_ids() {
		return $this->held_back_job_ids;
	}

	public function get_refused_duplications(): array {
		return $this->refused_duplications;
	}

	private function refuse_duplication( $element_id, $language, $reason ) {
		$this->refused_duplications[] = [
			'element_id' => (int) $element_id,
			'language'   => (string) $language,
			'reason'     => (string) $reason,
		];
	}

	public function get_sent_job_ids(): array {
		return array_reduce(
			$this->sent_job_ids_per_type,
			function ( $carry, $item ) {
				return array_merge( $carry, $item );
			},
			[]
		);
	}

	public function add_message_from_action( $type, $message, $id = null ) {
		$message = array(
			'type' => $type,
			'text' => $message,
		);
		if ( $id ) {
			$message['id'] = $id;
		}

		$this->add_message( $message );
	}

	function add_message( $message ) {
		$this->messages[] = $message;
		$this->messages   = array_unique( $this->messages, SORT_REGULAR );
	}

	function update_translation_status( $data, $rid = null ) {
		global $wpdb;
		if ( ! isset( $data['translation_id'] ) ) {
			JobLog::add( 'Cannot find translation_id in update_translation_status' );
			return array( false, false );
		}

		if ( ! $rid ) {
			$rid = $this->get_rid_from_translation_id( $data['translation_id'] );
			JobLog::add( 'update_translation_status: found rid from translation_id `' . $data['translation_id'] . '`' );
		} else {
			JobLog::add( 'update_translation_status: using existing rid `' . $rid . '`' );
		}

		$valid_columns = [
			'translation_id',
			'status',
			'translator_id',
			'needs_update',
			'md5',
			'translation_service',
			'batch_id',
			'translation_package',
			'timestamp',
			'links_fixed',
			'_prevstate',
			'uuid',
			'tp_id',
			'tp_revision',
			'ts_status',
			'review_status',
			'ate_comm_retry_count'
		];

		$filtered_data = array_intersect_key( $data, array_flip( $valid_columns ) );

		$this->last_status_write_failed = false;
		$this->last_status_write_errno  = 0;

		$update = (bool) $rid;
		if ( true === $update ) {
			$data_where = array( 'rid' => $rid );
			$affected   = null;
			$written    = $this->write_healing_status_schema(
				function () use ( $filtered_data, $data_where, &$affected ) {
					global $wpdb;

					return $affected = $wpdb->update( $wpdb->prefix . 'icl_translation_status', $filtered_data, $data_where );
				}
			);
			if ( $written && 0 === $affected && ! $this->statusRowExists( $rid ) ) {
				$this->record_status_write( false );
				JobLog::add( 'update_translation_status: the translation status record for rid `' . $rid . '` was missing', $filtered_data );

				return array( 0, $update );
			}
			$this->record_status_write( $written );
			JobLog::add(
				'update_translation_status: updated existing translation status with rid `' . $rid . '`',
				$filtered_data
			);
		} else {
			$written = $this->write_healing_status_schema(
				fn() => $wpdb->insert( $wpdb->prefix . 'icl_translation_status', $filtered_data )
			);
			$rid = $written ? (int) $wpdb->insert_id : 0;
			$this->record_status_write( $written && $rid );
			if ( ! $rid ) {
				JobLog::add( 'update_translation_status: the new translation status record was not written', $filtered_data );

				return array( 0, $update );
			}
			JobLog::add(
				'update_translation_status: created new translation status record with rid `' . $rid . '`',
				$filtered_data
			);
		}
		$data['rid'] = $rid;

		do_action( 'wpml_updated_translation_status', $data );

		return array( $rid, $update );
	}

	private function write_healing_status_schema( callable $write ) {
		global $wpdb;

		$result = $write();
		$errno  = $this->db_errno();

		if ( $wpdb->last_error && TranslationStatusSchema::healMissingColumns() ) {
			$result = $write();
			$errno  = $this->db_errno();
		}

		$this->last_status_write_errno = false === $result ? $errno : 0;

		return false !== $result;
	}

	private function db_errno() {
		global $wpdb;

		$dbh = $wpdb->dbh;

		return $dbh instanceof \mysqli ? (int) $dbh->errno : 0;
	}

	private function record_status_write( $written ) {
		$this->last_status_write_failed = ! $written;
	}

	private function report_failed_status_write( $element_type, $element_id, $lang, $translation_id ) {
		global $wpdb;

		$missing = \WPML\TM\Jobs\StatusWriteFailure::missingColumns( new WPML_Upgrade_Schema( $wpdb ) );

		$this->add_message(
			[
				'id'   => \WPML\TM\Jobs\StatusWriteFailure::CODE_WRITE_FAILED,
				'type' => 'error',
				'text' => \WPML\TM\Jobs\StatusWriteFailure::details(
					$this->last_status_write_errno,
					\WPML\TM\Jobs\StatusWriteFailure::whereAreTheDetails()
				),
			]
		);

		JobLog::addError(
			\WPML\TM\Jobs\StatusWriteFailure::CODE_WRITE_FAILED,
			[
				'element_type'    => $element_type,
				'element_id'      => (int) $element_id,
				'target_language' => $lang,
				'translation_id'  => (int) $translation_id,
				'missing_columns' => $missing,
				'mysql_errno'     => $this->last_status_write_errno,
			]
		);
	}

	private function report_failed_job_write( $element_type, $element_id, $lang, $translation_id, $rid ) {
		$errno = WPML_TM_Update_Translation_Data_Action::lastJobInsertErrno();

		$this->add_message(
			[
				'id'   => \WPML\TM\Jobs\StatusWriteFailure::CODE_JOB_WRITE_FAILED,
				'type' => 'error',
				'text' => \WPML\TM\Jobs\StatusWriteFailure::jobWriteDetails(
					$errno,
					\WPML\TM\Jobs\StatusWriteFailure::whereAreTheDetails()
				),
			]
		);

		JobLog::addError(
			\WPML\TM\Jobs\StatusWriteFailure::CODE_JOB_WRITE_FAILED,
			[
				'element_type'    => $element_type,
				'element_id'      => (int) $element_id,
				'target_language' => $lang,
				'translation_id'  => (int) $translation_id,
				'rid'             => (int) $rid,
				'mysql_errno'     => $errno,
			]
		);
	}

	private function revert_failed_job_write( $translation_id, array $backup_translation_status ) {
		global $wpdb;

		if ( $backup_translation_status ) {
			$wpdb->update( "{$wpdb->prefix}icl_translation_status", $backup_translation_status, [ 'translation_id' => $translation_id ] );
		} else {
			$wpdb->delete( "{$wpdb->prefix}icl_translation_status", [ 'translation_id' => $translation_id ] );
		}
	}

	public function describe_unconfirmed_send( $jobs ) {
		return \WPML\TM\Jobs\StatusWriteFailure::notConfirmedDetails( (int) $jobs );
	}

	private function get_rid_from_translation_id( $translation_id ) {
		global $wpdb;

		return (int) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT rid
				FROM {$wpdb->prefix}icl_translation_status
				WHERE translation_id = %d",
				$translation_id
			)
		);
	}

	private function statusRowExists( $rid ) {
		global $wpdb;

		return (bool) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT rid
				FROM {$wpdb->prefix}icl_translation_status
				WHERE rid = %d",
				(int) $rid
			)
		);
	}


	public function action_send_jobs( \WPML_TM_Translation_Batch $batch, $type = 'post', $sendFrom = null ) {
		$this->send_jobs( $batch, $type, $sendFrom );
	}

	function send_jobs( \WPML_TM_Translation_Batch $batch, $type = 'post', $sendFrom = null ) {
		global $sitepress;

		if ( defined( 'WPML_TM_VERSION' ) && ! function_exists( 'wpml_tm_add_translation_job' ) ) {
			require_once WPML_TM_PATH . '/inc/wpml-private-actions-tm.php';
		}

		JobLog::maybeInitRequest();
		JobLog::createNewGroup(
			JobLog::GROUP_ID_SEND_JOBS,
			'Sending jobs to translations for type `' . $type . '`',
			[
				'type'     => $type,
				'sendFrom' => $sendFrom,
				'batch'    => $batch->toArray(),
			]
		);
		JobLog::addExtraLogData( 'type', $type );

		$this->sent_job_ids_per_type[ $type ] = [];
		$this->refused_duplications           = [];
		$job_ids                              = array();
		$added_jobs                           = array();
		$budget_is_armed                      = null !== $this->send_deadline;
		$batch_id                             = TranslationProxy_Batch::update_translation_batch( $batch->get_basket_name() );

		$batch = apply_filters( 'wpml_send_jobs_batch', $batch );

		foreach ( $batch->get_elements_by_type( $type ) as $element ) {
			if ( $budget_is_armed
				 && ( $this->send_was_truncated
					  || ( $this->send_processed_elements > 0
						   && $this->send_processed_elements >= \WPML\TM\TranslationProxy\SendTuning::maxElementsPerRequest() ) ) ) {
				if ( ! $this->send_was_truncated ) {
					JobLog::add( 'Send element cap reached after ' . (string) $this->send_processed_elements . ' elements. The browser continues the rest.' );
				}
				$this->send_was_truncated = true;
				break;
			}
			$this->send_processed_elements ++;

			$elementId = JobLog::safeCall( $element, 'get_element_id' );
			$post      = $elementId !== null ? $this->get_post( $elementId, $type ) : null;
			if ( ! $post ) {
				JobLog::add( 'Translatable element not found for id `' . (string) $elementId . '`' );
				continue;
			}

			JobLog::addExtraLogData( 'element_id', $elementId );
			JobLog::add( 'Translatable element found' );

			$targetLangs = $element->get_target_langs();
			$targetLangs = array_intersect_key(
				$targetLangs,
				array_flip( TranslationPause::filterTranslatable( array_keys( $targetLangs ) ) )
			);

			if ( ! $targetLangs ) {
				JobLog::add(
					'translation_paused_element_dropped',
					[ 'paused_targets' => array_keys( $element->get_target_langs() ) ]
				);
				JobLog::removeExtraLogData( 'element_id' );
				continue;
			}

			$wpmlElementTranslationPackage = make( WPML_Element_Translation_Package::class );
			$wpmlElementTranslationPackage->do_action_before_creating_translation_package( $post );

			$element_type        = $type . '_' . $post->post_type;
			$post_trid           = $sitepress->get_element_trid( $element->get_element_id(), $element_type );
			$post_translations   = $sitepress->get_element_translations( $post_trid, $element_type );

			foreach ( (array) $post_translations as $rowLang => $row ) {
				if ( is_object( $row ) && empty( $row->element_id ) ) {
					JobLog::add( 'broken_translation_row_missing_element_id', [
						'trid'            => $post_trid,
						'broken_language' => $rowLang,
						'translation_id'  => isset( $row->translation_id ) ? (int) $row->translation_id : null,
						'row_language'    => isset( $row->language_code ) ? $row->language_code : null,
					] );
				}
			}

			if ( 'post' === $type ) {
				\WPML\TM\Jobs\BaselineJobCreator::build()->create_missing(
					$element->get_element_id(),
					\WPML\TM\Jobs\BaselineJobCreator::TRIGGER_SEND
				);
			}

			$md5                 = $this->post_md5( $post );

			JobLog::addExtraLogData( 'element_type', $element_type );
			JobLog::add(
				'Selected translatable element data',
				[
					'trid'         => $post_trid,
					'md5'          => $md5,
					'translations' => $post_translations,
				]
			);

			$translation_package = $wpmlElementTranslationPackage->create_translation_package( $post, true ) ?: false;
			JobLog::add(
				'Translation package created',
				$translation_package
			);

			foreach ( $targetLangs as $lang => $action ) {
				if ( $budget_is_armed
					 && ( $this->send_was_truncated
						  || ( $this->send_processed_pairs > 0
							   && microtime( true ) >= $this->send_deadline ) ) ) {
					if ( ! $this->send_was_truncated ) {
						JobLog::add( 'Send time budget spent; yielding after ' . (string) $this->send_processed_pairs . ' item-language pairs. The browser continues the rest.' );
					}
					$this->send_was_truncated = true;
					break;
				}
				$this->send_processed_pairs ++;

				JobLog::addExtraLogData( 'target_lang', $lang );

				if ( $action == self::DUPLICATE_ELEMENT_ACTION ) {
					JobLog::add( 'Duplicating post');
					$current_translation_status = $this->get_element_translation( $element->get_element_id(), $lang, $element_type );
					if ( $current_translation_status
						 && in_array(
							 (int) $current_translation_status->status,
							 [ ICL_TM_WAITING_FOR_TRANSLATOR, ICL_TM_IN_PROGRESS, ICL_TM_ATE_NEEDS_RETRY ],
							 true
						 ) ) {
						JobLog::add( 'Cannot duplicate post because a job is waiting or in progress' );
						$this->refuse_duplication( $element->get_element_id(), $lang, self::DUPLICATION_REFUSED_IN_PROGRESS );
						JobLog::removeExtraLogData( 'target_lang' );
						continue;
					}

					$duplicate_id = $this->make_duplicate( $element->get_element_id(), $lang );
					if ( false === $duplicate_id ) {
						JobLog::add( 'Cannot duplicate post because the language holds a translation' );
						$this->refuse_duplication( $element->get_element_id(), $lang, self::DUPLICATION_REFUSED_TRANSLATION_EXISTS );
						JobLog::removeExtraLogData( 'target_lang' );
						continue;
					}

					if ( true === $duplicate_id ) {
						JobLog::add( 'Nothing to duplicate' );
						JobLog::removeExtraLogData( 'target_lang' );
						continue;
					}

					$job_ids[] = $duplicate_id;
					JobLog::add(
						'Post duplicated successfully',
						[
							'job_ids' => $job_ids,
						]
					);
				} elseif ( $action == self::TRANSLATE_ELEMENT_ACTION ) {
					JobLog::add( 'Translating post' );

					$sendLock = new HeldOpenLock(
						( new OpenLock() )->acquire( [ 'trid' => $post_trid, 'language_code' => $lang ] )
					);

					$post_translations = $this->release_foreign_language_row( $post_translations, $lang, $element_type, $post_trid );

					$translation_row_created = empty( $post_translations[ $lang ] );
					if ( $translation_row_created ) {
						$translation_id = $sitepress->set_element_language_details( null, $element_type, $post_trid, $lang, $element->get_source_lang() );
						JobLog::add(
							'Created entry with id `' . $translation_id . '` in icl_translations table',
							[
								'translation_id' => $translation_id,
								'element_type'   => $element_type,
								'trid'           => $post_trid,
								'lang'           => $lang,
								'source_lang'    => $element->get_source_lang(),
							]
						);
					} else {
						$translation_id = $post_translations[ $lang ]->translation_id;
						$sitepress->set_element_language_details( $post_translations[ $lang ]->element_id, $element_type, $post_trid, $lang, $element->get_source_lang() );
						JobLog::add(
							'Updated entry with id `' . $translation_id . '` in icl_translations table',
							[
								'translation_id' => $translation_id,
								'element_type'   => $element_type,
								'trid'           => $post_trid,
								'lang'           => $lang,
								'source_lang'    => $element->get_source_lang(),
							]
						);
					}

					$current_translation_status = $this->get_element_translation( $element->get_element_id(), $lang, $element_type );
					JobLog::add(
						'Current translation status',
						[
							'current_translation_status' => $current_translation_status,
						]
					);

					if (
						$current_translation_status && (
							$current_translation_status->status == ICL_TM_IN_PROGRESS ||
							$current_translation_status->status == ICL_TM_WAITING_FOR_TRANSLATOR
						)
					) {
						$superseded_job = $this->findPriorInFlightJobForRid( (int) $current_translation_status->rid );
						$this->cancelSupersededJobInATE(
							$superseded_job,
							(int) $current_translation_status->rid,
							$sendFrom
						);
						$this->forget_unconfirmed_create( $superseded_job );

						$this->cancel_translation_request( $translation_id, false );
						JobLog::add(
							'Translation request canceled',
							[
								'current_translation_status' => $current_translation_status,
							]
						);
					}

					$_status = ICL_TM_WAITING_FOR_TRANSLATOR;

					$translator       = $batch->get_translator( $lang );
					$translation_data = TranslationProxy_Service::get_translator_data_from_wpml( $translator );
					$translator_id    = $sendFrom === Jobs::SENT_AUTOMATICALLY ? 0 : $translation_data['translator_id'];

					$translation_service = $translation_data['translation_service'];

					$data = array(
						'translation_id'      => $translation_id,
						'status'              => $_status,
						'translator_id'       => $translator_id,
						'needs_update'        => 0,
						'md5'                 => $md5,
						'translation_service' => $translation_service,
						'batch_id'            => $batch_id,
						'uuid'                => $this->get_uuid( $current_translation_status, $post ),
						'ts_status'           => null,
						'timestamp'           => date( 'Y-m-d H:i:s', time() ),
						'links_fixed'         => true,
					);

					$backup_translation_status = $this->get_translation_status_data( $translation_id );
					$rid = isset( $backup_translation_status['rid'] )
						? $backup_translation_status['rid'] : null;

					$endsAnOpenReview = ReviewStatus::EDITING === ( $backup_translation_status['review_status'] ?? null );
					if ( $endsAnOpenReview ) {
						$data['review_status'] = null;
					}
					if ( $rid ) {
						JobLog::add( 'Found backup translation status `' . $rid . '`' );
					}

					$priorInFlightJob = ( $translation_package && $rid ) ? $this->findPriorInFlightJobForRid( $rid ) : null;

					list( $rid ) = $this->update_translation_status( $data, $rid );
					if ( $this->last_status_write_failed ) {
						$this->report_failed_status_write( $element_type, $element->get_element_id(), $lang, $translation_id );
						if ( $translation_row_created ) {
							$sitepress->delete_element_translation( (string) $post_trid, $element_type, $lang, true );
						}
						$sendLock->release();
						JobLog::removeExtraLogData( 'target_lang' );
						continue;
					}
					$this->maybe_update_prev_state( $translation_id, $backup_translation_status );

					if ( $endsAnOpenReview ) {
						ReviewStatusLookup::invalidate();
					}

					if ( $translation_package ) {
						$package_for_lang = $wpmlElementTranslationPackage->filter_translation_package_for_lang(
							$translation_package,
							$post,
							$lang,
							$sendFrom
						);

						$job_id = wpml_tm_add_translation_job( $rid, $translator_id, $package_for_lang, $batch->get_batch_options(), $sendFrom, true );

						if ( ! $job_id && ! \WPML\TM\Jobs\TranslationPauseGate::refusesRid( (int) $rid ) ) {
							$this->revert_failed_job_write( $translation_id, $backup_translation_status );
							$this->report_failed_job_write( $element_type, $element->get_element_id(), $lang, $translation_id, $rid );
							if ( $translation_row_created ) {
								$sitepress->delete_element_translation( (string) $post_trid, $element_type, $lang, true );
							}
							$sendLock->release();
							JobLog::removeExtraLogData( 'target_lang' );
							continue;
						}

						$editorAtCreation = Jobs::SENT_AUTOMATICALLY === $sendFrom
							? WPML_TM_Editors::ATE
							: WPML_TM_Editors::NONE;
						wpml_tm_load_job_factory()->update_job_data( $job_id, array( 'editor' => $editorAtCreation ) );

						if ( $endsAnOpenReview ) {
							JobLog::add( 'review_status_cleared_by_send', [
								'job_id'                 => (int) $job_id,
								'rid'                    => (int) $rid,
								'previous_review_status' => ReviewStatus::EDITING,
							] );
						}

						if ( $priorInFlightJob && (int) $priorInFlightJob['job_id'] !== (int) $job_id ) {
							JobLog::addError( 'translation_job_superseded', [
								'new_job_id'              => (int) $job_id,
								'old_job_id'              => (int) $priorInFlightJob['job_id'],
								'rid'                     => (int) $rid,
								'target_lang'             => $lang,
								'old_status'              => isset( $priorInFlightJob['status'] ) ? (int) $priorInFlightJob['status'] : null,
								'old_translated_flag'     => isset( $priorInFlightJob['translated'] ) ? (int) $priorInFlightJob['translated'] : null,
								'old_job_age_seconds'     => isset( $priorInFlightJob['age_seconds'] ) ? (int) $priorInFlightJob['age_seconds'] : null,
								'old_ate_job_id'          => isset( $priorInFlightJob['editor_job_id'] ) ? (int) $priorInFlightJob['editor_job_id'] : null,
							] );

							$this->cancelSupersededJobInATE( $priorInFlightJob, $rid, $sendFrom );
							$this->forget_unconfirmed_create( $priorInFlightJob );
						}

						if ( $translation_service !== 'local' ) {
							global $ICL_Pro_Translation;

							$tp_job_id = $ICL_Pro_Translation->send_post( $post, array( $lang ), $translator_id, $job_id, $batch->getTpBatchInfo() );

							if ( $tp_job_id ) {
								$this->update_translation_status(
									array(
										'translation_id' => $translation_id,
										'tp_id'          => $tp_job_id,
									),
									$rid
								);
								JobLog::add(
									'Updated translation status for `' . $rid . '`',
									[
										'tp_id'          => $tp_job_id,
										'translation_id' => $translation_id,
									]
								);

								$job_ids[]                            = $job_id;
								$added_jobs[ $translation_service ][] = $job_id;
							} else {
								$revert_info = $this->revert_job_when_tp_job_could_not_be_created( $job_id, $rid, $data['translation_id'], $backup_translation_status );

								$failure_reason = $revert_info['reasons']
									? implode( ' ', $revert_info['reasons'] )
									: 'Translation Proxy did not accept the job.';

								$failure_is_ambiguous = \WPML\TM\TranslationProxy\SendTuning::isAmbiguousSendFailure( $failure_reason );
								$reason_for_display   = \WPML\TM\TranslationProxy\SendTuning::sanitizeFailureReason( $failure_reason );

								if ( $failure_is_ambiguous ) {
									$this->record_ambiguous_send(
										$element,
										$post,
										$lang,
										$batch->get_basket_name(),
										$reason_for_display
									);
								} elseif ( $translation_row_created ) {
									$sitepress->delete_element_translation( (string) $post_trid, $element_type, $lang, true );
								}

								\WPML\TM\Jobs\JobLog::addError(
									$failure_is_ambiguous ? 'tp_job_send_ambiguous' : 'tp_job_send_failed',
									array(
										'element_id'      => (int) $element->get_element_id(),
										'target_language' => (string) $lang,
										'reason'          => $reason_for_display,
										'error_code'      => $revert_info['error_code'],
									)
								);

								$this->failed_send_elements[] = array(
									'elementType'    => 'st-batch' === $type ? 'string' : $type,
									'elementId'      => (int) $element->get_element_id(),
									'targetLanguage' => (string) $lang,
									'reason'         => $failure_is_ambiguous
										? 'Could not confirm whether the translation service received this job. It needs manual review. (' . $reason_for_display . ')'
										: $reason_for_display,
									'kind'           => $failure_is_ambiguous ? 'ambiguous' : 'failed',
									'errorCode'      => $revert_info['error_code'],
								);
							}
						} else {
							$job_ids[]                            = $job_id;
							$added_jobs[ $translation_service ][] = $job_id;
						}
					}

					$sendLock->release();
				}

				do_action( 'wpml_tm_added_translation_element', $element, $post );

				JobLog::removeExtraLogData( 'target_lang' );
			}

			JobLog::removeExtraLogData( 'element_id' );
			JobLog::removeExtraLogData( 'element_type' );
		}

		do_action( 'wpml_added_translation_jobs', $added_jobs, $sendFrom, $batch );

		icl_cache_clear_preserving_language_names();
		do_action( 'wpml_tm_empty_mail_queue' );

		$this->sent_job_ids_per_type[ $type ] = $job_ids;

		JobLog::removeExtraLogData( 'type' );
		JobLog::finishCurrentGroup();

		return $job_ids;
	}

	private function release_foreign_language_row( $post_translations, $lang, $element_type, $trid ) {
		global $sitepress;

		if ( 0 !== strpos( (string) $element_type, 'post_' ) || ! is_array( $post_translations ) || empty( $post_translations[ $lang ] ) ) {
			return $post_translations;
		}

		$row = $post_translations[ $lang ];

		if (
			! is_object( $row )
			|| empty( $row->element_id )
			|| ! isset( $row->element_type )
			|| (string) $row->element_type === (string) $element_type
		) {
			return $post_translations;
		}

		JobLog::add(
			'send_foreign_relation_released',
			[
				'trid'           => (int) $trid,
				'language_code'  => $lang,
				'element_type'   => $element_type,
				'translation_id' => isset( $row->translation_id ) ? (int) $row->translation_id : null,
				'foreign'        => [
					'element_id'   => (int) $row->element_id,
					'element_type' => (string) $row->element_type,
				],
			]
		);

		$sitepress->set_element_language_details( (int) $row->element_id, (string) $row->element_type, false, $lang );

		unset( $post_translations[ $lang ] );

		return $post_translations;
	}

	private function record_ambiguous_send( $element, $post, $lang, $batch_name, $reason ) {
		$post_type = isset( $post->post_type ) ? $post->post_type : 'post';
		$cms_id    = implode(
			'_',
			array( $post_type, (int) $element->get_element_id(), (string) $element->get_source_lang(), (string) $lang )
		);

		$ambiguous   = get_option( 'wpml_tp_ambiguous_jobs', array() );
		$ambiguous[] = array(
			'elementId'      => (int) $element->get_element_id(),
			'targetLanguage' => (string) $lang,
			'batchName'      => (string) $batch_name,
			'cms_id'         => $cms_id,
			'time'           => time(),
			'reason'         => $reason,
		);
		update_option( 'wpml_tp_ambiguous_jobs', $ambiguous, false );

		JobLog::add(
			'Ambiguous send recorded for reconciliation (timeout - TP may hold a live job)',
			array( 'cms_id' => $cms_id, 'batch' => $batch_name )
		);
	}

	private function revert_job_when_tp_job_could_not_be_created(
		$jobId,
		$rid,
		$translationId,
		$backup_translation_status
	) {
		global $wpdb, $ICL_Pro_Translation;

		$wpdb->query( $wpdb->prepare( "DELETE FROM {$wpdb->prefix}icl_translate_job WHERE job_id=%d", $jobId ) );
		$wpdb->query( $wpdb->prepare( "UPDATE {$wpdb->prefix}icl_translate_job SET revision = NULL WHERE rid=%d ORDER BY job_id DESC LIMIT 1", $rid ) );
		$wpdb->query( $wpdb->prepare( "DELETE FROM {$wpdb->prefix}icl_translate WHERE job_id=%d", $jobId ) );
		if ( $backup_translation_status ) {
			$wpdb->update( "{$wpdb->prefix}icl_translation_status", $backup_translation_status, [ 'translation_id' => $translationId ] );
			JobLog::add(
				'Reverted job with backup translation status when translation proxy job could not be created for job id `' . $jobId . '` and rid `' . $rid . '`',
				[
					'translation_id'            => $translationId,
					'backup_translation_status' => $backup_translation_status,
				]
			);
		} else {
			$wpdb->delete( "{$wpdb->prefix}icl_translation_status", [ 'translation_id' => $translationId ] );
			JobLog::add(
				'Reverted job with removing translation status when translation proxy job could not be created for job id `' . $jobId . '` and rid `' . $rid . '`',
				[
					'translation_id' => $translationId,
				]
			);
		}
		$reasons    = array();
		$new_errors = array_slice( $ICL_Pro_Translation->errors, $this->tp_errors_consumed );
		$this->tp_errors_consumed = count( $ICL_Pro_Translation->errors );

		foreach ( $new_errors as $error ) {
			if ( $error instanceof Exception ) {
				$message = [
					'type' => 'error',
					'text' => $error->getMessage(),
				];
				$this->add_message( $message );
				$reasons[] = $error->getMessage();
			} elseif ( is_wp_error( $error ) ) {
				$reasons[] = $error->get_error_message();
			}
		}

		return array(
			'reasons'    => $reasons,
			'error_code' => $this->first_wp_error_code( $new_errors ),
		);
	}

	private function first_wp_error_code( array $errors ) {
		foreach ( $errors as $error ) {
			if ( is_wp_error( $error ) ) {
				return (string) $error->get_error_code();
			}
		}

		return '';
	}

	private function get_uuid( $current_translation_status, $post ) {
		if ( ! empty( $current_translation_status->uuid ) ) {
			return $current_translation_status->uuid;
		} else {
			return wpml_uuid( $post->ID, $post->post_type );
		}
	}

	private function get_translation_status_data( $translation_id ) {
		global $wpdb;

		$data = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT *
				FROM {$wpdb->prefix}icl_translation_status
				WHERE translation_id = %d",
				$translation_id
			),
			ARRAY_A
		);

		return isset( $data[0] ) ? $data[0] : array();
	}

	function get_translation_jobs( $args = array() ) {

		return apply_filters( 'wpml_translation_jobs', array(), $args );
	}

	function add_translation_job( $rid, $translator_id, $translation_package, $batch_options = array(), $sendFrom = null ) {
		return wpml_tm_add_translation_job( $rid, $translator_id, $translation_package, $batch_options, $sendFrom );
	}

	function get_translation_job( $job_id, $include_non_translatable_elements = false, $auto_assign = false, $revisions = 0 ) {
		return apply_filters( 'wpml_get_translation_job', $job_id, $include_non_translatable_elements, $revisions );
	}

	function get_translation_job_id_filter( $empty, $args ) {
		if ( ! is_array( $args ) || ! isset( $args['trid'], $args['language_code'] ) ) {
			return $empty;
		}

		$trid          = $args['trid'];
		$language_code = $args['language_code'];

		return $this->get_translation_job_id( $trid, $language_code );
	}

	private function get_translation_job_info( $trid ) {
		global $wpdb;

		if ( ! $trid ) {
			return [];
		}

		$found    = false;
		$cache    = $this->cache_factory->get( 'TranslationManagement::get_translation_job_id' );
		$job_info = $cache->get( $trid, $found );
		if ( ! $found ) {
			$results = $wpdb->get_results(
				$wpdb->prepare(
					"SELECT tj.job_id, tj.editor, t.language_code FROM {$wpdb->prefix}icl_translate_job tj
					JOIN {$wpdb->prefix}icl_translation_status ts ON tj.rid = ts.rid
					JOIN {$wpdb->prefix}icl_translations t ON ts.translation_id = t.translation_id
					WHERE t.trid = %d
					ORDER BY tj.job_id DESC",
					$trid
				)
			);

			$job_info = array();
			foreach ( $results as $result ) {
				if ( ! isset( $job_info[ $result->language_code ] ) ) {
					$job_info[ $result->language_code ] = [
						'job_id' => $result->job_id,
						'editor' => $result->editor,
					];
				}
			}
			$cache->set( $trid, $job_info );
		}

		return $job_info;
	}

	public function prime_translation_job_info( array $trids ) {
		global $wpdb;

		$cache = $this->cache_factory->get( 'TranslationManagement::get_translation_job_id' );

		$missing = array();
		foreach ( array_unique( array_filter( array_map( 'intval', $trids ) ) ) as $trid ) {
			$found = false;
			$cache->get( $trid, $found );
			if ( ! $found ) {
				$missing[] = $trid;
			}
		}

		if ( ! $missing ) {
			return;
		}

		$grouped = array_fill_keys( $missing, array() );

		$results = $wpdb->get_results(
			"SELECT tj.job_id, tj.editor, t.language_code, t.trid FROM {$wpdb->prefix}icl_translate_job tj
			JOIN {$wpdb->prefix}icl_translation_status ts ON tj.rid = ts.rid
			JOIN {$wpdb->prefix}icl_translations t ON ts.translation_id = t.translation_id
			WHERE t.trid IN (" . implode( ',', $missing ) . ')
			ORDER BY tj.job_id DESC'
		);

		foreach ( (array) $results as $result ) {
			$trid = (int) $result->trid;
			if ( ! isset( $grouped[ $trid ][ $result->language_code ] ) ) {
				$grouped[ $trid ][ $result->language_code ] = array(
					'job_id' => $result->job_id,
					'editor' => $result->editor,
				);
			}
		}

		foreach ( $grouped as $trid => $job_info ) {
			$cache->set( $trid, $job_info );
		}
	}

	public function get_translation_job_id( $trid, $language_code ) {
		$job_info = $this->get_translation_job_info( $trid );

		return ( null !== $language_code && isset( $job_info[ $language_code ] ) ) ? $job_info[ $language_code ]['job_id'] : null;
	}

	public function get_translation_job_editor( $trid, $language_code ) {
		$job_info = $this->get_translation_job_info( $trid );

		return isset( $job_info[ $language_code ] ) ? $job_info[ $language_code ]['editor'] : null;
	}

	function save_translation( $data ) {
		do_action( 'wpml_save_translation_data', $data );
	}

	function save_job_fields_from_post( $job_id ) {
		do_action( 'wpml_save_job_fields_from_post', $job_id );
	}

	function mark_job_done( $job_id ) {
		global $wpdb;
		$wpdb->update( $wpdb->prefix . 'icl_translate_job', array( 'translated' => 1 ), array( 'job_id' => $job_id ) );
		$wpdb->update( $wpdb->prefix . 'icl_translate', array( 'field_finished' => 1 ), array( 'job_id' => $job_id ) );
		do_action( 'wpml_tm_empty_mail_queue' );
	}

	function resign_translator( $job_id, $skip_notification = false ) {
		global $wpdb;
		list( $translator_id, $rid, $translated, $completed_date ) = $wpdb->get_row( $wpdb->prepare( "SELECT translator_id, rid, translated, completed_date FROM {$wpdb->prefix}icl_translate_job WHERE job_id=%d", $job_id ), ARRAY_N );

		if ( \WPML\TM\Jobs\TranslationRecord::isCompleted( [ 'translated' => $translated, 'completed_date' => $completed_date ] ) ) {
			JobLog::add( 'resign_refused_on_completed_job', [ 'job_id' => (int) $job_id, 'rid' => (int) $rid ] );

			return;
		}
		$notify_manager = (int) Obj::pathOr(
			ICL_TM_NOTIFICATION_NONE,
			[ 'notification', WPML_TM_Emails_Settings::MANAGER_RESIGNED ],
			$this->settings
		);
		if ( ! $skip_notification && ! empty( $translator_id ) && ICL_TM_NOTIFICATION_NONE !== $notify_manager && $job_id ) {
			do_action( 'wpml_tm_resign_job_notification', $translator_id, $job_id );
		}
		if ( $this->translationRecord()->closeRequest( $job_id ) ) {
			return;
		}
		$wpdb->update( $wpdb->prefix . 'icl_translate_job', array( 'translator_id' => 0 ), array( 'job_id' => $job_id ) );
		if ( (int) $job_id !== \WPML\TM\API\Job\Map::jobIdFromDB( $rid ) ) {
			return;
		}
		$wpdb->update(
			$wpdb->prefix . 'icl_translation_status',
			array(
				'translator_id' => 0,
				'status'        => ICL_TM_WAITING_FOR_TRANSLATOR,
			),
			array( 'rid' => $rid )
		);
	}

	protected function translationRecord() {
		return new \WPML\TM\Jobs\TranslationRecord();
	}

	public function resign_translator_from_unfinished_jobs( WP_User $translator ) {
		global $wpdb;

		$unfinished_job_ids = $wpdb->get_col(
			$wpdb->prepare(
				"SELECT job_id
				FROM {$wpdb->prefix}icl_translate_job
				WHERE translator_id = %d AND translated = 0",
				$translator->ID
			)
		);

		$remove_job_without_notification = partialRight( [ $this, 'resign_translator' ], true );

		array_map( $remove_job_without_notification, $unfinished_job_ids );
	}

	function remove_translation_job( $job_id, $new_translation_status = ICL_TM_WAITING_FOR_TRANSLATOR, $new_translator_id = 0 ) {
		global $wpdb;

		$error = false;

		list( $prev_translator_id, $rid ) = $wpdb->get_row( $wpdb->prepare( "SELECT translator_id, rid FROM {$wpdb->prefix}icl_translate_job WHERE job_id=%d", $job_id ), ARRAY_N );

		$wpdb->update( $wpdb->prefix . 'icl_translate_job', array( 'translator_id' => $new_translator_id ), array( 'job_id' => $job_id ) );
		$wpdb->update(
			$wpdb->prefix . 'icl_translate',
			array(
				'field_data_translated' => '',
				'field_finished'        => 0,
			),
			array( 'job_id' => $job_id )
		);

		if ( $rid ) {
			$data       = array(
				'status'        => $new_translation_status,
				'translator_id' => $new_translator_id,
			);
			$data_where = array( 'rid' => $rid );
			$wpdb->update( $wpdb->prefix . 'icl_translation_status', $data, $data_where );

			if ( $this->settings['notification']['resigned'] == ICL_TM_NOTIFICATION_IMMEDIATELY && ! empty( $prev_translator_id ) ) {
				do_action( 'wpml_tm_remove_job_notification', $prev_translator_id, $job_id );
			}
		} else {
			/* translators: Error message shown when a translation job cannot be found. %d: the number that stands for that job. */
			$error = sprintf( __( 'Translation entry not found for: %d', 'sitepress' ), $job_id );
		}

		return $error;
	}

	function cancel_translation_request( $translation_id, $remove_translation_record = true ) {
		global $wpdb;

		if ( is_array( $translation_id ) ) {
			foreach ( $translation_id as $id ) {
				$this->cancel_translation_request( $id );
			}
		} else {
			list( $rid, $translator_id, $status_before, $prevstate_before ) = $wpdb->get_row(
				$wpdb->prepare(
					"SELECT rid, translator_id, status, _prevstate
                     FROM {$wpdb->prefix}icl_translation_status
                     WHERE translation_id=%d
                       AND ( status = %d OR status = %d )",
					$translation_id,
					ICL_TM_WAITING_FOR_TRANSLATOR,
					ICL_TM_IN_PROGRESS
				),
				ARRAY_N
			);
			if ( ! $rid ) {
				JobLog::add( 'cancel_request_no_active_status', [
					'translation_id' => $translation_id,
				] );
				return;
			}
			$current_row = $wpdb->get_row( $wpdb->prepare( "SELECT job_id, translated, completed_date, editor, editor_job_id FROM {$wpdb->prefix}icl_translate_job WHERE rid=%d AND revision IS NULL ORDER BY job_id DESC", $rid ), ARRAY_A );
			$job_id      = $current_row ? $current_row['job_id'] : null;

			if ( \WPML\TM\Jobs\TranslationRecord::isCompleted( $current_row ) ) {
				if ( empty( $prevstate_before ) ) {
					$this->cancelSupersededJobInATE(
						[
							'job_id'        => (int) $current_row['job_id'],
							'status'        => isset( $status_before ) ? (int) $status_before : null,
							'translated'    => (int) $current_row['translated'],
							'editor'        => isset( $current_row['editor'] ) ? (string) $current_row['editor'] : null,
							'editor_job_id' => isset( $current_row['editor_job_id'] ) ? (int) $current_row['editor_job_id'] : 0,
						],
						(int) $rid,
						null
					);
				}

				$wpdb->update(
					$wpdb->prefix . 'icl_translation_status',
					array( 'status' => ICL_TM_COMPLETE, '_prevstate' => null ),
					array( 'translation_id' => $translation_id )
				);

				JobLog::add( 'cancel_request_restored_completed_record', [
					'translation_id' => $translation_id,
					'rid'            => $rid,
					'job_id'         => $job_id,
					'status_before'  => isset( $status_before ) ? (int) $status_before : null,
				] );

				return;
			}

			JobLog::add( 'cancel_request_started', [
				'translation_id'            => $translation_id,
				'rid'                       => $rid,
				'job_id'                    => $job_id,
				'translator_id'             => $translator_id,
				'status_before'             => isset( $status_before ) ? (int) $status_before : null,
				'remove_translation_record' => $remove_translation_record,
			] );

			if ( isset( $this->settings['notification']['resigned'] )
			     && $this->settings['notification']['resigned'] == ICL_TM_NOTIFICATION_IMMEDIATELY && ! empty( $translator_id ) ) {
				do_action( 'wpml_tm_remove_job_notification', $translator_id, $job_id );
			}

			$jobDeletedRows       = $wpdb->query( $wpdb->prepare( "DELETE FROM {$wpdb->prefix}icl_translate_job WHERE job_id=%d", $job_id ) );
			$translateDeletedRows = $wpdb->query( $wpdb->prepare( "DELETE FROM {$wpdb->prefix}icl_translate WHERE job_id=%d", $job_id ) );

			$max_job_id = \WPML\TM\API\Job\Map::fromRid( $rid );
			if ( $max_job_id ) {
				$wpdb->query( $wpdb->prepare( "UPDATE {$wpdb->prefix}icl_translate_job SET revision = NULL WHERE job_id=%d", $max_job_id ) );
				\WPML\Translation\PreviousStateServiceFactory::create()->revertToPreviousState( $translation_id );

				JobLog::add( 'cancel_request_reverted_to_previous_job', [
					'rid'                          => $rid,
					'job_id'                       => $job_id,
					'max_job_id'                   => $max_job_id,
					'job_table_deleted_rows'       => is_int( $jobDeletedRows ) ? $jobDeletedRows : null,
					'translate_table_deleted_rows' => is_int( $translateDeletedRows ) ? $translateDeletedRows : null,
				] );
			} else {
				$statusDeletedRows = $wpdb->query( $wpdb->prepare( "DELETE FROM {$wpdb->prefix}icl_translation_status WHERE translation_id=%d", $translation_id ) );

				JobLog::add( 'cancel_request_deleted_status', [
					'translation_id'                => $translation_id,
					'rid'                           => $rid,
					'job_id'                        => $job_id,
					'status_table_deleted_rows'     => is_int( $statusDeletedRows ) ? $statusDeletedRows : null,
					'job_table_deleted_rows'        => is_int( $jobDeletedRows ) ? $jobDeletedRows : null,
					'translate_table_deleted_rows'  => is_int( $translateDeletedRows ) ? $translateDeletedRows : null,
				] );
			}

			if ( $remove_translation_record ) {
				$translationsDeleted = $wpdb->query( $wpdb->prepare( "DELETE FROM {$wpdb->prefix}icl_translations WHERE translation_id=%d AND element_id IS NULL", $translation_id ) );
				JobLog::add( 'cancel_request_removed_translation_record', [
					'translation_id'              => $translation_id,
					'icl_translations_deleted'    => is_int( $translationsDeleted ) ? $translationsDeleted : null,
				] );
			}

			icl_cache_clear_preserving_language_names();
		}
	}

	function render_option_writes( $name, $value, $key = '' ) {
		if ( ! defined( 'WPML_ST_FOLDER' ) ) {
			return;
		}
		static $option = false;

		if ( ! $key ) {
			$option = get_option( $name );
			if ( is_object( $option ) ) {
				$option = (array) $option;
			}
		}

		$admin_option_names = get_option( '_icl_admin_option_names', [] );
		if ( ! is_array( $admin_option_names ) ) {
			$admin_option_names = [];
		}

		$es_context = '';

		$context = '';
		$slug    = '';
		foreach ( $admin_option_names as $context => $element ) {
			$found = false;
			foreach ( (array) $element as $slug => $options ) {
				$found = false;
				foreach ( (array) $options as $option_key => $option_value ) {
					$found      = false;
					$es_context = '';
					if ( $option_key == $name ) {
						if ( is_scalar( $option_value ) ) {
							$es_context = 'admin_texts_' . $context . '_' . $slug;
							$found      = true;
						} elseif ( is_array( $option_value ) && is_array( $value ) && ( $option_value == $value ) ) {
							$es_context = 'admin_texts_' . $context . '_' . $slug;
							$found      = true;
						}
					}
					if ( $found ) {
						break;
					}
				}
				if ( $found ) {
					break;
				}
			}
			if ( $found ) {
				break;
			}
		}

		echo '<ul class="icl_tm_admin_options">';
		echo '<li>';

		$context_html = '';
		if ( ! $key ) {
			$context_html = '[' . esc_html( $context ) . ': ' . esc_html( $slug ) . '] ';
		}

		if ( is_scalar( $value ) ) {
			preg_match_all( '#\[([^\]]+)\]#', $key, $matches );

			if ( count( $matches[1] ) > 1 ) {
				$o_value = $option;
				for ( $i = 1; $i < count( $matches[1] ); $i++ ) {
					$o_value = $o_value[ $matches[1][ $i ] ];
				}
				$o_value   = $o_value[ $name ];
				$edit_link = '';
			} else {
				if ( is_scalar( $option ) ) {
					$o_value = $option;
				} elseif ( isset( $option[ $name ] ) ) {
					$o_value = $option[ $name ];
				} else {
					$o_value = '';
				}

				if ( ! $key ) {
					if ( icl_st_is_registered_string( $es_context, $name ) ) {
						/* translators: Link text inside a sentence that opens the screen where the text is translated. It starts in lower case because it sits inside the sentence. Verb, imperative. */
						$edit_link = '[<a href="' . admin_url( 'admin.php?page=' . WPML_TM_FOLDER . '/menu/main.php&tab=strings&context=' . esc_html( $es_context ) ) . '">' . esc_html__( 'translate', 'sitepress' ) . '</a>]';
					} else {
						/* translators: Note shown in place of a translation when the text was never handed to WPML, so there is nothing to translate. It starts in lower case as the code shows it. */
						$edit_link = '<div class="updated below-h2">' . esc_html__( 'string not registered', 'sitepress' ) . '</div>';
					}
				} else {
					$edit_link = '';
				}
			}

			if ( false !== strpos( $name, '*' ) ) {
				/* translators: Shown in place of a value when the field holds several different values at once. It stands between braces so the reader sees it is not a value of its own. */
				$o_value = '<span style="color:#bbb">{{ ' . esc_html__( 'Multiple options', 'sitepress' ) . ' }}</span>';
			} else {
				$o_value = esc_html( $o_value );
				if ( strlen( $o_value ) > 200 ) {
					$o_value = substr( $o_value, 0, 200 ) . ' ...';
				}
			}
			echo $context_html . esc_html( $name ) . ': <i>' . $o_value . '</i> ' . $edit_link;
		} else {
			/* translators: Link text inside a sentence that opens the screen where the text is translated. It starts in lower case because it sits inside the sentence. Verb, imperative. */
			$edit_link = '[<a href="' . admin_url( 'admin.php?page=' . WPML_TM_FOLDER . '/menu/main.php&tab=strings&context=' . esc_html( $es_context ) ) . '">' . esc_html__( 'translate', 'sitepress' ) . '</a>]';
			echo '<strong>' . $context_html . $name . '</strong> ' . $edit_link;
			if ( ! icl_st_is_registered_string( $es_context, $name ) ) {
				/* translators: Notice shown above a list of texts when some of them were never handed to WPML, so they cannot be translated. It starts in lower case as the code shows it. */
				$notice = '<div class="updated below-h2">' . esc_html__( 'some strings might be not registered', 'sitepress' ) . '</div>';
				echo $notice;
			}

			foreach ( (array) $value as $o_key => $o_value ) {
				$this->render_option_writes( $o_key, $o_value, $o_key . '[' . $name . ']' );
			}

			$option = false;
		}
		echo '</li>';
		echo '</ul>';
	}

	public static function current_service_info( $info = array() ) {
		return TranslationProxy::get_current_service_info( $info );
	}

	static function set_page_url( $post_id ) {

		global $wpdb;

		if ( wpml_get_setting_filter( false, 'translated_document_page_url' ) === 'copy-encoded' ) {

			$post = $post_id
				? $wpdb->get_row( $wpdb->prepare( "SELECT post_type FROM {$wpdb->posts} WHERE ID=%d", $post_id ) )
				: null;
			if ( ! $post || empty( $post->post_type ) ) {
				return;
			}
			$translation_row = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$wpdb->prefix}icl_translations WHERE element_id=%d AND element_type=%s", $post_id, 'post_' . $post->post_type ) );
			if ( ! $translation_row || empty( $translation_row->language_code ) ) {
				return;
			}

			$encode_url = $wpdb->get_var( $wpdb->prepare( "SELECT encode_url FROM {$wpdb->prefix}icl_languages WHERE code=%s", $translation_row->language_code ) );

			if ( $encode_url ) {

				$trid             = $translation_row->trid;
				$original_post_id = $wpdb->get_var( $wpdb->prepare( "SELECT element_id FROM {$wpdb->prefix}icl_translations WHERE trid=%d AND source_language_code IS NULL", $trid ) );
				$post_name_original      = get_post_field( 'post_name', $original_post_id );
				$post_parent_original    = get_post_field( 'post_parent', $original_post_id );
				$post_parent_translation = $post_parent_original
					? apply_filters( 'wpml_object_id', $post_parent_original, $post->post_type, false, $translation_row->language_code )
					: 0;

				$post_name_to_be = $post_name_original;
				$incr            = 1;
				do {
					$taken = $wpdb->get_var(
						$wpdb->prepare(
							"
						SELECT ID FROM {$wpdb->posts} p
						JOIN {$wpdb->prefix}icl_translations t ON p.ID = t.element_id
						WHERE ID <> %d AND t.element_type = %s AND t.language_code = %s AND p.post_name = %s AND p.post_parent = %d
						",
							$post_id,
							'post_' . $post->post_type,
							$translation_row->language_code,
							$post_name_to_be,
							$post_parent_translation
						)
					);
					if ( $taken ) {
						$incr ++;
						$post_name_to_be = $post_name_original . '-' . $incr;
					} else {
						$taken = false;
					}
				} while ( $taken == true );
				$post_to_update = new WPML_WP_Post( $wpdb, $post_id );
				$post_to_update->update( array( 'post_name' => $post_name_to_be ), true );
			}
		}
	}

	public function icl_insert_post( $postarr, $lang ) {
		$create_post_helper = wpml_get_create_post_helper();

		return $create_post_helper->insert_post( $postarr, $lang );
	}

	private function add_missing_language_to_posts( $post_types ) {
		global $wpdb;

		if ( ! $post_types ) {
			return;
		}

		$posts = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT ID, post_type, post_status FROM {$wpdb->posts} WHERE post_type IN (" . implode( ', ', array_fill( 0, count( $post_types ), '%s' ) ) . ')',
				$post_types
			)
		);
		if ( $posts ) {
			foreach ( $posts as $post ) {
				$this->add_missing_language_to_post( $post );
			}
		}
	}

	private function add_missing_language_to_post( $post ) {
		global $sitepress, $wpdb;

		$query_results = $wpdb->get_row(
			$wpdb->prepare( "SELECT translation_id, language_code FROM {$wpdb->prefix}icl_translations WHERE element_type=%s AND element_id=%d", array( 'post_' . $post->post_type, $post->ID ) )
		);

		if ( ! is_null( $query_results ) ) {
			$translation_id = $query_results->translation_id;
			$language_code  = $query_results->language_code;
		} else {
			$translation_id = null;
			$language_code  = null;
		}

		$urls             = $sitepress->get_setting( 'urls' );
		$is_root_page     = $urls && isset( $urls['root_page'] ) && $urls['root_page'] == $post->ID;
		$default_language = $sitepress->get_default_language();

		if ( ! $translation_id && ! $is_root_page && ! in_array( $post->post_status, array( 'auto-draft' ) ) ) {
			$sitepress->set_element_language_details( $post->ID, 'post_' . $post->post_type, null, $default_language, null, true, true );
		} elseif ( $translation_id && $is_root_page ) {
			$trid = $sitepress->get_element_trid( $post->ID, 'post_' . $post->post_type );
			if ( $trid ) {
				$sitepress->delete_element_translation( $trid, 'post_' . $post->post_type );
			}
		} elseif ( $translation_id && ! $language_code && $default_language ) {
			$where = array( 'translation_id' => $translation_id );
			$data  = array( 'language_code' => $default_language );
			$wpdb->update( $wpdb->prefix . 'icl_translations', $data, $where );

			do_action(
				'wpml_translation_update',
				array(
					'type'           => 'update',
					'element_id'     => $post->ID,
					'element_type'   => 'post_' . $post->post_type,
					'translation_id' => $translation_id,
					'context'        => 'post',
				)
			);
		}
	}

	private function add_missing_language_to_taxonomies( $post_types ) {
		global $sitepress, $wpdb;
		$taxonomy_types = array();
		foreach ( $post_types as $post_type ) {
			$taxonomy_types = array_merge( $sitepress->get_translatable_taxonomies( true, $post_type ), $taxonomy_types );
		}
		$taxonomy_types = array_unique( $taxonomy_types );
		if ( ! $taxonomy_types ) {
			return;
		}

		$taxonomies = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT taxonomy, term_taxonomy_id FROM {$wpdb->term_taxonomy} WHERE taxonomy IN (" . implode( ', ', array_fill( 0, count( $taxonomy_types ), '%s' ) ) . ')',
				$taxonomy_types
			)
		);
		if ( $taxonomies ) {
			foreach ( $taxonomies as $taxonomy ) {
				$this->add_missing_language_to_taxonomy( $taxonomy );
			}
		}
	}

	private function add_missing_language_to_taxonomy( $taxonomy ) {
		global $sitepress, $wpdb;
		$tid = $wpdb->get_var(
			$wpdb->prepare( "SELECT translation_id FROM {$wpdb->prefix}icl_translations WHERE element_type=%s AND element_id=%d", 'tax_' . $taxonomy->taxonomy, $taxonomy->term_taxonomy_id )
		);
		if ( ! $tid ) {
			$sitepress->set_element_language_details( $taxonomy->term_taxonomy_id, 'tax_' . $taxonomy->taxonomy, null, $sitepress->get_default_language(), null, true, true );
		}
	}

	public function add_missing_language_information() {
		global $sitepress;
		$translatable_documents = array_keys( $sitepress->get_translatable_documents( false ) );

		if ( $translatable_documents ) {
			$lock = make( AdvisoryLockFactory::class )->create( 'add_missing_language_information' );

			if ( $lock->acquire( 0 ) ) {
				try {
					$this->add_missing_language_to_posts( $translatable_documents );
					$this->add_missing_language_to_taxonomies( $translatable_documents );
				} finally {
					$lock->release();
				}

				return true;
			}

			return false;
		}

		return true;
	}

	public static function get_job_status_string( $status_id, $needs_update = false ) {
		$job_status_text = self::status2text( $status_id );
		if ( $needs_update ) {
			/* translators: Added after the state of a translation job to say the original changed since, so the translation has to be gone over again. It follows the state, which is why it starts with a space and a dash. */
			$job_status_text .= __( ' - (needs update)', 'sitepress' );
		}

		return $job_status_text;
	}

	public function get_element_type( $trid ) {
		global $wpdb;

		return $wpdb->get_var(
			$wpdb->prepare( "SELECT element_type FROM {$wpdb->prefix}icl_translations WHERE trid=%d LIMIT 0,1", $trid )
		);
	}

	public function is_external_type( $type ) {
		return apply_filters( 'wpml_is_external', false, $type );
	}

	public function get_post( $post_id, $element_type_prefix ) {
		if ( ! $this->is_external_type( $element_type_prefix ) ) {
			return get_post( $post_id );
		}

		$item = apply_filters( 'wpml_get_translatable_item', null, $post_id, $element_type_prefix );

		if ( ! $item && 'package' === $element_type_prefix ) {
			$item = self::get_string_package( $post_id );
		}

		return $item ?: null;
	}

	public static function get_string_package( $package_id ) {
		$package = apply_filters( 'wpml_st_get_string_package', null, (int) $package_id );

		return is_object( $package ) && ! empty( $package->ID ) ? $package : null;
	}

	private function init_comments_synchronization() {
		if ( wpml_get_setting_filter( null, 'sync_comments_on_duplicates' ) ) {
			add_action( 'delete_comment', array( $this, 'duplication_delete_comment' ) );
			add_action( 'edit_comment', array( $this, 'duplication_edit_comment' ) );
			add_action( 'wp_set_comment_status', array( $this, 'duplication_status_comment' ), 10, 2 );
			add_action( 'wp_insert_comment', array( $this, 'duplication_insert_comment' ), 100 );
		}
	}

	private function init_default_settings() {
		if ( ! isset( $this->settings_data[ $this->get_translation_setting_name( 'custom-fields' ) ] ) ) {
			$this->settings_data[ $this->get_translation_setting_name( 'custom-fields' ) ] = array();
		}

		if ( ! isset( $this->settings_data[ $this->get_readonly_translation_setting_name( 'custom-fields' ) ] ) ) {
			$this->settings_data[ $this->get_readonly_translation_setting_name( 'custom-fields' ) ] = array();
		}

		if ( ! isset( $this->settings_data[ $this->get_custom_translation_setting_name( 'custom-fields' ) ] ) ) {
			$this->settings_data[ $this->get_custom_translation_setting_name( 'custom-fields' ) ] = array();
		}

		if ( ! isset( $this->settings_data[ $this->get_custom_readonly_translation_setting_name( 'custom-fields' ) ] ) ) {
			$this->settings_data[ $this->get_custom_readonly_translation_setting_name( 'custom-fields' ) ] = array();
		}

		if ( ! isset( $this->settings_data['doc_translation_method'] ) ) {
			$this->settings_data['doc_translation_method'] = ICL_TM_TMETHOD_MANUAL;
		}
	}

	public function init_current_translator() {
		if ( did_action( 'init' ) ) {
			global $current_user;
			$current_translator = null;
			$user               = false;
			if ( isset( $current_user->ID ) ) {
				$user = new WP_User( $current_user->ID );
			}

			if ( $user && isset( $user->data ) && $user->data ) {
				$current_translator               = new WPML_Translator();
				$current_translator->ID           = $current_user->ID;
				$current_translator->user_login   = isset( $user->data->user_login ) ? $user->data->user_login : false;
				$current_translator->display_name = isset( $user->data->display_name ) ? $user->data->display_name : $current_translator->user_login;
				$current_translator               = $this->init_translator_language_pairs( $current_user, $current_translator );
			}

			$this->current_translator = $current_translator;
		}
	}

	public function get_translation_setting_name( $section ) {
		return $this->get_sanitized_translation_setting_section( $section ) . '_translation';
	}

	public function get_custom_translation_setting_name( $section ) {
		return $this->get_translation_setting_name( $section ) . '_custom';
	}

	public function get_custom_readonly_translation_setting_name( $section ) {
		return $this->get_custom_translation_setting_name( $section ) . '_readonly';
	}

	public function get_readonly_translation_setting_name( $section ) {
		return $this->get_sanitized_translation_setting_section( $section ) . '_readonly_config';
	}

	private function get_sanitized_translation_setting_section( $section ) {
		$section = preg_replace( '/-/', '_', $section );
		return $section;
	}

	private function assign_translation_job( $job_id, $translator_id, $service = 'local', $type = 'post' ) {
		do_action( 'wpml_tm_assign_translation_job', $job_id, $translator_id, $service, $type );

		return true;
	}

	private function initial_translation_states( $table ) {
		global $wpdb;

		if ( $wpdb->postmeta === $table ) {
			$custom_keys = $wpdb->get_col( "SELECT DISTINCT meta_key FROM {$wpdb->postmeta}" );
		} else {
			$custom_keys = $wpdb->get_col( "SELECT DISTINCT meta_key FROM {$wpdb->termmeta}" );
		}

		return $custom_keys;
	}

	private function persist_notification_settings( $data ) {
		foreach (
			array(
				'new-job',
				'include_xliff',
				'job_limits',
				'resigned',
				WPML_TM_Emails_Settings::MANAGER_RESIGNED,
				WPML_TM_Emails_Settings::SERVICE_JOB_UPDATE,
				'completed_frequency',
				'overdue',
				'overdue_offset',
			) as $setting
		) {
			if ( ! Obj::hasPath( [ 'notification', $setting ], $data ) ) {
				$data['notification'][ $setting ] = ICL_TM_NOTIFICATION_NONE;
			}
		}

		$this->settings['notification'] = $data['notification'];
		$this->save_settings();
		do_action( 'wpml_tm_notification_settings_saved', $this->settings['notification'] );
	}

	public function ajax_save_notification_settings() {
		if ( \WPML\Setup\Initializer::rejectSettingsMutationAjax() ) {
			return;
		}

		check_ajax_referer( 'save_notification_settings_nonce', 'save_notification_settings_nonce' );

		if ( ! current_user_can( 'manage_translations' ) ) {
			wp_send_json_error( array( 'message' => __( 'You are not allowed to change these settings.', 'sitepress' ) ) );
		}

		$notification = array();
		if ( isset( $_POST['notification'] ) && is_array( $_POST['notification'] ) ) {
			$notification = array_map( 'sanitize_text_field', wp_unslash( $_POST['notification'] ) );
		}

		$this->persist_notification_settings( array( 'notification' => $notification ) );

		/* translators: Notice shown after the translation settings of the user are saved. */
		wp_send_json_success( array( 'message' => __( 'Preferences saved.', 'sitepress' ) ) );
	}

	public function get_init_priority() {
		return self::INIT_PRIORITY;
	}

	private function maybe_update_prev_state( int $translationId, array $translation_status_data ) {
		if ( $translation_status_data) {
			$updated = \WPML\Translation\PreviousStateServiceFactory::create()->update( $translationId, $translation_status_data );
			JobLog::add( 'Previous translation status was ' . ( $updated ? 'updated' : 'not updated' ) );
		}
	}

	private function is_unlocked_type( $type, $unlocked_options ) {
		return isset( $unlocked_options[ $type ] ) && $unlocked_options[ $type ];
	}

	private function capture_translation_editor_switched_event( $previous_editor, $new_editor ) {
		if ( ! \WPML\PostHog\State\PostHogState::isEnabled() ) {
			return;
		}

		$get_editor_name = function( $editor_value ) {
			if ( (string) $editor_value === ICL_TM_TMETHOD_ATE ) {
				return 'ATE';
			}
			return 'CTE';
		};

		$event_props = array(
			'previous_editor' => $get_editor_name( $previous_editor ),
			'new_editor'      => $get_editor_name( $new_editor ),
		);

		\WPML\PostHog\Event\CaptureEvent::capture(
			( new EventInstanceService() )->getTranslationEditorSwitchedEvent( $event_props )
		);
	}


	private function triggerCdtStatsResend() {
		$editorSwitchService = \WPML\ContentStats\EditorSwitchServiceFactory::create();
		$editorSwitchService->handleEditorSwitch();
	}

	protected function findPriorInFlightJobForRid( $rid ) {
		global $wpdb;

		$row = $wpdb->get_row( $wpdb->prepare(
			"SELECT j.job_id,
			        j.translated,
			        j.editor,
			        j.editor_job_id,
			        j.wpml_words_to_translate_count,
			        j.wpml_automatic_translation_costs,
			        s.status,
			        UNIX_TIMESTAMP() - UNIX_TIMESTAMP(s.timestamp) AS age_seconds
			 FROM {$wpdb->prefix}icl_translate_job j
			 LEFT JOIN {$wpdb->prefix}icl_translation_status s ON s.rid = j.rid
			 WHERE j.rid = %d
			   AND j.translated = 0
			 ORDER BY j.job_id DESC
			 LIMIT 1",
			(int) $rid
		), ARRAY_A );

		if ( ! is_array( $row ) ) {
			return null;
		}

		return [
			'job_id'                      => (int) $row['job_id'],
			'status'                      => isset( $row['status'] ) ? (int) $row['status'] : null,
			'translated'                  => (int) $row['translated'],
			'age_seconds'                 => isset( $row['age_seconds'] ) ? (int) $row['age_seconds'] : 0,
			'editor'                      => isset( $row['editor'] ) ? (string) $row['editor'] : null,
			'editor_job_id'               => isset( $row['editor_job_id'] ) ? (int) $row['editor_job_id'] : 0,
			'words_to_translate_count'    => isset( $row['wpml_words_to_translate_count'] )
				? (int) $row['wpml_words_to_translate_count']
				: null,
			'automatic_translation_costs' => isset( $row['wpml_automatic_translation_costs'] )
				? (int) $row['wpml_automatic_translation_costs']
				: null,
		];
	}

	private function forget_unconfirmed_create( $priorJob ) {
		if ( is_array( $priorJob ) && ! empty( $priorJob['job_id'] ) ) {
			delete_option( \WPML\TM\ATE\JobCreation\AteCreateReconciler::UNCONFIRMED_OPTION_PREFIX . (int) $priorJob['job_id'] );
			\WPML\TM\ATE\JobCreation\AteCreateReconciler::clearUnreadable( [ (int) $priorJob['job_id'] ] );
		}
	}

	protected function cancelSupersededJobInATE( $priorJob, $rid, $sendFrom ) {
		if ( Jobs::SENT_AUTOMATICALLY === $sendFrom ) {
			return;
		}

		if ( ! is_array( $priorJob ) ) {
			return;
		}

		if ( WPML_TM_Editors::ATE !== $priorJob['editor'] || $priorJob['editor_job_id'] <= 0 ) {
			return;
		}

		if ( isset( $this->supersededAteJobIdsAnnounced[ $priorJob['editor_job_id'] ] ) ) {
			return;
		}
		$this->supersededAteJobIdsAnnounced[ $priorJob['editor_job_id'] ] = true;

		$jobEntity = new WPML_TM_Post_Job_Entity(
			(int) $rid,
			WPML_TM_Job_Entity::POST_TYPE,
			0,
			new WPML_TM_Jobs_Batch( 0, '' ),
			isset( $priorJob['status'] ) ? (int) $priorJob['status'] : ICL_TM_IN_PROGRESS,
			[]
		);
		$jobEntity->set_translate_job_id( (int) $priorJob['job_id'] );
		$jobEntity->set_translation_service( 'local' );
		$jobEntity->set_editor( WPML_TM_Editors::ATE );
		$jobEntity->set_editor_job_id( (int) $priorJob['editor_job_id'] );
		$jobEntity->set_words_to_translate_count(
			array_key_exists( 'words_to_translate_count', $priorJob ) ? $priorJob['words_to_translate_count'] : null
		);
		$jobEntity->set_automatic_translation_costs(
			array_key_exists( 'automatic_translation_costs', $priorJob ) ? $priorJob['automatic_translation_costs'] : null
		);

		do_action( 'wpml_tm_job_cancelled', $jobEntity, WPML_TM_ATE_API::CANCEL_REASON_SUPERSEDED );
	}

}
