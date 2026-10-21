<?php

namespace WPML\Upgrade\Commands;

use WPML\LanguageEditor\Presets\CatalogueVersionStore;

class ClearLanguagesCatalogueSyncVersion extends \WPML_Upgrade_Run_All {

	protected function run() {
		$store = new CatalogueVersionStore();
		if ( null !== $store->getVersion( CatalogueVersionStore::SECTION_LANGUAGES ) ) {
			$store->clearVersion( CatalogueVersionStore::SECTION_LANGUAGES );
		}

		return true;
	}
}
