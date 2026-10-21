<?php

use WPML\FP\Obj;
use WPML\LIB\WP\Nonce;
use WPML\LIB\WP\User;
use WPML\Media\Option;
use WPML\MediaTranslation\PostWithMediaFiles;
use WPML\Core\BackgroundTask\Service\BackgroundTaskService;
use function WPML\Container\make;
use WPML\Records\Translations as TranslationRecords;
use WPML\TM\API\Jobs;

class WPML_Media_Attachments_Duplication {

	const WPML_MEDIA_PROCESSED_META_KEY = 'wpml_media_processed';

	const PREPARE_TRANSLATION_EDITOR_MEDIA_ACTION = 'wpml_media_prepare_translation_editor_media';
	const CLEANUP_DUPLICATED_MEDIA_ACTION         = 'wpml_media_cleanup_duplicated_media';

	const PREPARE_DUPLICATED    = 'duplicated';
	const PREPARE_NOTHING_TO_DO = 'nothing-to-do';
	const PREPARE_DENIED        = 'denied';

	const BATCH_DUPLICATE_CURSOR_OPTION = '_wpml_media_batch_duplicate_cursor';
	const BATCH_DUPLICATE_LEFT_OPTION   = '_wpml_media_batch_duplicate_left';
	const BATCH_FEATURED_CURSOR_OPTION  = '_wpml_media_batch_featured_cursor';
	const BATCH_FEATURED_MAX_OPTION     = '_wpml_media_batch_featured_max';

	private $attachments_model;

	private $sitepress;

	private $wpdb;

	private $language_resolution;

	private $post_media_factory;

	private $background_task_service;

	private $original_thumbnail_ids = array();

	private $save_post_queue = [];

	private $translated_posts = [];

	public function __construct(
		SitePress $sitepress,
		WPML_Model_Attachments $attachments_model,
		wpdb $wpdb,
		WPML_Language_Resolution $language_resolution,
		\WPML\MediaTranslation\PostWithMediaFilesFactory $post_media_factory,
		BackgroundTaskService $background_task_service
	) {
		$this->sitepress               = $sitepress;
		$this->attachments_model       = $attachments_model;
		$this->post_media_factory      = $post_media_factory;
		$this->wpdb                    = $wpdb;
		$this->language_resolution     = $language_resolution;
		$this->post_media_factory      = $post_media_factory;
		$this->background_task_service = $background_task_service;
	}

	public function add_hooks() {
		if ( ! isset( $_GET['import'] ) || $_GET['import'] !== 'wordpress' ) {
			add_action( 'add_attachment', array( $this, 'save_attachment_actions' ) );
			add_action( 'add_attachment', array( $this, 'save_translated_attachments' ) );
			add_filter( 'wp_generate_attachment_metadata', array( $this, 'wp_generate_attachment_metadata' ), 10, 2 );
		}

		$active_languages = $this->language_resolution->get_active_language_codes();

		if ( $this->is_admin_or_xmlrpc() && 1 < count( $active_languages ) ) {
			add_action( 'edit_attachment', array( $this, 'save_attachment_actions' ) );
			add_action( 'icl_make_duplicate', array( $this, 'make_duplicate' ), 10, 4 );
		}

		$this->add_postmeta_hooks();

		add_action( 'save_post', array( $this, 'save_post_actions' ), 100, 2 );
		if ( Option::shouldHandleMediaAuto() ) {
			add_action( 'wp_after_insert_post', array( $this, 'extract_media_ids_from_post_content_and_meta' ), 100, 3 );
			add_action( 'woocommerce_after_product_object_save', array( $this, 'woocommerce_extract_media_ids_from_post_content_and_meta' ), 100, 2 );
			add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_media_screen_commands' ) );
			\WPML\Request\Adapter\Ajax::register( self::PREPARE_TRANSLATION_EDITOR_MEDIA_ACTION, \WPML\Request\Policy\Policy::capability( [ 'edit_posts', 'translate', 'manage_translations' ], \WPML\Request\Policy\Authenticity::actionNonce( self::PREPARE_TRANSLATION_EDITOR_MEDIA_ACTION, 'nonce' ) ), array( $this, 'ajax_prepare_translation_editor_media' ), 10, 0 );
			\WPML\Request\Adapter\Ajax::register( self::CLEANUP_DUPLICATED_MEDIA_ACTION, \WPML\Request\Policy\Policy::capability( 'upload_files', \WPML\Request\Policy\Authenticity::actionNonce( self::CLEANUP_DUPLICATED_MEDIA_ACTION, 'nonce' ) ), array( $this, 'ajax_cleanup_duplicated_media' ), 10, 0 );
		}
		add_action( 'before_delete_post', array( $this, 'delete_post_media_usages' ), 10, 1 );
		add_action( 'wpml_pro_translation_completed', array( $this, 'sync_on_translation_complete' ), 10, 3 );

		\WPML\Request\Adapter\Ajax::register( 'wpml_media_translate_media', \WPML\Request\Policy\Policy::capability( [ 'wpml_manage_media_translation', 'manage_translations' ], \WPML\Request\Policy\Authenticity::actionNonce( 'wpml_media_translate_media', 'nonce' ) ), array( $this, 'ajax_batch_translate_media' ), 10, 0 );
		\WPML\Request\Adapter\Ajax::register( 'wpml_media_duplicate_media', \WPML\Request\Policy\Policy::capability( [ 'wpml_manage_media_translation', 'manage_translations' ], \WPML\Request\Policy\Authenticity::actionNonce( 'wpml_media_duplicate_media', 'nonce' ) ), array( $this, 'ajax_batch_duplicate_media' ), 10, 0 );
		\WPML\Request\Adapter\Ajax::register( 'wpml_media_duplicate_featured_images', \WPML\Request\Policy\Policy::capability( [ 'wpml_manage_media_translation', 'manage_translations' ], \WPML\Request\Policy\Authenticity::actionNonce( 'wpml_media_duplicate_featured_images', 'nonce' ) ), array( $this, 'ajax_batch_duplicate_featured_images' ), 10, 0 );

		\WPML\Request\Adapter\Ajax::register( 'wpml_media_mark_processed', \WPML\Request\Policy\Policy::capability( [ 'wpml_manage_media_translation', 'manage_translations' ], \WPML\Request\Policy\Authenticity::actionNonce( 'wpml_media_mark_processed', 'nonce' ) ), array( $this, 'ajax_batch_mark_processed' ), 10, 0 );
		\WPML\Request\Adapter\Ajax::register( 'wpml_media_scan_prepare', \WPML\Request\Policy\Policy::capability( [ 'wpml_manage_media_translation', 'manage_translations' ], \WPML\Request\Policy\Authenticity::actionNonce( 'wpml_media_scan_prepare', 'nonce' ) ), array( $this, 'ajax_batch_scan_prepare' ), 10, 0 );
		\WPML\Request\Adapter\Ajax::register( 'wpml_media_save_should_handle_media_auto_setting', \WPML\Request\Policy\Policy::capability( [ 'wpml_manage_media_translation', 'manage_translations' ], \WPML\Request\Policy\Authenticity::actionNonce( 'wpml_media_save_should_handle_media_auto_setting', 'nonce' ) ), array( $this, 'ajax_save_should_handle_media_auto_setting' ), 10, 0 );

		\WPML\Request\Adapter\Ajax::register( 'wpml_media_set_content_prepare', \WPML\Request\Policy\Policy::capability( [ 'wpml_manage_media_translation', 'manage_translations' ], \WPML\Request\Policy\Authenticity::actionNonce( 'wpml_media_set_content_prepare', 'nonce' ) ), array( $this, 'set_content_defaults_prepare' ) );
		add_action( 'wpml_loaded', array( $this, 'add_settings_hooks' ) );
		add_action( 'admin_notices', array( $this, 'maybe_render_admin_notices' ), PHP_INT_MAX );

		add_action( 'shutdown', [ $this, 'maybe_translate_medias_in_posts' ], 40 );

		if (
			Option::shouldShowHandleMediaAutoNotice30DaysAfterUpgrade() ||
			$this->should_show_admin_notice_for_elementor_on_mt_homepage()
		) {
			add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_scripts' ) );
		}

		if ( Option::shouldShowHandleMediaAutoNotice30DaysAfterUpgrade() ) {
			\WPML\Request\Adapter\Ajax::register( 'wpml_media_dismiss_should_handle_media_auto_notice', \WPML\Request\Policy\Policy::capability( [ 'wpml_manage_media_translation', 'manage_translations' ], \WPML\Request\Policy\Authenticity::actionNonce( 'wpml_media_dismiss_should_handle_media_auto_notice', 'nonce' ) ), array( $this, 'ajax_dismiss_should_handle_media_auto_notice' ), 10, 0 );
		}

		\WPML\Request\Adapter\Ajax::register( 'wpml_media_dismiss_admin_notice_for_elementor_on_mt_homepage_notice', \WPML\Request\Policy\Policy::capability( [ 'wpml_manage_media_translation', 'manage_translations' ], \WPML\Request\Policy\Authenticity::actionNonce( 'wpml_media_dismiss_admin_notice_for_elementor_on_mt_homepage_notice', 'nonce' ) ), array( $this, 'ajax_dismiss_admin_notice_for_elementor_on_mt_homepage_notice' ), 10, 0 );
	}

	public function enqueue_scripts() {
		$handle = 'wpml-media-admin-notices';

		wp_register_script(
			$handle,
			ICL_PLUGIN_URL . '/res/js/media/admin-notices.js',
			[ 'sitepress' ],
			ICL_SITEPRESS_SCRIPT_VERSION,
			true
		);

		wp_localize_script(
			$handle,
			'wpml_media_admin_notices_data',
			[
				'nonce_wpml_media_dismiss_should_handle_media_auto_notice' => wp_create_nonce( 'wpml_media_dismiss_should_handle_media_auto_notice' ),
				'nonce_wpml_media_dismiss_admin_notice_for_elementor_on_mt_homepage_notice' => wp_create_nonce( 'wpml_media_dismiss_admin_notice_for_elementor_on_mt_homepage_notice' ),
			]
		);

		wp_enqueue_script( $handle );
	}

	public function add_settings_hooks() {
		if ( User::getCurrent() && ( User::canManageTranslations() || User::hasCap( 'wpml_manage_media_translation' ) )
		) {
			\WPML\Request\Adapter\Ajax::register( 'wpml_media_set_content_defaults', \WPML\Request\Policy\Policy::capability( [ 'wpml_manage_media_translation', 'manage_translations' ], \WPML\Request\Policy\Authenticity::actionNonce( 'wpml_media_set_content_defaults', 'nonce' ) ), array($this, 'wpml_media_set_content_defaults') );
		}
	}

	private function add_postmeta_hooks() {
		add_action( 'update_postmeta', [ $this, 'record_original_thumbnail_ids_and_sync' ], 10, 4 );
		add_action( 'delete_post_meta', [ $this, 'record_original_thumbnail_ids_and_sync' ], 10, 4 );
	}

	private function withPostMetaFiltersDisabled( callable $callback ) {
		$filter = [ $this, 'record_original_thumbnail_ids_and_sync' ];

		$shouldRestoreFilters = remove_action( 'update_postmeta', $filter, 10 )
			&& remove_action( 'delete_post_meta', $filter, 10 );

		$callback();

		if ( $shouldRestoreFilters ) {
			$this->add_postmeta_hooks();
		}
	}

