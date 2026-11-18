<?php

namespace WPML\PB\Gutenberg\Hooks;

class TextDefaultRules {

	const LAYOUT_TAGS = [ 'div', 'span', 'section', 'article', 'header', 'footer', 'aside', 'nav', 'main' ];

	const CSS_KEYWORDS = [ 'left', 'right', 'center', 'justify', 'wide', 'full', 'none', 'auto', 'inherit', 'initial' ];

	const KIND_KEY_WORDS = [
		'type', 'style', 'tag', 'align', 'alignment', 'direction', 'layout', 'mode', 'variant', 'position', 'effect', 'size', 'unit',
		'icon',
		'display', 'orientation', 'repeat', 'attachment', 'overflow',
	];

	const CODE_KEY_WORDS = [ 'id', 'uid', 'uuid', 'selector' ];

	const MEDIA_FILE_EXTENSIONS = [ 'jpg', 'jpeg', 'png', 'gif', 'webp', 'avif', 'svg', 'bmp', 'ico', 'tif', 'tiff', 'heic', 'mp4', 'm4v', 'webm', 'ogv', 'mov' ];

	const CSS_POSITION_KEYWORDS = [ 'left', 'right', 'top', 'bottom', 'center' ];

	const CSS_LENGTH_PATTERN = '/^-?\d+(?:\.\d+)?(?:%|px|em|rem|vw|vh|vmin|vmax|ch|ex|pt|cm|mm|in)?$/';

	const CSS_UNITS = [ 'px', '%', 'em', 'rem', 'vh', 'vw', 'vmin', 'vmax', 'dvh', 'svh', 'lvh', 'pt', 'ch', 'ex', 'fr', 'cm', 'mm', 'deg' ];

	const LINK_TARGETS = [ '_self', '_blank', '_parent', '_top' ];

	const UUID_PATTERN = '/^(?:[a-z0-9]+[-_])*[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/i';

	const CSS_COLOR_PATTERN = '/^(?:#(?:[0-9a-f]{6}|[0-9a-f]{8})|(?:rgba?|hsla?)\(\s*[0-9.,%\s\/]+\))$/i';

	const SHORT_HEX_COLOR_PATTERN = '/^#[0-9a-f]{3,4}$/i';

	private $tagNames;

	public function isText( string $key, $value, array $schema = [] ): bool {
		if ( ! is_string( $value ) || '' === trim( $value ) || is_numeric( $value ) ) {
			return false;
		}

		if ( $this->isCodeKey( $key ) || $this->isMediaFileUrl( $value ) || $this->isCssPosition( $value ) ) {
			return false;
		}

		if ( array_key_exists( 'enum', $schema ) || ( isset( $schema['type'] ) && 'string' !== $schema['type'] ) ) {
			return false;
		}

		return ! $this->isCodeKeyword( $value )
			&& ! $this->hasCodeShape( $value )
			&& ! $this->isCodeUnderKey( $key, $value );
	}

	private function isCodeKeyword( string $value ): bool {
		return isset( $this->getTagNames()[ $value ] )
			|| in_array( $value, self::CSS_KEYWORDS, true )
			|| in_array( $value, self::CSS_UNITS, true )
			|| in_array( $value, self::LINK_TARGETS, true );
	}

	private function hasCodeShape( string $value ): bool {
		return preg_match( '#^[a-z0-9-]+/[a-z0-9-]+$#', $value )
			|| preg_match( self::UUID_PATTERN, $value )
			|| preg_match( self::CSS_COLOR_PATTERN, $value );
	}

	private function isCodeUnderKey( string $key, string $value ): bool {
		return ( $this->isColorKey( $key ) && preg_match( self::SHORT_HEX_COLOR_PATTERN, $value ) )
			|| ( $this->isKindKey( $key ) && preg_match( '/^[a-z][a-z0-9]*(?:[-_][a-z0-9]+)*$/', $value ) )
			|| ( $this->isKeyKey( $key ) && $this->isSingleToken( $value ) );
	}

	private function isSingleToken( string $value ): bool {
		return ! preg_match( '/\s/', $value );
	}

	private function isKindKey( string $key ): bool {
		return (bool) preg_match( '/([A-Z][a-z]*|[a-z]+)$/', $key, $matches )
			&& in_array( strtolower( $matches[1] ), self::KIND_KEY_WORDS, true );
	}

	private function isCodeKey( string $key ): bool {
		return (bool) preg_match( '/(UUID|UID|ID|[A-Z][a-z]*|[a-z]+)$/', $key, $matches )
			&& in_array( strtolower( $matches[1] ), self::CODE_KEY_WORDS, true );
	}

	private function isMediaFileUrl( string $value ): bool {
		if ( ! preg_match( '#^(?:https?:)?/\S*$#i', $value ) ) {
			return false;
		}

		$path = (string) parse_url( $value, PHP_URL_PATH );

		return (bool) preg_match( '/\.([a-z0-9]+)$/i', $path, $matches )
			&& in_array( strtolower( $matches[1] ), self::MEDIA_FILE_EXTENSIONS, true );
	}

	private function isCssPosition( string $value ): bool {
		$parts = preg_split( '/\s+/', trim( $value ) );
		if ( ! is_array( $parts ) || count( $parts ) < 2 || count( $parts ) > 4 ) {
			return false;
		}

		$keywords = 0;
		foreach ( $parts as $part ) {
			if ( in_array( $part, self::CSS_POSITION_KEYWORDS, true ) ) {
				$keywords++;
			} elseif ( ! preg_match( self::CSS_LENGTH_PATTERN, $part ) ) {
				return false;
			}
		}

		return $keywords > 0;
	}

	private function isKeyKey( string $key ): bool {
		return (bool) preg_match( '/(?:^key|Key|[_-]key)$/', $key );
	}

	private function isColorKey( string $key ): bool {
		return (bool) preg_match( '/colou?r$/i', $key );
	}

	private function getTagNames(): array {
		if ( null === $this->tagNames ) {
			$postTags       = function_exists( 'wp_kses_allowed_html' ) ? (array) wp_kses_allowed_html( 'post' ) : [];
			$tagNames       = array_map( 'strval', array_keys( $postTags ) );
			$this->tagNames = array_fill_keys( array_merge( $tagNames, self::LAYOUT_TAGS ), true );
		}

		return $this->tagNames;
	}
}
