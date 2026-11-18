<?php

namespace WPML\PB\SiteOrigin\Hooks;

use WPML\LIB\WP\Hooks;
use WPML\PB\Gutenberg\StringsInBlock\StringsInBlock;

use function WPML\FP\spreadArgs;

class LayoutBlockNestedLayouts implements \IWPML_Backend_Action, \IWPML_Frontend_Action {

	const LAYOUT_BLOCK = 'siteorigin-panels/layout-block';

	private $finders;

	public function __construct( StringsInBlock $finders ) {
		$this->finders = $finders;
	}

	public function add_hooks() {
		Hooks::onFilter( 'wpml_found_strings_in_block', 10, 2 )
			->then( spreadArgs( [ $this, 'findStringsInNestedLayouts' ] ) );

		Hooks::onFilter( 'wpml_update_strings_in_block', 10, 3 )
			->then( spreadArgs( [ $this, 'updateStringsInNestedLayouts' ] ) );
	}

	public function findStringsInNestedLayouts( array $strings, \WP_Block_Parser_Block $block ) {
		foreach ( $this->nestedLayouts( $block ) as $nestedLayout ) {
			$strings = array_merge( $strings, $this->finders->find( $nestedLayout ) );
		}

		return $strings;
	}

	public function updateStringsInNestedLayouts( \WP_Block_Parser_Block $block, array $stringTranslations, $language ) {
		foreach ( $this->nestedLayouts( $block ) as $widgetIndex => $nestedLayout ) {
			$translatedLayout = $this->finders->update( $nestedLayout, $stringTranslations, $language );

			$block->attrs['panelsData']['widgets'][ $widgetIndex ]['panels_data'] = $translatedLayout->attrs['panelsData'];
		}

		return $block;
	}

	private function nestedLayouts( \WP_Block_Parser_Block $block ) {
		if ( self::LAYOUT_BLOCK !== $block->blockName || ! is_array( $block->attrs ) ) {
			return [];
		}

		$widgets = $block->attrs['panelsData']['widgets'] ?? null;

		if ( ! is_array( $widgets ) ) {
			return [];
		}

		$nestedLayouts = [];

		foreach ( $widgets as $widgetIndex => $widget ) {
			$panelsData = is_array( $widget ) ? ( $widget['panels_data'] ?? null ) : null;

			if ( is_array( $panelsData ) && is_array( $panelsData['widgets'] ?? null ) ) {
				$nestedLayouts[ $widgetIndex ] = new \WP_Block_Parser_Block( self::LAYOUT_BLOCK, [ 'panelsData' => $panelsData ], [], '', [] );
			}
		}

		return $nestedLayouts;
	}
}
