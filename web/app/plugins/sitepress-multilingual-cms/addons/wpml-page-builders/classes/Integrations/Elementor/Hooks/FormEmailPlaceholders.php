<?php

namespace WPML\PB\Elementor\Hooks;

use WPML\FP\Obj;
use WPML\LIB\WP\Hooks;

use function WPML\FP\spreadArgs;

class FormEmailPlaceholders implements \IWPML_Frontend_Action, \IWPML_Backend_Action {

	public const STRING_NAME_PATTERN = '/^(?:email_(?:subject|content|from_name)(?:_2)?|redirect_to)-form-/';

	public const PLACEHOLDER_PATTERNS = [
		'/\[all-fields\]/',
		'/\[field[^\]]*id="\w+"[^\]]*\]/',
	];

	public function add_hooks() {
		Hooks::onFilter( 'wpml_pb_string_placeholder_patterns' )
			->then( spreadArgs( Obj::assoc( self::STRING_NAME_PATTERN, self::PLACEHOLDER_PATTERNS ) ) );
	}
}
