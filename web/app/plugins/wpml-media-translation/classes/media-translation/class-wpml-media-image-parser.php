<?php

namespace WPML\Media\Classes;

class WPML_Media_Image_Parser extends WPML_Media_Element_Parser {

	protected static $getFromCssBackgroundImagesRegex = '/<\w+[^>]+style\s*=\s*(["\'])(?:(?!\1).)*?background-image\s*:\s*url\(\s*(?:["\']|&quot;|&apos;|&#0?39;)?(.+?)(?:["\']|&quot;|&apos;|&#0?39;)?\s*\)/';
	protected static $mediaElementsRegex              = [
		'/<img ([^>]+)>/s',
		'/<video ([^>]+)>/s',
		'/<audio ([^>]+)>/s',
	];

	public function getMediaElements() {
		$mediaElements = $this->getFromTags();

		if ( ! $this->canParseBlocks() ) {
			return array_merge( $mediaElements, $this->getFromCssBackgroundImages( $this->blockText ) );
		}

		return array_merge( $mediaElements, $this->getFromCssBackgroundImagesInBlocks( parse_blocks( $this->blockText ) ) );
	}

	protected function canParseBlocks() {
		return $this->textContains( '<!-- wp:', $this->blockText ) && function_exists( 'parse_blocks' );
	}


	public function getMediaSrcFromAttributes( $attrs ) {
		return $this->attributeOr( $attrs, 'src' );
	}

	protected function getFromTags() {
		$mediaElements = [];

		foreach ( self::$mediaElementsRegex as $mediaElementExpression ) {
			if ( preg_match_all( $mediaElementExpression, $this->blockText, $matches ) ) {
				$mediaElements = array_merge( $mediaElements, $this->getAttachments( $matches ) );
			}
		}

		return $mediaElements;
	}

	public function validate() {
		return (
			( function_exists( 'parse_blocks' ) &&
				( $this->textContains( '<!-- wp:image', $this->blockText )
					|| $this->textContains( '<!-- wp:video', $this->blockText )
					|| $this->textContains( '<!-- wp:audio', $this->blockText )
				)
			)
			||
			$this->textContains( '<img', $this->blockText )
			||
			$this->textContains( '<video', $this->blockText )
			||
			$this->textContains( '<audio', $this->blockText )
			||
			$this->hasCssBackgroundImage()
			||
			$this->hasBlockAttributeBackground()
		);
	}

	protected function hasBlockAttributeBackground() {
		return $this->textContains( '"backgroundImage":', $this->blockText );
	}

	protected function hasCssBackgroundImage() {
		return 1 === preg_match( self::$getFromCssBackgroundImagesRegex, (string) $this->blockText );
	}

	protected function sanitizeBlock( $block ) {
		$block = (object) $block;

		if ( isset( $block->attrs ) ) {
			$block->attrs = (object) $block->attrs;
		}

		return $block;
	}

	protected function getFromCssBackgroundImages( $text ) {
		$images = [];

		if ( preg_match_all( self::$getFromCssBackgroundImagesRegex, $text, $matches ) ) {
			foreach ( $matches[2] as $src ) {
				$images[] = [
					'attributes' => [ 'src' => $src ],
				];
			}
		}

		return $images;
	}

	protected function getFromCssBackgroundImagesInBlocks( $blocks ) {
		$images = [];

		foreach ( $blocks as $block ) {
			$block = $this->sanitizeBlock( $block );

			$attribute_background = $this->getBackgroundFromBlockAttributes( $block );
			if ( $attribute_background ) {
				$images[] = [ 'attributes' => [ 'src' => $attribute_background ] ];
			}

			if ( ! empty( $block->innerBlocks ) ) {
				$inner_images = $this->getFromCssBackgroundImagesInBlocks( $block->innerBlocks );
				$images       = array_merge( $images, $inner_images );
			}

			if ( ! isset( $block->innerHTML ) ) {
				continue;
			}

			if ( $this->isFreeformBlock( $block ) ) {
				$images = array_merge( $images, $this->getFromCssBackgroundImages( $block->innerHTML ) );
				continue;
			}

			if ( ! isset( $block->attrs->id ) ) {
				$images = array_merge( $images, $this->getFromCssBackgroundImages( $block->innerHTML ) );
				continue;
			}

			$background_images = $this->getFromCssBackgroundImages( $block->innerHTML );
			$image             = reset( $background_images );

			if ( $image ) {
				$images[] = $image;
			}
		}

		return $images;
	}

	protected function getBackgroundFromBlockAttributes( $block ) {
		if ( ! isset( $block->attrs->style ) ) {
			return null;
		}

		$style = json_decode( (string) json_encode( $block->attrs->style ), true );
		$url   = isset( $style['background']['backgroundImage']['url'] ) ? $style['background']['backgroundImage']['url'] : null;

		return is_string( $url ) && '' !== $url ? $url : null;
	}

	protected function isFreeformBlock( $block ) {
		return property_exists( $block, 'blockName' )
			&& null === $block->blockName
			&& empty( $block->innerBlocks );
	}
}
