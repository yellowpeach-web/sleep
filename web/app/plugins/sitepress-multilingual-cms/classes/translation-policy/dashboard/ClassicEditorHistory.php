<?php

namespace WPML\TranslationPolicy\Dashboard;

use WPML\TranslationPolicy\Policy\Policy;
use WPML\TranslationPolicy\Policy\PolicyReaderInterface;

class ClassicEditorHistory implements \IWPML_Backend_Action {

	const SCRIPT_DATA_KEY = 'useAteForOldTranslationsCreatedWithCte';

	private $reader;

	public function __construct( $reader = null ) {
		$this->reader = $reader;
	}

	public function add_hooks() {
		add_filter( 'wpml_dashboard_initial_script_data', [ $this, 'allowAutomaticSendOfClassicEditorTranslations' ] );
	}

	public function allowAutomaticSendOfClassicEditorTranslations( $data ) {
		if ( ! is_array( $data ) ) {
			return $data;
		}

		$reader = $this->reader ?? Policy::reader();
		if ( $reader->readsClassicEditorHistory() ) {
			return $data;
		}

		$data[ self::SCRIPT_DATA_KEY ] = true;

		return $data;
	}
}
