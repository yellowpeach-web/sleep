<?php

use WPML\API\Sanitize;
use WPML\Notices\BlockEditorNotice;
use WPML\TM\API\Jobs;
use WPML\TM\ATE\Release\InFlightChargedJobs;

class WPML_TM_Post_Edit_Notices {

	const TEMPLATE_TRANSLATION_IN_PROGRESS = 'translation-in-progress.twig';
	const TEMPLATE_EDIT_ORIGINAL_TRANSLATION_IN_PROGRESS = 'edit-original-translation-in-progress.twig';
	const TEMPLATE_USE_PREFERABLY_TM_DASHBOARD = 'use-preferably-tm-dashboard.twig';
	const TEMPLATE_USE_PREFERABLY_TE = 'use-preferably-translation-editor.twig';
	const DO_NOT_SHOW_AGAIN_EDIT_ORIGINAL_TRANSLATION_IN_PROGRESS_ACTION = 'wpml_dismiss_post_edit_original_te_notice';
	const DO_NOT_SHOW_AGAIN_USE_PREFERABLY_TE_ACTION = 'wpml_dismiss_post_edit_te_notice';
	const DISPLAY_LIMIT_TRANSLATIONS_IN_PROGRESS = 5;

	private $post_status;

	private $sitepress;

	private $template_render;

	private $super_globals;

	private $status_display;

	private $element_factory;

	private $tm_ate;

	private $translator_name;

	private $translation_service;

	public function __construct(
		WPML_Post_Status $post_status,
		SitePress $sitepress,
		IWPML_Template_Service $template_render,
		WPML_Super_Globals_Validation $super_globals,
		WPML_TM_Translation_Status_Display $status_display,
		WPML_Translation_Element_Factory $element_factory,
		WPML_TM_ATE $tm_ate,
		WPML_TM_Rest_Job_Translator_Name $translator_name,
		WPML_TM_Rest_Jobs_Translation_Service $translation_service
	) {
		$this->post_status            = $post_status;
		$this->sitepress              = $sitepress;
		$this->template_render        = $template_render;
		$this->super_globals          = $super_globals;
		$this->status_display         = $status_display;
		$this->element_factory        = $element_factory;
		$this->tm_ate                 = $tm_ate;
		$this->translator_name		  = $translator_name;
		$this->translation_service	  = $translation_service;
	}

	public function add_hooks() {
		$request_get_trid = isset( $_GET['trid'] ) ?
			filter_var( $_GET['trid'], FILTER_SANITIZE_NUMBER_INT ) :
			'';

		$request_get_post = isset( $_GET['post'] ) ?
			filter_var( $_GET['post'], FILTER_SANITIZE_NUMBER_INT ) :
			'';

		if ( $request_get_trid || $request_get_post ) {
			add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_assets' ) );
			add_action( 'admin_notices', array( $this, 'display_notices' ) );
		}

		\WPML\Request\Adapter\Ajax::register( self::DO_NOT_SHOW_AGAIN_EDIT_ORIGINAL_TRANSLATION_IN_PROGRESS_ACTION, \WPML\Request\Policy\Policy::authenticated( \WPML\Request\Policy\Authenticity::actionNonce( self::DO_NOT_SHOW_AGAIN_EDIT_ORIGINAL_TRANSLATION_IN_PROGRESS_ACTION, 'nonce' ), 'hides a post-edit notice for the caller (own user option)' ), array( $this, 'do_not_display_it_again_to_user' ) );
		\WPML\Request\Adapter\Ajax::register( self::DO_NOT_SHOW_AGAIN_USE_PREFERABLY_TE_ACTION, \WPML\Request\Policy\Policy::capability( 'edit_posts', \WPML\Request\Policy\Authenticity::actionNonce( self::DO_NOT_SHOW_AGAIN_USE_PREFERABLY_TE_ACTION, 'nonce' ) ), array( $this, 'do_not_display_it_again' ) );
	}

