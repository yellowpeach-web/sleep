<?php

namespace WPML\Compatibility\WPBakery\Hooks;

use WPML\LIB\WP\Hooks;

use function WPML\FP\spreadArgs;

class RawHtml implements \IWPML_Frontend_Action, \IWPML_Backend_Action, \IWPML_REST_Action {

	const UNMATCHABLE_ELEMENT = 'wpml_unmatchable_element';

	public function add_hooks() {
		Hooks::onFilter( 'wpb_custom_html_elements' )
			->then( spreadArgs( [ $this, 'keep_raw_html_on_ate_delivery' ] ) );
	}

	public function keep_raw_html_on_ate_delivery( $elements ) {
		return apply_filters( 'wpml_pb_grant_unfiltered_html', false )
			? [ self::UNMATCHABLE_ELEMENT ]
			: $elements;
	}
}
