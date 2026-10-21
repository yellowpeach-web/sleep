<?php

namespace WPML\PB\Elementor\Hooks;

use WPML\LIB\WP\Hooks;

use function WPML\FP\spreadArgs;

class RawHtmlSetting implements \IWPML_Frontend_Action, \IWPML_Backend_Action {

	const NODE = 'html';

	private $settings;

	public function __construct( ?\WPML_Page_Builder_Settings $settings = null ) {
		$this->settings = $settings ?: new \WPML_Page_Builder_Settings();
	}

	public function add_hooks() {
		Hooks::onFilter( 'wpml_elementor_widgets_to_translate' )
			->then( spreadArgs( [ $this, 'removeHtmlNodeWhenDisabled' ] ) );
	}

	public function removeHtmlNodeWhenDisabled( $nodes ) {
		if ( ! $this->settings->is_raw_html_translatable() ) {
			unset( $nodes[ self::NODE ] );
		}

		return $nodes;
	}
}
