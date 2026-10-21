<?php

namespace WPML\TM\XLIFF;

class DeclaredPlaceholders {

	private const MARKER = '<wpml_custom_pattern wpml_content="%s"/>';

	private const TAG_PATTERN = '/(<!--.*?-->|<\/?[a-zA-Z][^<>]*>)/s';

	public static function encode( string $content, array $patterns ): string {
		if ( ! $patterns || '' === $content ) {
			return $content;
		}

		$parts = preg_split( self::TAG_PATTERN, $content, -1, PREG_SPLIT_DELIM_CAPTURE );

		if ( false === $parts ) {
			return $content;
		}

		$encoded = '';
		foreach ( $parts as $index => $part ) {
			$isTag    = 1 === $index % 2;
			$encoded .= $isTag ? $part : self::encodeText( $part, $patterns );
		}

		return $encoded;
	}

	private static function encodeText( string $text, array $patterns ): string {
		$encoded = '';
		$offset  = 0;
		$match   = self::firstMatch( $text, $patterns, $offset );

		while ( $match ) {
			[ $placeholder, $position ] = $match;

			$end = $position + strlen( $placeholder );
			if ( preg_match( '/\G\s+/', $text, $whitespace, 0, $end ) ) {
				$placeholder .= $whitespace[0];
				$end         += strlen( $whitespace[0] );
			}

			$encoded .= substr( $text, $offset, $position - $offset ) . self::marker( $placeholder );
			$offset   = $end;
			$match    = self::firstMatch( $text, $patterns, $offset );
		}

		return $encoded . substr( $text, $offset );
	}

	private static function firstMatch( string $text, array $patterns, int $offset ): ?array {
		$first = null;

		foreach ( $patterns as $pattern ) {
			if ( ! preg_match( $pattern, $text, $matches, PREG_OFFSET_CAPTURE, $offset ) || '' === $matches[0][0] ) {
				continue;
			}

			[ $placeholder, $position ] = $matches[0];

			if (
				null === $first
				|| $position < $first[1]
				|| ( $position === $first[1] && strlen( $placeholder ) > strlen( $first[0] ) )
			) {
				$first = [ $placeholder, $position ];
			}
		}

		return $first;
	}

	private static function marker( string $bytes ): string {
		return sprintf( self::MARKER, base64_encode( $bytes ) );
	}
}
