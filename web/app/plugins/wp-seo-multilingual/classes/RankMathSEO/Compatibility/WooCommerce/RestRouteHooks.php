<?php

namespace WPML\WPSEO\RankMathSEO\Compatibility\WooCommerce;

use WPML\WPSEO\RankMathSEO\Utils;

class RestRouteHooks implements \IWPML_Frontend_Action {

	const PRIORITY_BEFORE_RANK_MATH = 10;
	const PRIORITY_AFTER_RANK_MATH  = 12;

	private $restQueryVars;

	public function add_hooks() {
		if ( Utils::isProductBaseRemoved() ) {
			add_filter( 'request', [ $this, 'storeRestQueryVars' ], self::PRIORITY_BEFORE_RANK_MATH );
			add_filter( 'request', [ $this, 'restoreRestQueryVars' ], self::PRIORITY_AFTER_RANK_MATH );
		}
	}

	public function storeRestQueryVars( $queryVars ) {
		$this->restQueryVars = isset( $queryVars['rest_route'] ) ? $queryVars : null;

		return $queryVars;
	}

	public function restoreRestQueryVars( $queryVars ) {
		if ( null !== $this->restQueryVars && ! isset( $queryVars['rest_route'] ) ) {
			return $this->restQueryVars;
		}

		return $queryVars;
	}
}