	private function is_admin_or_xmlrpc() {
		$is_admin  = is_admin();
		$is_xmlrpc = defined( 'XMLRPC_REQUEST' ) && XMLRPC_REQUEST;
		return $is_admin || $is_xmlrpc;
	}

	public function save_attachment_actions( $post_id, $override_always_translate_media = false, $target_languages = null ) {
		if ( $this->is_uploading_media_on_wpml_media_screen() ) {
			return;
		}

		if ( $this->is_uploading_plugin_or_theme() && get_post_type( $post_id ) == 'attachment' ) {
			return;
		}

		$media_language = $this->sitepress->get_language_for_element( $post_id, 'post_attachment' );
		$trid           = false;
		if ( ! empty( $media_language ) ) {
			$trid = $this->sitepress->get_element_trid( $post_id, 'post_attachment' );
		}
		if ( empty( $media_language ) ) {
			$wpdb = $this->wpdb;
			$parent_post = $wpdb->get_row(
				$wpdb->prepare( "SELECT p2.ID, p2.post_type FROM {$wpdb->posts} p1 JOIN {$wpdb->posts} p2 ON p1.post_parent = p2.ID WHERE p1.ID=%d", $post_id )
			);

			if ( $parent_post ) {
				$media_language = $this->sitepress->get_language_for_element( $parent_post->ID, 'post_' . $parent_post->post_type );
			}

			if ( empty( $media_language ) ) {
				$media_language = $this->sitepress->get_admin_language_cookie();
			}
			if ( empty( $media_language ) ) {
				$media_language = $this->sitepress->get_default_language();
			}
		}
		if ( ! empty( $media_language ) ) {
			$this->sitepress->set_element_language_details( $post_id, 'post_attachment', $trid, $media_language );

			$this->save_translated_attachments( $post_id, $override_always_translate_media, $target_languages );
			$this->update_attachment_metadata( $post_id );
		}
	}

	private function is_uploading_media_on_wpml_media_screen() {
		return isset( $_POST['action'] ) && 'wpml_media_save_translation' === $_POST['action'];
	}

	public function wp_generate_attachment_metadata( $metadata, $attachment_id ) {
		if ( $this->is_uploading_media_on_wpml_media_screen() ) {
			return $metadata;
		}

		$this->synchronize_attachment_metadata( $metadata, $attachment_id );

		return $metadata;
	}

	private function update_attachment_metadata( $source_attachment_id ) {
		$original_element_id = $this->sitepress->get_original_element_id( $source_attachment_id, 'post_attachment', false, false, true );
		if ( $original_element_id ) {
			$metadata = wp_get_attachment_metadata( $original_element_id );
			$this->synchronize_attachment_metadata( $metadata, $original_element_id );
		}
	}

	private function synchronize_attachment_metadata( $metadata, $attachment_id ) {
		$trid = $this->sitepress->get_element_trid( $attachment_id, 'post_attachment' );

		if ( $trid ) {
			$attached_file = get_post_meta( $attachment_id, '_wp_attached_file', true );
			$translations  = $this->sitepress->get_element_translations( $trid, 'post_attachment', true, true, true );
			foreach ( $translations as $translation ) {
				if ( $translation->element_id != $attachment_id ) {
					$this->update_attachment_texts( $translation );

					do_action( 'wpml_after_update_attachment_texts', $attachment_id, $translation );

					$attachment_meta_data = get_post_meta( $translation->element_id, '_wp_attachment_metadata' );
					if ( isset( $attachment_meta_data[0]['file'] ) ) {
						continue;
					}

					if ( $attached_file && get_post_meta( $translation->element_id, '_wp_attached_file', true ) !== $attached_file ) {
						continue;
					}

					$translation_metadata = $metadata;

					if ( isset( $attachment_meta_data[0]['sizes'] ) ) {
						$translation_metadata['sizes'] = $attachment_meta_data[0]['sizes'];
					}

					update_post_meta( $translation->element_id, '_wp_attachment_metadata', $translation_metadata );
					$mime_type = get_post_mime_type( $attachment_id );
					if ( $mime_type ) {
						$this->wpdb->update( $this->wpdb->posts, array( 'post_mime_type' => $mime_type ), array( 'ID' => $translation->element_id ) );
					}
				}
			}
		}
	}

	private function update_attachment_texts( $translation ) {
		if ( ! isset( $_POST['changes'] ) ) {
			return;
		}

		$changes = array( 'ID' => $translation->element_id );

		foreach ( $_POST['changes'] as $key => $value ) {
			switch ( $key ) {
				case 'caption':
					$post = get_post( $translation->element_id );
					if ( ! $post->post_excerpt ) {
						$changes['post_excerpt'] = $value;
					}

					break;

				case 'description':
					$translated_attachment = get_post( $translation->element_id );
					if ( ! $translated_attachment->post_content ) {
						$changes['post_content'] = $value;
					}

					break;

				case 'alt':
					if ( ! get_post_meta( $translation->element_id, '_wp_attachment_image_alt', true ) ) {
						update_post_meta( $translation->element_id, '_wp_attachment_image_alt', $value );
					}

					break;
			}
		}

		remove_action( 'edit_attachment', array( $this, 'save_attachment_actions' ) );
		wp_update_post( $changes );
		add_action( 'edit_attachment', array( $this, 'save_attachment_actions' ) );
	}

	public function save_translated_attachments( $post_id, $override_always_translate_media = false, $target_languages = null ) {
		if ( $this->is_uploading_plugin_or_theme() && get_post_type( $post_id ) == 'attachment' ) {
			return;
		}

		$language_details = $this->sitepress->get_element_language_details( $post_id, 'post_attachment' );
		if ( isset( $language_details->language_code ) ) {
			$this->translate_attachments( $post_id, $language_details->language_code, $override_always_translate_media, $target_languages );
		}
	}

	private function translate_attachments( $attachment_id, $source_language, $override_always_translate_media = false, $target_languages = null ) {
		if ( ! $source_language ) {
			return;
		}

		if ( $override_always_translate_media || ( Obj::prop( 'always_translate_media', Option::getNewContentSettings() ) && ! Option::shouldHandleMediaAuto() ) ) {

			global $sitepress;

			$original_attachment_id = false;
			$trid                   = $sitepress->get_element_trid( $attachment_id, 'post_attachment' );
			if ( $trid ) {
				$translations                   = $sitepress->get_element_translations( $trid, 'post_attachment', true, true );
				$translated_languages           = [];
				$default_language               = $sitepress->get_default_language();
				$default_language_attachment_id = false;
				foreach ( $translations as $translation ) {
					if ( $translation->original ) {
						$original_attachment_id = $translation->element_id;
					}
					if ( $translation->language_code == $default_language ) {
						$default_language_attachment_id = $translation->element_id;
					}
					$translated_languages[] = $translation->language_code;
				}
				if ( ! $original_attachment_id ) {
					$attachment = get_post( $attachment_id );
					if ( ! $attachment ) {
						return;
					}
					if ( ! $default_language_attachment_id ) {
						$this->create_duplicate_attachment( $attachment_id, $attachment->post_parent, $default_language );
					} else {
						$sitepress->set_element_language_details( $default_language_attachment_id, 'post_attachment', $trid, $default_language, null );
					}
					$this->translate_attachments( $attachment->ID, $source_language );
				} else {
					$original = get_post( $original_attachment_id );
					if ( ! $original ) {
						return;
					}
					$codes    = \WPML\LanguageEditor\TranslationPause::filterTranslatable(
						array_keys( $sitepress->get_active_languages() )
					);
					if ( is_array( $target_languages ) ) {
						$codes = array_filter(
							$codes,
							function( $code ) use ( $target_languages ) {
								return in_array( $code, $target_languages );
							}
						);
					}

					foreach ( $codes as $code ) {
						if ( ! in_array( $code, $translated_languages ) ) {
							$this->create_duplicate_attachment( $attachment_id, $original->post_parent, $code );
						}
					}
				}
			}
		}

	}

	private function is_uploading_plugin_or_theme() {
		global $action;

		return isset( $action ) && ( $action == 'upload-plugin' || $action == 'upload-theme' );
	}

	public function make_duplicate( $master_post_id, $target_lang, $post_array, $target_post_id ) {
		$wpdb = $this->wpdb;

		$translated_attachment_id = false;
		$last_translated_attachment_id = false;

		$master_post_attachment_ids = $wpdb->get_col(
			$wpdb->prepare(
				"SELECT ID FROM {$wpdb->posts} WHERE post_parent = %d AND post_type = %s",
				$master_post_id,
				'attachment'
			)
		);

		if ( $master_post_attachment_ids ) {
			foreach ( $master_post_attachment_ids as $master_post_attachment_id ) {
				$translated_attachment_id = false;

				$attachment_trid = $this->sitepress->get_element_trid( $master_post_attachment_id, 'post_attachment' );

				if ( $attachment_trid ) {
					$attachment_translations = $this->sitepress->get_element_translations( $attachment_trid, 'post_attachment' );

					foreach ( $attachment_translations as $attachment_translation ) {
						if ( $attachment_translation->language_code == $target_lang ) {
							$translated_attachment_id = $attachment_translation->element_id;
							break;
						}
					}

					if ( ! $translated_attachment_id ) {
						$translated_attachment_id = $this->create_duplicate_attachment( $master_post_attachment_id, wp_get_post_parent_id( $master_post_id ), $target_lang );
					}

					if ( $translated_attachment_id ) {
						$last_translated_attachment_id = $translated_attachment_id;

					$translated_attachment = get_post( $translated_attachment_id );
					if ( $translated_attachment && (int) $translated_attachment->post_parent !== (int) $target_post_id ) {
						$wpdb->update(
							$wpdb->posts,
							array( 'post_parent' => $target_post_id ),
							array( 'ID' => $translated_attachment_id ),
							array( '%d' ),
							array( '%d' )
						);
						clean_post_cache( $translated_attachment_id );
						}
					}
				}
			}
		}


		$thumbnail_id = get_post_meta( $master_post_id, '_thumbnail_id', true );

		if ( $thumbnail_id ) {

			$thumbnail_trid = $this->sitepress->get_element_trid( $thumbnail_id, 'post_attachment' );

			if ( $thumbnail_trid ) {
				$t_thumbnail_id = icl_object_id( $thumbnail_id, 'attachment', false, $target_lang );
				if ( $t_thumbnail_id == null ) {
					$dup_att_id     = $this->create_duplicate_attachment( $thumbnail_id, $target_post_id, $target_lang );
					$t_thumbnail_id = $dup_att_id;
				}

				if ( $t_thumbnail_id != null ) {
					update_post_meta( $target_post_id, '_thumbnail_id', $t_thumbnail_id );
				}
			}
		}

		return $last_translated_attachment_id;
	}

