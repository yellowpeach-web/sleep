<?php

namespace WPML\TaxonomyTermTranslation;

use WPML\Language\ActiveLanguagesReadModel;

class TaxonomyScreenLanguage {

	public static function fromRequest( $requested, $current ): string {
		$requested = (string) $requested;

		if ( '' === $requested ) {
			return (string) $current;
		}

		$resolved = ActiveLanguagesReadModel::canonical( $requested );

		return array_key_exists( $resolved, ActiveLanguagesReadModel::rows() )
			? $resolved
			: (string) $current;
	}
}
