<?php

namespace WPML\WPSEO\RankMathSEO\Compatibility\WooCommerce;

use WPML\WPSEO\RankMathSEO\Utils;

class PreviewQueryVarHooks implements \IWPML_Frontend_Action {

	const PREVIEW_QUERY_VAR = 'preview';

	private $previewValue;

	public function add_hooks() {
		if ( Utils::isProductBaseRemoved() ) {
			add_filter( 'request', [ $this, 'storePreviewQueryVar' ], RestRouteHooks::PRIORITY_BEFORE_RANK_MATH );
			add_filter( 'request', [ $this, 'restorePreviewQueryVar' ], RestRouteHooks::PRIORITY_AFTER_RANK_MATH );
		}
	}

	public function storePreviewQueryVar( $queryVars ) {
		$this->previewValue = $queryVars[ self::PREVIEW_QUERY_VAR ] ?? null;

		return $queryVars;
	}

	public function restorePreviewQueryVar( $queryVars ) {
		if ( null !== $this->previewValue && ! isset( $queryVars[ self::PREVIEW_QUERY_VAR ] ) ) {
			$queryVars[ self::PREVIEW_QUERY_VAR ] = $this->previewValue;
		}

		return $queryVars;
	}
}