	public function enqueue_assets() {
		wp_enqueue_script(
			'wpml-tm-post-edit-alert',
			WPML_TM_URL . '/res/js/post-edit-alert.js',
			array( 'jquery', 'jquery-ui-dialog' ),
			ICL_SITEPRESS_SCRIPT_VERSION
		);
	}

	public function display_notices() {

		$trid    = $this->super_globals->get( 'trid', FILTER_SANITIZE_NUMBER_INT, FILTER_NULL_ON_FAILURE );
		$post_id = $this->super_globals->get( 'post', FILTER_SANITIZE_NUMBER_INT, FILTER_NULL_ON_FAILURE );
		$lang    = $this->super_globals->get( 'lang', FILTER_SANITIZE_FULL_SPECIAL_CHARS, FILTER_NULL_ON_FAILURE );

		if ( ! $post_id ) {
			if ( $trid && $lang && $this->in_flight_ate_job( $trid, $lang ) ) {
				$this->display_save_cancels_ate_job_notice( true );
			}

			return;
		}

		$translations_are_sent_automatically = \WPML\Setup\Option::shouldTranslateEverything();

		$post_element = $this->element_factory->create( $post_id, 'post' );
		$is_original  = ! $post_element->get_source_language_code();

		if ( ! $trid ) {
			$trid = $post_element->get_trid();
		}

		if ( $trid ) {
			$translations_in_progress = $is_original && ! $translations_are_sent_automatically
				? $this->get_translations_in_progress( $post_element )
				: [];

			if (
				! empty( $translations_in_progress ) &&
				$this->should_display_it_to_user( self::DO_NOT_SHOW_AGAIN_EDIT_ORIGINAL_TRANSLATION_IN_PROGRESS_ACTION )
			) {
				$translations_in_progress =
					$this->prepare_translations_for_gui (
						$translations_in_progress
					);

				$msg_stale_job =
					$this->prepare_stale_jobs_for_gui(
						$translations_in_progress
					);

				$model = array(
					'warning'                  => sprintf(
						/* translators: Title of the notice on the post editing screen shown while the translation is being made. %1$s: the opening tag that puts the line in bold, %2$s: its closing tag. */
						__( '%1$sTranslation in progress - wait before editing%2$s', 'sitepress' ),
						'<strong>',
						'</strong>'
					),
					/* translators: Text of the notice on the post editing screen shown while the translation is being made. "It's best" is advice to the reader. */
					'message'                  => __( 'This page that you are editing is being translated right now. If you edit now, some or all of the translation for this page may be missing. It\'s best to wait until translation completes, then edit and update the translation.', 'sitepress' ),
					'translations_in_progress' => [
						'display_limit' => self::DISPLAY_LIMIT_TRANSLATIONS_IN_PROGRESS,
						'translations'  => $translations_in_progress,
						'title'         => __( 'Waiting for translators...', 'sitepress' ),
						/* translators: %d is the number of translations. */
						'more'          => __( '...and %d more translations.', 'sitepress' ),
						'no_translator' => __( 'First available translator', 'sitepress' ),
						'msg_stale_job' => $msg_stale_job,
					],
					'go_back_button'           => __( 'Take me back', 'sitepress' ),
					'edit_anyway_button'       => __( 'I understand - continue editing', 'sitepress' ),
					'do_not_show_again'        => __( "Don't show this warning again", 'sitepress' ),
					'do_not_show_again_action' => self::DO_NOT_SHOW_AGAIN_EDIT_ORIGINAL_TRANSLATION_IN_PROGRESS_ACTION,
					'nonce'                    => wp_nonce_field(
						self::DO_NOT_SHOW_AGAIN_EDIT_ORIGINAL_TRANSLATION_IN_PROGRESS_ACTION,
						self::DO_NOT_SHOW_AGAIN_EDIT_ORIGINAL_TRANSLATION_IN_PROGRESS_ACTION,
						true,
						false
					),
				);

				echo $this->template_render->show( $model, self::TEMPLATE_EDIT_ORIGINAL_TRANSLATION_IN_PROGRESS );
			} elseif (
				! $is_original
				&& in_array( (int) $this->post_status->get_status( $post_id, $trid, $lang ), InFlightChargedJobs::IN_FLIGHT_STATUSES, true )
				&& ! WPML_TM_Post_Edit_TM_Editor_Mode::uses_native_editor( $post_id )
				&& $this->in_flight_ate_job( $trid, $lang )
			) {
				$this->display_save_cancels_ate_job_notice( false );
			} elseif (
				! $translations_are_sent_automatically
				&& $this->is_waiting_for_a_translation( (int) $this->post_status->get_status( $post_id, $trid, $lang ) )
				&& ! WPML_TM_Post_Edit_TM_Editor_Mode::uses_native_editor( $post_id )
			) {
				$model = array(
					'warning' => sprintf(
						/* translators: Warning on the post editing screen when the site is set up to translate in the WPML editor. %1$s: the opening tag that puts the word Warning in bold, %2$s: its closing tag. */
						__( '%1$sWarning:%2$s You are about to edit your translation using the standard WordPress editor but your site is configured to use the WPML Translation Editor. Any edits you do here will be lost if you later open it in the WPML Translation Editor.', 'sitepress' ),
						'<strong>',
						'</strong>'
					),
				);

				$this->render_notice(
					$model,
					self::TEMPLATE_TRANSLATION_IN_PROGRESS,
					array( 'text' => wp_strip_all_tags( $model['warning'] ) )
				);

			} elseif (
				! $is_original &&
				! WPML_TM_Post_Edit_TM_Editor_Mode::uses_native_editor( $post_id ) &&
				apply_filters( 'wpml_tm_show_page_builders_translation_editor_warning', true, $post_id ) &&
			    $this->should_display_it( self::DO_NOT_SHOW_AGAIN_USE_PREFERABLY_TE_ACTION )
			) {

				$model = array(
					'warning_line_1' => sprintf(
						/* translators: Title of the warning shown before leaving the post editing screen when the edits would be lost. %1$s: the opening tag around the word Warning, %2$s: its closing tag. */
						__( '%1$sWarning:%2$s Edits you\'re about to make will be lost', 'sitepress' ),
						'<span>',
						'</span>'
					),
					'warning_line_2' 						=> __( 'You are about to edit this translation using the standard WordPress editor.', 'sitepress' ),
					'warning_line_3' 						=> __( 'Any changes you make will be lost the next time you send this page for translation.', 'sitepress' ),
					/* translators: Button label in that warning: return to the screen the user came from. Verb, imperative. */
					'go_back_button'						=> __( 'Go Back', 'sitepress' ),
					'edit_anyway_button'				=> __( 'Edit Anyway (Not Recommended)', 'sitepress' ),
					'open_in_te_button'					=> __( 'Edit in Advanced Translation Editor', 'sitepress' ),
					'translation_editor_url'		=> $this->get_translation_editor_link( $post_element ),
					'do_not_show_again'					=> __( "Don't show this warning again", 'sitepress' ),
					'do_not_show_again_action'	=> self::DO_NOT_SHOW_AGAIN_USE_PREFERABLY_TE_ACTION,
					'nonce'                  		=> wp_nonce_field(
						self::DO_NOT_SHOW_AGAIN_USE_PREFERABLY_TE_ACTION,
						self::DO_NOT_SHOW_AGAIN_USE_PREFERABLY_TE_ACTION,
						true,
						false
					),
				);

				echo $this->template_render->show( $model, self::TEMPLATE_USE_PREFERABLY_TE );
			}

		} elseif ( ! $translations_are_sent_automatically
		           && $post_element->is_translatable()
		           && ! WPML_TM_Post_Edit_TM_Editor_Mode::uses_native_editor( $post_id )
		           && (int) $post_id !== (int) \WPML_Root_Page::get_root_id()
		) {
			$tm_dashboard_url = admin_url( 'admin.php?page=' . WPML_TM_FOLDER . '/menu/main.php' );

			$model = array(
				'warning' => sprintf(
					/* translators: Warning on the post editing screen when a translation is being started outside the WPML editor. %1$s: the opening tag that puts the word Warning in bold, %2$s: its closing tag. */
					__( '%1$sWarning:%2$s You are trying to add a translation using the standard WordPress editor but your site is configured to use the WPML Translation Editor.', 'sitepress' ),
					'<strong>',
					'</strong>'
				),
				'use_tm_dashboard' => sprintf(
					/* translators: %s: Translation Management Dashboard URL. */
					__( 'You should use <a href="%s">Translation management dashboard</a> to send the original document to translation.', 'sitepress' ),
					$tm_dashboard_url
				),
			);

			$this->render_notice(
				$model,
				self::TEMPLATE_USE_PREFERABLY_TM_DASHBOARD,
				array(
					'text'    => wp_strip_all_tags( $model['warning'] ) . ' ' . wp_strip_all_tags( $model['use_tm_dashboard'] ),
					'actions' => array(
						array(
							'label' => __( 'Translation management dashboard', 'sitepress' ),
							'url'   => $tm_dashboard_url,
						),
					),
				)
			);
		}
	}

