<?php

namespace WPML\StringTranslation\Infrastructure\TranslateEverything;

use WPML\Setup\Option;
use WPML\StringTranslation\Application\StringHtml\Command\ProcessFrontendStringsObserverInterface;
use WPML\TM\AutomaticTranslation\Actions\Actions;

class ProcessFrontendStringsObserver implements ProcessFrontendStringsObserverInterface {

	private $untranslatedStrings;

	private $actions;

	public function __construct( UntranslatedStrings $untranslatedStrings, Actions $actions ) {
		$this->untranslatedStrings = $untranslatedStrings;
		$this->actions             = $actions;
	}


	public function newFrontendStringsRegistered( array $stringIds ) {
		if ( count( $stringIds ) && Option::shouldTranslateEverything() ) {
			$elements = $this->untranslatedStrings->getElementsToProcessForStrings(
				$this->untranslatedStrings->getEligibleLanguageCodes( true ),
				$stringIds
			);

			if ( count( $elements ) ) {
				$this->untranslatedStrings->createTranslationJobs( $this->actions, $elements, 'string' );
			}
		}
	}

}
