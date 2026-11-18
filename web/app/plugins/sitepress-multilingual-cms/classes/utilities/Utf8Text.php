<?php

namespace WPML\Utilities;

class Utf8Text {

	const REPLACEMENT = "\xEF\xBF\xBD";

	private static $windows1252 = [
		0x80 => "\xE2\x82\xAC",
		0x81 => null,
		0x82 => "\xE2\x80\x9A",
		0x83 => "\xC6\x92",
		0x84 => "\xE2\x80\x9E",
		0x85 => "\xE2\x80\xA6",
		0x86 => "\xE2\x80\xA0",
		0x87 => "\xE2\x80\xA1",
		0x88 => "\xCB\x86",
		0x89 => "\xE2\x80\xB0",
		0x8A => "\xC5\xA0",
		0x8B => "\xE2\x80\xB9",
		0x8C => "\xC5\x92",
		0x8D => null,
		0x8E => "\xC5\xBD",
		0x8F => null,
		0x90 => null,
		0x91 => "\xE2\x80\x98",
		0x92 => "\xE2\x80\x99",
		0x93 => "\xE2\x80\x9C",
		0x94 => "\xE2\x80\x9D",
		0x95 => "\xE2\x80\xA2",
		0x96 => "\xE2\x80\x93",
		0x97 => "\xE2\x80\x94",
		0x98 => "\xCB\x9C",
		0x99 => "\xE2\x84\xA2",
		0x9A => "\xC5\xA1",
		0x9B => "\xE2\x80\xBA",
		0x9C => "\xC5\x93",
		0x9D => null,
		0x9E => "\xC5\xBE",
		0x9F => "\xC5\xB8",
	];

	public static function scrub( string $text ): string {
		if ( mb_check_encoding( $text, 'UTF-8' ) ) {
			return $text;
		}

		$scrubbed = '';
		$length   = strlen( $text );
		$offset   = 0;
		while ( $offset < $length ) {
			$ascii = strspn( $text, self::asciiBytes(), $offset );
			if ( $ascii > 0 ) {
				$scrubbed .= substr( $text, $offset, $ascii );
				$offset   += $ascii;
				continue;
			}

			$sequence = self::sequenceLength( $text, $offset, $length );
			if ( $sequence > 0 ) {
				$scrubbed .= substr( $text, $offset, $sequence );
				$offset   += $sequence;
			} else {
				$scrubbed .= self::fromWindows1252( ord( $text[ $offset ] ) );
				$offset++;
			}
		}

		return $scrubbed;
	}

	private static function asciiBytes() {
		static $bytes = null;
		if ( null === $bytes ) {
			$bytes = implode( '', array_map( 'chr', range( 0, 0x7F ) ) );
		}

		return $bytes;
	}

	private static function sequenceLength( $text, $offset, $length ) {
		$lead = ord( $text[ $offset ] );
		if ( $lead >= 0xC2 && $lead <= 0xDF ) {
			$ranges = [ [ 0x80, 0xBF ] ];
		} elseif ( 0xE0 === $lead ) {
			$ranges = [ [ 0xA0, 0xBF ], [ 0x80, 0xBF ] ];
		} elseif ( 0xED === $lead ) {
			$ranges = [ [ 0x80, 0x9F ], [ 0x80, 0xBF ] ];
		} elseif ( $lead >= 0xE1 && $lead <= 0xEF ) {
			$ranges = [ [ 0x80, 0xBF ], [ 0x80, 0xBF ] ];
		} elseif ( 0xF0 === $lead ) {
			$ranges = [ [ 0x90, 0xBF ], [ 0x80, 0xBF ], [ 0x80, 0xBF ] ];
		} elseif ( $lead >= 0xF1 && $lead <= 0xF3 ) {
			$ranges = [ [ 0x80, 0xBF ], [ 0x80, 0xBF ], [ 0x80, 0xBF ] ];
		} elseif ( 0xF4 === $lead ) {
			$ranges = [ [ 0x80, 0x8F ], [ 0x80, 0xBF ], [ 0x80, 0xBF ] ];
		} else {
			return 0;
		}

		if ( $offset + count( $ranges ) >= $length ) {
			return 0;
		}
		foreach ( $ranges as $index => $range ) {
			$byte = ord( $text[ $offset + 1 + $index ] );
			if ( $byte < $range[0] || $byte > $range[1] ) {
				return 0;
			}
		}

		return 1 + count( $ranges );
	}

	private static function fromWindows1252( $byte ) {
		if ( $byte < 0xA0 ) {
			return isset( self::$windows1252[ $byte ] ) ? self::$windows1252[ $byte ] : self::REPLACEMENT;
		}

		return chr( 0xC0 | ( $byte >> 6 ) ) . chr( 0x80 | ( $byte & 0x3F ) );
	}
}