	private function in_flight_ate_job( $trid, $lang ) {
		$job = Jobs::getTridJob( (int) $trid, (string) $lang );

		if ( ! is_object( $job ) ) {
			return null;
		}

		$status = isset( $job->status ) ? (int) $job->status : null;
		if ( ! in_array( $status, InFlightChargedJobs::IN_FLIGHT_STATUSES, true ) ) {
			return null;
		}

		$is_ate = isset( $job->editor ) && WPML_TM_Editors::ATE === $job->editor
			&& isset( $job->editor_job_id ) && (int) $job->editor_job_id > 0
			&& empty( $job->translated );

		return $is_ate ? $job : null;
	}

	private function display_save_cancels_ate_job_notice( $is_new_translation ) {
		$warning = $is_new_translation
			? sprintf(
				/* translators: Warning on the screen that creates a translation by hand while WPML's Advanced Translation Editor is still translating it. %1$s: the opening tag that puts the word Warning in bold, %2$s: its closing tag. */
				__( '%1$sWarning:%2$s This translation is in progress in the WPML Translation Editor. If you save a translation here, the translation in progress will be cancelled and you will not be charged for it.', 'sitepress' ),
				'<strong>',
				'</strong>'
			)
			: sprintf(
				/* translators: Warning on the post editing screen of a translation that WPML's Advanced Translation Editor is still translating. %1$s: the opening tag that puts the word Warning in bold, %2$s: its closing tag. */
				__( '%1$sWarning:%2$s A translation of this page is in progress in the WPML Translation Editor. If you save your edits here, the translation in progress will be cancelled and you will not be charged for it. Any edits you do here will be lost if you later open it in the WPML Translation Editor.', 'sitepress' ),
				'<strong>',
				'</strong>'
			);

		$model = array( 'warning' => $warning );

		$this->render_notice(
			$model,
			self::TEMPLATE_TRANSLATION_IN_PROGRESS,
			array( 'text' => wp_strip_all_tags( $warning ) )
		);
	}

