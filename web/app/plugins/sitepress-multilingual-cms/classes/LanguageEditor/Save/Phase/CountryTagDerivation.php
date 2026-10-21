<?php

namespace WPML\LanguageEditor\Save\Phase;

use WPML\Core\Component\LanguageEditor\Domain\Bcp47;
use WPML\LanguageEditor\Adapter\LanguageRepository;
use WPML\LanguageEditor\LanguageCodeResolution;

final class CountryTagDerivation {

	public static function forCountry( $code, $country ) {
		$identity = LanguageCodeResolution::publishedIdentity( (string) $code );

		if ( '' === (string) $identity['language'] ) {
			return '';
		}

		return Bcp47::compose( $identity['language'], $identity['script'], $country );
	}

	public static function legacyLocaleTag( LanguageRepository $languages, $code, $country ) {
		$code    = (string) $code;
		$country = strtoupper( trim( (string) $country ) );

		if ( '' === $country ) {
			return LanguageCodeResolution::head( $code );
		}

		$resolved = LanguageCodeResolution::resolve( $code );
		$preset   = null !== $resolved ? (string) $resolved['preset_code'] : '';

		if ( '' === $preset ) {
			return '';
		}

		$pair = $languages->presetPair( $preset, $country, true );

		if ( ! is_array( $pair ) || ! isset( $pair['default_locale'] ) ) {
			return '';
		}

		$locale = trim( (string) $pair['default_locale'] );

		return '' === $locale ? '' : str_replace( '_', '-', $locale );
	}

	public static function rewrite( $storedTag, $ownedOld, $newTag ) {
		$storedTag = trim( (string) $storedTag );
		$newTag    = trim( (string) $newTag );

		if ( '' === $newTag || $storedTag === $newTag ) {
			return null;
		}

		if ( '' === $storedTag ) {
			return $newTag;
		}

		foreach ( (array) $ownedOld as $owned ) {
			$owned = trim( (string) $owned );
			if ( '' !== $owned && 0 === strcasecmp( $storedTag, $owned ) ) {
				return $newTag;
			}
		}

		return null;
	}
}
