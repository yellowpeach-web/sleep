<?php

namespace WPML\TM\Settings;

use WPML\Core\Component\CustomFieldPreferences\Domain\ElementType;
use WPML\Core\Component\CustomFieldPreferences\Domain\MapComposer;
use WPML\Infrastructure\WordPress\Component\CustomFieldPreferences\ContainerFreeServices;

class PreferenceResolver {

	public static function map( string $type ): array {
		return self::liveTmMap( $type ) ?? ContainerFreeServices::maps()->getMap( $type );
	}

	public static function mode( string $type, string $name ): ?int {
		$live = self::liveTmMap( $type );
		if ( null !== $live ) {
			return isset( $live[ $name ] ) ? (int) $live[ $name ] : null;
		}

		return ContainerFreeServices::maps()->getMode( $type, $name );
	}

	public static function modes( string $type, array $names ): array {
		$live = self::liveTmMap( $type );
		if ( null === $live ) {
			return ContainerFreeServices::maps()->getModes( $type, $names );
		}

		$modes = [];
		foreach ( $names as $name ) {
			$name           = (string) $name;
			$modes[ $name ] = isset( $live[ $name ] ) ? (int) $live[ $name ] : null;
		}

		return $modes;
	}

	public static function namesByMode( string $type, int $mode ): array {
		return array_keys(
			array_filter(
				self::map( $type ),
				fn( $value ) => (int) $value === $mode
			)
		);
	}

	public static function composeTmSettings( array $raw ): array {
		$live = [];
		foreach ( array_keys( ElementType::BLOB_KEYS ) as $type ) {
			$live[ $type ] = self::liveTmMap( $type );
		}

		$stored = in_array( null, $live, true )
			? ContainerFreeServices::maps()->getAllMaps()
			: [];

		$maps = [];
		foreach ( ElementType::BLOB_KEYS as $type => $blobKey ) {
			$maps[ $blobKey ] = $live[ $type ] ?? ( $stored[ $type ] ?? [] );
		}

		return MapComposer::compose( $raw, $maps );
	}

	private static function liveTmMap( string $type ): ?array {
		$tm = $GLOBALS['iclTranslationManagement'] ?? null;
		if ( ! $tm instanceof \TranslationManagement || ! $tm->settings_loaded() ) {
			return null;
		}

		$settings = $tm->settings;
		$map      = $settings[ ElementType::BLOB_KEYS[ $type ] ] ?? null;

		return is_array( $map ) ? $map : null;
	}
}
