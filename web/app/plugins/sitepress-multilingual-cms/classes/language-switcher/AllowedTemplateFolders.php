<?php

namespace WPML\LanguageSwitcher;

class AllowedTemplateFolders {

	const REASON_UPLOADS = 'uploads';
	const REASON_OUTSIDE = 'outside';

	private static $roots;

	public static function roots() {
		if ( null === self::$roots ) {
			$sub   = '/templates/language-switchers';
			$roots = array(
				WPML_PLUGIN_PATH . $sub,
				get_template_directory() . '/wpml' . $sub,
				get_stylesheet_directory() . '/wpml' . $sub,
			);

			$added = apply_filters( 'wpml_ls_directories_to_scan', array() );

			foreach ( is_array( $added ) ? $added : array() as $dir ) {
				if ( is_string( $dir ) && '' !== $dir ) {
					$roots[] = $dir;
				}
			}

			self::$roots = array_values( array_unique( $roots ) );
		}

		return self::$roots;
	}

	public static function refusal( $file ) {
		if ( UploadsFolder::contains( $file ) ) {
			return self::REASON_UPLOADS;
		}

		return FolderIdentity::inside( $file, self::roots(), true ) ? null : self::REASON_OUTSIDE;
	}

	public static function reset() {
		self::$roots = null;
	}
}
