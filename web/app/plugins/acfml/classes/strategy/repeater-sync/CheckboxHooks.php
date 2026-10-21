<?php

namespace ACFML\Repeater\Sync;

use ACFML\Repeater\Shuffle\Strategy;
use WPML\LIB\WP\Hooks;
use function WPML\FP\spreadArgs;

class CheckboxHooks implements \IWPML_Backend_Action {

	const STORE_SYNC_OPTION_PRIORITY = 4;

	private $shuffled;

	public function __construct( Strategy $shuffled ) {
		$this->shuffled = $shuffled;
	}

	public function add_hooks() {
		Hooks::onAction( 'acf/save_post', self::STORE_SYNC_OPTION_PRIORITY )
			->then( spreadArgs( [ $this, 'storeSynchroniseOption' ] ) );
	}

	public function storeSynchroniseOption( $elementID ) {
		if ( $this->shuffled->hasTranslations( $elementID ) ) {
			$trid = $this->shuffled->getTrid( $elementID );
			if ( $trid && CheckboxUI::isOptionSent() ) {
				$synchroniseOption = CheckboxOption::get();
				if ( CheckboxUI::isSelected() ) {
					$synchroniseOption[ $trid ] = true;
				} else {
					$synchroniseOption[ $trid ] = false;
				}
				CheckboxOption::update( $synchroniseOption );
			}
		}
	}
}
