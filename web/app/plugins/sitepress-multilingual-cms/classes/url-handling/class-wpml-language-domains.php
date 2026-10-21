<?php

class WPML_Language_Domains {

	const NORMALIZE_INVALID = 'invalid';

	const NORMALIZE_NEEDS_PUNYCODE = 'needs_punycode';

	private $domains;

	public function __construct(
		SitePress $sitepress,
		WPML_URL_Converter_Url_Helper $converter_url_helper
	) {
		$domains = $sitepress->get_setting( 'language_domains' );

		$this->domains = is_array( $domains ) ? $domains : array();

		$this->domains[ $sitepress->get_default_language() ] = wpml_parse_url( $converter_url_helper->get_abs_home(), PHP_URL_HOST );
	}

	public function get( $lang ) {
		return isset( $this->domains[ $lang ] ) ? $this->domains[ $lang ] : null;
	}

	public static function hostOf( $domain ) {
		$domain = trim( (string) $domain );

		if ( '' === $domain ) {
			return null;
		}

		if ( ! preg_match( '#^[a-z][a-z0-9+.-]*://#i', $domain ) ) {
			$domain = '//' . ltrim( $domain, '/' );
		}

		$parts = wp_parse_url( $domain );

		return is_array( $parts ) && ! empty( $parts['host'] ) ? strtolower( (string) $parts['host'] ) : null;
	}

	public static function hostsOf( $domains ) {
		$hosts = array();

		foreach ( is_array( $domains ) ? $domains : array() as $language => $domain ) {
			$host = self::hostOf( $domain );

			if ( $host ) {
				$hosts[ $language ] = $host;
			}
		}

		return $hosts;
	}

	public static function normalize( $input ) {
		$value = trim( (string) $input );

		if ( '' === $value ) {
			return '';
		}

		$value = (string) preg_replace( '#^[a-z][a-z0-9+.-]*://#i', '', $value );
		$value = substr( $value, 0, strcspn( $value, '/?#' ) );
		$at    = strrpos( $value, '@' );
		if ( false !== $at ) {
			$value = substr( $value, $at + 1 );
		}

		$host  = $value;
		$port  = '';
		$colon = strrpos( $value, ':' );
		if ( false !== $colon ) {
			$host   = substr( $value, 0, $colon );
			$digits = substr( $value, $colon + 1 );
			if ( ! preg_match( '/^[0-9]{1,5}$/', $digits ) || (int) $digits < 1 || (int) $digits > 65535 ) {
				return self::NORMALIZE_INVALID;
			}
			$port = ':' . (int) $digits;
		}

		if ( ! preg_match( '/[^\x00-\x7F]/', $host ) ) {
			$ascii = strtolower( $host );
		} elseif ( static::intl_available() ) {
			$ascii = idn_to_ascii( $host, IDNA_NONTRANSITIONAL_TO_ASCII, INTL_IDNA_VARIANT_UTS46 );
			if ( ! is_string( $ascii ) ) {
				return self::NORMALIZE_INVALID;
			}
		} else {
			$ascii = self::to_ascii_without_intl( $host );
			if ( self::is_normalize_error( $ascii ) ) {
				return $ascii;
			}
		}

		return self::is_ascii_host( $ascii ) ? $ascii . $port : self::NORMALIZE_INVALID;
	}

	public static function is_normalize_error( $normalized ) {
		return self::NORMALIZE_INVALID === $normalized || self::NORMALIZE_NEEDS_PUNYCODE === $normalized;
	}

	public static function to_store( $posted, $stored = null ) {
		if ( is_string( $stored ) && '' !== trim( $stored ) && self::as_shown( $posted ) === self::as_shown( $stored ) ) {
			return $stored;
		}

		return self::normalize( $posted );
	}

	public static function normalize_error_message( $error, $typed ) {
		if ( self::NORMALIZE_NEEDS_PUNYCODE === $error ) {
			return sprintf(
				/* translators: Error shown when a language domain is an international domain name, such as bücher.example, and the server cannot convert it. The xn-- form (punycode) is the ASCII spelling of such a name. %s: the domain as the user typed it. */
				__( 'Your server cannot convert international domain names. Enter the xn-- form of %s.', 'sitepress' ),
				$typed
			);
		}

		/* translators: Error shown when a language domain cannot be used as a domain name. */
		return __( 'The domain you have entered is invalid.', 'sitepress' );
	}

	private static function as_shown( $domain ) {
		$domain = (string) preg_replace( '#^[a-z][a-z0-9+.-]*://#i', '', trim( (string) $domain ) );

		return substr( $domain, 0, strcspn( $domain, '/?#' ) );
	}

	protected static function intl_available() {
		return function_exists( 'idn_to_ascii' ) && defined( 'IDNA_NONTRANSITIONAL_TO_ASCII' ) && defined( 'INTL_IDNA_VARIANT_UTS46' );
	}

	private static function to_ascii_without_intl( $host ) {
		if ( ! preg_match( '//u', $host ) ) {
			return self::NORMALIZE_INVALID;
		}

		$encoder = static::idna_encoder();

		if ( null === $encoder || ! function_exists( 'mb_strtolower' ) ) {
			return self::NORMALIZE_NEEDS_PUNYCODE;
		}

		$host = mb_strtolower( $host, 'UTF-8' );

		if ( preg_match( '/\p{M}/u', $host ) ) {
			return self::NORMALIZE_NEEDS_PUNYCODE;
		}

		$labels = array();
		foreach ( explode( '.', $host ) as $label ) {
			try {
				$labels[] = (string) call_user_func( array( $encoder, 'encode' ), $label );
			} catch ( \Exception $e ) {
				return self::NORMALIZE_NEEDS_PUNYCODE;
			}
		}

		return implode( '.', $labels );
	}

	protected static function idna_encoder() {
		foreach ( array( 'WpOrg\\Requests\\IdnaEncoder', 'Requests_IDNAEncoder' ) as $class ) {
			if ( class_exists( $class ) ) {
				return $class;
			}
		}

		return null;
	}

	private static function is_ascii_host( $host ) {
		if ( ! preg_match( '/^[a-z0-9._-]+$/', $host ) ) {
			return false;
		}

		foreach ( explode( '.', $host ) as $label ) {
			$length = strlen( $label );
			if ( $length < 1 || $length > 63 ) {
				return false;
			}
		}

		return true;
	}

	public static function baseUrlOf( $domain, $default_scheme ) {
		$domain = rtrim( trim( (string) $domain ), '/' );

		if ( '' === $domain ) {
			return '';
		}

		if ( preg_match( '#^[a-z][a-z0-9+.-]*://#i', $domain ) ) {
			return $domain;
		}

		return $default_scheme . '://' . ltrim( $domain, '/' );
	}
}
