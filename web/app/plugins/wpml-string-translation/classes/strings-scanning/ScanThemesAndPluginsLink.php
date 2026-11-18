<?php

namespace WPML\ST\StringsScanning;

class ScanThemesAndPluginsLink {

	const URL = 'admin.php?page=wpml-admin-texts-translation#wpml-st-localization';

	public static function render() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return '';
		}

		$link = '<a href="' . esc_url( admin_url( self::URL ) ) . '">';

		return '<p class="wpml-st-scan-themes-plugins-link">'
			. sprintf(
				/* translators: Line under the filters of the Strings tab of WPML → Translations. %1$s and %2$s: the opening and closing tags of the link around "Scan themes and plugins", which opens the scan of the theme, the plugins and WordPress for texts to translate. */
				esc_html__( 'Looking for strings from your theme or plugins? %1$sScan themes and plugins%2$s', 'wpml-string-translation' ),
				$link,
				'</a>'
			)
			. '</p>';
	}
}
