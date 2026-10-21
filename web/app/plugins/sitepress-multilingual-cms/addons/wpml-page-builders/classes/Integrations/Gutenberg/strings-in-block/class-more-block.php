<?php

namespace WPML\PB\Gutenberg\StringsInBlock;

use WPML\FP\Obj;

class MoreBlock extends Base {

	const BLOCK_NAME = 'core/more';
	const ATTRIBUTE  = 'customText';
	const MORE_TAG   = '/<!--more(?:\s.*?)?-->/s';

	public function find( \WP_Block_Parser_Block $block ) {
		$text = $this->getCustomText( $block );

		if ( null === $text ) {
			return [];
		}

		return [
			$this->build_string(
				$this->get_string_id( $block->blockName, $text ),
				$this->get_block_label( $block ),
				$text,
				self::get_string_type( $text )
			),
		];
	}

	public function update( \WP_Block_Parser_Block $block, array $string_translations, $lang ) {
		$text = $this->getCustomText( $block );

		if ( null === $text ) {
			return $block;
		}

		$string_id = $this->get_string_id( $block->blockName, $text );

		if ( ICL_TM_COMPLETE !== (int) Obj::path( [ $string_id, $lang, 'status' ], $string_translations ) ) {
			return $block;
		}

		$translation = $string_translations[ $string_id ][ $lang ]['value'];

		$block->attrs[ self::ATTRIBUTE ] = $translation;
		$block->innerHTML                = $this->replaceMoreText( $block->innerHTML, $translation );
		$block->innerContent             = array_map(
			function ( $content ) use ( $translation ) {
				return is_string( $content ) ? $this->replaceMoreText( $content, $translation ) : $content;
			},
			(array) $block->innerContent
		);

		return $block;
	}

	private function getCustomText( \WP_Block_Parser_Block $block ) {
		if ( self::BLOCK_NAME !== $block->blockName || ! is_array( $block->attrs ) ) {
			return null;
		}

		$text = trim( (string) ( $block->attrs[ self::ATTRIBUTE ] ?? '' ) );

		return '' === $text ? null : $text;
	}

	private function replaceMoreText( $html, $translation ) {
		$replacement = '<!--more ' . strtr(
			$translation,
			[
				'\\' => '\\\\',
				'$'  => '\\$',
			]
		) . '-->';

		return preg_replace( self::MORE_TAG, $replacement, $html, 1 ) ?? $html;
	}
}