	private function render_notice( array $model, $template, array $block_editor_notice ) {
		if ( $this->is_block_editor_screen() ) {
			$this->enqueue_block_editor_notice( $block_editor_notice );

			return;
		}

		echo $this->template_render->show( $model, $template );
	}

	private function is_block_editor_screen() {
		return function_exists( 'get_current_screen' )
			   && class_exists( 'WPML_Block_Editor_Helper' )
			   && WPML_Block_Editor_Helper::is_edit_post();
	}

	private function enqueue_block_editor_notice( array $notice ) {
		BlockEditorNotice::enqueue(
			array(
				'id'            => 'wpml-tm-post-edit-notice',
				'status'        => 'warning',
				'text'          => isset( $notice['text'] ) ? $notice['text'] : '',
				'actions'       => isset( $notice['actions'] ) ? $notice['actions'] : array(),
				'isDismissible' => true,
			)
		);
	}

	public function do_not_display_it_again_to_user() {
		$action = Sanitize::stringProp( 'action', $_POST );
		if( is_string( $action ) && $this->is_valid_request( $action ) ){
			update_user_option( get_current_user_id(), $action, 1 );
		}
	}

	public function do_not_display_it_again() {
		$action = Sanitize::stringProp( 'action', $_POST );
		if( is_string( $action ) && $this->is_valid_request( $action ) ){
			update_option( $action, 1, false );
		}
	}

