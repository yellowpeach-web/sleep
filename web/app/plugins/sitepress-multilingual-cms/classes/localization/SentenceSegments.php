<?php

namespace WPML\Localization;

class SentenceSegments {

	const TYPE_TEXT    = 'text';
	const TYPE_CONTROL = 'control';

	public static function split( $translated, $controls, $english ) {
		$segments = self::parse( $translated, $controls );

		if ( null === $segments ) {
			$segments = self::parse( $english, $controls );
		}

		return null === $segments ? array() : $segments;
	}

	private static function parse( $sentence, $controls ) {
		if ( ! is_string( $sentence ) || '' === $sentence ) {
			return null;
		}

		$parts = preg_split( '/(%\d+\$s)/', $sentence, -1, PREG_SPLIT_DELIM_CAPTURE );
		if ( false === $parts ) {
			return null;
		}

		$segments = array();
		$seen     = array();

		foreach ( $parts as $index => $part ) {
			if ( 1 === $index % 2 ) {
				$control = (int) substr( $part, 1 );
				if ( $control < 1 || $control > $controls || isset( $seen[ $control ] ) ) {
					return null;
				}
				$seen[ $control ] = true;
				$segments[]       = array(
					'type'    => self::TYPE_CONTROL,
					'control' => $control,
				);
				continue;
			}

			$text = self::text( $part );
			if ( null === $text ) {
				return null;
			}
			if ( '' !== $text ) {
				$segments[] = array(
					'type' => self::TYPE_TEXT,
					'text' => $text,
				);
			}
		}

		return count( $seen ) === (int) $controls ? $segments : null;
	}

	private static function text( $part ) {
		$without_literal_percent = str_replace( '%%', '', $part );
		if ( preg_match( '/%(\d+\$)?[-+ 0#\'.\d]*[bcdeEfFgGosuxX]/', $without_literal_percent ) ) {
			return null;
		}

		return trim( str_replace( '%%', '%', $part ) );
	}
}
