<?php

namespace WPML\Notices\SiteKey;

class RegistrationSiteUrl {

	public static function get() {
		if ( function_exists( 'is_multisite' ) && is_multisite() && function_exists( 'OTGS_Installer' ) ) {
			$installer = \OTGS_Installer();

			if ( is_object( $installer ) && is_callable( [ $installer, 'get_installer_site_url' ] ) ) {
				$url = (string) $installer->get_installer_site_url( 'wpml' );

				if ( '' !== $url ) {
					return $url;
				}
			}
		}

		return \WPML_Default_Site_Url::get();
	}
}
