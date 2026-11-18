<?php

namespace WPML\TM\Menu\TranslationServices;

use WPML\LanguageEditor\PageData;

class CountryName {

	public static function localize( $country ) {
		if ( ! is_array( $country ) || empty( $country['code'] ) ) {
			return $country;
		}

		$localized = PageData::localizedCountryName( (string) $country['code'] );
		if ( is_string( $localized ) && '' !== $localized ) {
			$country['name'] = $localized;
		}

		return $country;
	}
}
