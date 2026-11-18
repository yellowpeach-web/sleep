<?php

namespace WPML\TM\ATE\AutoTranslate;

use WPML\FP\Obj;
use WPML\Settings\PostType\Automatic;
use WPML\TM\API\Jobs;
use WPML\TranslationPolicy\Policy\Policy;

class Eligibility extends EligibilityRules {

	protected function postTranslatesAutomatically( $postId ) {
		return $this->shouldUseTMEditor( $postId )
		       && Automatic::shouldTranslate( get_post_type( $postId ) );
	}

	private function shouldUseTMEditor( $postId ) {
		return Policy::translateEverythingGate()->opensOnClick(
			(int) $postId,
			function () use ( $postId ) {
				return ! Policy::listEditor()->usesNativeEditor(
					$postId,
					function () {
						return [];
					}
				);
			}
		);
	}

	protected function isTranslationOpen( $translation ) {
		$jobId = (int) Obj::prop( 'job_id', $translation );

		if ( ! $jobId ) {
			return true;
		}

		return ! wpml_tm_load_old_jobs_editor()->shouldStickToWPMLEditor( $jobId, Jobs::get( $jobId ) );
	}
}
