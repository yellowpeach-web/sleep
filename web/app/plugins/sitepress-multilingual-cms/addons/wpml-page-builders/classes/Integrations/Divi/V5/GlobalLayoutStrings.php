<?php

namespace WPML\Compatibility\Divi\V5;

use WPML\LIB\WP\Hooks;
use WPML\PB\Gutenberg\StringsInBlock\StringsInBlock;

use function WPML\FP\spreadArgs;

class GlobalLayoutStrings implements \IWPML_Backend_Action, \IWPML_Frontend_Action {

	const WRAPPER_BLOCK = 'divi/global-layout';

	private $finders;

	public function __construct( StringsInBlock $finders ) {
		$this->finders = $finders;
	}

	public function add_hooks() {
		Hooks::onFilter( 'wpml_found_strings_in_block', 10, 2 )
			->then( spreadArgs( [ $this, 'findStringsInLocalAttrs' ] ) );

		Hooks::onFilter( 'wpml_update_strings_in_block', 10, 3 )
			->then( spreadArgs( [ $this, 'updateStringsInLocalAttrs' ] ) );
	}

	public function findStringsInLocalAttrs( array $strings, \WP_Block_Parser_Block $block ) {
		$module = $this->embeddedModule( $block );

		return $module ? $this->finders->find( $module ) : $strings;
	}

	public function updateStringsInLocalAttrs( \WP_Block_Parser_Block $block, array $stringTranslations, $lang ) {
		$module = $this->embeddedModule( $block );

		if ( $module ) {
			$block->attrs['localAttrs'] = $this->finders->update( $module, $stringTranslations, $lang )->attrs;
		}

		return $block;
	}

	private function embeddedModule( \WP_Block_Parser_Block $block ) {
		$attrs      = is_array( $block->attrs ) ? $block->attrs : [];
		$moduleName = $attrs['blockName'] ?? null;
		$localAttrs = $attrs['localAttrs'] ?? null;

		if ( self::WRAPPER_BLOCK !== $block->blockName || ! is_string( $moduleName ) || ! is_array( $localAttrs ) || ! $localAttrs ) {
			return null;
		}

		return new \WP_Block_Parser_Block( $moduleName, $localAttrs, [], '', [] );
	}
}
