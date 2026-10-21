<?php

namespace WPML\LanguageEditor\Flags;

final class FlagManifest {

	const KIND_ORDER = [ 'country', 'region', 'language', 'organisation' ];

	private static $instance;

	private static $loadedFrom = '';

	private $entries = [];

	private $byFile = [];

	private function __construct( array $entries ) {
		foreach ( $entries as $entry ) {
			if ( ! is_array( $entry ) || ! isset( $entry['file'] ) || ! is_string( $entry['file'] ) ) {
				continue;
			}
			$this->entries[]                = $entry;
			$this->byFile[ $entry['file'] ] = $entry;
		}
	}

	public static function instance( string $path = '' ): self {
		$file = '' === $path ? self::shippedPath() : $path;

		if ( ! self::$instance || self::$loadedFrom !== $file ) {
			self::$instance   = new self( self::read( $file ) );
			self::$loadedFrom = $file;
		}

		return self::$instance;
	}

	public static function reset(): void {
		self::$instance   = null;
		self::$loadedFrom = '';
	}

	private static function shippedPath(): string {
		return ICL_PLUGIN_PATH . '/res/flags/manifest.json';
	}

	private static function read( string $file ): array {
		if ( ! is_file( $file ) || ! is_readable( $file ) ) {
			\WPML\PHP\Logger\error( sprintf( 'Flag manifest is missing or unreadable: %s', $file ) );

			return [];
		}

		$raw     = file_get_contents( $file );
		$decoded = is_string( $raw ) ? json_decode( $raw, true ) : null;

		if ( ! is_array( $decoded ) || ! isset( $decoded['flags'] ) || ! is_array( $decoded['flags'] ) ) {
			\WPML\PHP\Logger\error( sprintf( 'Flag manifest is corrupt (no flags array): %s', $file ) );

			return [];
		}

		return $decoded['flags'];
	}

	public function has( string $file ): bool {
		return isset( $this->byFile[ $file ] );
	}

	public function files(): array {
		return array_column( $this->entries, 'file' );
	}

	public function byKind(): array {
		$grouped = [];
		foreach ( self::KIND_ORDER as $kind ) {
			$grouped[ $kind ] = [];
		}

		foreach ( $this->entries as $entry ) {
			$kind = isset( $entry['kind'] ) ? (string) $entry['kind'] : '';
			if ( isset( $grouped[ $kind ] ) ) {
				$grouped[ $kind ][] = $entry;
			}
		}

		return $grouped;
	}

	public function nameOf( string $file ): ?string {
		return isset( $this->byFile[ $file ]['name'] ) ? (string) $this->byFile[ $file ]['name'] : null;
	}

	public function countryFile( string $countryCode ): ?string {
		$file = 'country/' . strtolower( trim( $countryCode ) ) . '.svg';

		return $this->has( $file ) ? $file : null;
	}

	public function languageFile( string $presetCode ): ?string {
		$entry = $this->languageEntry( $presetCode );

		return null === $entry ? null : (string) $entry['file'];
	}

	public function legacyLanguageFile( string $presetCode ): ?string {
		$entry = $this->languageEntry( $presetCode );

		if ( null === $entry || ! isset( $entry['current_file'] ) || ! is_string( $entry['current_file'] ) ) {
			return null;
		}

		return '' === $entry['current_file'] ? null : $entry['current_file'];
	}

	private function languageEntry( string $presetCode ): ?array {
		$code = strtolower( trim( $presetCode ) );
		if ( '' === $code ) {
			return null;
		}

		$languages = $this->byKind()['language'];

		foreach ( $languages as $entry ) {
			$listed = isset( $entry['languages'] ) && is_array( $entry['languages'] ) ? $entry['languages'] : [];
			foreach ( $listed as $listedCode ) {
				if ( strtolower( (string) $listedCode ) === $code ) {
					return $entry;
				}
			}
		}

		$base = strtok( $code, '-' );
		foreach ( $languages as $entry ) {
			if ( basename( $entry['file'], '.svg' ) === $base ) {
				return $entry;
			}
		}

		return null;
	}
}
