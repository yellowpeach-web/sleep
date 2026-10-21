<?php

namespace WPML\Languages;

use WPML\LanguageEditor\TranslationPause;

class LanguageWriteGuard {

	const REASON_REMOVED = 'removed';

	const REASON_PAUSED = 'paused';

	public static function isWritable( $code ) {
		return '' === self::reasonFor( $code );
	}

	public static function reasonFor( $code ) {
		$code = is_scalar( $code ) ? (string) $code : '';

		if ( '' === $code ) {
			return '';
		}

		if ( RemovedLanguages::has( $code ) ) {
			return self::REASON_REMOVED;
		}

		if ( TranslationPause::isPaused( $code ) ) {
			return self::REASON_PAUSED;
		}

		return '';
	}

	public static function filterWritable( array $codes ) {
		if ( ! $codes ) {
			return array();
		}

		return array_values(
			array_filter(
				$codes,
				function ( $code ) {
					return self::isWritable( $code );
				}
			)
		);
	}
}
