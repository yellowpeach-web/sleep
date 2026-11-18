<?php

namespace WPML\TM\ATE;

use WPML\Element\API\Languages;
use WPML\TM\API\ATE\CachedLanguageMappings;
use WPML\TM\Jobs\JobLog;

class AutomaticPairSupport {

	const REFUSAL_FILTER = 'wpml_ate_pair_refusal_enabled';

	private static $bySource = [];

	public static function supportFrom( string $source ): array {
		$source = (string) $source;
		if ( '' === $source ) {
			return [];
		}

		$memoKey = ( function_exists( 'get_current_blog_id' ) ? (int) get_current_blog_id() : 0 ) . '|' . $source;
		if ( array_key_exists( $memoKey, self::$bySource ) ) {
			return self::$bySource[ $memoKey ];
		}

		$flags = [];
		try {
			if ( static::isRefusalEnabled() && AutomaticTranslationCapabilities::isAvailable() ) {
				$asked = $source === (string) Languages::getDefaultCode() ? null : $source;
				$rows  = static::readSupport( $asked );

				foreach ( (array) $rows as $row ) {
					$code = is_array( $row ) ? ( $row['code'] ?? null ) : ( is_object( $row ) ? ( $row->code ?? null ) : null );
					if ( ! is_string( $code ) || '' === $code ) {
						continue;
					}
					$flag = is_array( $row ) ? ( $row['can_be_translated_automatically'] ?? null ) : ( $row->can_be_translated_automatically ?? null );

					$flags[ $code ] = null === $flag ? null : (bool) $flag;
				}
			}
		} catch ( \Throwable $e ) {
			JobLog::addError( 'automatic_pair_support_unreadable', [ 'source_lang' => $source, 'error' => get_class( $e ) ] );
			$flags = [];
		}

		self::$bySource[ $memoKey ] = $flags;

		return $flags;
	}

	public static function isRefusalEnabled(): bool {
		return function_exists( 'apply_filters' ) && true === apply_filters( self::REFUSAL_FILTER, false );
	}

	protected static function readSupport( $source ) {
		return CachedLanguageMappings::getAllLanguagesWithAutomaticSupportInfo( $source );
	}

	public static function isRefused( string $source, string $target ): bool {
		if ( '' === $source || '' === $target ) {
			return false;
		}
		if ( $source === $target ) {
			return true;
		}

		$flags = self::supportFrom( $source );

		return array_key_exists( $target, $flags ) && false === $flags[ $target ];
	}

	public static function refusedTargets( string $source, array $targets ): array {
		$refused = [];
		foreach ( $targets as $target ) {
			$target = (string) $target;
			if ( self::isRefused( $source, $target ) ) {
				$refused[] = $target;
			}
		}

		return array_values( array_unique( $refused ) );
	}

	public static function partitionTargets( string $source, array $targetLangs ): array {
		$refused = self::refusedTargets( $source, array_map( 'strval', array_keys( $targetLangs ) ) );
		$kept    = array_diff_key( $targetLangs, array_flip( $refused ) );

		return [ $kept, $refused ];
	}

	public static function refusedSourcesByTarget( array $sources, array $targets ): array {
		$targets = array_values( array_unique( array_map( 'strval', $targets ) ) );
		$sources = array_values( array_unique( array_map( 'strval', $sources ) ) );

		$out = array_fill_keys( $targets, [] );
		foreach ( $sources as $source ) {
			foreach ( self::refusedTargets( $source, $targets ) as $target ) {
				if ( $target !== $source ) {
					$out[ $target ][] = $source;
				}
			}
		}

		return $out;
	}

	public static function reset() {
		self::$bySource = [];
	}
}
