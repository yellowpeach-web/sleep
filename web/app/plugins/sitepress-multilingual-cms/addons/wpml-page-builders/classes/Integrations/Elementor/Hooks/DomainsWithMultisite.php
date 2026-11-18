<?php

namespace WPML\PB\Elementor\Hooks;

use WPML\FP\Obj;
use WPML\LIB\WP\Hooks;
use function WPML\FP\spreadArgs;
use WPML\PB\Helper\LanguageNegotiation;
use WPML\UrlHandling\UrlBuilder;

class DomainsWithMultisite implements \IWPML_Backend_Action {

	public function add_hooks() {
		if ( is_multisite() && LanguageNegotiation::isUsingDomains() ) {
			Hooks::onAction( 'elementor/editor/init' )
				->then( spreadArgs( [ $this, 'onElementorEditor' ] ) );
		}
	}

	public function onElementorEditor() {
		$isCurrentLangDifferentThanDefault = apply_filters( 'wpml_current_language', null ) !== apply_filters( 'wpml_default_language', null );

		if ( $isCurrentLangDifferentThanDefault ) {
			Hooks::onFilter( 'admin_url' )
				->then( spreadArgs( [ $this, 'filterUrl' ] ) );
		}
	}

	public function filterUrl( $url ) {
		$parsedUrl = wpml_parse_url( $url );

		if ( is_array( $parsedUrl ) && ! empty( $parsedUrl['host'] ) ) {
			$host = isset( $_SERVER['HTTP_HOST'] ) ? sanitize_text_field( wp_unslash( $_SERVER['HTTP_HOST'] ) ) : '';

			return UrlBuilder::build( Obj::assoc( 'host', $host, $parsedUrl ) );
		}

		return $url;
	}
}
