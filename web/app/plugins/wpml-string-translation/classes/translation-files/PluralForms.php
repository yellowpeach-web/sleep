<?php

namespace WPML\ST\TranslationFile;

class PluralForms {

	const OPTION = 'wpml_st_plural_forms';

	const TWO_FORMS = 'nplurals=2; plural=n != 1;';

	const MAX_FORMS = 6;

	private static $resolved = [];

	private static $shared = [];

	private static $core = [];

	private static $rules = [];

	public static function save( $domain, $locale, $expression ) {
		$normalized = self::normalize( $expression );
		if ( null === $normalized || '' === (string) $domain || '' === (string) $locale ) {
			return false;
		}

		$stored = self::getAllStored();
		if ( ! isset( $stored[ $domain ][ $locale ] ) || $stored[ $domain ][ $locale ] !== $normalized ) {
			$stored[ $domain ][ $locale ] = $normalized;
			update_option( self::OPTION, $stored, false );
			self::$resolved = [];
			self::$shared   = [];
		}

		return true;
	}

	public static function copyLocale( $from, $to ) {
		if ( '' === (string) $from || '' === (string) $to || $from === $to ) {
			return;
		}

		if ( self::getStored( 'default', $to ) || self::getFromCoreFile( $to ) ) {
			return;
		}

		$stored = self::getAllStored();
		$copied = false;
		foreach ( $stored as $domain => $locales ) {
			if ( is_array( $locales ) && isset( $locales[ $from ] ) && ! isset( $locales[ $to ] ) ) {
				$stored[ $domain ][ $to ] = $locales[ $from ];
				$copied                   = true;
			}
		}

		if ( $copied ) {
			update_option( self::OPTION, $stored, false );
			self::$resolved = [];
			self::$shared   = [];
		}
	}

	public static function get( $domain, $locale ) {
		$key = $domain . '|' . $locale;

		if ( ! isset( self::$resolved[ $key ] ) ) {
			self::$resolved[ $key ] = self::getStored( $domain, $locale ) ?: self::getForLocale( $locale );
		}

		return self::$resolved[ $key ];
	}

	public static function getForSharedFile( $domain, $locale ) {
		$key = strtolower( (string) $domain ) . '|' . $locale;
		if ( ! isset( self::$shared[ $key ] ) ) {
			self::$shared[ $key ] = self::resolveForSharedFile( (string) $domain, (string) $locale );
		}

		return self::$shared[ $key ];
	}

	private static function resolveForSharedFile( $domain, $locale ) {
		$rules = [];
		foreach ( self::getAllStored() as $stored_domain => $locales ) {
			if ( strtolower( (string) $stored_domain ) !== strtolower( (string) $domain ) || ! is_array( $locales ) || ! isset( $locales[ $locale ] ) ) {
				continue;
			}
			$rule = self::normalize( $locales[ $locale ] );
			if ( null !== $rule ) {
				$rules[ $rule ] = true;
			}
		}

		return 1 === count( $rules ) ? (string) key( $rules ) : self::getForLocale( $locale );
	}

	private static function getForLocale( $locale ) {
		return self::getStored( 'default', $locale )
			?: self::getFromCoreFile( $locale )
			?: self::TWO_FORMS;
	}

	public static function getIndex( $expression, $number ) {
		$parsed = self::parse( $expression ) ?: self::parse( self::TWO_FORMS );
		list( $nplurals, $plural ) = $parsed;

		$rule  = self::getRule( $plural );
		$index = $rule ? (int) $rule->get( (int) $number ) : ( 1 === (int) $number ? 0 : 1 );

		return $index >= 0 && $index < $nplurals ? $index : 0;
	}

	public static function getIndexForDomain( $domain, $number, $locale = null ) {
		$locale = null === $locale ? self::getCurrentLocale() : (string) $locale;

		return self::getIndex( self::getForSharedFile( (string) $domain, $locale ), $number );
	}

	public static function selectOriginal( $singular, $plural, $number ) {
		return 0 === self::getIndex( self::TWO_FORMS, $number ) ? $singular : $plural;
	}

