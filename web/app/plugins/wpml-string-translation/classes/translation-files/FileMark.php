<?php

namespace WPML\ST\TranslationFile;

final class FileMark {

	const HEADER = 'X-WPML-ST-Mark';

	const MAX_HEADER_BYTES = 65536;

	public static function format( $newest, $served ) {
		return (int) $newest . '/' . (int) $served;
	}

	public static function read( $filepath ) {
		$handle = @fopen( $filepath, 'rb' );
		if ( false === $handle ) {
			return null;
		}

		try {
			$stat  = fstat( $handle );
			$mtime = is_array( $stat ) && isset( $stat['mtime'] ) ? (int) $stat['mtime'] : 0;

			$head = fread( $handle, 20 );
			if ( ! is_string( $head ) || strlen( $head ) < 20 ) {
				return null;
			}

			$format = self::detectFormat( substr( $head, 0, 4 ) );
			if ( ! $format ) {
				return null;
			}

			$offsets = unpack( $format . 'revision/' . $format . 'count/' . $format . 'originals/' . $format . 'translations', substr( $head, 4, 16 ) );
			if ( ! $offsets || $offsets['count'] < 1 ) {
				return null;
			}

			$original = self::readPair( $handle, $offsets['originals'], $format );
			if ( ! $original || 0 !== $original['length'] ) {
				return [ 'mark' => null, 'mtime' => $mtime ];
			}

			$translation = self::readPair( $handle, $offsets['translations'], $format );
			if (
				! $translation
				|| $translation['length'] < 1
				|| $translation['length'] > self::MAX_HEADER_BYTES
				|| 0 !== fseek( $handle, $translation['offset'] )
			) {
				return [ 'mark' => null, 'mtime' => $mtime ];
			}

			$header = fread( $handle, $translation['length'] );

			return [
				'mark'  => is_string( $header ) ? self::parse( $header ) : null,
				'mtime' => $mtime,
			];
		} finally {
			fclose( $handle );
		}
	}

	public static function parse( $header ) {
		foreach ( explode( "\n", $header ) as $line ) {
			if ( false === strpos( $line, ':' ) ) {
				continue;
			}

			list( $name, $value ) = array_map( 'trim', explode( ':', $line, 2 ) );

			if ( 0 === strcasecmp( $name, self::HEADER ) && preg_match( '/^\d+\/\d+$/', $value ) ) {
				return $value;
			}
		}

		return null;
	}

	private static function detectFormat( $magic ) {
		foreach ( [ 'V', 'N' ] as $format ) {
			$unpacked = unpack( $format, $magic );
			if ( $unpacked && 0x950412de === $unpacked[1] ) {
				return $format;
			}
		}

		return null;
	}

	private static function readPair( $handle, $position, $format ) {
		if ( 0 !== fseek( $handle, $position ) ) {
			return null;
		}

		$bytes = fread( $handle, 8 );
		if ( ! is_string( $bytes ) || strlen( $bytes ) < 8 ) {
			return null;
		}

		$pair = unpack( $format . 'length/' . $format . 'offset', $bytes );

		return $pair ? [
			'length' => (int) $pair['length'],
			'offset' => (int) $pair['offset'],
		] : null;
	}
}
