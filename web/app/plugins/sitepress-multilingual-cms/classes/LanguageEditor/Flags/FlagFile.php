<?php

namespace WPML\LanguageEditor\Flags;

final class FlagFile {

	const PATTERN = '/^(?:(?:country|language)\/)?[a-z0-9][a-z0-9._-]*\.svg$/';

	const NEUTRAL_GLOBE = 'nil.svg';

	private static $reported = [];

	private static $rejected = [];

	public static function normalize( string $raw ): string {
		$name = ltrim( strtolower( trim( $raw ) ), '/' );

		if ( 0 === strpos( $name, 'res/flags/' ) ) {
			$name = substr( $name, strlen( 'res/flags/' ) );
		}

		return self::isShippedName( $name ) ? $name : '';
	}

	public static function isShippedName( string $name ): bool {
		return 1 === preg_match( self::PATTERN, $name );
	}

	public static function exists( string $name ): bool {
		$normalized = self::normalize( $name );

		return '' !== $normalized && is_file( self::path( $normalized ) );
	}

	public static function resolve( string $name ): string {
		$normalized = self::normalize( $name );

		if ( self::NEUTRAL_GLOBE === $normalized ) {
			return self::NEUTRAL_GLOBE;
		}

		if ( '' !== $normalized && is_file( self::path( $normalized ) ) ) {
			return $normalized;
		}

		self::reportOnce( $name );

		return self::NEUTRAL_GLOBE;
	}

	public static function resetReported(): void {
		self::$reported = [];
		self::$rejected = [];
	}

	public static function reportRejected( string $raw, string $where ): void {
		if ( '' === trim( $raw ) || isset( self::$rejected[ $raw ] ) ) {
			return;
		}
		self::$rejected[ $raw ] = true;

		\WPML\PHP\Logger\error(
			sprintf( 'Flag name "%s" served for %s is not a shipped-flag name; ignored.', $raw, $where )
		);
	}

	private static function path( string $normalized ): string {
		return ICL_PLUGIN_PATH . '/res/flags/' . $normalized;
	}

	private static function reportOnce( string $name ): void {
		if ( isset( self::$reported[ $name ] ) ) {
			return;
		}
		self::$reported[ $name ] = true;

		$message = '' === trim( $name )
			? sprintf( 'Flag row has no file name; rendering %s', self::NEUTRAL_GLOBE )
			: sprintf( 'Flag file "%s" is not shipped by this build; falling back to %s.', $name, self::NEUTRAL_GLOBE );

		\WPML\PHP\Logger\error( $message );
	}
}