	public function create_duplicate_attachment( $attachment_id, $parent_id, $target_language ) {
		try {
			$attachment_post = get_post( $attachment_id );
			if ( ! $attachment_post ) {
				throw new WPML_Media_Exception( sprintf( 'Post with id %d does not exist', $attachment_id ) );
			}

			$trid = $this->sitepress->get_element_trid( $attachment_id, WPML_Model_Attachments::ATTACHMENT_TYPE );
			if ( ! $trid ) {
				throw new WPML_Media_Exception( sprintf( 'Attachment with id %s does not contain language information', $attachment_id ) );
			}

			$duplicated_attachment    = $this->attachments_model->find_duplicated_attachment( $trid, $target_language );
			$duplicated_attachment_id = null;
			if ( null !== $duplicated_attachment ) {
				$duplicated_attachment_id = $duplicated_attachment->ID;
			}
			$translated_parent_id = $this->attachments_model->fetch_translated_parent_id( $duplicated_attachment, $parent_id, $target_language );

			if ( null !== $duplicated_attachment ) {
				if ( (int) $duplicated_attachment->post_parent !== (int) $translated_parent_id ) {
					$this->attachments_model->update_parent_id_in_existing_attachment( $translated_parent_id, $duplicated_attachment );
				}
			} else {
				$duplicated_attachment_id = $this->attachments_model->duplicate_attachment( $attachment_id, $target_language, $translated_parent_id, $trid );
			}

			$this->attachments_model->duplicate_post_meta_data( $attachment_id, $duplicated_attachment_id );

			do_action( 'wpml_after_duplicate_attachment', $attachment_id, $duplicated_attachment_id );

			return $duplicated_attachment_id;
		} catch ( WPML_Media_Exception $e ) {
			return null;
		}
	}

	public function sync_on_translation_complete( $new_post_id, $fields, $job ) {
		$new_post = get_post( $new_post_id );
		$this->save_post_actions( $new_post_id, $new_post );
	}

	public function record_original_thumbnail_ids_and_sync( $meta_id, $object_id, $meta_key, $meta_value ) {
		if ( '_thumbnail_id' === $meta_key ) {
			$original_thumbnail_id = get_post_meta( $object_id, $meta_key, true );
			if ( $original_thumbnail_id !== $meta_value ) {
				$this->original_thumbnail_ids[ $object_id ] = $original_thumbnail_id;
				$this->sync_post_thumbnail( $object_id, $meta_value ? $meta_value : false );
			}
		}
	}

	public function enqueue_media_screen_commands() {
		if ( ! Option::shouldHandleMediaAuto() ) {
			return;
		}

		$commands = array();

		$screen                      = function_exists( 'get_current_screen' ) ? get_current_screen() : null;
		$is_media_library_screen     = $screen && 'upload' === $screen->base;
		$is_media_translation_screen = isset( $_GET['page'] ) && 'wpml-media' === $_GET['page'];

		if ( $is_media_library_screen || $is_media_translation_screen ) {
			$queue = $this->sitepress->get_setting( PostWithMediaFiles::POSTS_QUEUE_WITH_DUPLICATED_COPIED_MEDIA_SETTING, array() );
			if ( ! empty( $queue ) && current_user_can( 'upload_files' ) ) {
				$commands[] = array(
					'action' => self::CLEANUP_DUPLICATED_MEDIA_ACTION,
					'nonce'  => wp_create_nonce( self::CLEANUP_DUPLICATED_MEDIA_ACTION ),
					'args'   => array(),
				);
			}
		} elseif ( class_exists( 'WPML_WP_API' ) ) {
			$wpml_wp_api = new WPML_WP_API();
			if ( $wpml_wp_api->is_new_post_page() || $wpml_wp_api->is_post_edit_page() || $wpml_wp_api->is_translation_queue_page() ) {
				$args = array();
				foreach ( array( 'trid', 'post', 'job_id' ) as $key ) {
					if ( isset( $_GET[ $key ] ) && is_numeric( $_GET[ $key ] ) ) {
						$args[ $key ] = (string) intval( $_GET[ $key ] );
					}
				}
				foreach ( array( 'language_code', 'language', 'lang' ) as $key ) {
					if ( ! isset( $args['lang'] ) && isset( $_GET[ $key ] ) && is_string( $_GET[ $key ] ) ) {
						$args['lang'] = sanitize_key( wp_unslash( $_GET[ $key ] ) );
					}
				}
				if ( isset( $args['trid'] ) || isset( $args['post'] ) || isset( $args['job_id'] ) ) {
					$commands[] = array(
						'action' => self::PREPARE_TRANSLATION_EDITOR_MEDIA_ACTION,
						'nonce'  => wp_create_nonce( self::PREPARE_TRANSLATION_EDITOR_MEDIA_ACTION ),
						'args'   => $args,
					);
				}
			}
		}

		if ( ! $commands ) {
			return;
		}

		$handle = 'wpml-media-screen-commands';
		wp_register_script(
			$handle,
			ICL_PLUGIN_URL . '/res/js/media/screen-commands.js',
			array(),
			ICL_SITEPRESS_SCRIPT_VERSION,
			true
		);
		wp_enqueue_script( $handle );
		wp_localize_script(
			$handle,
			'wpmlMediaScreenCommands',
			array(
				'ajaxUrl'  => admin_url( 'admin-ajax.php' ),
				'commands' => $commands,
			)
		);
	}

	public function ajax_prepare_translation_editor_media() {
		$nonce = isset( $_POST['nonce'] ) ? sanitize_text_field( wp_unslash( $_POST['nonce'] ) ) : '';

		if ( ! wp_verify_nonce( $nonce, self::PREPARE_TRANSLATION_EDITOR_MEDIA_ACTION ) ) {
			/* translators: Error message returned when a request from the browser cannot be trusted and is turned away. */
			wp_send_json_error( esc_html__( 'Invalid request!', 'sitepress' ), 403 );
			return;
		}

		$verdict = $this->prepare_translation_editor_media( wp_unslash( $_POST ) );

		if ( self::PREPARE_DENIED === $verdict ) {
			wp_send_json_error( esc_html__( 'You are not allowed to do this.', 'sitepress' ), 403 );
			return;
		}

		wp_send_json_success( array( 'duplicated' => self::PREPARE_DUPLICATED === $verdict ) );
	}

	public function prepare_translation_editor_media( array $request ) {
		if ( ! Option::shouldHandleMediaAuto() ) {
			return self::PREPARE_NOTHING_TO_DO;
		}

		$job_id  = isset( $request['job_id'] ) && is_numeric( $request['job_id'] ) ? (int) $request['job_id'] : 0;
		$post_id = isset( $request['post'] ) && is_numeric( $request['post'] ) ? (int) $request['post'] : 0;
		$trid    = isset( $request['trid'] ) && is_numeric( $request['trid'] ) ? (int) $request['trid'] : 0;

		$original_post = null;
		$lang          = null;

		if ( $job_id > 0 ) {
			if ( ! $this->current_user_can_translate_job( $job_id ) ) {
				return self::PREPARE_DENIED;
			}

			$job = $this->get_translation_job( $job_id );
			if ( ! is_object( $job ) || empty( $job->trid ) ) {
				return self::PREPARE_NOTHING_TO_DO;
			}

			$source = $this->get_source_element_by_trid( (int) $job->trid );
			if ( ! is_object( $source ) || empty( $source->element_id ) ) {
				return self::PREPARE_NOTHING_TO_DO;
			}

			$original_post = get_post( (int) $source->element_id );
			$lang          = isset( $job->language_code ) && is_string( $job->language_code ) ? $job->language_code : null;
		} elseif ( $post_id > 0 ) {
			$translated_post = get_post( $post_id );
			if ( ! $translated_post ) {
				return self::PREPARE_DENIED;
			}

			if ( ! current_user_can( 'edit_post', $post_id ) ) {
				return self::PREPARE_DENIED;
			}

			$original_post_id = $this->get_original_element_id( $post_id, 'post_' . $translated_post->post_type );

			if ( $original_post_id <= 0 || $original_post_id === $post_id ) {
				return self::PREPARE_NOTHING_TO_DO;
			}

			$original_post = get_post( $original_post_id );
			if ( ! $original_post ) {
				return self::PREPARE_NOTHING_TO_DO;
			}

			if (
				! current_user_can( 'read_post', $original_post->ID )
				&& ! current_user_can( 'edit_post', $original_post->ID )
			) {
				return self::PREPARE_DENIED;
			}

			$language_details = $this->sitepress->get_element_language_details( $post_id, 'post_' . $translated_post->post_type );
			$lang             = is_object( $language_details ) && isset( $language_details->language_code ) ? $language_details->language_code : null;
		} elseif ( $trid > 0 ) {
			$original_post_id = $this->get_original_element_id_by_trid( $trid );
			if ( $original_post_id <= 0 ) {
				return self::PREPARE_DENIED;
			}

			$original_post = get_post( $original_post_id );
			if ( ! $original_post ) {
				return self::PREPARE_DENIED;
			}

			$post_type_object = get_post_type_object( $original_post->post_type );
			if ( ! $post_type_object || ! isset( $post_type_object->cap ) ) {
				return self::PREPARE_DENIED;
			}

			if (
				! current_user_can( $post_type_object->cap->edit_posts )
				|| ! current_user_can( $post_type_object->cap->create_posts )
			) {
				return self::PREPARE_DENIED;
			}

			if (
				! current_user_can( 'read_post', $original_post->ID )
				&& ! current_user_can( 'edit_post', $original_post->ID )
			) {
				return self::PREPARE_DENIED;
			}

			$lang = isset( $request['lang'] ) && is_string( $request['lang'] ) ? $request['lang'] : null;
			if ( null === $lang || ! in_array( $lang, $this->language_resolution->get_active_language_codes(), true ) ) {
				return self::PREPARE_DENIED;
			}
		} else {
			return self::PREPARE_NOTHING_TO_DO;
		}

		if ( ! is_object( $original_post ) || empty( $original_post->ID ) ) {
			return self::PREPARE_NOTHING_TO_DO;
		}

		if ( ! is_string( $lang ) || strlen( $lang ) < 2 ) {
			return self::PREPARE_NOTHING_TO_DO;
		}

		$post_media = $this->post_media_factory->create( $original_post->ID );
		$post_media->ensure_media_ids_extracted();

		foreach ( $post_media->get_copied_and_referenced__media_ids() as $media_id ) {
			$this->save_attachment_actions( $media_id, true, [ $lang ] );
		}

		$post_media->save_to_posts_queue_with_duplicated_copied_media();

		return self::PREPARE_DUPLICATED;
	}

	public function ajax_cleanup_duplicated_media() {
		$nonce = isset( $_POST['nonce'] ) ? sanitize_text_field( wp_unslash( $_POST['nonce'] ) ) : '';

		if ( ! wp_verify_nonce( $nonce, self::CLEANUP_DUPLICATED_MEDIA_ACTION ) ) {
			/* translators: Error message returned when a request from the browser cannot be trusted and is turned away. */
			wp_send_json_error( esc_html__( 'Invalid request!', 'sitepress' ), 403 );
			return;
		}

		if ( ! $this->cleanup_duplicated_copied_media() ) {
			wp_send_json_error( esc_html__( 'You are not allowed to do this.', 'sitepress' ), 403 );
			return;
		}

		wp_send_json_success();
	}

	public function cleanup_duplicated_copied_media() {
		if ( ! Option::shouldHandleMediaAuto() ) {
			return false;
		}

		if ( ! current_user_can( 'upload_files' ) ) {
			return false;
		}

		$this->maybe_clear_duplicated_copied_media_in_posts_queue();

		return true;
	}