	private function is_valid_request( $action ) {
		return isset( $_POST['nonce'] ) && wp_verify_nonce( $_POST['nonce'], $action );
	}

	private function should_display_it_to_user( $action ) {
		return false === get_user_option( $action );
	}

	private function should_display_it( $action ) {
		return false === get_option( $action );
	}

	private function get_translations_in_progress( $post_element ) {
		$translations = $this->sitepress->get_element_translations(
			$post_element->get_trid(),
			$post_element->get_wpml_element_type()
		);

		if ( ! is_array( $translations ) || empty( $translations ) ) {
			return [];
		}

		$wpml_element_translations = wpml_tm_load_element_translations();
		$translations_in_progress  = [];

		foreach ( $translations as $translation ) {
			if ( $translation->original ) {
				continue;
			}

			$job = Jobs::getTridJob(
				$post_element->get_trid(),
				$translation->language_code
			);

			if (
				$job
				&& ! $this->is_waiting_for_a_translation( $job->status )
			) {
				continue;
			}

			if (
				$job
				&& 'ate' === $job->editor
				&& array_key_exists( 'referer', $_GET )
				&& 'ate' === $_GET['referer']
				&& $this->tm_ate->is_translation_method_ate_enabled()
				&& $this->tm_ate->is_translation_ready_for_post(
					$post_element->get_trid(),
					$translation->language_code
				)
			) {
				continue;
			}

			if (
				$this->is_waiting_for_a_translation(
					$wpml_element_translations->get_translation_status(
						$post_element->get_trid(),
						$translation->language_code
					)
				)
			) {
				$translations_in_progress[] = $job;
			}
		}

		return $translations_in_progress;
	}

	private function prepare_translations_for_gui( $translations ) {
		$translations = array_map(
			[ $this, 'prepare_translation_for_gui' ],
			$translations
		);

		usort(
			$translations,
			function ( $a, $b ) {
				if ( $a['to_language'] == $b['to_language'] ) {
					return 0;
				}

				return $a['to_language'] > $b['to_language'] ? 1 : - 1;
			}
		);

		return $translations;
	}

	private function prepare_translation_for_gui( $job ) {
		if (
			! is_object( $job )
			|| ! property_exists( $job, 'language_code' )
			|| ! property_exists( $job, 'to_language' )
		) {
			return;
		}

		$since = null;
		if ( property_exists( $job, 'elements' ) && is_array( $job->elements ) ) {
			foreach ( $job->elements as $element ) {
				if (
					! property_exists( $element, 'timestamp' )
					|| ! property_exists( $element, 'field_finished' )
					|| 0 !== (int) $element->field_finished
				) {
					continue;
				}

				$element_since = strtotime( $element->timestamp );

				$since = null === $since || $element_since < $since
					? $element_since
					: $since;
			}
		}

		$is_automatic = property_exists( $job, 'automatic' )
			? (bool) $job->automatic
			: false;

		$editor_job_id = property_exists( $job, 'editor_job_id' )
			? (int) $job->editor_job_id
			: null;

		return [
			'to_language'   => $job->to_language,
			'is_automatic'  => $is_automatic,
			'flag'          => $this->sitepress->get_flag_image( $job->language_code ),
			'translator'    => $this->translator_name_by_job( $job ),
			'since'         => $since,
			'waiting_for'   => $this->waiting_for_x_time( $since ),
			'editor_job_id' => $editor_job_id,
		];
	}

