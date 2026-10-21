<?php

namespace WPML\LanguageEditor;

use WPML\Core\Component\LanguageEditor\Domain\LanguageDerivation;

final class MappingKind {

	const VARIANT = 'variant';

	const CROSS = 'cross';

	const HEAD = 'head';

	public static function of( $sourceCode, $targetCode ) {
		$source = trim( (string) $sourceCode );
		$target = trim( (string) $targetCode );
		if ( '' === $source || '' === $target ) {
			return self::CROSS;
		}

		$identity = LanguageCodeResolution::publishedIdentity( $source );
		if ( '' === $identity['language'] ) {
			return self::isRegisteredHead( $source, $target ) ? self::HEAD : self::CROSS;
		}

		$sourceHead = LanguageDerivation::baseIdentifier( $identity['language'], $identity['script'] );

		$targetHead = LanguageCodeResolution::head( str_replace( '_', '-', strtolower( $target ) ) );

		return strtolower( $sourceHead ) === strtolower( $targetHead ) ? self::VARIANT : self::CROSS;
	}

	private static function isRegisteredHead( $source, $target ) {
		if ( ! LanguageCodeResolution::isCustomIdentity( $source ) ) {
			return false;
		}

		return EngineConfirmedHead::isCandidate( $source, $target );
	}
}
