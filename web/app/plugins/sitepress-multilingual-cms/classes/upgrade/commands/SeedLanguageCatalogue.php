<?php

namespace WPML\Upgrade\Commands;

use WPML\LanguageEditor\Presets\CountriesSeeder;
use WPML\LanguageEditor\Presets\PresetsSeeder;

class SeedLanguageCatalogue implements \IWPML_Upgrade_Command, \IWPML_Pre_Setup_Upgrade_Command {

	const TABLE = 'icl_language_presets';

	private $schema;

	public function __construct( array $args ) {
		$this->schema = $args[0];
	}

	private function run() {
		$wpdb = $this->schema->get_wpdb();

		CreateLanguagePresetsTable::create_table_if_not_exists( $wpdb );
		CreateCountriesTable::create_table_if_not_exists( $wpdb );
		CreateLanguagePresetCountriesTable::create_table_if_not_exists( $wpdb );

		if ( class_exists( CountriesSeeder::class ) ) {
			CountriesSeeder::create()->seed();
		}
		if ( class_exists( PresetsSeeder::class ) ) {
			PresetsSeeder::create()->seed();
		}

		return $this->isPopulated( $wpdb );
	}

	private function isPopulated( $wpdb ) {
		return self::isCataloguePopulated( $wpdb );
	}

	public static function isCataloguePopulated( $wpdb ): bool {
		$tables = [
			$wpdb->prefix . self::TABLE,
			$wpdb->prefix . CreateLanguagePresetCountriesTable::TABLE_NAME,
		];

		foreach ( $tables as $table ) {
			if ( ! $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $table ) ) ) {
				return false;
			}

			if ( (int) $wpdb->get_var( "SELECT COUNT(*) FROM `{$table}`" ) < 1 ) {
				return false;
			}
		}

		return true;
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
