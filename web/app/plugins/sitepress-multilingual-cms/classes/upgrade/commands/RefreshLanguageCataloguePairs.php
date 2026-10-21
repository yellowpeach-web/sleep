<?php

namespace WPML\Upgrade\Commands;

use WPML\LanguageEditor\Presets\CatalogueVersionStore;

class RefreshLanguageCataloguePairs implements \IWPML_Upgrade_Command, \IWPML_Pre_Setup_Upgrade_Command {

	private $schema;

	public function __construct( array $args ) {
		$this->schema = $args[0];
	}

	private function run() {
		if ( true !== $this->seedCatalogue() ) {
			return false;
		}

		$store = new CatalogueVersionStore();
		foreach (
			[
				CatalogueVersionStore::SECTION_LANGUAGES,
				CatalogueVersionStore::SECTION_LANGUAGE_TRANSLATIONS,
				CatalogueVersionStore::SECTION_COUNTRY_TRANSLATIONS,
				CatalogueVersionStore::SECTION_FLAGS,
			] as $section
		) {
			if ( null !== $store->getVersion( $section ) ) {
				$store->clearVersion( $section );
			}
		}

		return true;
	}

	protected function seedCatalogue() {
		return ( new SeedLanguageCatalogue( [ $this->schema ] ) )->run_admin();
	}

	public function run_admin() {
		return $this->run();
	}

	public function run_ajax() {
		return $this->run();
	}

	public function run_frontend() {
		return $this->run();
	}

	public function get_results() {
		return true;
	}
}
