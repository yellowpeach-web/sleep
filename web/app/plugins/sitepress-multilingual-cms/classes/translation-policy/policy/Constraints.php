<?php

namespace WPML\TranslationPolicy\Policy;

class Constraints {

	const STRENGTHS = [ PolicyTable::STRENGTH_LOCK, PolicyTable::STRENGTH_DEFAULT ];

	const REASON_MAX_LENGTH = 32;

	private static $labels = [];

	private static $writer;

	private static $sql;

	public static function register( string $source, string $strength, string $scope, string $scopeId, $editor, $method, string $reason = '' ): bool {
		self::assertSource( $source );
		if ( ! in_array( $strength, self::STRENGTHS, true ) ) {
			throw new \InvalidArgumentException( sprintf( 'A plugin registers a lock or a default, not "%s".', esc_html( $strength ) ) );
		}
		if ( strlen( $reason ) > self::REASON_MAX_LENGTH ) {
			throw new \InvalidArgumentException( sprintf( 'A constraint reason has at most %d characters.', (int) self::REASON_MAX_LENGTH ) );
		}

		return self::writer()->set( $scope, $scopeId, $editor, $method, $strength, $source, $reason );
	}

	public static function setDefault( string $source, string $scope, string $scopeId, $editor, $method, string $reason = '' ): bool {
		return self::register( $source, PolicyTable::STRENGTH_DEFAULT, $scope, $scopeId, $editor, $method, $reason );
	}

	public static function unregister( string $source, string $strength, string $scope, string $scopeId ): bool {
		self::assertSource( $source );
		if ( ! in_array( $strength, self::STRENGTHS, true ) ) {
			throw new \InvalidArgumentException( sprintf( 'A plugin registers a lock or a default, not "%s".', esc_html( $strength ) ) );
		}

		return self::writer()->remove( $scope, $scopeId, $strength, $source );
	}

	public static function registered( string $source, string $strength ): array {
		self::assertSource( $source );

		return self::sql()->rowsOfSource( $source, $strength );
	}

	public static function registerLabel( string $source, callable $label ) {
		self::assertSource( $source );
		self::$labels[ $source ] = $label;
	}

	public static function label( string $source, string $reason, int $postId ): string {
		if ( ! isset( self::$labels[ $source ] ) ) {
			return '';
		}

		$label = call_user_func( self::$labels[ $source ], $reason, $postId );

		return is_string( $label ) ? $label : '';
	}

	public static function overrideForTests( $writer, $sql ) {
		self::$writer = $writer;
		self::$sql    = $sql;
		self::$labels = [];
	}

	private static function assertSource( string $source ) {
		if ( '' === $source ) {
			throw new \InvalidArgumentException( 'A constraint needs the source of the plugin that registers it.' );
		}
	}

	private static function writer(): PolicyWriter {
		if ( null === self::$writer ) {
			global $wpdb;

			self::$writer = new PolicyWriter( $wpdb, new PolicyTable( $wpdb ), self::sql() );
		}

		return self::$writer;
	}

	private static function sql(): PolicySql {
		if ( null === self::$sql ) {
			global $wpdb;

			self::$sql = new PolicySql( $wpdb );
		}

		return self::$sql;
	}
}
