<?php

namespace OTGS\Installer\Api;

class SiteUrl {

	public function get( $repository_id = false ) {
		if ( $repository_id && is_multisite() ) {
			$site_url = untrailingslashit( network_site_url() );
		} else {
			$site_url = defined( 'ATE_CLONED_SITE_URL' ) ? ATE_CLONED_SITE_URL : get_site_url();
		}

		$filtered_site_url = filter_var( apply_filters( 'otgs_installer_site_url', $site_url ), FILTER_SANITIZE_URL );

		return $filtered_site_url ?: $site_url;
	}
}
