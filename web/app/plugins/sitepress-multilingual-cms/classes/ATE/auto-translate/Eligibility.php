<?php

namespace WPML\TM\ATE\AutoTranslate;

use WPML\Element\API\Languages;
use WPML\FP\Obj;
use WPML\Settings\PostType\Automatic;
use WPML\TM\API\Jobs;
use WPML\TM\ATE\AutomaticTranslationCapabilities;

class Eligibility {

	public function shouldAutoTranslate( $trid, $postId, $targetLang ) {
		global $sitepress;

		if ( ! AutomaticTranslationCapabilities::isAvailable() ) {
			return false;
		}

		$postTranslations = $sitepress->post_translations();

		$isOriginalPost = ! (bool) $postTranslations->get_source_lang_code( $postId );
		$postLanguage   = $postTranslations->get_element_lang_code( $postId );

		return $isOriginalPost
		       && $postLanguage === Languages::getDefaultCode()
		       && $this->shouldUseTMEditor( $postId )
		       && Automatic::shouldTranslate( get_post_type( $postId ) )
		       && AutomaticTranslationCapabilities::isLanguageEligible( $targetLang, $postLanguage )
		       && $this->isExistingTranslationOpenToAte( $trid, $targetLang );
	}

	private function shouldUseTMEditor( $postId ) {
		return ! \WPML_TM_Post_Edit_TM_Editor_Mode::uses_native_editor( $postId );
	}

	private function isExistingTranslationOpenToAte( $trid, $targetLang ) {
		$translation = Jobs::getTridJob( $trid, $targetLang );

		if ( ! $translation ) {
			return true;
		}

		if ( Jobs::isDeliveredAndCurrent( $translation ) ) {
			return false;
		}

		$jobId = (int) Obj::prop( 'job_id', $translation );

		if ( ! $jobId ) {
			return true;
		}

		return ! wpml_tm_load_old_jobs_editor()->shouldStickToWPMLEditor( $jobId, Jobs::get( $jobId ) );
	}
}
