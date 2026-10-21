<?php

namespace ACFML\Helper;

class MediaTranslation {

	private const OPTION_KEY = '_wpml_media';

	public static function isEnabled() : bool {
		return self::isEnabledInSettings( get_option( self::OPTION_KEY ) );
	}

	public static function isEnabledInSettings( $settings ) : bool {
		return is_array( $settings )
			&& ( ! empty( $settings['translate_media_library_texts'] ) || ! empty( $settings['should_handle_media_auto'] ) );
	}
}
