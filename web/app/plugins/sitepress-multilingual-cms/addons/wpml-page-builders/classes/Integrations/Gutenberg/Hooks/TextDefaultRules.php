<?php

namespace WPML\PB\Gutenberg\Hooks;

class TextDefaultRules {

	const LAYOUT_TAGS = [ 'div', 'span', 'section', 'article', 'header', 'footer', 'aside', 'nav', 'main' ];

	const CSS_KEYWORDS = [ 'left', 'right', 'center', 'justify', 'wide', 'full', 'none', 'auto', 'inherit', 'initial' ];

	const KIND_KEY_WORDS = [ 'type', 'style', 'tag', 'align', 'alignment', 'direction', 'layout', 'mode', 'variant', 'position', 'effect', 'size', 'unit' ];

	const UUID_PATTERN = '/^(?:[a-z0-9]+[-_])*[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/i';

	const CSS_COLOR_PATTERN = '/^(?:#(?:[0-9a-f]{6}|[0-9a-f]{8})|(?:rgba?|hsla?)\(\s*[0-9.,%\s\/]+\))$/i';

	const SHORT_HEX_COLOR_PATTERN = '/^#[0-9a-f]{3,4}$/i';

	private $tagNames;

	public function isText( string $key, $value, array $schema = [] ): bool {
		if ( ! is_string( $value ) || '' === trim( $value ) || is_numeric( $value ) ) {
			return false;
		}

		if ( array_key_exists( 'enum', $schema ) || ( isset( $schema['type'] ) && 'string' !== $schema['type'] ) ) {
			return false;
		}

		return ! isset( $this->getTagNames()[ $value ] )
			&& ! in_array( $value, self::CSS_KEYWORDS, true )
			&& ! preg_match( '#^[a-z0-9-]+/[a-z0-9-]+$#', $value )
			&& ! preg_match( self::UUID_PATTERN, $value )
			&& ! preg_match( self::CSS_COLOR_PATTERN, $value )
			&& ! ( $this->isColorKey( $key ) && preg_match( self::SHORT_HEX_COLOR_PATTERN, $value ) )
			&& ! ( $this->isKindKey( $key ) && preg_match( '/^[a-z][a-z0-9]*(?:[-_][a-z0-9]+)*$/', $value ) );
	}

	private function isKindKey( string $key ): bool {
		return (bool) preg_match( '/([A-Z][a-z]*|[a-z]+)$/', $key, $matches )
			&& in_array( strtolower( $matches[1] ), self::KIND_KEY_WORDS, true );
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
