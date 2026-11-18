<?php

namespace WPML\TM\ATE\ClonedSites;

use WPML\API\Settings;

class TranslateEverythingBackOn implements \IWPML_Backend_Action, \IWPML_REST_Action, \IWPML_DIC_Action {

	const PENDING = 'translate_everything_off_on_new_record_notice';
	const REMOVAL = 'translate_everything_off_on_new_record_notice_removal';

	public function add_hooks() {
		add_action( 'wpml_set_translate_everything', [ $this, 'onTranslateEverythingSet' ] );
	}

	public function onTranslateEverythingSet( $enabled ) {
		if ( ! $enabled ) {
			return;
		}

		Settings::set( self::PENDING, false );
		Settings::setAndSave( self::REMOVAL, true );
	}
}
