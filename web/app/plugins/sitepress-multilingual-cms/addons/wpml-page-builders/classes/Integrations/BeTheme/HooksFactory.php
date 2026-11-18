<?php

namespace WPML\PB\BeTheme;

class HooksFactory implements \IWPML_Backend_Action_Loader, \IWPML_Frontend_Action_Loader {

	public function create() {
		if ( ! self::isActive() ) {
			return null;
		}

		global $sitepress;

		$defaultLanguage    = $sitepress->get_default_language();
		$secondaryLanguages = array_values(
			array_diff( array_keys( (array) $sitepress->get_active_languages() ), [ $defaultLanguage ] )
		);

		return [
			new TemplateIndexHooks(),
			new TemplateFallbackHooks( $secondaryLanguages ),
		];
	}

	public static function isActive() {
		return function_exists( 'mfn_template_part_ID' );
	}
}
