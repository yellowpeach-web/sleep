<?php

namespace WPML\WPSEO\RankMathSEO\Breadcrumbs;

use WPML\WPSEO\RankMathSEO\Utils;

class Hooks implements \IWPML_Frontend_Action {

	public function add_hooks() {
		add_filter( 'rank_math/frontend/breadcrumb/strings', [ $this, 'convertHomeLink' ] );
	}

	public function convertHomeLink( $strings ) {
		if ( ! is_array( $strings ) ) {
			return $strings;
		}

		$homeLink = isset( $strings['home_link'] )
			? $strings['home_link']
			: Utils::getBreadcrumbsHomeLink();

		if ( $homeLink ) {
			$strings['home_link'] = apply_filters( 'wpml_permalink', $homeLink, apply_filters( 'wpml_current_language', null ) );
		}

		return $strings;
	}
}
