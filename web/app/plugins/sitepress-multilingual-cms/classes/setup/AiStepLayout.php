<?php

namespace WPML\Setup;

class AiStepLayout {

	const SINGLE = 'single';
	const PARTS  = 'parts';

	const CONSTANT = 'WPML_SETUP_AI_STEP_LAYOUT';
	const FILTER   = 'wpml_setup_ai_step_layout';

	public static function get() {
		$layout = defined( self::CONSTANT ) ? constant( self::CONSTANT ) : self::SINGLE;

		$layout = apply_filters( self::FILTER, $layout );

		return in_array( $layout, [ self::SINGLE, self::PARTS ], true ) ? $layout : self::SINGLE;
	}
}
