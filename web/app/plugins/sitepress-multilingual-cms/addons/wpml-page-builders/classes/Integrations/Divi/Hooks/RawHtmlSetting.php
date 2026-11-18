<?php

namespace WPML\Compatibility\Divi\Hooks;

use WPML\FP\Lst;
use WPML\FP\Obj;

class RawHtmlSetting extends \WPML\Compatibility\BaseRawHtmlShortcodeSetting {

	const SHORTCODES = [ 'et_pb_code', 'et_pb_fullwidth_code' ];

	const BLOCKS = [ 'divi/code', 'divi/fullwidth-code' ];

	const PRIORITY_SHOULD_HANDLE_CONTENT = \WPML_Compatibility_Divi::PRIORITY_SHOULD_HANDLE_CONTENT + 1;

	const PRIORITY_BEFORE_DYNAMIC_CONTENT = \WPML\Compatibility\Divi\V5\DynamicContent::PRIORITY_FOUND_STRINGS - 1;

	public function add_hooks() {
		parent::add_hooks();
		add_filter( 'wpml_found_strings_in_block', [ $this, 'removeCodeBlockContentWhenDisabled' ], self::PRIORITY_BEFORE_DYNAMIC_CONTENT, 2 );
	}

	protected function getCodeShortcodes(): array {
		return self::SHORTCODES;
	}

	public function removeCodeBlockContentWhenDisabled( $strings, $block ) {
		if ( ! in_array( $block->blockName, self::BLOCKS, true ) || $this->isRawHtmlTranslatable() ) {
			return $strings;
		}

		$codeValues = array_filter( Lst::flatten( (array) Obj::pathOr( [], [ 'attrs', 'content', 'innerContent' ], $block ) ), 'is_string' );

		return array_values(
			array_filter(
				(array) $strings,
				function ( $foundString ) use ( $codeValues ) {
					return ! in_array( Obj::prop( 'value', $foundString ), $codeValues, true );
				}
			)
		);
	}
}
