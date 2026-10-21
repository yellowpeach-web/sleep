<?php

namespace WPML\Core\Component\LanguageEditor\Domain;

use WPML\Core\Component\LanguageEditor\Domain\Repository\LanguageRepositoryInterface;

final class EnglishNameQualification {

	public static function composedName( $presetName, $country, $code, LanguageRepositoryInterface $languages ) {
		$country = null === $country ? '' : $country;

		if ( $country !== '' ) {
			$countryName = $languages->countryName( $country );
			$label       = $countryName !== null && $countryName !== '' ? $countryName : strtoupper( $country );

			return sprintf( '%s (%s)', $presetName, $label );
		}

		return sprintf( '%s (%s)', $presetName, $code );
	}


	public static function yieldedName( $candidate, array $holder, LanguageRepositoryInterface $languages ) {
		$n       = 2;
		$yielded = $candidate . ' ' . $n;
		while ( $languages->englishNameTaken( $yielded, $holder['code'] ) ) {
			$n++;
			$yielded = $candidate . ' ' . $n;
		}

		return $yielded;
	}


	public static function qualifiedName( $name, array $owner, LanguageRepositoryInterface $languages ) {
		$candidate = self::composedName(
			$name,
			isset( $owner['country'] ) ? $owner['country'] : null,
			$owner['code'],
			$languages
		);

		$base = $candidate;
		$n    = 2;
		while ( $languages->englishNameTaken( $candidate, $owner['code'] ) ) {
			$candidate = $base . ' ' . $n;
			$n++;
		}

		return $candidate;
	}
}