	protected function current_user_can_translate_job( $job_id ) {
		return \WPML\TM\Jobs\Authorization\JobAuthorization::currentUserCanTranslateJob( $job_id );
	}

	protected function get_translation_job( $job_id ) {
		return Jobs::get( $job_id );
	}

	protected function get_source_element_by_trid( $trid ) {
		return TranslationRecords::getSourceByTrid( $trid );
	}

	protected function get_original_element_id( $element_id, $element_type ) {
		return (int) SitePress::get_original_element_id( $element_id, $element_type );
	}

	protected function get_original_element_id_by_trid( $trid ) {
		$original_element_id = SitePress::get_original_element_id_by_trid( $trid );

		return is_numeric( $original_element_id ) ? (int) $original_element_id : 0;
	}

	public function delete_post_media_usages( $post_id ) {
		$maybe_original_post = get_post( $post_id );
		if ( ! $maybe_original_post ) {
			return null;
		}
		$original_post_id = (int) SitePress::get_original_element_id( $maybe_original_post->ID, 'post_' . $maybe_original_post->post_type );

		if ( $original_post_id !== (int) $post_id ) {
			return null;
		}

		$post_media = $this->post_media_factory->create( $original_post_id );
		$post_media->remove_usage_of_media_files_in_post();
	}

	private function maybe_clear_duplicated_copied_media_in_posts_queue() {
		$post_media = null;
		$queue      = $this->sitepress->get_setting( PostWithMediaFiles::POSTS_QUEUE_WITH_DUPLICATED_COPIED_MEDIA_SETTING, array() );
		foreach ( $queue as $post_id ) {
			$post_media = $this->post_media_factory->create( $post_id );
			$post_media->delete_duplicated_copied_media();
		}
		if ( $post_media ) {
			$post_media->clear_posts_queue_with_duplicated_copied_media();
		}
	}

	public function woocommerce_extract_media_ids_from_post_content_and_meta( $product, $data_store ) {
		if ( ! is_object( $product ) || ! method_exists( $product, 'get_id' ) ) {
			return;
		}

		$post = get_post( (int) $product->get_id() );
		$this->extract_media_ids_from_post_content_and_meta( $post, $data_store );
	}

	public function extract_media_ids_from_post_content_and_meta( $post, $update, $post_before = null ) {
		if ( is_int( $post ) ) {
			$post = get_post( $post );
		}

		if ( 'attachment' === $post->post_type ) {
			return;
		}

		if ( (int) $post->ID === 0) {
			return;
		}

		if ( ! $this->is_valid_post_to_process( $post->ID, $post->post_type, $post->post_status, false ) ) {
			return;
		}

		if ( ! $this->is_original( $post ) ) {
			return;
		}

		$post_media = $this->post_media_factory->create( $post->ID );
		$post_media->extract_and_save_media_ids();
	}

	private function is_original( $post ) {
		if ( 'revision' === $post->post_type ) {
			$post = get_post( $post->post_parent );
		}

		return (int) $post->ID === (int) $this->sitepress->get_original_element_id( $post->ID, 'post_' . $post->post_type, false, false, false, true );
	}

	function save_post_actions( $pidd, $post ) {
		if ( ! $post ) {
			return;
		}

		if ( $post->post_type !== 'attachment' && $post->post_status !== 'auto-draft' ) {
			$this->sync_attachments( $pidd, $post );

			if ( ! $this->is_original( $post ) ) {
				$this->save_post_queue[] = $post;
			}
		}

		if ( $post->post_type === 'attachment' ) {
			$metadata      = wp_get_attachment_metadata( $post->ID );
			$attachment_id = $pidd;
			if ( $metadata ) {
				$this->synchronize_attachment_metadata( $metadata, $attachment_id );
			}
		}
	}

	function maybe_translate_medias_in_posts() {
		foreach ( $this->save_post_queue as $post ) {
			$all_meta = get_post_meta( $post->ID );
			$data     = [];
			$lang     = $this->sitepress->get_language_for_element( $post->ID, 'post_' . $post->post_type );

			foreach ( $all_meta as $key => $value ) {
				if ( strpos( $key, '_bricks_' ) === 0 ) {
					$item = maybe_unserialize( $value[0] );
					if ( ! is_array( $item ) ) {
						continue;
					}

					$this->translate_bricks_media( $item, $lang );
					$this->update_post_meta_without_double_escaping_slashes( $post->ID, $key, $item, $value[0] );
				}

				if ( strpos( $key, 'panels_data' ) === 0 ) {
					$item = maybe_unserialize( $value[0] );
					if ( ! is_array( $item ) ) {
						continue;
					}

					$this->translate_siteorigin_media( $item, $lang );
					$this->update_post_meta_without_double_escaping_slashes( $post->ID, $key, $item, $value[0] );
				}
			}
		}
	}

	private function update_post_meta_without_double_escaping_slashes( $post_id, $key, $item, $original_serialized_item ) {
		$new_serialized_item = maybe_serialize( $item );
		if ( $original_serialized_item === $new_serialized_item ) {
			return;
		}

		$this->wpdb->update(
			$this->wpdb->postmeta,
			[ 'meta_value' => $new_serialized_item ],
			[ 'post_id' => $post_id, 'meta_key' => $key ],
			[ '%s' ],
			[ '%d', '%s' ]
		);
	}

	private function translate_bricks_media( &$data, $lang ) {
		$iterator = function( &$node ) use ( &$iterator, $lang ) {
			if ( ! is_array( $node ) ) {
				return;
			}

			if ( isset( $node['settings']['image']['id'] ) ) {
				$node['settings']['image']['id'] = $this->get_translated_attachment_id( $node['settings']['image']['id'], $lang );
			}

			if ( isset( $node['settings']['items']['images'] ) && is_array( $node['settings']['items']['images'] ) ) {
				foreach ( $node['settings']['items']['images'] as &$image ) {
					if ( isset( $image['id'] ) ) {
						$image['id'] = $this->get_translated_attachment_id( $image['id'], $lang );
					}
				}
			}

			if ( isset( $node['settings']['_background']['image']['id'] ) ) {
				$node['settings']['_background']['image']['id'] = $this->get_translated_attachment_id( $node['settings']['_background']['image']['id'], $lang );
			}

			foreach ( $node as &$value ) {
				if ( is_array( $value ) ) {
					$iterator( $value );
				}
			}
		};

		$iterator( $data );
	}

	private function translate_siteorigin_media( &$data, $lang ) {
		$iterator = function( &$node ) use ( &$iterator, $lang ) {
			if ( ! is_array( $node ) ) {
				return;
			}

			if ( isset( $node['option_name'] ) && $node['option_name'] === 'widget_sow-image' && isset( $node['image'] ) && is_numeric( $node['image'] ) ) {
				$is_custom_alt_setup = isset( $node['alt'] ) && is_string( $node['alt'] ) && strlen( $node['alt'] ) > 0;
				if ( ! $is_custom_alt_setup ) {
					$node['image'] = $this->get_translated_attachment_id( $node['image'], $lang );
				}
			}

			if ( isset( $node['option_name'] ) && $node['option_name'] === 'widget_sow-slider' && isset( $node['frames'] ) && is_array( $node['frames'] ) ) {
				foreach ( $node['frames'] as &$image ) {
					if ( isset( $image['foreground_image'] ) && is_numeric( $image['foreground_image'] ) ) {
						$image['foreground_image'] = $this->get_translated_attachment_id( $image['foreground_image'], $lang );
					}
					if ( isset( $image['background_image'] ) && is_numeric( $image['background_image'] ) ) {
						$image['background_image'] = $this->get_translated_attachment_id( $image['background_image'], $lang );
					}
				}
			}

			foreach ( $node as &$value ) {
				if ( is_array( $value ) ) {
					$iterator( $value );
				}
			}
		};

		$iterator( $data );
	}

	private function get_translated_attachment_id( $id, $lang ) {
		if ( ! is_string( $lang ) ) {
			return $id;
		}

		$key = $id . $lang;

		if ( ! array_key_exists( $key, $this->translated_posts ) ) {
			$factory = new WPML_Translation_Element_Factory( $this->sitepress );

			$this->translated_posts[ $key ] = null;
			$element                        = $factory->create_post( $id );
			$translation                    = $element->get_translation( $lang, true );

			if ( $translation ) {
				$this->translated_posts[ $key ] = $translation->get_wp_object();
			}
		}

		return is_object( $this->translated_posts[ $key ] ) ? $this->translated_posts[ $key ]->ID : $id;
	}

	private function is_valid_post_to_process($pidd, $post_type, $post_status, bool $check_if_is_translated_type = true ) {
		$is_invalid = (
			( $check_if_is_translated_type && ! $this->sitepress->is_translated_post_type( $post_type ) )
			|| isset( $_POST['autosave'] )
			|| ( isset( $_POST['post_ID'] ) && (int) $_POST['post_ID'] !== (int) $pidd )
			|| ( isset( $_POST['post_type'] ) && 'revision' === $_POST['post_type'] )
			|| 'revision' === $post_type
			|| get_post_meta( $pidd, '_wp_trash_meta_status', true )
			|| ( isset( $_GET['action'] ) && 'restore' === $_GET['action'] )
			|| 'auto-draft' === $post_status
		);

		return ! $is_invalid;
	}

