<?php

namespace WPML\StringTranslation\Infrastructure\TranslateEverything;

use WPML\Setup\Option;
use WPML\TM\ATE\TranslateEverything\CompletedMap;
use WPML\WP\OptionManager;

class CompletedList {

	public static function update( string $key, callable $change ) {
		if ( method_exists( CompletedMap::class, 'updateList' ) ) {
			CompletedMap::updateList( $key, $change );

			return;
		}

		( new OptionManager() )->mutate(
			Option::OPTION_GROUP,
			$key,
			function ( $current ) use ( $change ) {
				return array_values( array_unique( $change( is_array( $current ) ? $current : [] ) ) );
			}
		);
	}
}
