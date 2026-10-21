<?php

namespace ACFML\Strings;

use ACFML\Options\ValueRegistration;
use WPML_ACF;
use function WPML\Container\make;

class HooksFactory implements \IWPML_Backend_Action_Loader, \IWPML_Frontend_Action_Loader {

	public function create() {
		$factory    = new Factory();
		$translator = new Translator( $factory );

		$hooks = [];

		if ( WPML_ACF::isWpmlSetupComplete() ) {
			$hooks[] = new STPluginHooks( new BackFill( $translator, make( ValueRegistration::class ) ) );
		}

		if ( self::isStActivated() ) {
			$hooks[] = new FieldHooks( $factory, $translator );
			$hooks[] = new CptHooks( $factory, $translator );
			$hooks[] = new TaxonomyHooks( $factory, $translator );
			$hooks[] = new OptionsPageHooks( $factory, $translator );
			$hooks[] = new TranslationJobHooks( $factory );
			$hooks[] = new TranslateEverythingHooks();
		}

		return $hooks;
	}

	public static function isStActivated() {
		return defined( 'WPML_ST_VERSION' );
	}
}
