<?php

namespace WPML\Requirements;

class PHP {

	const MINIMUM_VERSION = '7.4';

	public static function checkMinimumRequiredVersion( $phpVersion = PHP_VERSION ) {
		if ( version_compare( $phpVersion, self::MINIMUM_VERSION, '<' ) ) {
			add_action( 'admin_notices', [ __CLASS__, 'displayMissingVersionRequirementNotice' ] );
			add_action( 'network_admin_notices', [ __CLASS__, 'displayMissingVersionRequirementNotice' ] );
			return false;
		}

		return true;
	}

	public static function displayMissingVersionRequirementNotice() {
		echo '<div class="error"><p>';
		echo esc_html(
			sprintf(
				/* translators: %1$s: the PHP version WPML needs, for example 7.4. %2$s: the PHP version this site runs. */
				__( 'WPML is disabled because it requires PHP version %1$s or above. This site runs PHP %2$s; ask your hosting provider to upgrade it.', 'sitepress' ),
				self::MINIMUM_VERSION,
				PHP_VERSION
			)
		);
		echo '</p></div>';
	}
}