	public static function getCurrentLocale() {
		return function_exists( 'determine_locale' ) ? (string) determine_locale() : (string) get_locale();
	}

	public static function parse( $expression ) {
		if ( ! is_string( $expression ) || ! preg_match( '/^\s*nplurals\s*=\s*(\d+)\s*;\s*plural\s*=\s*(.+?)\s*;?\s*$/s', $expression, $matches ) ) {
			return null;
		}

		$nplurals = (int) $matches[1];
		if ( $nplurals < 1 || $nplurals > self::MAX_FORMS || '' === trim( $matches[2] ) ) {
			return null;
		}

		return [ $nplurals, trim( $matches[2] ) ];
	}

	public static function reset() {
		self::$resolved = [];
		self::$shared   = [];
		self::$core     = [];
	}

	public static function readFromMoFile( $path ) {
		$header = self::readMoHeader( $path );
		if ( null === $header || ! preg_match( '/^Plural-Forms:\s*(.+)$/mi', $header, $matches ) ) {
			return null;
		}

		return self::normalize( $matches[1] );
	}

	private static function normalize( $expression ) {
		$parsed = self::parse( $expression );

		return $parsed ? 'nplurals=' . $parsed[0] . '; plural=' . $parsed[1] . ';' : null;
	}

	private static function getStored( $domain, $locale ) {
		$stored = self::getAllStored();

		return isset( $stored[ $domain ][ $locale ] ) ? self::normalize( $stored[ $domain ][ $locale ] ) : null;
	}

	private static function getAllStored() {
		$stored = get_option( self::OPTION, [] );

		return is_array( $stored ) ? $stored : [];
	}

	private static function getFromCoreFile( $locale ) {
		if ( ! array_key_exists( $locale, self::$core ) ) {
			self::$core[ $locale ] = defined( 'WP_LANG_DIR' ) && preg_match( '/^[A-Za-z0-9_@-]+$/', (string) $locale )
				? self::readFromMoFile( WP_LANG_DIR . '/' . $locale . '.mo' )
				: null;
		}

		return self::$core[ $locale ];
	}

	private static function getRule( $plural ) {
		if ( ! array_key_exists( $plural, self::$rules ) ) {
			self::$rules[ $plural ] = null;
			if ( class_exists( '\Plural_Forms' ) ) {
				try {
					self::$rules[ $plural ] = new \Plural_Forms( $plural );
				} catch ( \Exception $e ) {
					self::$rules[ $plural ] = null;
				}
			}
		}

		return self::$rules[ $plural ];
	}

	private static function readMoHeader( $path ) {
		if ( ! is_file( $path ) || ! is_readable( $path ) ) {
			return null;
		}

		$handle = fopen( $path, 'rb' );
		if ( false === $handle ) {
			return null;
		}

		try {
			$head = fread( $handle, 20 );
			if ( ! is_string( $head ) || strlen( $head ) < 20 ) {
				return null;
			}

			$magic = unpack( 'V', substr( $head, 0, 4 ) );
			if ( 0x950412de === $magic[1] ) {
				$format = 'V';
			} else {
				$magic = unpack( 'N', substr( $head, 0, 4 ) );
				if ( 0x950412de !== $magic[1] ) {
					return null;
				}
				$format = 'N';
			}

			$offsets = unpack( $format . 'revision/' . $format . 'count/' . $format . 'originals/' . $format . 'translations', substr( $head, 4, 16 ) );
			if ( ! $offsets || $offsets['count'] < 1 ) {
				return null;
			}

			$original = self::readPair( $handle, $offsets['originals'], $format );
			if ( ! $original || 0 !== $original['length'] ) {
				return null;
			}

			$translation = self::readPair( $handle, $offsets['translations'], $format );
			if ( ! $translation || $translation['length'] < 1 || $translation['length'] > 65536 || 0 !== fseek( $handle, $translation['offset'] ) ) {
				return null;
			}

			$header = fread( $handle, $translation['length'] );

			return is_string( $header ) ? $header : null;
		} finally {
			fclose( $handle );
		}
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
