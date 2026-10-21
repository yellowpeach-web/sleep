<?php

namespace WPML\TM\Settings;

class TranslationModeTransition {

	const MADE_TRANSLATABLE     = 'wpml_taxonomy_made_translatable';
	const MADE_NOT_TRANSLATABLE = 'wpml_taxonomy_made_not_translatable';

	public static function isBecomingTranslatable( $newMode, $oldMode ): bool {
		return false !== $newMode
			&& in_array(
				(int) $newMode,
				[ WPML_CONTENT_TYPE_TRANSLATE, WPML_CONTENT_TYPE_DISPLAY_AS_IF_TRANSLATED ],
				true
			)
			&& false !== $oldMode
			&& WPML_CONTENT_TYPE_DONT_TRANSLATE === (int) $oldMode;
	}

	public static function isBecomingNotTranslatable( $newMode, $oldMode ): bool {
		return false !== $newMode
			&& WPML_CONTENT_TYPE_DONT_TRANSLATE === (int) $newMode
			&& false !== $oldMode
			&& in_array(
				(int) $oldMode,
				[ WPML_CONTENT_TYPE_TRANSLATE, WPML_CONTENT_TYPE_DISPLAY_AS_IF_TRANSLATED ],
				true
			);
	}

	public static function announceForTaxonomy( string $taxonomy, $newMode, $oldMode ) {
		if ( self::isBecomingTranslatable( $newMode, $oldMode ) ) {
			do_action( self::MADE_TRANSLATABLE, $taxonomy );

			return;
		}

		if ( self::isBecomingNotTranslatable( $newMode, $oldMode ) ) {
			do_action( self::MADE_NOT_TRANSLATABLE, $taxonomy );
		}
	}
}
