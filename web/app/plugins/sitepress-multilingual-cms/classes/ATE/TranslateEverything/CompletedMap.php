<?php

namespace WPML\TM\ATE\TranslateEverything;

use WPML\Setup\Option;
use WPML\WP\OptionManager;

class CompletedMap {

	public static function update( string $key, callable $change ): array {
		$normalize = Option::TRANSLATE_EVERYTHING_PACKAGES_COMPLETED === $key
			? [ UntranslatedPackages::class, 'normalizeKindKeys' ]
			: null;

		$written = ( new OptionManager() )->mutate(
			Option::OPTION_GROUP,
			$key,
			function ( $current ) use ( $change, $normalize ) {
				$fresh = is_array( $current ) ? $current : [];
				if ( $normalize ) {
					return self::compact( $normalize( $change( $normalize( $fresh ) ) ) );
				}

				return self::compact( $change( $fresh ) );
			}
		);

		return is_array( $written ) ? $written : [];
	}

	public static function updateList( string $key, callable $change ): array {
		$written = ( new OptionManager() )->mutate(
			Option::OPTION_GROUP,
			$key,
			function ( $current ) use ( $change ) {
				return self::compactList( $change( is_array( $current ) ? $current : [] ) );
			}
		);

		return is_array( $written ) ? $written : [];
	}

	public static function compactList( array $languages ): array {
		return array_values( array_unique( $languages ) );
	}

	public static function compact( array $map ): array {
		foreach ( $map as $type => $languages ) {
			if ( is_array( $languages ) ) {
				$map[ $type ] = self::compactList( $languages );
			}
		}

		return $map;
	}
}
