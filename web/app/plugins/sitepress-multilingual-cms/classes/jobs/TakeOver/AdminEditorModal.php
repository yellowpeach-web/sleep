<?php

namespace WPML\TM\Jobs\TakeOver;

use WPML\FP\Obj;
use WPML\TM\Jobs\TranslationRecord;
use WPML\TM\Menu\TranslationQueue\TranslationQueuePage;

class AdminEditorModal {

	public static function maybeForRequest( $get ) {
		$jobId = (int) Obj::prop( 'job_id', $get );
		if ( $jobId <= 0 ) {
			return null;
		}

		$job = wpml_tm_load_job_factory()->get_translation_job_as_active_record( TranslationRecord::rowToOpen( $jobId ) );
		if ( ! $job instanceof \WPML_Element_Translation_Job ) {
			return null;
		}

		$payload = Payload::forJob( $job );
		if ( ! $payload ) {
			return null;
		}

		$payload['immediate'] = true;
		$payload['cancelUrl'] = admin_url( TranslationQueuePage::base() );

		add_action( 'admin_enqueue_scripts', function () use ( $payload ) {
			self::enqueue( $payload );
		} );

		return $payload;
	}

	public static function enqueue( array $payload ) {
		wp_enqueue_style( 'wpml-tm-takeover', WPML_TM_URL . '/res/css/takeover.css', array(), ICL_SITEPRESS_SCRIPT_VERSION );
		wp_register_script( 'wpml-tm-takeover', WPML_TM_URL . '/res/js/takeover.js', array(), ICL_SITEPRESS_SCRIPT_VERSION, true );
		wp_localize_script( 'wpml-tm-takeover', 'wpmlTakeOver', $payload );
		wp_enqueue_script( 'wpml-tm-takeover' );
	}
}
