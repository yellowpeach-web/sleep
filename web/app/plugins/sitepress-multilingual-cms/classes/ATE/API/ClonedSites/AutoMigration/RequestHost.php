<?php

namespace WPML\TM\ATE\ClonedSites\AutoMigration;

class RequestHost {

	const FILTER_REQUEST_HOST = 'wpml_ate_auto_migration_request_host';

	public static function servesRecordedCopy(): bool {
		$data = Handler::getMigrationData();

		return self::matches(
			is_array( $data ) && ! empty( $data['new_url'] ) ? (string) $data['new_url'] : ''
		);
	}

	public static function matches( string $url ): bool {
		$recorded = self::hostOf( $url );

		if ( '' === $recorded ) {
			return true;
		}

		if ( in_array( $recorded, self::simulatedHosts(), true ) ) {
			return true;
		}

		$request = self::get();

		return '' === $request || $request === $recorded;
	}

	public static function get(): string {
		$host = isset( $_SERVER['HTTP_HOST'] )
			? (string) filter_var( wp_unslash( $_SERVER['HTTP_HOST'] ), FILTER_SANITIZE_URL )
			: '';

		return self::hostOf( (string) apply_filters( self::FILTER_REQUEST_HOST, $host ) );
	}

	private static function simulatedHosts(): array {
		$hosts = [];

		if ( defined( 'ATE_CLONED_SITE_URL' ) ) {
			$hosts[] = self::hostOf( (string) ATE_CLONED_SITE_URL );
		}

		if ( defined( 'ATE_CLONED_DEFAULT_SITE_URL' ) ) {
			$hosts[] = self::hostOf( (string) ATE_CLONED_DEFAULT_SITE_URL );
		}

		return array_filter( $hosts );
	}

	public static function hostOf( string $url ): string {
		$url = trim( $url );

		if ( '' === $url ) {
			return '';
		}

		$url  = ltrim( (string) preg_replace( '#^https?://#i', '', $url ), '/' );
		$host = explode( '?', explode( '/', $url )[0] )[0];
		$host = (string) preg_replace( '#:\d+$#', '', $host );

		return strtolower( rtrim( $host, '.' ) );
	}
}
