<?php

namespace WPML\Utilities;

class Utf8Html {

	const DECLARATION = '<?xml encoding="UTF-8">';

	public static function load( \DOMDocument $dom, $html, $options = 0 ) {
		$loaded = $dom->loadHTML( self::DECLARATION . $html, $options );
		if ( $loaded ) {
			self::removeDeclarationNode( $dom );
		}

		return $loaded;
	}

	private static function removeDeclarationNode( \DOMDocument $dom ) {
		foreach ( [ $dom, $dom->documentElement, self::firstElement( $dom, 'body' ) ] as $parent ) {
			if ( ! $parent instanceof \DOMNode ) {
				continue;
			}
			foreach ( $parent->childNodes as $child ) {
				if ( self::isDeclarationNode( $child ) ) {
					$parent->removeChild( $child );

					return;
				}
			}
		}
	}

	private static function isDeclarationNode( \DOMNode $node ) {
		if ( $node instanceof \DOMProcessingInstruction ) {
			return 'xml' === $node->target && 'encoding="UTF-8"' === trim( (string) $node->data );
		}

		return $node instanceof \DOMComment && '?xml encoding="UTF-8"' === trim( (string) $node->data );
	}

	private static function firstElement( \DOMDocument $dom, $tag ) {
		return $dom->getElementsByTagName( $tag )->item( 0 );
	}
}
