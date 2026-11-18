<?php

namespace WPML\TM\ATE\TranslateEverything;

use WPML\Setup\Option;

class OfferedTypeSinceDate {

	public static function resolve( string $postType ): string {
		$sinceDates = Option::getTranslateEverythingPostsSinceDates();

		if ( isset( $sinceDates[ $postType ] ) ) {
			return (string) $sinceDates[ $postType ];
		}

		if ( ! $sinceDates || ! Option::shouldTranslateEverything() ) {
			return Option::SINCE_DATE_ALL;
		}

		return Option::SINCE_DATE_SKIP_TYPE;
	}
}
