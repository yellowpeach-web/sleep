<?php

namespace WPML\Upgrade\Commands;

use WPML\LanguageEditor\Presets\CatalogueVersionStore;
use WPML\LanguageEditor\Presets\PresetsSeeder;

class RefreshLanguageCatalogueForLanguageColumn implements \IWPML_Upgrade_Command, \IWPML_Pre_Setup_Upgrade_Command {

	private $schema;

	public function __construct( array $args ) {
		$this->schema = $args[0];
	}

	const PRESETS_TABLE = 'icl_language_presets';

	private function run() {
		if ( true !== $this->seedCatalogue() ) {
			return false;
		}

		$unfilled = $this->unfilledCodes();
		if ( null === $unfilled ) {
			return false;
		}

		$shipped = self::shippedCodes();
		$stray   = [];
		foreach ( $unfilled as $code ) {
			if ( isset( $shipped[ strtolower( trim( (string) $code ) ) ] ) ) {
				return false;
			}
			$stray[] = (string) $code;
		}

		if ( $stray ) {
			$this->logUnfilledRows( $stray );
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

	private function unfilledCodes() {
		$wpdb  = $this->schema->get_wpdb();
		$table = $wpdb->prefix . self::PRESETS_TABLE;

		$hasColumn = (bool) $wpdb->get_var(
			$wpdb->prepare( "SHOW COLUMNS FROM `{$table}` LIKE %s", 'language' )
		);
		if ( ! $hasColumn ) {
			return null;
		}

		$codes = $wpdb->get_col( "SELECT `code` FROM `{$table}` WHERE `language` = ''" );

		return is_array( $codes ) ? array_map( 'strval', $codes ) : [];
	}

	private static function shippedCodes() {
		$codes = [];
		foreach ( PresetsSeeder::data() as $row ) {
			if ( isset( $row['code'] ) && '' !== trim( (string) $row['code'] ) ) {
				$codes[ strtolower( trim( (string) $row['code'] ) ) ] = true;
			}
		}

		return $codes;
	}

	protected function logUnfilledRows( array $codes ) {
		if ( function_exists( '\WPML\PHP\Logger\notice' ) ) {
			\WPML\PHP\Logger\notice(
				sprintf(
					'RefreshLanguageCatalogueForLanguageColumn: settled with %d language preset row(s) the shipped catalogue does not carry, left with an empty language: %s',
					count( $codes ),
					implode( ', ', $codes )
				)
			);
		}
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
