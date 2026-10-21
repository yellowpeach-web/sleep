<?php

require_once __DIR__ . '/constants-since-5-0.php';

use WPML\TM\Editor\ClassicEditorActions;
use WPML\TM\Jobs\Query\CompositeQuery;
use WPML\TM\Jobs\Query\LimitQueryHelper;
use WPML\TM\Jobs\Query\OrderQueryHelper;
use WPML\TM\Jobs\Query\PackageQuery;
use WPML\TM\Jobs\Query\PostQuery;
use WPML\TM\Jobs\Query\QueryBuilder;
use WPML\TM\Jobs\Query\StringsBatchQuery;
use WPML\TM\Jobs\Query\TaxonomyQuery;
use WPML\TM\Settings\RequestSettings;
use WPML\FP\Obj;
use function WPML\Container\make;
use \WPML\Setup\Option as SetupOptions;

if ( \WPML\Plugins::latchTMLoadedForRequest(
	! \WPML\Plugins::isTMActive() && ( ! wpml_is_setup_complete() || false !== SetupOptions::isTMAllowed() )
) ) {

	function wpml_tm_load_element_translations() {
		global $wpml_tm_element_translations, $wpdb, $wpml_post_translations, $wpml_term_translations;

		if ( ! isset( $wpml_tm_element_translations ) && defined( 'WPML_TM_VERSION' ) ) {
			require_once WPML_TM_PATH . '/inc/core/wpml-tm-element-translations.class.php';
			$tm_records                   = new WPML_TM_Records( $wpdb, $wpml_post_translations, $wpml_term_translations );
			$wpml_tm_element_translations = new WPML_TM_Element_Translations( $tm_records );
			$wpml_tm_element_translations->init_hooks();
		}

		return $wpml_tm_element_translations;
	}

	function wpml_tm_load_status_display_filter() {
		global $wpml_tm_status_display_filter, $iclTranslationManagement, $sitepress, $wpdb;

		$blog_translators = wpml_tm_load_blog_translators();
		$tm_api           = new WPML_TM_API( $blog_translators, $iclTranslationManagement );
		$tm_api->init_hooks();
		if ( ! isset( $wpml_tm_status_display_filter ) ) {
			$status_helper                 = wpml_get_post_status_helper();
			$job_factory                   = wpml_tm_load_job_factory();
			$wpml_tm_status_display_filter = new WPML_TM_Translation_Status_Display(
				$wpdb,
				$sitepress,
				$status_helper,
				$job_factory,
				$tm_api,
				make( WPML\TM\ATE\TranslateEverything\UntranslatedPosts::class )
			);
		}

		$wpml_tm_status_display_filter->init();
	}

	function wpml_tm_page_builders_hooks() {
		static $page_builder_hooks;
		if ( ! $page_builder_hooks ) {
			global $sitepress;
			$page_builder_hooks = new WPML_TM_Page_Builders_Hooks( null, $sitepress );
		}

		return $page_builder_hooks;
	}

	function wpml_tm_custom_xml_factory() {
		static $tm_custom_xml_factory;
		if ( ! $tm_custom_xml_factory ) {
			$tm_custom_xml_factory = new WPML_Custom_XML_Factory();
		}

		return $tm_custom_xml_factory;
	}

	function wpml_tm_custom_xml_ui_hooks() {
		static $tm_custom_xml_ui_hooks;
		if ( ! $tm_custom_xml_ui_hooks ) {
			global $sitepress;
			$factory = wpml_tm_custom_xml_factory();
			if ( $factory ) {
				$tm_custom_xml_ui_hooks = new WPML_Custom_XML_UI_Hooks( $factory->create_resources( $sitepress->get_wp_api() ) );
			}
		}

		return $tm_custom_xml_ui_hooks;
	}

	function wpml_ui_screen_options_factory() {
		static $screen_options_factory;
		if ( ! $screen_options_factory ) {
			global $sitepress;
			$screen_options_factory = new WPML_UI_Screen_Options_Factory( $sitepress );
		}

		return $screen_options_factory;
	}

	function wpml_tm_loader() {
		static $tm_loader;
		if ( ! $tm_loader ) {
			$tm_loader = new WPML_TM_Loader();
		}

		return $tm_loader;
	}

	function wpml_tm_translator() {
		static $tm_translator;
		if ( ! $tm_translator ) {
			$tm_translator = new WPML_TP_Translator();
		}

		return $tm_translator;
	}

	function wpml_translation_management() {
		global $WPML_Translation_Management;
		if ( ! $WPML_Translation_Management ) {
			global $sitepress;
			$WPML_Translation_Management = new WPML_Translation_Management( $sitepress, wpml_tm_loader(), wpml_load_core_tm(), wpml_tm_translator() );
		}

		return $WPML_Translation_Management;
	}

	function wpml_tm_load_cli() {
		tm_after_load();

		if ( ! defined( 'ICL_SITEPRESS_VERSION' ) || ( defined( 'ICL_PLUGIN_INACTIVE' ) && ICL_PLUGIN_INACTIVE ) ) {
			return;
		}

		add_action( 'wpml_save_custom_field_translation_option', 'wpml_tm_cli_save_custom_field_translation_option', 10, 2 );
		add_filter( 'wpml_config_white_list_pages', 'wpml_tm_cli_config_white_list_pages' );
		do_action( 'wpml_tm_loaded' );
	}

	function wpml_tm_cli_save_custom_field_translation_option( $custom_field_name, $translation_option ) {
		wpml_translation_management()->wpml_save_custom_field_translation_option( $custom_field_name, $translation_option );
	}

	function wpml_tm_cli_config_white_list_pages( array $white_list_pages ) {
		return wpml_translation_management()->filter_wpml_config_white_list_pages( $white_list_pages );
	}

	function wpml_tm_load_tp_networking() {
		global $wpml_tm_tp_networking;

		if ( ! isset( $wpml_tm_tp_networking ) ) {
			$tp_lock_factory       = new WPML_TP_Lock_Factory();
			$wpml_tm_tp_networking = new WPML_Translation_Proxy_Networking( new WP_Http(), $tp_lock_factory->create() );
		}

		return $wpml_tm_tp_networking;
	}

	function wpml_tm_load_blog_translators() {
		static $instance;

		if ( ! $instance ) {
			$instance = ( new WPML_TM_Blog_Translators_Factory() )->create();
		}

		return $instance;
	}

	function wpml_tm_get_translators_dropdown() {
		static $instance;

		if ( ! $instance ) {
			$instance = new WPML_TM_Translators_Dropdown( wpml_tm_load_blog_translators() );
		}

		return $instance;
	}

	function wpml_tm_init_mail_notifications() {
		global $wpml_tm_mailer, $sitepress, $wpdb, $iclTranslationManagement, $wp_api;

		if ( null === $wp_api ) {
			$wp_api = new WPML_WP_API();
		}

		if ( is_admin() && defined( 'WPML_TM_VERSION' ) ) {
			$blog_translators            = wpml_tm_load_blog_translators();
			$email_twig_factory          = new WPML_TM_Email_Twig_Template_Factory();
			$batch_report                = new WPML_TM_Batch_Report( $blog_translators, $wpdb );
			$batch_report_email_template = new WPML_TM_Email_Jobs_Summary_View(
				$email_twig_factory->create(),
				$blog_translators,
				$sitepress
			);
			$batch_report_email_builder  = new WPML_TM_Batch_Report_Email_Builder(
				$batch_report,
				$batch_report_email_template
			);
			$batch_report_email_process  = new WPML_TM_Batch_Report_Email_Process(
				$batch_report,
				$batch_report_email_builder
			);
			$batch_report_hooks          = new WPML_TM_Batch_Report_Hooks( $batch_report, $batch_report_email_process );
			$batch_report_hooks->add_hooks();

			$user_jobs_notification_settings = new WPML_User_Jobs_Notification_Settings();
			$user_jobs_notification_settings->add_hooks();

			$email_twig_factory    = new WPML_Twig_Template_Loader( array( WPML_TM_PATH . '/templates/user-profile/' ) );
			$notification_template = new WPML_User_Jobs_Notification_Settings_Template( $email_twig_factory->get_template() );

			$user_jobs_notification_settings_render = new WPML_User_Jobs_Notification_Settings_Render( $notification_template );
			$user_jobs_notification_settings_render->add_hooks();
		}

		if ( ! isset( $wpml_tm_mailer ) ) {
			$settings = RequestSettings::tmNotificationSettings();

			$email_twig_factory      = new WPML_TM_Email_Twig_Template_Factory();
			$email_notification_view = new WPML_TM_Email_Notification_View( $email_twig_factory->create() );

			$has_active_remote_service = TranslationProxy::is_current_service_active_and_authenticated();

			$wpml_tm_mailer = new WPML_TM_Mail_Notification(
				$sitepress,
				$wpdb,
				wpml_tm_load_job_factory(),
				$email_notification_view,
				$settings,
				$has_active_remote_service
			);
		}
		$wpml_tm_mailer->init();

		return $wpml_tm_mailer;
	}

	function wpml_tm_load_tm_dashboard_ajax() {
		global $sitepress;

		if ( defined( 'OTG_TRANSLATION_PROXY_URL' ) && defined( 'ICL_SITEPRESS_VERSION' ) ) {
			$wpml_tp_api = wpml_tm_get_tp_project_api();

			$wpml_tp_api_ajax = new WPML_TP_Refresh_Language_Pairs( $wpml_tp_api );
			$wpml_tp_api_ajax->add_hooks();

			$sync_jobs_ajax_handler = new WPML_TP_Sync_Ajax_Handler(
				wpml_tm_get_tp_sync_jobs(),
				new WPML_TM_Last_Picked_Up( $sitepress )
			);
			$sync_jobs_ajax_handler->add_hooks();
		}
	}

	function wpml_tm_load_and_intialize_dashboard_ajax() {
		if ( defined( 'ICL_SITEPRESS_VERSION' ) ) {
			if ( defined( 'DOING_AJAX' ) ) {
				wpml_tm_load_tm_dashboard_ajax();
			}
		}
	}

	add_action( 'plugins_loaded', 'wpml_tm_load_and_intialize_dashboard_ajax' );

	add_action( 'admin_init', array( '\WPML\PostHog\DashboardSessionCounter', 'maybeCount' ) );

	add_action( 'admin_init', array( '\WPML\PostHog\RetryPendingOptOutStop', 'handle' ) );

	add_action( 'wpml_set_translate_everything', array( '\WPML\PostHog\StopOptOutOnTeaEnabled', 'handle' ) );

	function wpml_tm_load_job_factory() {
		global $wpml_translation_job_factory, $wpdb, $wpml_post_translations, $wpml_term_translations;

		if ( ! $wpml_translation_job_factory ) {
			$tm_records                   = new WPML_TM_Records( $wpdb, $wpml_post_translations, $wpml_term_translations );
			$wpml_translation_job_factory = new WPML_Translation_Job_Factory( $tm_records );
			$wpml_translation_job_factory->init_hooks();
		}

		return $wpml_translation_job_factory;
	}

	function wpml_tm_xliff_factory() {
		static $xliff_factory;

		if ( ! $xliff_factory ) {
			$xliff_factory = new WPML_TM_XLIFF_Factory();
		}

		return $xliff_factory;
	}

	function wpml_tm_xliff_shortcodes() {
		static $xliff_shortcodes;

		if ( ! $xliff_shortcodes ) {
			$xliff_shortcodes = new WPML_TM_XLIFF_Shortcodes();
		}

		return $xliff_shortcodes;
	}

	function wpml_tm_load_old_jobs_editor() {
		static $instance;

		if ( ! $instance ) {
			$instance = new WPML_TM_Old_Jobs_Editor( wpml_tm_load_job_factory() );
		}

		return $instance;
	}

	function tm_after_load() {
		global $wpml_tm_translation_status, $wpdb, $wpml_post_translations, $wpml_term_translations;

		if ( ! isset( $wpml_tm_translation_status ) && defined( 'WPML_TM_VERSION' ) ) {
			require_once WPML_TM_PATH . '/inc/translation-proxy/translationproxy.class.php';

			if ( ! wpml_is_cli() || is_admin() ) {
				( new ClassicEditorActions() )->addHooks();
			}

			wpml_tm_load_job_factory();
			wpml_tm_init_mail_notifications();
			wpml_tm_load_element_translations();
			$wpml_tm_translation_status = make( WPML_TM_Translation_Status::class );
			$wpml_tm_translation_status->init();
			add_action( 'wpml_pre_status_icon_display', 'wpml_tm_load_status_display_filter' );
			require_once WPML_TM_PATH . '/inc/wpml-private-actions-tm.php';
		}
	}

	function wpml_tm_get_records() {
		global $wpdb, $wpml_post_translations, $wpml_term_translations;

		return new WPML_TM_Records( $wpdb, $wpml_post_translations, $wpml_term_translations );
	}

	function setup_xliff_frontend() {
		global $xliff_frontend;

		$xliff_factory  = new WPML_TM_XLIFF_Factory();
		$xliff_frontend = $xliff_factory->create_frontend();

		add_action( 'init', array( $xliff_frontend, 'init' ), $xliff_frontend->get_init_priority() );

		return $xliff_frontend;
	}

	function wpml_tm_create_ATE_job_creation_model( $job_id, $applyTranslationMemoryForCompletedJobs = true ) {
		$job_factory     = wpml_tm_load_job_factory();
		$translation_job = $job_factory->get_translation_job( $job_id, false, 0, true );

		if ( ! $translation_job ) {
			return null;
		}

		$rid = \WPML\TM\API\Job\Map::fromJobId( $job_id );

		$job             = new WPML_TM_ATE_Models_Job_Create();
		$job->id         = $job_id;
		$job->source_id  = $rid;
		$job->element_id = $translation_job->get_original_element_id();

		$translationId  = $translation_job->get_translation_id();
		$previousStatus = is_numeric( $translationId )
			? \WPML\Translation\PreviousStateServiceFactory::create()->get( (int) $translationId )
			: null;

		$stillAtesOwnJob = WPML_TM_Editors::ATE === $translation_job->get_basic_data_property( 'editor' );

		if ( $previousStatus && ICL_TM_ATE_CANCELLED === (int) $previousStatus['status'] && $stillAtesOwnJob ) {
			wpml_tm_load_job_factory()->update_job_data( $job_id, array( 'editor' => WPML_TM_Editors::ATE ) );
			$job->existing_ate_id = make( \WPML\TM\ATE\JobRecords::class )->get_ate_job_id( $job_id );
		} else {
			$completedTranslationService = ( new \WPML\Translation\CompletedTranslationServiceFactory() )->create();

			$hasBeenCompletedBefore = $completedTranslationService->hasJobBeenCompletedBeforeResending( $job_id );
			$isStringBatchJob       = strpos( $translation_job->get_basic_data_property( 'original_post_type' ) ?? '', 'st-batch_' ) === 0;
			$apply_memory           = ( $hasBeenCompletedBefore || $isStringBatchJob ) ? $applyTranslationMemoryForCompletedJobs : true;

			$job->source_language->code = $translation_job->get_source_language_code();
			$job->source_language->name = $translation_job->get_source_language_code( true );
			$job->target_language->code = $translation_job->get_language_code();
			$job->target_language->name = $translation_job->get_language_code( true );
			$job->deadline              = WPML_TM_Job_Deadline::to_timestamp( $translation_job->get_deadline_date() );
			$job->apply_memory          = $apply_memory;
			$job->job_sender            = \WPML\TM\ATE\JobSender\JobSenderRepository::get();

			$wtt = wpml_tm_set_words_to_translate( $job, $job_id, $apply_memory );

			if ( null === $wtt ) {
				return null;
			}

			$job->permalink = '#';
			if ( $translation_job instanceof WPML_Post_Translation_Job ) {
				$originalElementId = $translation_job->get_original_element_id();
				$job->permalink    = get_permalink( $originalElementId );

				$orderingService = \WPML\Translation\AteSyncOrderingServiceFactory::create();
				$tierAndRank     = $orderingService->getTierAndRankForPost( (int) $originalElementId );
				if ( null !== $tierAndRank ) {
					$job->tier = (string) $tierAndRank['tier'];
					$job->rank = $tierAndRank['rank'];
				}
			} elseif ( $isStringBatchJob || $translation_job instanceof WPML_Package_Translation_Job ) {
				$job->tier = '1';
				$job->rank = [ 0 ];
			}

			$job->notify_enabled = true;
			$job->notify_url     = \WPML\TM\ATE\REST\PublicReceive::get_receive_ate_job_url( $job_id );

			$job->site_identifier = wpml_get_site_id( WPML_TM_ATE::SITE_ID_SCOPE );

			$xliff = wpml_tm_get_job_xliff_or_null( $job_id );

			$job->file->type = 'data:application/x-xliff;base64';
			$job->file->name = $translation_job->get_title();

			$job->file->content = null === $xliff ? null : base64_encode( $xliff );

			$job->wpml_evidence_manifest = wpml_tm_create_evidence_manifest( $wtt, $job, $translation_job, $xliff );
		}

		return $job;
	}

	function wpml_tm_create_evidence_manifest( $wtt, $job, $translation_job, $xliff ) {
		$manifest = $wtt ? $wtt->getEvidenceManifest() : null;

		if ( ! $manifest ) {
			return null;
		}

		$data = $manifest->toArray();

		$data['doc'] = array(
			'id'    => (int) $job->element_id,
			'type'  => $translation_job->get_basic_data_property( 'original_post_type' ),
			'title' => $translation_job->get_title(),
			'url'   => $job->permalink,
		);

		$data['xliff_sha256'] = null === $xliff ? null : hash( 'sha256', $xliff );

		\WPML\TM\Jobs\JobLog::add(
			'evidence_manifest',
			array(
				'job_id'       => $job->id,
				'kind'         => $data['kind'],
				'tier'         => $data['tier'],
				'words'        => $data['words'],
				'proof_bytes'  => $data['proof_bytes'],
				'fingerprint'  => $data['fingerprint'],
				'xliff_sha256' => $data['xliff_sha256'],
			)
		);

		return $data;
	}

	function wpml_tm_set_words_to_translate( $job, $job_id, $apply_memory ) {
		try {
			global $wpml_dic;
			$wordsToTranslateService = $wpml_dic->make( \WPML\Core\Component\WordsToTranslate\Application\Service\WordsToTranslateService::class );
			$wtt = $wordsToTranslateService->getForJob( $job_id, ! $apply_memory );

			$job->wpml_words_to_translate_count    = $wtt->getWordsToTranslate();
			$job->wpml_automatic_translation_costs = $wtt->getAutomaticTranslationCosts();
			$job->ate_previous_job_ids             = $wtt->getPreviousAteJobIds();

			wpml_tm_load_job_factory()->update_job_data(
				$job->id,
				array(
					'wpml_words_to_translate_count'    => $job->wpml_words_to_translate_count,
					'wpml_automatic_translation_costs' => $job->wpml_automatic_translation_costs,
				)
			);

			wpml_tm_clear_words_to_translate_failure( $job_id );

			return $wtt;
		} catch ( \Throwable $e ) {
			\WPML\TM\Jobs\JobLog::addError(
				'words_to_translate_failed',
				array(
					'job_id'  => (int) $job_id,
					'message' => $e->getMessage(),
				)
			);
			wpml_tm_record_words_to_translate_failure( $job_id, $e->getMessage() );

			try {
				\WPML\TM\API\Jobs::setStatus( (int) $job_id, ICL_TM_ATE_NEEDS_RETRY );
				wpml_tm_load_old_jobs_editor()->set( $job_id, WPML_TM_Editors::ATE );
			} catch ( \Throwable $retryMarkingError ) {
				\WPML\TM\Jobs\JobLog::addError(
					'words_to_translate_retry_marking_failed',
					array(
						'job_id'  => (int) $job_id,
						'message' => $retryMarkingError->getMessage(),
					)
				);
			}

			return null;
		}
	}

	function wpml_tm_record_words_to_translate_failure( $job_id, $message ) {
		$failures = get_option( 'wpml_words_to_translate_failures', array() );
		if ( ! is_array( $failures ) ) {
			$failures = array();
		}

		$isNewFailure = ! isset( $failures[ (int) $job_id ] );

		$failures[ (int) $job_id ] = array(
			'time'    => $isNewFailure ? time() : (int) ( $failures[ (int) $job_id ]['time'] ?? time() ),
			'message' => (string) $message,
		);

		if ( count( $failures ) > 100 ) {
			uasort(
				$failures,
				function ( $a, $b ) {
					return $a['time'] - $b['time'];
				}
			);
			$failures = array_slice( $failures, count( $failures ) - 100, null, true );
		}

		update_option( 'wpml_words_to_translate_failures', $failures, false );
		wpml_tm_refresh_words_to_translate_failure_notice( $failures, $isNewFailure );
	}

	function wpml_tm_clear_words_to_translate_failure( $job_id ) {
		$failures = get_option( 'wpml_words_to_translate_failures', array() );

		if ( ! is_array( $failures ) || ! isset( $failures[ (int) $job_id ] ) ) {
			return;
		}

		unset( $failures[ (int) $job_id ] );
		update_option( 'wpml_words_to_translate_failures', $failures, false );
		wpml_tm_refresh_words_to_translate_failure_notice( $failures );
	}

	function wpml_tm_sync_words_to_translate_failure_notice() {
		$failures = get_option( 'wpml_words_to_translate_failures', array() );
		$failures = is_array( $failures ) ? $failures : array();

		$pruned = wpml_tm_prune_words_to_translate_failures( $failures );
		if ( $pruned !== $failures ) {
			update_option( 'wpml_words_to_translate_failures', $pruned, false );
		}

		wpml_tm_refresh_words_to_translate_failure_notice( $pruned );
	}
	add_action( 'admin_init', 'wpml_tm_sync_words_to_translate_failure_notice' );

	function wpml_tm_prune_words_to_translate_failures( $failures ) {
		if ( ! $failures ) {
			return $failures;
		}

		$maxAgeInSeconds = 30 * DAY_IN_SECONDS;
		$now             = time();

		global $wpdb;
		$jobIds       = array_map( 'intval', array_keys( $failures ) );
		$placeholders = implode( ',', array_fill( 0, count( $jobIds ), '%d' ) );

		$heldJobIds = $wpdb->get_col(
			$wpdb->prepare(
				"SELECT j.job_id
				 FROM {$wpdb->prefix}icl_translate_job j
				 LEFT JOIN {$wpdb->prefix}icl_translation_status s ON s.rid = j.rid
				 WHERE j.job_id IN ( $placeholders )
				   AND j.translated = 0
				   AND ( s.status IS NULL OR s.status NOT IN ( %d, %d, %d, %d, %d ) )
				   AND j.job_id = (
				       SELECT MAX( j2.job_id )
				       FROM {$wpdb->prefix}icl_translate_job j2
				       WHERE j2.rid = j.rid
				   )",
				array_merge(
					$jobIds,
					array( ICL_TM_COMPLETE, ICL_TM_DUPLICATE, ICL_TM_ATE_CANCELLED, ICL_TM_NOT_TRANSLATED, ICL_TM_ATE_UNSOLVABLE )
				)
			)
		);

		$heldJobIds = array_map( 'intval', $heldJobIds );

		return array_filter(
			$failures,
			function ( $failure, $jobId ) use ( $heldJobIds, $now, $maxAgeInSeconds ) {
				if ( ! in_array( (int) $jobId, $heldJobIds, true ) ) {
					return false;
				}

				return ( $now - (int) ( $failure['time'] ?? 0 ) ) <= $maxAgeInSeconds;
			},
			ARRAY_FILTER_USE_BOTH
		);
	}

	function wpml_tm_refresh_words_to_translate_failure_notice( $failures, $resetDismissal = false ) {
		$notices = wpml_get_admin_notices();

		if ( ! $failures ) {
			$notices->remove_notice( 'wpml-tm', 'words-to-translate-failed' );
			return;
		}

		$text = '<p>' . sprintf(
			/* translators: %d is the number of translation jobs that were held back. */
			_n(
				'WPML could not calculate the word count for %d translation job, so it was not sent for translation. WPML retries automatically; you can also resend the content or open the job in the Translation Queue.',
				'WPML could not calculate the word count for %d translation jobs, so they were not sent for translation. WPML retries automatically; you can also resend the content or open the jobs in the Translation Queue.',
				count( $failures ),
				'sitepress'
			),
			count( $failures )
		) . '</p>';

		$notice = $notices->create_notice( 'words-to-translate-failed', $text, 'wpml-tm' );
		$notice->set_css_class_types( 'notice-warning' );
		$notice->set_dismissible( true );
		if ( $resetDismissal ) {
			$notice->reset_dismiss();
		}
		$notices->add_notice( $notice, true );
	}

	function wpml_tm_get_job_xliff( $job_id ) {
		static $xliff_writer;

		if ( ! $xliff_writer ) {
			$job_factory  = wpml_tm_load_job_factory();
			$xliff_writer = new WPML_TM_Xliff_Writer( $job_factory );
		}

		return $xliff_writer->generate_job_xliff( $job_id );
	}

	function wpml_tm_get_job_xliff_or_null( $job_id ) {
		static $xliff_writer;

		if ( ! $xliff_writer ) {
			$job_factory  = wpml_tm_load_job_factory();
			$xliff_writer = ( new WPML_TM_Xliff_Writer( $job_factory ) )->withDeclaredPlaceholders();
		}

		return $xliff_writer->generate_job_xliff_or_null( $job_id );
	}

	function wpml_tm_get_wpml_rest() {
		static $wpml_rest;

		if ( ! $wpml_rest ) {
			$http      = new WP_Http();
			$wpml_rest = new WPML_Rest( $http );
		}

		return $wpml_rest;
	}

	function wpml_tm_get_tp_api_client() {
		static $client;

		if ( ! $client ) {
			$client = new WPML_TP_API_Client(
				OTG_TRANSLATION_PROXY_URL,
				new WP_Http(),
				new WPML_TP_Lock( new WPML_WP_API() ),
				new WPML_TP_HTTP_Request_Filter()
			);
		}

		return $client;
	}

	function wpml_tm_get_tp_project() {
		static $project;

		if ( ! $project ) {
			global $sitepress;

			$translation_service  = $sitepress->get_setting( 'translation_service' );
			$translation_projects = $sitepress->get_setting( 'icl_translation_projects' );
			$project              = new WPML_TP_Project( $translation_service, $translation_projects );
		}

		return $project;
	}

	function wpml_tm_get_tp_jobs_api() {
		static $api;

		if ( ! $api ) {
			$api = new WPML_TP_Jobs_API(
				wpml_tm_get_tp_api_client(),
				wpml_tm_get_tp_project(),
				new WPML_TM_Log()
			);
		}

		return $api;
	}

	function wpml_tm_get_tp_project_api() {
		static $api;

		if ( ! $api ) {
			$api = new WPML_TP_Project_API(
				wpml_tm_get_tp_api_client(),
				wpml_tm_get_tp_project(),
				new WPML_TM_Log()
			);
		}

		return $api;
	}

	function wpml_tm_get_tp_xliff_api() {
		static $api;

		if ( ! $api ) {
			$api = new WPML_TP_XLIFF_API(
				wpml_tm_get_tp_api_client(),
				wpml_tm_get_tp_project(),
				new WPML_TM_Log(),
				new WPML_TP_Xliff_Parser(
					new \WPML_TM_Validate_HTML()
				)
			);
		}

		return $api;
	}

	function wpml_tm_get_jobs_repository( $forceReload = false, $dontCache = false ) {
		static $repository;

		if ( ! $repository || $forceReload ) {
			global $wpdb;

			$limit_helper = new LimitQueryHelper();
			$order_helper = new OrderQueryHelper();

			$subqueries = array(
				new PostQuery( $wpdb, new QueryBuilder( $limit_helper, $order_helper ) ),
				new TaxonomyQuery( $wpdb, new QueryBuilder( $limit_helper, $order_helper ) ),
			);
			if ( wpml_is_st_loaded() && get_option( 'wpml-package-translation-db-updates-run' ) ) {
				$subqueries[] = new PackageQuery(
					$wpdb,
					new QueryBuilder( $limit_helper, $order_helper )
				);
				$subqueries[] = new StringsBatchQuery(
					$wpdb,
					new QueryBuilder( $limit_helper, $order_helper )
				);
			}

			$result = new WPML_TM_Jobs_Repository(
				$wpdb,
				new CompositeQuery(
					$subqueries,
					$limit_helper,
					$order_helper
				),
				new WPML_TM_Job_Elements_Repository( $wpdb )
			);

			if ( $dontCache ) {
				return $result;
			} else {
				$repository = $result;
			}
		}

		return $repository;
	}

	function wpml_tm_reload_jobs_repository() {
		wpml_tm_get_jobs_repository( true );
	}

	add_action( 'wpml_register_string_packages', 'wpml_tm_reload_jobs_repository' );

	function wpml_tm_get_ate_jobs_repository() {
		static $instance;

		if ( ! $instance ) {
			return new WPML_TM_ATE_Job_Repository( wpml_tm_get_jobs_repository(), new \WPML\TM\ATE\Jobs() );
		}

		return $instance;
	}

	function wpml_tm_get_ate_job_records() {
		global $wpdb;
		static $instance;

		if ( ! $instance ) {
			$instance = new WPML\TM\ATE\JobRecords( $wpdb );
		}

		return $instance;
	}

	function wpml_tm_get_tp_sync_jobs() {
		static $sync_jobs;

		if ( ! $sync_jobs ) {
			global $wpdb, $sitepress;

			$sync_jobs = new WPML_TP_Sync_Jobs(
				new WPML_TM_Sync_Jobs_Status( wpml_tm_get_jobs_repository(), wpml_tm_get_tp_jobs_api() ),
				new WPML_TM_Sync_Jobs_Revision( wpml_tm_get_jobs_repository(), wpml_tm_get_tp_jobs_api() ),
				new WPML_TP_Sync_Update_Job( $wpdb, $sitepress )
			);
		}

		return $sync_jobs;
	}

	function wpml_tm_get_tp_translations_repository() {
		static $repository;

		if ( ! $repository ) {
			$repository = new WPML_TP_Translations_Repository(
				wpml_tm_get_tp_xliff_api(),
				wpml_tm_get_jobs_repository()
			);
		}

		return $repository;
	}

	function wpml_tm_get_wp_user_query_factory() {
		static $wp_user_query_factory;

		if ( ! $wp_user_query_factory ) {
			$wp_user_query_factory = new WPML_WP_User_Query_Factory();
		}

		return $wp_user_query_factory;
	}

	function wpml_tm_get_wp_user_factory() {
		static $wp_user_factory;

		if ( ! $wp_user_factory ) {
			$wp_user_factory = new WPML_WP_User_Factory();
		}

		return $wp_user_factory;
	}

	function wpml_tm_get_email_twig_template_factory() {
		static $email_twig_template_factory;

		if ( ! $email_twig_template_factory ) {
			$email_twig_template_factory = new WPML_TM_Email_Twig_Template_Factory();
		}

		return $email_twig_template_factory;
	}

	function wpml_tm_ams_ate_factories() {
		static $tm_ams_ate_factories;

		if ( ! $tm_ams_ate_factories ) {
			$tm_ams_ate_factories = new WPML_TM_AMS_ATE_Factories();
		}

		return $tm_ams_ate_factories;
	}

	function wpml_tm_get_ams_ate_console_url() {
		return admin_url( 'admin.php?page=wpml-ai-translation-billing' );
	}

	function wpml_tm_create_translated_field( $original, $translation, $finished_state ) {
		return new WPML_TM_Translated_Field( $original, $translation, $finished_state );
	}

	function wpml_tm_save_post( $post_id, $post, $force_set_status = false ) {
		global $wpdb, $wpml_post_translations, $wpml_term_translations;

		if ( false === $force_set_status && get_post_meta( $post_id, '_icl_lang_duplicate_of', true ) ) {
			$force_set_status = ICL_TM_DUPLICATE;
		}

		$action_helper    = new WPML_TM_Action_Helper();
		$blog_translators = wpml_tm_load_blog_translators();
		$tm_records       = new WPML_TM_Records( $wpdb, $wpml_post_translations, $wpml_term_translations );
		$save_post_action = new WPML_TM_Post_Actions( $action_helper, $blog_translators, $tm_records );
		if ( 'revision' === $post->post_type || 'auto-draft' === $post->post_status || isset( $_POST['autosave'] ) ) {
			return;
		}
		$save_post_action->save_post_actions( $post_id, $post, $force_set_status );
	}

	add_action( 'wpml_tm_save_post', 'wpml_tm_save_post', 10, 3 );

}

// They are called from classes that autoload on every licence - the AMS/ATE
// licence, where tm.php is skipped, those calls ended in "Call to undefined
if ( ! \WPML\Plugins::isTMActive() && ! function_exists( 'wpml_tm_ate_ams_log' ) ) {

	function wpml_tm_ate_ams_log( WPML\TM\ATE\Log\Entry $entry, $avoidDuplication = false ) {
		make( WPML\TM\ATE\Log\Storage::class )->add( $entry, $avoidDuplication );
	}

	function wpml_tm_ate_ams_log_remove( WPML\TM\ATE\Log\Entry $entry ) {
		make( WPML\TM\ATE\Log\Storage::class )->remove( $entry );
	}
}
