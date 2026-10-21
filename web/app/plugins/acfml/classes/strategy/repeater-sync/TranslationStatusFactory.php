<?php

namespace ACFML\Repeater\Sync;

use ACFML\Repeater\Shuffle\OptionsPage;
use ACFML\Repeater\Shuffle\Strategy;

class TranslationStatusFactory {

	private $records;

	public function __construct( \WPML_TM_Records $records ) {
		$this->records = $records;
	}

	public static function fromGlobals() {
		global $wpdb, $wpml_post_translations, $wpml_term_translations;

		return new self( new \WPML_TM_Records( $wpdb, $wpml_post_translations, $wpml_term_translations ) );
	}

	public function create( Strategy $shuffled ) {
		return $shuffled instanceof OptionsPage
			? new UntrackedTranslationStatus()
			: new ManagedTranslationStatus( $this->records );
	}
}