	private function translator_name_by_job( $job ) {
		if (
			property_exists( $job, 'automatic' )
			&& 1 === (int) $job->automatic
		) {
			/* translators: Shown in place of the name of a translator when the translation was made by a machine. */
			return __( 'Automatic translation', 'sitepress' );
		}

		if (
			property_exists( $job, 'translation_service' )
			&& is_numeric( $job->translation_service )
		) {
			return $this->translation_service
				->get_name( $job->translation_service );
		}

		return property_exists( $job, 'translator_id' )
			? $this->translator_name->get( $job->translator_id )
			: null;
	}

	private function waiting_for_x_time( $since_timestamp ) {
		if ( empty( $since_timestamp ) ) {
			return '';
		}

		$since = new \DateTime();
		$since->setTimestamp( $since_timestamp );

		$interval = $since->diff( new \DateTime() );

		if ( $interval->days > 0 ) {
			return sprintf(
				/* translators: How long a translation has been under way, shown inside a notice on the post editing screen, as in "2 days". %d: the number of days. */
				_n( '%d day', '%d days', $interval->days, 'sitepress' ),
				$interval->days
			);
		}

		if ( $interval->h > 0 ) {
			return sprintf(
				/* translators: How long a translation has been under way, shown inside a notice on the post editing screen, as in "3 hours". %d: the number of hours. */
				_n( '%d hour', '%d hours', $interval->h, 'sitepress' ),
				$interval->h
			);
		}

		return sprintf(
			/* translators: How long a translation has been under way, shown inside a notice on the post editing screen, as in "20 minutes". %d: the number of minutes. */
			_n( '%d minute', '%d minutes', $interval->i, 'sitepress' ),
			$interval->i
		);
	}

	private function prepare_stale_jobs_for_gui( &$translations ) {
		$stale_ids = [];
		foreach ( $translations as $k => $translation ) {
			if ( $translation === null || ! $translation['is_automatic'] ) {
				continue;
			}

			if ( empty( $translation['since'] ) ) {
				continue;
			}

			$since = new \DateTime();
			$since->setTimestamp( $translation['since'] );
			$interval = $since->diff( new \DateTime() );

			if ( 0 === $interval->days ) {
				continue;
			}

			$stale_ids[] = $translation['editor_job_id'];

			if (
				count( $translations ) >
					self::DISPLAY_LIMIT_TRANSLATIONS_IN_PROGRESS
			) {
				unset( $translations[ $k ] );
				array_unshift( $translations, $translation );
			}
		}

		if ( empty( $stale_ids ) ) {
			return '';
		}

		return sprintf(
			/* translators: Notice on the post editing screen when an automatic translation stopped moving. %1$1s: the opening tag of a link to WPML support, %2$2s: its closing tag, %3$3s: the numbers that stand for the stuck translations, separated by commas. */
			_n(
				'Something went wrong with automatic translation. Please contact %1$1sWPML support%2$2s and report that the following automatic translation is stuck: %3$3s',
				'Something went wrong with automatic translation. Please contact %1$1sWPML support%2$2s and report that the following automatic translations are stuck: %3$3s',
				count( $stale_ids ),
				'sitepress'
			),
			'<a href="' . esc_url( \WPML\OutboundLinks\OutboundLinks::to( \WPML\UserInterface\Web\Core\SharedKernel\Domain\SupportForumUrl::URL, array( 'medium' => 'post-editor', 'campaign' => 'support' ) ) ) . '" target="_blank">',
			'</a>',
			implode( ', ', $stale_ids )
		);
	}


	private function is_waiting_for_a_translation( $translation_status ) {
		return ! is_null( $translation_status )
		       && $translation_status > 0
		       && $translation_status != ICL_TM_DUPLICATE
		       && $translation_status < ICL_TM_COMPLETE;
	}

	private function get_translation_editor_link( $post_element ) {
		$post_id             = $post_element->get_id();
		$source_post_element = $post_element->get_source_element();

		if ( $source_post_element ) {
			$post_id = $source_post_element->get_id();
		}

		$url = $this->status_display->filter_status_link(
			'#', $post_id, $post_element->get_language_code(), $post_element->get_trid()
		);

		return remove_query_arg( 'return_url', $url );
	}
}
