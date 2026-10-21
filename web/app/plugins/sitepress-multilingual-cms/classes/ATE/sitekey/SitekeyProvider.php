<?php

namespace WPML\TM\ATE\Sitekey;

use WPML\Notices\SiteKey\StoredSiteKey;

class SitekeyProvider {

	public function hasSitekey(): bool {
		return ! empty( $this->getSitekey() );
	}

	public function getSitekey() {
		if ( ! $this->isInstallerAvailable() ) {
			return null;
		}

		$read = \OTGS_Installer()->get_site_key( 'wpml' );
		$key  = StoredSiteKey::orStored( $read );

		return '' !== $key ? $key : $read;
	}

	private function isInstallerAvailable() {
		return function_exists( 'OTGS_Installer' );
	}
}
