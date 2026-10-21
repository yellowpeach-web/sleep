<?php

namespace WPML\PB\Gutenberg\StringsInBlock\DOMHandler;

class ListItemBlock extends StandardBlock {


	public function applyStringTranslations( \WP_Block_Parser_Block $block, \DOMNode $element, $translation, $originalValue = null) {
		if ( $element instanceof \DOMElement && $element->childNodes->length > 0 ) {
			if ( $this->registersAsText( $element ) ) {
				return $this->replaceTextInListItem(
					$block,
					$this->getTextMarkupPairs(
						$originalValue ?: $element->nodeValue,
						$this->getTranslationAsText( $element, $translation )
					)
				);
			}

			$originalValueVariations = $this->getOriginalVariationsForBrAndImgTags( $originalValue );

			$block->innerHTML = str_replace( $originalValueVariations, $translation, $block->innerHTML );
			foreach ( $block->innerContent as &$inner_content ) {
				if ( $inner_content ) {
					$inner_content = str_replace( $originalValueVariations, $translation, $inner_content );
				}
			}
			return $block;
		}

		return parent::applyStringTranslations( $block, $element, $translation, $originalValue );
	}

	private function replaceTextInListItem( \WP_Block_Parser_Block $block, array $pairs ) {
		foreach ( $pairs as list( $original, $translation ) ) {
			$found = false !== strpos( (string) $block->innerHTML, $original );

			foreach ( $block->innerContent as $inner_content ) {
				$found = $found || ( $inner_content && false !== strpos( $inner_content, $original ) );
			}

			if ( ! $found ) {
				continue;
			}

			$block->innerHTML = str_replace( $original, $translation, $block->innerHTML );
			foreach ( $block->innerContent as &$inner_content ) {
				if ( $inner_content ) {
					$inner_content = str_replace( $original, $translation, $inner_content );
				}
			}

			return $block;
		}

		return $block;
	}

	private function getOriginalVariationsForBrAndImgTags( $originalValue ) {
		$extractInsideBrOrImgTagsPattern = '/<(br[^>\/\s]*|img[^>]*[^>\/\s])(?:\s*\/)?\s*>/';

		preg_match( $extractInsideBrOrImgTagsPattern, $originalValue, $matches );

		if ( $matches ) {
			return array_unique(
				array_filter(
					[
						$originalValue,
						preg_replace( $extractInsideBrOrImgTagsPattern, '<${1}/>', $originalValue ),
						preg_replace( $extractInsideBrOrImgTagsPattern, '<${1}>', $originalValue ),
					]
				)
			);
		}

		return [ $originalValue ];
	}
}
