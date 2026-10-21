<?php

namespace WPML\LanguageEditor;


class LanguageDomains {

	const DOMAIN_MODE = 2;

	public static function isDomainMode( \SitePress $sitepress ) {
		return self::DOMAIN_MODE === (int) $sitepress->get_setting( 'language_negotiation_type' );
	}

	public static function stored( \SitePress $sitepress ) {
		$domains = $sitepress->get_setting( 'language_domains', array() );

		return is_array( $domains ) ? $domains : array();
	}

	public static function hasDomain( \SitePress $sitepress, $code ) {
		$domains = self::stored( $sitepress );

		return isset( $domains[ $code ] ) && null !== \WPML_Language_Domains::hostOf( $domains[ $code ] );
	}

	public static function demoted( \SitePress $sitepress, $newDefault ) {
		if ( ! function_exists( 'wpml_is_setup_complete' ) || ! wpml_is_setup_complete() || ! self::isDomainMode( $sitepress ) ) {
			return null;
		}

		$current = (string) $sitepress->get_default_language();

		if ( '' === $current || $current === (string) $newDefault ) {
			return null;
		}

		$active = $sitepress->get_active_languages();

		if ( ! is_array( $active ) || ! isset( $active[ $current ] ) ) {
			return null;
		}

		return $current;
	}

	public static function demotedWithoutDomain( \SitePress $sitepress, $newDefault ) {
		$demoted = self::demoted( $sitepress, $newDefault );

		return null === $demoted || self::hasDomain( $sitepress, $demoted ) ? null : $demoted;
	}

	public static function storedDomain( \SitePress $sitepress, $code ) {
		$domains = self::stored( $sitepress );

		if ( ! isset( $domains[ $code ] ) ) {
			return null;
		}

		$host = self::shown( $domains[ $code ] );

		return '' === $host ? null : $host;
	}

	public static function withoutDomain( \SitePress $sitepress, array $codes, $defaultCode ) {
		if ( ! function_exists( 'wpml_is_setup_complete' ) || ! wpml_is_setup_complete() || ! self::isDomainMode( $sitepress ) ) {
			return array();
		}

		$missing = array();
		foreach ( $codes as $code ) {
			$code = (string) $code;
			if ( '' === $code || $code === (string) $defaultCode || self::hasDomain( $sitepress, $code ) ) {
				continue;
			}
			$missing[] = $code;
		}

		return $missing;
	}

	public static function posted( $posted, array $stored = array() ) {
		$domains = array();
		foreach ( is_array( $posted ) ? $posted : array() as $code => $domain ) {
			$code   = (string) $code;
			$domain = \WPML_Language_Domains::to_store( is_scalar( $domain ) ? (string) $domain : '', isset( $stored[ $code ] ) ? $stored[ $code ] : null );
			if ( '' !== $domain ) {
				$domains[ $code ] = $domain;
			}
		}

		return $domains;
	}

	public static function refusalForInvalid( array $postedDomains, $posted ) {
		foreach ( $postedDomains as $code => $domain ) {
			if ( \WPML_Language_Domains::is_normalize_error( $domain ) ) {
				$typed = is_array( $posted ) && isset( $posted[ $code ] ) && is_scalar( $posted[ $code ] ) ? trim( (string) $posted[ $code ] ) : '';

				return array(
					'error'  => 'domain_invalid',
					'code'   => (string) $code,
					'reason' => $domain,
					'typed'  => $typed,
				);
			}
		}

		return null;
	}

	public static function refusalForSave( \SitePress $sitepress, array $languages, $defaultCode, array $postedDomains ) {
		$missing = array();

		$demoted = self::demoted( $sitepress, $defaultCode );
		if ( null !== $demoted ) {
			$missing[] = $demoted;
		}

		$activeCodes = array_map( 'strval', array_keys( (array) $sitepress->get_active_languages() ) );
		$newCodes    = array();
		foreach ( $languages as $row ) {
			$row  = (array) $row;
			$code = isset( $row['code'] ) ? (string) $row['code'] : '';
			if ( '' !== $code && ! in_array( $code, $activeCodes, true ) ) {
				$newCodes[] = $code;
			}
		}
		$missing = array_merge( $missing, self::withoutDomain( $sitepress, $newCodes, $defaultCode ) );

		foreach ( $missing as $code ) {
			if ( ! isset( $postedDomains[ $code ] ) ) {
				return array(
					'error'  => 'domain_required',
					'code'   => $code,
					'reason' => $code === $demoted ? 'demoted' : 'added',
					'stored' => self::storedDomain( $sitepress, $code ),
				);
			}
		}

		return null;
	}

	public static function assign( \SitePress $sitepress, $code, $domain, $save_now = true ) {
		$domains = self::stored( $sitepress );
		$host    = \WPML_Language_Domains::to_store( (string) $domain, isset( $domains[ $code ] ) ? $domains[ $code ] : null );

		if ( '' === $host || \WPML_Language_Domains::is_normalize_error( $host ) ) {
			return false;
		}

		$domains[ $code ] = $host;
		$sitepress->set_setting( 'language_domains', $domains, (bool) $save_now );

		return true;
	}

	public static function shown( $domain ) {
		$domain = trim( (string) $domain );

		if ( '' === $domain ) {
			return '';
		}

		if ( preg_match( '#^(?:.+//)?([^\s/\\\\?\#&<>"\']*)#u', $domain, $m ) ) {
			$domain = $m[1];
		}

		return null === \WPML_Language_Domains::hostOf( $domain ) ? '' : $domain;
	}
}
