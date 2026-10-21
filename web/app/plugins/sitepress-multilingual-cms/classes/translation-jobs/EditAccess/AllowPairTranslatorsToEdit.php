<?php

namespace WPML\TM\Jobs\EditAccess;

use WPML\FP\Lst;
use WPML\FP\Obj;
use WPML\TM\API\Translators;

class AllowPairTranslatorsToEdit implements \IWPML_Frontend_Action, \IWPML_Backend_Action {

	public function add_hooks() {
		add_filter( 'wpml_tm_allowed_translators_for_job', [ $this, 'addCurrentTranslatorIfPairAllowed' ], 10, 2 );
	}

	public function addCurrentTranslatorIfPairAllowed( $allowedTranslators, \WPML_Translation_Job $job ) {
		$translator = Translators::getCurrent();

		if (
			$translator->ID
			&& self::canEditPair( $translator, (string) $job->get_source_language_code(), (string) $job->get_language_code() )
		) {
			return array_merge( $allowedTranslators, [ (int) $translator->ID ] );
		}

		return $allowedTranslators;
	}

	private static function canEditPair( $translator, $sourceLang, $targetLang ) {
		$targetsForSource = Obj::pathOr( [], [ 'language_pairs', $sourceLang ], $translator );

		return Lst::includes( $targetLang, $targetsForSource );
	}
}
