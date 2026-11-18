<?php

namespace WPML\Compatibility\Divi\V5;

use WPML\Compatibility\Divi\DisplayConditions as DisplayConditionsV4;
use WPML\FP\Obj;
use WPML\PB\Integrations\Divi\Helper;
use WPML\LIB\WP\Hooks;

use function WPML\FP\spreadArgs;

class DisplayConditions implements \IWPML_Frontend_Action {

	const PRIORITY = 1;

	const CONDITIONS_PATH = [ 'attrs', 'module', 'decoration', 'conditions', 'desktop', 'value' ];

	public function add_hooks() {
		Hooks::onFilter( 'render_block_data', self::PRIORITY )
			->then( spreadArgs( [ $this, 'translateConditions' ] ) );
	}

	public function translateConditions( $block ) {
		if ( ! Helper::isDiviBlockName( (string) Obj::propOr( '', 'blockName', $block ) ) ) {
			return $block;
		}

		$conditions = Obj::pathOr( null, self::CONDITIONS_PATH, $block );

		if ( ! is_array( $conditions ) || ! $conditions ) {
			return $block;
		}

		foreach ( $conditions as &$condition ) {
			$condition = DisplayConditionsV4::translateConditionIds( $condition, true );
		}

		return Obj::assocPath( self::CONDITIONS_PATH, $conditions, $block );
	}
}
