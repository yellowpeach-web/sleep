<?php

namespace WPML\TM\ATE\AutoTranslate\Endpoint;

use WPML\API\PostTypes;
use WPML\Collect\Support\Collection;
use WPML\FP\Either;
use WPML\FP\Lst;
use WPML\FP\Obj;
use WPML\Setup\Option;
use WPML\TM\API\ATE\LanguageMappings;
use WPML\TM\ATE\TranslateEverything;

class ActivateLanguage {

	private $translateEverything;

	public function __construct( TranslateEverything $translateEverything ) {
		$this->translateEverything = $translateEverything;
	}


	public function run( Collection $data ) {
		$translateExistingContent = $data->get( 'translate-existing-content', false );

		$newLanguages = array_filter( (array) $data->get( 'languages' ), 'is_string' );

		if ( $translateExistingContent ) {
			$isRefusedByATE = function ( $code ) {
				$languageDetails = [ $code => [ 'code' => $code ] ];
				$languageDetails = LanguageMappings::withCanBeTranslatedAutomatically( $languageDetails );

				return false === Obj::pathOr( null, [ $code, 'can_be_translated_automatically' ], $languageDetails );
			};
			list( $newLanguagesWhichCannotBeAutoTranslated, $newLanguagesLeftOpen ) = Lst::partition(
				$isRefusedByATE,
				$newLanguages
			);

			$this->translateEverything->markLanguagesAsUncompleted( $newLanguagesLeftOpen );

			$this->translateEverything->markLanguagesAsCompleted( $newLanguagesWhichCannotBeAutoTranslated );
		} else {
			$this->translateEverything->markLanguagesAsCompleted( $newLanguages );
		}


		return Either::of( 'ok' );
	}
}