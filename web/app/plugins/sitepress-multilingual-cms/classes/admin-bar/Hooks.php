<?php

namespace WPML\TM\AdminBar;

use WPML\LIB\WP\Nonce;
use WPML\LIB\WP\User;
use WPML\TM\Jobs\TakeOver\Decision;
use WPML\TM\Jobs\TakeOver\Reassign;

class Hooks implements \IWPML_Frontend_Action, \IWPML_DIC_Action {

	private $post_translations;

	public function __construct( \WPML_Post_Translation $postTranslations ) {
		$this->post_translations = $postTranslations;
	}

	public function add_hooks() {
		if ( is_user_logged_in() ) {
			add_action( 'admin_bar_menu', [ $this, 'addTranslateMenuItem' ], 80 );
			add_action( 'wp_enqueue_scripts', [ $this, 'enqueueScripts' ] );
		}
	}

	public function addTranslateMenuItem( \WP_Admin_Bar $wpAdminMenu ) {
		global $wp_the_query;

		$queriedObject = $wp_the_query->get_queried_object();

		if ( ! empty( $queriedObject ) && ! empty( $queriedObject->post_type ) ) {
			wpml_tm_load_status_display_filter();

			$trid = $this->post_translations->get_element_trid( $queriedObject->ID );
			if ( $trid ) {
				$originalID = $this->post_translations->get_original_post_ID( $trid );
				$lang       = $this->post_translations->get_element_lang_code( $queriedObject->ID );

				$translateLink = apply_filters( 'wpml_link_to_translation', '', $originalID, $lang, $trid );

				if ( $translateLink ) {
					$img = '<img class="ab-icon" src="' . ICL_PLUGIN_URL . '/res/img/icon16.svg">';
					$wpAdminMenu->add_menu(
						[
							'id'    => 'translate',
							/* translators: Item in the WordPress toolbar that opens the translation of the page being looked at. */
							'title' => $img . __( 'Edit Translation', 'sitepress' ),
							'href'  => admin_url() . $translateLink,
						]
					);
				}
			}
		}
	}

	public function enqueueScripts() {
		wp_enqueue_style( 'wpml-tm-admin-bar', WPML_TM_URL . '/res/css/admin-bar-style.css', array(), ICL_SITEPRESS_SCRIPT_VERSION );

		$takeOverData = $this->getTakeOverData();
		if ( $takeOverData ) {
			wp_enqueue_style( 'wpml-tm-takeover', WPML_TM_URL . '/res/css/takeover.css', array(), ICL_SITEPRESS_SCRIPT_VERSION );
			wp_register_script( 'wpml-tm-takeover', WPML_TM_URL . '/res/js/takeover.js', array(), ICL_SITEPRESS_SCRIPT_VERSION, true );
			wp_localize_script( 'wpml-tm-takeover', 'wpmlTakeOver', $takeOverData );
			wp_enqueue_script( 'wpml-tm-takeover' );
		}
	}

	private function getTakeOverData() {
		global $wp_the_query, $sitepress, $iclTranslationManagement;

		$queriedObject = $wp_the_query ? $wp_the_query->get_queried_object() : null;
		if ( empty( $queriedObject ) || empty( $queriedObject->post_type ) ) {
			return null;
		}

		$trid = $this->post_translations->get_element_trid( $queriedObject->ID );
		if ( ! $trid ) {
			return null;
		}

		$lang  = $this->post_translations->get_element_lang_code( $queriedObject->ID );
		$jobId = (int) $iclTranslationManagement->get_translation_job_id( $trid, $lang );
		if ( ! $jobId ) {
			return null;
		}

		$job = wpml_tm_load_job_factory()->get_translation_job_as_active_record( $jobId );
		if ( ! $job ) {
			return null;
		}

		if ( User::canManageTranslations() ) {
			return null;
		}

		$assigneeId    = (int) $job->get_translator_id();
		$currentUserId = (int) get_current_user_id();

		if ( Decision::HARD !== Decision::forJob( $job->get_status_value(), $assigneeId, $currentUserId ) ) {
			return null;
		}

		if ( ! $job->user_can_translate( wp_get_current_user() ) ) {
			return null;
		}

		$assignee     = get_userdata( $assigneeId );
		$assigneeName = $assignee ? $assignee->display_name : '';
		$languageName = $sitepress->get_display_language_name( $lang, $sitepress->get_admin_language() );
		$title        = $queriedObject->post_title;

		return array(
			'nodeId'   => 'wp-admin-bar-translate',
			'jobId'    => $jobId,
			'assignee' => $assigneeId,
			'ajaxUrl'  => admin_url( 'admin-ajax.php' ),
			'endpoint' => Reassign::class,
			'nonce'    => Nonce::create( Reassign::class ),
			'strings'  => array(
				'title'         => __( 'Someone is already translating this', 'sitepress' ),
				/* translators: Line in the dialog shown when somebody else is already translating this content. %1$s: the name of that person, %2$s: the title of the content, %3$s: the name of the language. */
				'body'          => sprintf( __( '%1$s is translating %2$s into %3$s.', 'sitepress' ), $assigneeName, $title, $languageName ),
				/* translators: %s: translator name */
				'risk'          => sprintf( __( 'If you take over, the translation is reassigned to you and %s loses any unsaved work.', 'sitepress' ), $assigneeName ),
				/* translators: %s: translator name */
				'ack'           => sprintf( __( 'I understand %s will lose any unsaved work', 'sitepress' ), $assigneeName ),
				/* translators: Button label in that dialog: take the job over from the person who holds it. Verb phrase, imperative. */
				'takeOver'      => __( 'Take over', 'sitepress' ),
				/* translators: Button label that closes a dialog without doing anything, or stops what is going on. Verb, imperative, not the noun "a cancellation". */
				'cancel'        => __( 'Cancel', 'sitepress' ),
				'statusChanged' => __( 'This translation changed since you opened the page. Reloading so you see its current state.', 'sitepress' ),
				'genericError'  => __( 'The translation could not be taken over. Reloading.', 'sitepress' ),
			),
		);
	}
}
