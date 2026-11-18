<?php

namespace WPML\PB\Gutenberg;

use WPML\EditHistory\Normaliser;
use WPML\FP\Obj;

class BlockStrings {

	private $stringsInBlock;

	public function __construct( StringsInBlock\Collection $stringsInBlock ) {
		$this->stringsInBlock = $stringsInBlock;
	}

	public function collect( $content ) {
		return $this->collectFromBlocks( \WPML_Gutenberg_Integration::parse_blocks( (string) $content ), '' );
	}

	public function units( $content ) {
		$units = [];
		foreach ( $this->collect( $content ) as $block ) {
			$preserves = in_array( $block['block'], Normaliser::PRESERVING_BLOCKS, true );
			foreach ( $block['strings'] as $string ) {
				$units[] = [ 'x' => (string) Obj::propOr( '', 'value', $string ) ] + ( $preserves ? [ Normaliser::PRESERVES_WHITESPACE => true ] : [] );
			}
		}

		return $units;
	}

	private function collectFromBlocks( array $blocks, $parentPath ) {
		$collected = [];

		foreach ( $blocks as $position => $block ) {
			if (
				'' === (string) Obj::propOr( '', 'blockName', $block )
				&& ! Obj::propOr( [], 'innerBlocks', $block )
				&& '' === trim( (string) Obj::propOr( '', 'innerHTML', $block ) )
			) {
				continue;
			}

			$block = \WPML_Gutenberg_Integration::sanitize_block( $block );
			$path  = '' === $parentPath ? (string) $position : $parentPath . '.' . $position;

			$collected[] = [
				'path'    => $path,
				'block'   => (string) Obj::propOr( '', 'blockName', $block ),
				'strings' => array_values( (array) $this->stringsInBlock->find( $block ) ),
			];

			if ( ! empty( $block->innerBlocks ) ) {
				$collected = array_merge( $collected, $this->collectFromBlocks( $block->innerBlocks, $path ) );
			}
		}

		return $collected;
	}
}