	function sync_attachments( $pidd, $post ) {
		$wpdb = $this->wpdb;

		if ( $post->post_type == 'attachment' || $post->post_status == 'auto-draft' ) {
			return;
		}

		list( $post_type, $post_status ) = $wpdb->get_row(
			$wpdb->prepare( "SELECT post_type, post_status FROM {$wpdb->posts} WHERE ID = %d", $pidd ),
			ARRAY_N
		);


		if ( ! $this->is_valid_post_to_process( $pidd, $post_type, $post_status ) ) {
			return;
		}

		$icl_trid = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT trid FROM {$wpdb->prefix}icl_translations WHERE element_id=%d AND element_type = %s",
				array( $pidd, 'post_' . $post_type )
			)
		);

		if ( $icl_trid ) {
			$language_details = $this->sitepress->get_element_language_details( $pidd, 'post_' . $post_type );

			if ( ! $language_details ) {
				$this->sitepress->get_translations_cache()->clear();
				$language_details = $this->sitepress->get_element_language_details( $pidd, 'post_' . $post_type );
			}
			if ( $language_details ) {
				$this->duplicate_post_attachments( $pidd, $icl_trid, $language_details->source_language_code, $language_details->language_code );
			}
		}
	}

	public function sync_post_thumbnail( $post_id, $request_post_thumbnail_id = null ) {

		if ( $post_id && Option::shouldDuplicateFeatured( $post_id ) || Option::shouldHandleMediaAuto() ) {

			if ( null === $request_post_thumbnail_id ) {
				$request_post_thumbnail_id = filter_input(
					INPUT_POST,
					'thumbnail_id',
					FILTER_SANITIZE_NUMBER_INT,
					FILTER_NULL_ON_FAILURE
				);

				$thumbnail_id = $request_post_thumbnail_id ?
					$request_post_thumbnail_id :
					get_post_meta( $post_id, '_thumbnail_id', true );
			} else {
				$thumbnail_id = $request_post_thumbnail_id;
			}

			$trid         = $this->sitepress->get_element_trid( $post_id, 'post_' . get_post_type( $post_id ) );
			$translations = $this->sitepress->get_element_translations( $trid, 'post_' . get_post_type( $post_id ) );

			$is_original = false;
			foreach ( $translations as $translation ) {
				if ( 1 === (int) $translation->original && (int) $translation->element_id === $post_id ) {
					$is_original = true;
				}
			}

			if ( $is_original ) {
				foreach ( $translations as $translation ) {
					if ( ! $translation->original && $translation->element_id ) {
						if ( $this->are_post_thumbnails_still_in_sync( $post_id, $thumbnail_id, $translation ) ) {
							if ( ! $thumbnail_id || - 1 === (int) $thumbnail_id ) {
								$this->withPostMetaFiltersDisabled(
									function () use ( $translation ) {
										delete_post_meta( $translation->element_id, '_thumbnail_id' );
									}
								);
							} else {
								$translated_thumbnail_id = wpml_object_id_filter(
									$thumbnail_id,
									'attachment',
									false,
									$translation->language_code
								);

								$id = get_post_meta( $translation->element_id, '_thumbnail_id', true );
								if ( (int) $id !== $translated_thumbnail_id ) {
									$this->withPostMetaFiltersDisabled(
										function () use ( $translation, $translated_thumbnail_id ) {
											update_post_meta( $translation->element_id, '_thumbnail_id', $translated_thumbnail_id );
										}
									);
								}
							}
						}
					}
				}
			}
		}
	}

	protected function are_post_thumbnails_still_in_sync( $source_id, $source_thumbnail_id, $translation ) {

		$translation_thumbnail_id = get_post_meta( $translation->element_id, '_thumbnail_id', true );

		if ( isset( $this->original_thumbnail_ids[ $source_id ] ) ) {
			if ( $this->original_thumbnail_ids[ $source_id ] === $translation_thumbnail_id ) {
				return true;
			}

			return $this->are_translations_of_each_other(
				$this->original_thumbnail_ids[ $source_id ],
				$translation_thumbnail_id
			);
		} else {
			return $this->are_translations_of_each_other(
				$source_thumbnail_id,
				$translation_thumbnail_id
			);
		}
	}

	private function are_translations_of_each_other( $post_id_1, $post_id_2 ) {
		return $this->sitepress->get_element_trid( $post_id_1, 'post_' . get_post_type( $post_id_1 ) ) ===
			$this->sitepress->get_element_trid( $post_id_2, 'post_' . get_post_type( $post_id_2 ) );
	}

	function duplicate_post_attachments( $pidd, $icl_trid, $source_lang = null, $lang = null ) {
		$wpdb = $this->wpdb;
		$pidd = ( is_numeric( $pidd ) ) ? (int) $pidd : null;


		if ( $icl_trid == '' ) {
			return;
		}

		if ( ! $source_lang ) {
			$source_lang = $wpdb->get_var(
				$wpdb->prepare( "SELECT source_language_code FROM {$wpdb->prefix}icl_translations WHERE element_id = %d AND trid=%d", $pidd, $icl_trid )
			);
		}

		if ( $source_lang == null || $source_lang == '' ) {
			if ( Option::shouldDuplicateMedia( $pidd ) || Option::shouldDuplicateFeatured( $pidd ) || Option::shouldHandleMediaAuto() ) {
				$active_language_codes = \WPML\LanguageEditor\TranslationPause::filterTranslatable(
					array_map( 'strval', array_keys( (array) $this->sitepress->get_active_languages() ) )
				);

				$translations = [];
				if ( $active_language_codes ) {
					$lang_codes_placeholder = implode( ',', array_fill( 0, count( $active_language_codes ), '%s' ) );

					$translations = $wpdb->get_col(
						$wpdb->prepare(
							"SELECT element_id FROM {$wpdb->prefix}icl_translations WHERE trid = %d AND language_code IN ( $lang_codes_placeholder )",
							array_merge( [ $icl_trid ], $active_language_codes )
						)
					);
					$translations = array_map( 'intval', $translations );
				}

				$source_attachments = $wpdb->get_col(
					$wpdb->prepare(
						'SELECT ID FROM ' . $wpdb->posts . ' WHERE post_parent = %d AND post_type = %s',
						array( $pidd, 'attachment' )
					)
				);
				$source_attachments = array_map( 'intval', $source_attachments );

				$all_element_ids           = [];
				$attachments_by_element_id = [];
				foreach ( $translations as $element_id ) {
					if ( $element_id && $element_id !== $pidd ) {
						$all_element_ids[]                        = $element_id;
						$attachments_by_element_id[ $element_id ] = [];
					}
				}
				$all_attachments = [];
				if ( count( $all_element_ids ) > 0 ) {
					$all_attachments = $wpdb->get_results(
						$wpdb->prepare(
							'SELECT ID, post_parent AS element_id FROM ' . $wpdb->posts . ' WHERE post_parent IN (' . wpml_prepare_in( $all_element_ids ) . ') AND post_type = %s',
							array( 'attachment' )
						),
						ARRAY_A
					);
				}
				foreach ( $all_attachments as $attachment ) {
					$attachments_by_element_id[ (int) $attachment['element_id'] ][] = (int) $attachment['ID'];
				}

				foreach ( $translations as $element_id ) {
					if ( $element_id && $element_id !== $pidd ) {
						$lang = $wpdb->get_var(
							$wpdb->prepare(
								"SELECT language_code FROM {$wpdb->prefix}icl_translations WHERE element_id = %d AND trid = %d",
								array( $element_id, $icl_trid )
							)
						);

						$should_duplicate_featured = Option::shouldDuplicateFeatured( $element_id ) || Option::shouldHandleMediaAuto();

						if ( $should_duplicate_featured ) {
							$attachments                           = $attachments_by_element_id[ $element_id ];
							$has_missing_translation_attachment_id = false;

							foreach ( $attachments as $attachment_id ) {
								if ( ! icl_object_id( $attachment_id, 'attachment', false, $lang ) ) {
									$has_missing_translation_attachment_id = true;
									break;
								}
							}

							$source_attachment_ids = $has_missing_translation_attachment_id ? $source_attachments : [];

							foreach ( $source_attachment_ids as $source_attachment_id ) {
								$this->create_duplicate_attachment_not_static( $source_attachment_id, $element_id, $lang );
							}
						}

						$translation_thumbnail_id = get_post_meta( $element_id, '_thumbnail_id', true );
						if ( $should_duplicate_featured && empty( $translation_thumbnail_id ) ) {
							$thumbnail_id = get_post_meta( $pidd, '_thumbnail_id', true );
							if ( $thumbnail_id ) {
								$t_thumbnail_id = icl_object_id( $thumbnail_id, 'attachment', false, $lang );
								if ( $t_thumbnail_id == null ) {
									$dup_att_id     = $this->create_duplicate_attachment_not_static( $thumbnail_id, $element_id, $lang );
									$t_thumbnail_id = $dup_att_id;
								}

								if ( $t_thumbnail_id != null ) {
									update_post_meta( $element_id, '_thumbnail_id', $t_thumbnail_id );
								}
							}
						}
					}
				}
			}
		} else {
			$source_id = (int) $wpdb->get_var(
				$wpdb->prepare(
					"SELECT element_id FROM {$wpdb->prefix}icl_translations WHERE language_code = %s AND trid = %d",
					array( $source_lang, $icl_trid )
				)
			);

			if ( ! $lang ) {
				$lang = $wpdb->get_var(
					$wpdb->prepare(
						"SELECT language_code FROM {$wpdb->prefix}icl_translations WHERE element_id = %d AND trid = %d",
						array( $pidd, $icl_trid )
					)
				);
			}


			$duplicate = Option::shouldDuplicateMedia( $pidd, false );
			if ( $duplicate === null ) {
				$duplicate = Option::shouldDuplicateMedia( $source_id );
			}

			$copied_media_ids     = [];
			$referenced_media_ids = [];

			if ( Option::shouldHandleMediaAuto() ) {
				$duplicate = true;
				if ( is_numeric( $source_id ) ) {
					$post_media = $this->post_media_factory->create( $source_id );
					$post_media->ensure_media_ids_extracted();
					$copied_media_ids     = $post_media->get_copied_media_ids();
					$referenced_media_ids = $post_media->get_referenced_media_ids();
				}
			}

			if ( $duplicate ) {
				$source_attachments = $wpdb->get_col(
					$wpdb->prepare(
						"SELECT ID FROM {$wpdb->posts} WHERE post_parent = %d AND post_type = %s",
						array( $source_id, 'attachment' )
					)
				);

				foreach ( $source_attachments as $source_attachment_id ) {
					$translation_attachment_id = icl_object_id( $source_attachment_id, 'attachment', false, $lang );

					if ( ! $translation_attachment_id ) {
						if ( $this->is_attachment_creation_paused( $lang ) ) {
							continue;
						}

						if (
							Option::shouldHandleMediaAuto() &&
							in_array( $source_attachment_id, $copied_media_ids ) &&
							! in_array( $source_attachment_id, $referenced_media_ids )
						) {
							continue;
						}

						self::create_duplicate_attachment( $source_attachment_id, $pidd, $lang );
					} else {
						$translated_attachment = get_post( $translation_attachment_id );
						if ( $translated_attachment && (int) $translated_attachment->post_parent !== (int) $pidd ) {
							$translated_attachment->post_parent = $pidd;
							wp_update_post( $translated_attachment );
						}
					}
				}
			}

			$featured = Option::shouldDuplicateFeatured( $pidd, false );
			if ( $featured === null ) {
				$featured = Option::shouldDuplicateFeatured( $source_id );
			}
			if ( Option::shouldHandleMediaAuto() ) {
				$featured = true;
			}

			$translation_thumbnail_id = get_post_meta( $pidd, '_thumbnail_id', true );
			if ( $featured && empty( $translation_thumbnail_id ) ) {
				$thumbnail_id = get_post_meta( $source_id, '_thumbnail_id', true );
				if ( $thumbnail_id ) {
					$t_thumbnail_id = icl_object_id( $thumbnail_id, 'attachment', false, $lang );
					if ( $t_thumbnail_id == null ) {
						if ( ! $this->is_attachment_creation_paused( $lang ) ) {
							$dup_att_id     = self::create_duplicate_attachment( $thumbnail_id, $pidd, $lang );
							$t_thumbnail_id = $dup_att_id;
						}
					}

					if ( $t_thumbnail_id != null ) {
						update_post_meta( $pidd, '_thumbnail_id', $t_thumbnail_id );
					}
				}
			}
		}

	}

	private function is_attachment_creation_paused( $lang ) {
		if ( WPML_Save_Translation_Data_Action::is_delivery_window_open() ) {
			return false;
		}

		return \WPML\LanguageEditor\TranslationPause::isPaused( $lang );
	}

	public function create_duplicate_attachment_not_static( $source_attachment_id, $pidd, $lang ) {
		return self::create_duplicate_attachment( $source_attachment_id, $pidd, $lang );
	}

	private function duplicate_featured_images( $limit = 0, $after_meta_id = 0, $max_meta_id = PHP_INT_MAX ) {
		global $wpdb;

		$featured_images_sql = $wpdb->prepare(
			"SELECT * FROM {$wpdb->postmeta} WHERE meta_key = '_thumbnail_id' AND meta_id > %d AND meta_id <= %d ORDER BY `meta_id` ASC",
			$after_meta_id,
			$max_meta_id
		);
		if ( $limit > 0 ) {
			$featured_images_sql .= $wpdb->prepare( ' LIMIT %d', $limit );
		}
		$featured_images = $wpdb->get_results( $featured_images_sql );

		$thumbnails   = array();
		$last_meta_id = $after_meta_id;
		foreach ( $featured_images as $featured ) {
			$thumbnails[ $featured->post_id ] = $featured->meta_value;
			$last_meta_id                     = max( $last_meta_id, (int) $featured->meta_id );
		}
		$processed = count( $featured_images );

		if ( sizeof( $thumbnails ) ) {
			$post_ids       = wpml_prepare_in( array_keys( $thumbnails ), '%d' );
			$posts_prepared = "SELECT ID, post_type FROM {$wpdb->posts} WHERE ID IN ({$post_ids})";
			$posts          = $wpdb->get_results( $posts_prepared );
			foreach ( $posts as $post ) {
				$this->duplicate_featured_image_in_post( $post, $thumbnails );
			}
		}

		return array( $processed, $last_meta_id );
	}

	public function get_post_thumbnail_map( $limit = 0, $offset = 0 ) {
		global $wpdb;

		$featured_images_sql = "SELECT * FROM {$wpdb->postmeta} WHERE meta_key = '_thumbnail_id' ORDER BY `meta_id`";

		if ( $limit > 0 ) {
			$featured_images_sql .= $wpdb->prepare( ' LIMIT %d, %d', $offset, $limit );
		}

		$featured_images = $wpdb->get_results( $featured_images_sql );
		$processed       = count( $featured_images );

		$thumbnails = array();
		foreach ( $featured_images as $featured ) {
			$thumbnails[ $featured->post_id ] = $featured->meta_value;
		}

		return array( $thumbnails, $processed );
	}

	public function duplicate_featured_image_in_post( $post, $thumbnails = array() ) {
		global $wpdb, $sitepress;

		$row_prepared = $wpdb->prepare(
			"SELECT trid, source_language_code
												FROM {$wpdb->prefix}icl_translations
												WHERE element_id=%d
													AND element_type = %s",
			array( $post->ID, 'post_' . $post->post_type )
		);
		$row          = $wpdb->get_row( $row_prepared );
		if ( $row && $row->trid && ( $row->source_language_code == null || $row->source_language_code == '' ) ) {

			$translations = $sitepress->get_element_translations( $row->trid, 'post_' . $post->post_type );
			foreach ( $translations as $translation ) {

				if ( \WPML\LanguageEditor\TranslationPause::isPaused( $translation->language_code ) ) {
					continue;
				}

				if ( $translation->element_id != $post->ID ) {

					$translation_thumbnail_id = get_post_meta( $translation->element_id, '_thumbnail_id', true );
					if ( empty( $translation_thumbnail_id ) ) {
						if ( ! in_array( $translation->element_id, array_keys( $thumbnails ) ) ) {

							$t_thumbnail_id = icl_object_id( $thumbnails[ $post->ID ], 'attachment', false, $translation->language_code );
							if ( $t_thumbnail_id == null ) {
								$dup_att_id     = self::create_duplicate_attachment( $thumbnails[ $post->ID ], $translation->element_id, $translation->language_code );
								$t_thumbnail_id = $dup_att_id;
							}

							if ( $t_thumbnail_id != null ) {
								update_post_meta( $translation->element_id, '_thumbnail_id', $t_thumbnail_id );
							}
						} elseif ( $thumbnails[ $post->ID ] ) {
							update_post_meta( $translation->element_id, '_thumbnail_id', $thumbnails[ $post->ID ] );
						}
					}
				}
			}
		}
	}

	public function ajax_batch_duplicate_featured_images() {

		$nonce = isset( $_POST['nonce'] ) ? sanitize_text_field( $_POST['nonce'] ) : '';

		if ( ! wp_verify_nonce( $nonce, 'wpml_media_duplicate_featured_images' ) ) {
			/* translators: Error message returned when a request from the browser cannot be trusted and is turned away. */
			wp_send_json_error( esc_html__( 'Invalid request!', 'sitepress' ) );
		}

		$featured_images_left = array_key_exists( 'featured_images_left', $_POST ) && is_numeric( $_POST['featured_images_left'] )
			? (int) $_POST['featured_images_left']
			: null;

		return $this->batch_duplicate_featured_images( true, $featured_images_left );
	}

	public function batch_duplicate_featured_images( $outputResult = true, $featured_images_left = null ) {
		$featured_images_left = is_numeric( $featured_images_left ) ? (int) $featured_images_left : null;

		if ( null === $featured_images_left ) {
			$featured_images_left = $this->get_featured_images_total_number();
		}

		$limit  = 10;
		$cursor = (int) get_option( self::BATCH_FEATURED_CURSOR_OPTION, 0 );

		$max_meta_id = (int) get_option( self::BATCH_FEATURED_MAX_OPTION, 0 );
		if ( ! $max_meta_id ) {
			global $wpdb;
			$max_meta_id = (int) $wpdb->get_var( "SELECT MAX(meta_id) FROM {$wpdb->postmeta} WHERE meta_key = '_thumbnail_id'" );
			update_option( self::BATCH_FEATURED_MAX_OPTION, $max_meta_id, false );
		}

		list( $processed, $cursor ) = $this->duplicate_featured_images( $limit, $cursor, $max_meta_id );

		$left = max( $featured_images_left - $processed, 0 );
		if ( $processed < $limit ) {
			$left = 0;
		}

		if ( $left > 0 ) {
			update_option( self::BATCH_FEATURED_CURSOR_OPTION, $cursor, false );
		} else {
			delete_option( self::BATCH_FEATURED_CURSOR_OPTION );
			delete_option( self::BATCH_FEATURED_MAX_OPTION );
		}

		$response = array( 'left' => $left );
		if ( $response['left'] ) {
			/* translators: Progress message shown while the images of the front page are being copied to the other languages. %d: how many are still to be done. */
			$response['message'] = sprintf( __( 'Duplicating featured content: %d left. Stay here until complete. May take a few minutes.', 'sitepress' ), $response['left'] );
		} else {
			$response['message'] = sprintf( __( 'Duplicating featured content: 0 left. Stay here until complete. May take a few minutes.', 'sitepress' ), $response['left'] );
		}

		if ( $outputResult ) {
			wp_send_json( $response );
		}
		return $response['left'];
	}

	private function get_featured_images_total_number() {
		$wpdb = $this->wpdb;

		return (int) $wpdb->get_var(
			"SELECT COUNT(*)
			FROM {$wpdb->postmeta}
			WHERE meta_key = '_thumbnail_id'"
		);
	}

	public function ajax_batch_duplicate_media() {
		$nonce = isset( $_POST['nonce'] ) ? sanitize_text_field( $_POST['nonce'] ) : '';

		if ( ! wp_verify_nonce( $nonce, 'wpml_media_duplicate_media' ) ) {
			/* translators: Error message returned when a request from the browser cannot be trusted and is turned away. */
			wp_send_json_error( esc_html__( 'Invalid request!', 'sitepress' ) );
		}

		return $this->batch_duplicate_media();
	}

	public function batch_duplicate_media( $outputResult = true ) {
		$wpdb     = $this->wpdb;
		$limit    = 10;
		$response = array();

		$cursor = (int) get_option( self::BATCH_DUPLICATE_CURSOR_OPTION, 0 );
		$found  = get_option( self::BATCH_DUPLICATE_LEFT_OPTION, false );

		if ( false === $found ) {
			$found = (int) $wpdb->get_var(
				$wpdb->prepare(
					"
					SELECT COUNT(*)
							 FROM {$wpdb->posts} p1
							 WHERE post_type = %s
							 AND ID NOT IN (
								 SELECT post_id FROM {$wpdb->postmeta} WHERE meta_key = %s
							 )
					",
					array( 'attachment', 'wpml_media_processed' )
				)
			);
		}
		$found = (int) $found;

		$attachments = $wpdb->get_results(
			$wpdb->prepare(
				"
				SELECT p1.ID, p1.post_parent
						 FROM {$wpdb->posts} p1 FORCE INDEX (PRIMARY)
						 LEFT JOIN {$wpdb->postmeta} pm
							 ON pm.post_id = p1.ID AND pm.meta_key = %s
						 WHERE p1.post_type = %s
						 AND p1.ID > %d
						 AND pm.post_id IS NULL
						 ORDER BY p1.ID ASC LIMIT %d
				",
				array( 'wpml_media_processed', 'attachment', $cursor, $limit )
			)
		);

		if ( $attachments ) {
			foreach ( $attachments as $attachment ) {
				$this->create_duplicated_media( $attachment );
				$cursor = (int) $attachment->ID;
			}
		}

		$processed = is_array( $attachments ) ? count( $attachments ) : 0;
		$left      = max( $found - $processed, 0 );
		if ( $processed < $limit ) {
			$left = 0;
		} elseif ( 0 === $left ) {
			$left = 1;
		}

		if ( $left > 0 ) {
			update_option( self::BATCH_DUPLICATE_CURSOR_OPTION, $cursor, false );
			update_option( self::BATCH_DUPLICATE_LEFT_OPTION, $left, false );
		} else {
			delete_option( self::BATCH_DUPLICATE_CURSOR_OPTION );
			delete_option( self::BATCH_DUPLICATE_LEFT_OPTION );
		}

		$response['left'] = $left;
		if ( $response['left'] ) {
			/* translators: Progress message shown while media is being copied to the other languages. %d: how many are still to be done. */
			$response['message'] = sprintf( __( 'Duplicating content: %d left. Stay here until complete. May take a few minutes.', 'sitepress' ), $response['left'] );
		} else {
			$response['message'] = sprintf( __( 'Duplicating content: 0 left. Stay here until complete. May take a few minutes.', 'sitepress' ), $response['left'] );
		}

		if ( $outputResult ) {
			wp_send_json( $response );
		}
		return $response['left'];

	}

	private function get_batch_translate_limit( $activeLanguagesCount ) {
		global $sitepress;

		$limit = $sitepress->get_wp_api()->constant( 'WPML_MEDIA_BATCH_LIMIT' );
		$limit = $limit ?: ceil( 100 / max( $activeLanguagesCount - 1, 1 ) );

		return max( $limit, 1 );
	}

	public function ajax_batch_translate_media() {
		$nonce = isset( $_POST['nonce'] ) ? sanitize_text_field( $_POST['nonce'] ) : '';

		if ( ! wp_verify_nonce( $nonce, 'wpml_media_translate_media' ) ) {
			/* translators: Error message returned when a request from the browser cannot be trusted and is turned away. */
			wp_send_json_error( esc_html__( 'Invalid request!', 'sitepress' ) );
		}

		return $this->batch_translate_media();
	}

	public function batch_translate_media( $outputResult = true ) {
		$wpdb     = $this->wpdb;
		$response = [];

		$activeLanguages      = (array) $this->sitepress->get_active_languages();
		$activeLanguages      = array_intersect_key(
			$activeLanguages,
			array_flip(
				\WPML\LanguageEditor\TranslationPause::filterTranslatable(
					array_map( 'strval', array_keys( $activeLanguages ) )
				)
			)
		);
		$activeLanguagesCount = count( $activeLanguages );

		if ( ! $activeLanguagesCount ) {
			$response['left']    = 0;
			$response['message'] = __( 'Duplicating content: 0 left. Stay here until complete. May take a few minutes.', 'sitepress' );

			if ( $outputResult ) {
				wp_send_json( $response );
			}

			return $response['left'];
		}

		$placeholders         = implode( ',', array_fill( 0, $activeLanguagesCount, '%s' ) );
		$limit                = $this->get_batch_translate_limit( $activeLanguagesCount );

		$found = (int) $wpdb->get_var(
			$wpdb->prepare(
				"
				SELECT COUNT(*) FROM (
						SELECT p1.ID
						FROM {$wpdb->prefix}icl_translations t
						INNER JOIN {$wpdb->posts} p1
						ON t.element_id = p1.ID AND p1.post_type = 'attachment'
						LEFT JOIN {$wpdb->prefix}icl_translations tt
						ON t.trid = tt.trid
						WHERE t.element_type = 'post_attachment'
						AND t.source_language_code IS NULL
						AND tt.language_code IN ($placeholders)
						GROUP BY p1.ID, p1.post_parent
						HAVING COUNT(tt.language_code) < %d
					) AS count_subquery
				",
				array_merge( array_keys( $activeLanguages ), array( $activeLanguagesCount ) )
			)
		);

		$attachments = $wpdb->get_results(
			$wpdb->prepare(
				"
				SELECT p1.ID, p1.post_parent
					FROM {$wpdb->prefix}icl_translations t
					INNER JOIN {$wpdb->posts} p1
						ON t.element_id = p1.ID AND p1.post_type = 'attachment'
					LEFT JOIN {$wpdb->prefix}icl_translations tt
						ON t.trid = tt.trid
					WHERE t.element_type = 'post_attachment'
						AND t.source_language_code IS NULL
						AND tt.language_code IN ($placeholders)
					GROUP BY p1.ID, p1.post_parent
					HAVING COUNT(tt.language_code) < %d
					LIMIT %d
				",
				array_merge( array_keys( $activeLanguages ), array( $activeLanguagesCount, $limit ) )
			)
		);

		if ( $attachments ) {
			foreach ( $attachments as $attachment ) {
				$lang = $this->sitepress->get_element_language_details( $attachment->ID, 'post_attachment' );
				$this->translate_attachments( $attachment->ID, ( is_object( $lang ) && property_exists( $lang, 'language_code' ) ) ? $lang->language_code : null, true );
			}
		}

		$response['left'] = max( $found - $limit, 0 );
		if ( $response['left'] ) {
			/* translators: Progress message shown while media is being copied to the other languages. %d: how many are still to be done. */
			$response['message'] = sprintf( esc_html__( 'Duplicating content: %d left. Stay here until complete. May take a few minutes.', 'sitepress' ), $response['left'] );
		} else {
			$response['message'] = __( 'Duplicating content: 0 left. Stay here until complete. May take a few minutes.', 'sitepress' );
		}

		if ( $outputResult ) {
			wp_send_json( $response );
		}

		return $response['left'];
	}

	public function batch_set_initial_language() {

		$nonce = isset( $_POST['nonce'] ) ? sanitize_text_field( $_POST['nonce'] ) : '';

		if ( ! wp_verify_nonce( $nonce, 'wpml_media_set_initial_language' ) ) {
			/* translators: Error message returned when a request from the browser cannot be trusted and is turned away. */
			wp_send_json_error( esc_html__( 'Invalid request!', 'sitepress' ) );
		}

		$wpdb             = $this->wpdb;
		$default_language = $this->sitepress->get_default_language();
		$limit            = 10;

		$response = array();
		$found    = (int) $wpdb->get_var(
			$wpdb->prepare(
				"
				SELECT COUNT(ID)
						 FROM {$wpdb->posts}
						 WHERE post_type = %s
						 AND ID NOT IN (
							 SELECT element_id FROM {$wpdb->prefix}icl_translations WHERE element_type = %s
						 )
				",
				array( 'attachment', 'post_attachment' )
			)
		);

		$attachments = $wpdb->get_col(
			$wpdb->prepare(
				"
				SELECT ID
						 FROM {$wpdb->posts}
						 WHERE post_type = %s
						 AND ID NOT IN (
							 SELECT element_id FROM {$wpdb->prefix}icl_translations WHERE element_type = %s
						 )
						 LIMIT %d
				",
				array( 'attachment', 'post_attachment', $limit )
			)
		);

		foreach ( $attachments as $attachment_id ) {
			$this->sitepress->set_element_language_details( $attachment_id, 'post_attachment', false, $default_language );
		}
		$response['left'] = max( $found - $limit, 0 );
		if ( $response['left'] ) {
			/* translators: Progress message shown while each media item is being given its language. %d: how many are still to be done. */
			$response['message'] = sprintf( __( 'Setting language to media. %d left', 'sitepress' ), $response['left'] );
		} else {
			$response['message'] = sprintf( __( 'Setting language to media: done!', 'sitepress' ), $response['left'] );
		}

		echo wp_json_encode( $response );
		exit;
	}

	public function ajax_save_should_handle_media_auto_setting() {
		$nonce = isset( $_POST['nonce'] ) ? sanitize_text_field( $_POST['nonce'] ) : '';

		if ( ! wp_verify_nonce( $nonce, 'wpml_media_save_should_handle_media_auto_setting' ) ) {
			/* translators: Error message returned when a request from the browser cannot be trusted and is turned away. */
			wp_send_json_error( esc_html__( 'Invalid request!', 'sitepress' ) );
		}

		$isEnabled = (bool) $_POST['isEnabled'];
		Option::setShouldHandleMediaAuto( $isEnabled );

		if ( $isEnabled ) {
			$endpoint = make( \WPML\TM\Settings\ProcessExistingMediaInPosts::class );
			$this->background_task_service->add( $endpoint, wpml_collect( [] ) );
		} else {
			Option::setShouldShowHandleMediaAutoNotice30DaysAfterUpgrade();
		}

		wp_send_json( [] );
	}

	public function ajax_batch_scan_prepare() {
		$nonce = isset( $_POST['nonce'] ) ? sanitize_text_field( $_POST['nonce'] ) : '';

		if ( ! wp_verify_nonce( $nonce, 'wpml_media_scan_prepare' ) ) {
			/* translators: Error message returned when a request from the browser cannot be trusted and is turned away. */
			wp_send_json_error( esc_html__( 'Invalid request!', 'sitepress' ) );
		}

		$this->batch_scan_prepare();
	}

	public function batch_scan_prepare( $outputResult = true ) {
		$response = array();
		$this->wpdb->delete( $this->wpdb->postmeta, array( 'meta_key' => 'wpml_media_processed' ) );

		delete_option( self::BATCH_DUPLICATE_CURSOR_OPTION );
		delete_option( self::BATCH_DUPLICATE_LEFT_OPTION );
		delete_option( self::BATCH_FEATURED_CURSOR_OPTION );
		delete_option( self::BATCH_FEATURED_MAX_OPTION );

		$response['message'] = '';

		if ( $outputResult ) {
			wp_send_json( $response );
		}
	}

	public function ajax_batch_mark_processed() {
		$nonce = isset( $_POST['nonce'] ) ? sanitize_text_field( $_POST['nonce'] ) : '';

		if ( ! wp_verify_nonce( $nonce, 'wpml_media_mark_processed' ) ) {
			/* translators: Error message returned when a request from the browser cannot be trusted and is turned away. */
			wp_send_json_error( esc_html__( 'Invalid request!', 'sitepress' ) );
		}

		$this->batch_mark_processed();
	}

	public function batch_mark_processed( $outputResult = true ) {

		$response                    = [];
		$wpmlMediaProcessedMetaValue = 1;
		$limit                       = 300;
		$wpdb                        = $this->wpdb;

		$attachmentsCount = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(ID) from {$wpdb->posts} where post_type = %s",
				'attachment'
			)
		);

		$limitedAttachmentsWithMetaDataQuery = "SELECT posts.ID, post_meta.post_id, post_meta.meta_key, post_meta.meta_value
		FROM {$this->wpdb->posts} AS posts
		LEFT JOIN {$this->wpdb->postmeta} AS post_meta
		ON posts.ID = post_meta.post_id AND post_meta.meta_key = %s
		WHERE posts.post_type = %s AND (post_meta.meta_value IS NULL OR post_meta.meta_value != %d)
		LIMIT %d";

		$limitedAttachmentsWithMetaDataQueryPrepared = $this->wpdb->prepare( $limitedAttachmentsWithMetaDataQuery,
			[
				self::WPML_MEDIA_PROCESSED_META_KEY,
				'attachment',
				1,
				$limit,
			] );


		$attachmentsProcessingLoopRounds = $attachmentsCount ? ceil( $attachmentsCount / $limit ) : 0;

		$attachmentHasNoMetaData = function ( $attachmentWithMetaData ) {
			return Obj::prop( 'post_id', $attachmentWithMetaData ) === null &&
				Obj::prop( 'meta_key', $attachmentWithMetaData ) === null &&
				Obj::prop( 'meta_value', $attachmentWithMetaData ) === null;
		};

		$prepareInsertAttachmentsMetaValues = function ( $attachmentId ) use ( $wpmlMediaProcessedMetaValue ) {
			return [ $wpmlMediaProcessedMetaValue, self::WPML_MEDIA_PROCESSED_META_KEY, $attachmentId ];
		};


		for ( $i = 0; $i < $attachmentsProcessingLoopRounds; $i ++ ) {

			$attachmentsWithMetaData = $this->wpdb->get_results( $limitedAttachmentsWithMetaDataQueryPrepared );

			if ( is_array( $attachmentsWithMetaData ) && count( $attachmentsWithMetaData ) ) {

				list( $notExistingMetaAttachmentIds, $existingAttachmentsWithMetaData ) = \WPML\FP\Lst::partition( $attachmentHasNoMetaData, $attachmentsWithMetaData );

				if ( is_array( $notExistingMetaAttachmentIds ) && count( $notExistingMetaAttachmentIds ) ) {


					$notExistingAttachmentsIds = \WPML\FP\Lst::pluck( 'ID', $notExistingMetaAttachmentIds );

					$attachmentMetaValuesPlaceholders = implode( ',', \WPML\FP\Lst::repeat( '(%d, %s, %d)', count( $notExistingAttachmentsIds ) ) );

					$insertAttachmentsMetaQuery = "INSERT INTO {$this->wpdb->postmeta} (meta_value, meta_key, post_id) VALUES ";
					$insertAttachmentsMetaQuery .= $attachmentMetaValuesPlaceholders;

					$insertAttachmentsMetaValues = array_map( $prepareInsertAttachmentsMetaValues, $notExistingAttachmentsIds );
					$insertAttachmentsMetaValues = array_merge( ...$insertAttachmentsMetaValues );

					$insertAttachmentsMetaQuery = $this->wpdb->prepare( $insertAttachmentsMetaQuery, $insertAttachmentsMetaValues );
					$this->wpdb->query( $insertAttachmentsMetaQuery );
				}

				if ( count( $existingAttachmentsWithMetaData ) ) {


					$existingAttachmentsIds = \WPML\FP\Lst::pluck( 'ID', $existingAttachmentsWithMetaData );

					$attachmentsIn = wpml_prepare_in( $existingAttachmentsIds, '%d' );

					$updateAttachmentsMetaQuery = $this->wpdb->prepare( "UPDATE {$this->wpdb->postmeta} SET meta_value = %d WHERE post_id IN ({$attachmentsIn})",
						[
							$wpmlMediaProcessedMetaValue,
						]
					);

					$this->wpdb->query( $updateAttachmentsMetaQuery );
				}
			} else {

				break;
			}

		}

		Option::setSetupFinished();

		/* translators: Message shown when a task on the media translation screen has finished. */
		$response['message'] = __( 'Done!', 'sitepress' );

		if ( $outputResult ) {
			wp_send_json( $response );
		}
	}

	public function create_duplicated_media( $attachment ) {
		static $parents_processed = array();

		if ( $attachment->post_parent && ! in_array( $attachment->post_parent, $parents_processed ) ) {
			$wpdb = $this->wpdb;

			$post_type = $wpdb->get_var(
				$wpdb->prepare(
					"SELECT post_type FROM {$wpdb->posts} WHERE ID = %d",
					array( $attachment->post_parent )
				)
			);
			$trid      = $wpdb->get_var(
				$wpdb->prepare(
					"SELECT trid FROM {$wpdb->prefix}icl_translations WHERE element_id=%d AND element_type = %s",
					array(
						$attachment->post_parent,
						'post_' . $post_type,
					)
				)
			);
			if ( $trid ) {

				$attachments = $wpdb->get_col(
					$wpdb->prepare(
						"SELECT ID FROM {$wpdb->posts} WHERE post_type = %s AND post_parent = %d",
						array(
							'attachment',
							$attachment->post_parent,
						)
					)
				);

				$translations = $this->sitepress->get_element_translations( $trid, 'post_' . $post_type );
				foreach ( $translations as $translation ) {
					if ( \WPML\LanguageEditor\TranslationPause::isPaused( $translation->language_code ) ) {
						continue;
					}

					if ( $translation->element_id && $translation->element_id != $attachment->post_parent ) {

						$attachments_in_translation = $wpdb->get_col(
							$wpdb->prepare(
								"SELECT ID FROM {$wpdb->posts} WHERE post_type = %s AND post_parent = %d",
								array(
									'attachment',
									$translation->element_id,
								)
							)
						);
						if ( sizeof( $attachments_in_translation ) == 0 ) {
							foreach ( $attachments as $attachment_id ) {
								self::create_duplicate_attachment( $attachment_id, $translation->element_id, $translation->language_code );
							}
						}
					}
				}
			}

			$parents_processed[] = $attachment->post_parent;

		} else {

			$target_language = $this->sitepress->get_default_language();

			$trid = $this->sitepress->get_element_trid( $attachment->ID, 'post_attachment' );
			if ( $trid ) {
				$target_language = $this->sitepress->get_language_for_element( $attachment->ID, 'post_attachment' );
			}

			$this->sitepress->set_element_language_details( $attachment->ID, 'post_attachment', $trid, $target_language );

		}

		$source_element_id = SitePress::get_original_element_id_by_trid( $trid );
		$post_type         = get_post_type( (int) $source_element_id );
		if ( $source_element_id && 'attachment' === $post_type ) {
			$this->update_attachment_metadata( $source_element_id );
		}

		update_post_meta( $attachment->ID, 'wpml_media_processed', 1 );
	}

	function set_content_defaults_prepare() {

		$nonce = isset( $_POST['nonce'] ) ? sanitize_text_field( $_POST['nonce'] ) : '';

		if ( ! wp_verify_nonce( $nonce, 'wpml_media_set_content_prepare' ) ) {
			/* translators: Error message returned when a request from the browser cannot be trusted and is turned away. */
			wp_send_json_error( esc_html__( 'Invalid request!', 'sitepress' ) );
		}

		/* translators: Message shown while the media settings are being saved; three dots show that it is still working. */
		$response = array( 'message' => __( 'Saving settings...', 'sitepress' ) );
		echo wp_json_encode( $response );
		exit;
	}

	public function wpml_media_set_content_defaults() {

		$nonce = isset( $_POST['nonce'] ) ? sanitize_text_field( $_POST['nonce'] ) : '';

		if ( ! wp_verify_nonce( $nonce, 'wpml_media_set_content_defaults' ) ) {
			/* translators: Error message returned when a request from the browser cannot be trusted and is turned away. */
			wp_send_json_error( esc_html__( 'Invalid request!', 'sitepress' ) );
		}

		$this->set_content_defaults();

	}

	private function set_content_defaults() {

		$always_translate_media = $_POST['always_translate_media'];
		$duplicate_media        = $_POST['duplicate_media'];
		$duplicate_featured     = $_POST['duplicate_featured'];
		$translateMediaLibraryTexts     = \WPML\API\Sanitize::stringProp('translate_media_library_texts', $_POST);

		$content_defaults_option = [
			'always_translate_media' => $always_translate_media == 'true',
			'duplicate_media'        => $duplicate_media == 'true',
			'duplicate_featured'     => $duplicate_featured == 'true',
		];

		Option::setNewContentSettings( $content_defaults_option );

		$settings                         = get_option( '_wpml_media' );
		$settings['new_content_settings'] = $content_defaults_option;
		$settings['translate_media_library_texts'] = $translateMediaLibraryTexts === 'true';

		update_option( '_wpml_media', $settings );

		$response = [
			'result'  => true,
			/* translators: Message shown when a task has finished. */
			'message' => __( 'Done', 'sitepress' ),
		];
		wp_send_json_success( $response );
	}

	private function is_mt_homepage_screen() {
		return isset( $_GET['page'] ) && 'wpml-media' === $_GET['page'];
	}

	private function should_show_admin_notice_for_elementor_on_mt_homepage() {
		return (
			$this->is_mt_homepage_screen() &&
			! Option::isAdminNoticeForElementorOnMtHomepageDismissed() &&
			is_admin() &&
			is_plugin_active( 'elementor/elementor.php' )
		);
	}

	public function maybe_render_admin_notices() {
		// page-namespace constant, which core defines on every license (wpmldev-8159).
		if ( ! \WPML\Plugins::isTMLoadedForRequest() || ! defined( 'WPML_TM_VERSION' ) || ! defined( 'WPML_TM_URL' ) ) {
			return;
		}

		if ( $this->should_show_admin_notice_for_elementor_on_mt_homepage() ) {
			wp_enqueue_style( 'otgs-notices' );
			$this->render_admin_notice_for_elementor_on_mt_homepage();
		}

		if ( Option::shouldHandleMediaAuto() ) {
			return;
		}

		$screen = get_current_screen();
		if ( ! is_admin() || ! $screen ) {
			return;
		}

		$excluded_bases = [
			'post',
		];

		if ( in_array( $screen->base, $excluded_bases, true ) ) {
			return;
		}

		if ( Option::shouldShowHandleMediaAutoNotice30DaysAfterUpgrade() ) {
			wp_enqueue_style( 'otgs-notices' );
			$this->render_admin_notice_about_automatic_media_detection();
		}
	}

	private function render_admin_notice_about_automatic_media_detection() {
		$media_translation_doc_url = 'https://wpml.org/documentation/translating-your-contents/media/';
		$media_translation_url = class_exists( '\\WPML\\OutboundLinks\\OutboundLinks' )
			? \WPML\OutboundLinks\OutboundLinks::to(
				$media_translation_doc_url,
				array(
					'medium'   => 'notice',
					'campaign' => 'media-translation',
				)
			)
			: $media_translation_doc_url;
		?>
		<div class="wpml-notices-list">
			<div id="admin_banner_about_automatic_media_detection_after_30_days" class="warning notice-warning otgs-notice wpml-notices-list-notice">
				<button class="dismiss-button dismiss-button-in-top-right" aria-label="<?php echo /* translators: Screen reader name of the button that closes a banner. */ esc_attr__( 'Close banner', 'sitepress' ); ?>"><span class="otgs-ico otgs-ico-cancel"></span></button>
				<p><?php echo esc_html__( 'We strongly recommend enabling WPML\'s automatic detection for image texts to avoid duplicated media fields and missing translations.', 'sitepress' ); ?>
					<a href="<?php echo esc_url( $media_translation_url ); ?>" class="external-link"><?php echo /* translators: Link text that opens a page on wpml.org explaining the notice above it. Verb phrase, imperative. */ esc_html__( 'Learn more', 'sitepress' ); ?></a></p>
				<a href="<?php echo esc_url( $this->get_media_settings_link() ); ?>">
					<button class="wpml-button base-btn button-with-progress button-horizontal-padding">
						<span class="button-text"><?php echo /* translators: Button label in a banner on the media translation screen; "it" is the setting the banner is about. Verb, imperative. */ esc_html__( 'Enable it now', 'sitepress' ); ?></span>
					</button>
				</a>
			</div>
		</div>
		<?php
	}

	public function ajax_dismiss_should_handle_media_auto_notice() {
		$nonce = isset( $_POST['nonce'] ) ? sanitize_text_field( $_POST['nonce'] ) : '';

		if ( ! wp_verify_nonce( $nonce, 'wpml_media_dismiss_should_handle_media_auto_notice' ) ) {
			/* translators: Error message returned when a request from the browser cannot be trusted and is turned away. */
			wp_send_json_error( esc_html__( 'Invalid request!', 'sitepress' ) );
		}

		Option::removeShouldShowHandleMediaAutoNotice30DaysAfterUpgrade();

		wp_send_json( [] );
	}

	private function render_admin_notice_for_elementor_on_mt_homepage() {
		?>
		<div class="wpml-notices-list">
			<div id="admin_banner_for_elementor_on_mt_homepage" class="warning notice-warning otgs-notice wpml-notices-list-notice">
				<button class="dismiss-button dismiss-button-in-top-right" aria-label="<?php echo /* translators: Screen reader name of the button that closes a banner. */ esc_attr__( 'Close banner', 'sitepress' ); ?>"><span class="otgs-ico otgs-ico-cancel"></span></button>
				<p><?php echo esc_html__( 'As this site uses Elementor, changes you make here might not be visible on the front-end. In this case, go to Elementor settings and clear its cache.', 'sitepress' ); ?>
			</div>
		</div>
		<?php
	}

	public function ajax_dismiss_admin_notice_for_elementor_on_mt_homepage_notice() {
		$nonce = isset( $_POST['nonce'] ) ? sanitize_text_field( $_POST['nonce'] ) : '';

		if ( ! wp_verify_nonce( $nonce, 'wpml_media_dismiss_admin_notice_for_elementor_on_mt_homepage_notice' ) ) {
			/* translators: Error message returned when a request from the browser cannot be trusted and is turned away. */
			wp_send_json_error( esc_html__( 'Invalid request!', 'sitepress' ) );
		}

		Option::setIsAdminNoticeForElementorOnMtHomepageDismissed();

		wp_send_json( [] );
	}

	private function get_media_settings_link() {
		return WPML_Admin_URL::multilingual_setup( 'media' );
	}
}
