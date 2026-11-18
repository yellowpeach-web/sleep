<?php

namespace WPML\LanguageEditor;

use WPML\Core\Component\LanguageEditor\Domain\LanguageDerivation;

final class MappingKind {

	const VARIANT = 'variant';

	const CROSS = 'cross';

	const HEAD = 'head';

	const SELF = 'self';

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

		$targetKey  = str_replace( '_', '-', strtolower( $target ) );
		$targetHead = LanguageCodeResolution::head( $targetKey );

		if ( strtolower( $sourceHead ) !== strtolower( $targetHead ) ) {
			return self::CROSS;
		}

		return self::isSelfTarget( $source, $targetKey, $targetHead ) ? self::SELF : self::VARIANT;
	}

	private static function isSelfTarget( $source, $targetKey, $targetHead ) {
		return strtolower( $source ) === $targetKey
			&& $targetKey === strtolower( $targetHead )
			&& null === LanguageCodeResolution::country( $source );
	}

	private static function isRegisteredHead( $source, $target ) {
		if ( ! LanguageCodeResolution::isCustomIdentity( $source ) ) {
			return false;
		}

		return EngineConfirmedHead::isCandidate( $source, $target );
	}
}
