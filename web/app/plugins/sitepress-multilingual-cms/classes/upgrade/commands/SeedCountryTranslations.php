<?php

namespace WPML\Upgrade\Commands;

use WPML\LanguageEditor\Presets\CountriesTranslationsData;

class SeedCountryTranslations implements \IWPML_Upgrade_Command, \IWPML_Pre_Setup_Upgrade_Command {

	const TABLE_NAME = 'icl_countries_translations';

	private $schema;

	private $result = false;

	public function __construct( array $args ) {
		$this->schema = $args[0];
	}

	public function run() {
		$wpdb  = $this->schema->get_wpdb();
		$table = $wpdb->prefix . self::TABLE_NAME;

		if ( ! $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $table ) ) ) {
			$this->result = false;

			return $this->result;
		}

		$rows = [];
		foreach ( CountriesTranslationsData::data() as $displayLanguageCode => $names ) {
			foreach ( $names as $countryCode => $name ) {
				$rows[] = [ strtoupper( (string) $countryCode ), (string) $displayLanguageCode, (string) $name ];
			}
		}

		$ok = true;
		foreach ( array_chunk( $rows, 1000 ) as $chunk ) {
			$placeholders = [];
			$args         = [];
			foreach ( $chunk as $row ) {
				$placeholders[] = '(%s, %s, %s)';
				array_push( $args, $row[0], $row[1], $row[2] );
			}
			$inserted = $wpdb->query(
				$wpdb->prepare(
					"INSERT IGNORE INTO `{$table}` (country_code, display_language_code, name) VALUES " . implode( ', ', $placeholders ),
					$args
				)
			);
			$ok = $ok && ( false !== $inserted );
		}

		$this->result = $ok;

		return $this->result;
	}

	public function run_admin() {
		return $this->run();
	}

	public function run_ajax() {
		return null;
	}

	public function run_frontend() {
		return null;
	}

	public function get_results() {
		return $this->result;
	}
}
