<?php

namespace WPML\LanguageSwitcher;

class SwitcherAssetsOptOut {

	const CSS_CONSTANT = 'ICL_DONT_LOAD_LANGUAGE_SELECTOR_CSS';
	const JS_CONSTANT  = 'ICL_DONT_LOAD_LANGUAGES_JS';

	private $phpFunctions;

	public function __construct( ?\WPML_PHP_Functions $phpFunctions = null ) {
		$this->phpFunctions = $phpFunctions ?: new \WPML_WP_API();
	}

	public function css() {
		return (bool) $this->phpFunctions->constant( self::CSS_CONSTANT );
	}

	public function js() {
		return (bool) $this->phpFunctions->constant( self::JS_CONSTANT );
	}
}
