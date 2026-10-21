<?php

namespace WPML\LanguageEditor;

use WPML\TM\API\ATE\CachedLanguageMappings;
use WPML\TM\ATE\AutomaticTranslationCapabilities;

final class EngineConfirmedHead {

	public static function resolve( $code, $ask = null ) {
		$candidates = self::candidates( $code );
		if ( ! $candidates ) {
			return '';
		}

		$support = self::supportedTargets( $candidates, $ask );
		if ( null === $support ) {
			return self::unverifiedHead( $code );
		}

		foreach ( $candidates as $candidate ) {
			if ( ! empty( $support[ $candidate ] ) ) {
				return $candidate;
			}
		}

		return '';
	}

	public static function supports( $target, $ask = null ) {
		$target = trim( (string) $target );
		if ( '' === $target ) {
			return false;
		}

		$support = self::supportedTargets( [ $target ], $ask );

		return null === $support ? null : ! empty( $support[ $target ] );
	}

	public static function candidates( $code ) {
		$code = trim( (string) $code );
		$own  = strtolower( $code );
		$out  = [];

		foreach ( (array) LanguageCodeResolution::ateLookupChain( $code ) as $candidate ) {
			$candidate = trim( (string) $candidate );
			if ( '' === $candidate || strtolower( $candidate ) === $own || in_array( $candidate, $out, true ) ) {
				continue;
			}
			$out[] = $candidate;
		}

		return $out;
	}

	public static function isCandidate( $code, $target ) {
		$target = str_replace( '_', '-', strtolower( trim( (string) $target ) ) );
		if ( '' === $target ) {
			return false;
		}

		foreach ( self::candidates( $code ) as $candidate ) {
			if ( strtolower( $candidate ) === $target ) {
				return true;
			}
		}

		return false;
	}

	public static function unverifiedHead( $code ) {
		$code = trim( (string) $code );
		$head = trim( (string) LanguageCodeResolution::head( $code ) );

		return ( '' === $head || strtolower( $head ) === strtolower( $code ) ) ? '' : $head;
	}

	private static function supportedTargets( array $codes, $ask = null ) {
		if ( ! AutomaticTranslationCapabilities::isAvailable() ) {
			return null;
		}

		try {
			$support = call_user_func( $ask ?: [ CachedLanguageMappings::class, 'supportedTargets' ], $codes );
		} catch ( \Throwable $e ) {
			return null;
		}

		return is_array( $support ) ? $support : null;
	}
}
