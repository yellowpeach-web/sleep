<?php

namespace WPML\Core\Component\LanguageEditor\Domain;

class DisplayCodeDerivation {

	public static function shortestUnambiguous( string $language, ?string $country, string $code, array $taken, bool $sameLanguageActive = false ): string {
		$used = [];
		foreach ( $taken as $value ) {
			$value = strtolower( trim( $value ) );
			if ( '' !== $value ) {
				$used[ $value ] = true;
			}
		}

		$code     = strtolower( trim( $code ) );
		$language = strtolower( trim( $language ) );
		$country  = null === $country ? '' : strtolower( trim( $country ) );

		if ( '' === $language ) {
			return $code;
		}

		$mayTakeBare = ! $sameLanguageActive || '' === $country;
		if ( $mayTakeBare && ! isset( $used[ $language ] ) ) {
			return $language;
		}

		if ( '' !== $country ) {
			$composed = $language . '-' . $country;
			if ( ! isset( $used[ $composed ] ) ) {
				return $composed;
			}
		}

		return $code;
	}
}
