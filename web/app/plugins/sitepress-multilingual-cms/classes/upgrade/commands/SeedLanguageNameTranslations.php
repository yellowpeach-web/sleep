<?php

namespace WPML\Upgrade\Commands;

use WPML\LanguageEditor\LanguageNames;
use WPML\LanguageEditor\Presets\LanguagesTranslationsData;

class SeedLanguageNameTranslations implements \IWPML_Upgrade_Command, \IWPML_Pre_Setup_Upgrade_Command {

	const TABLE_NAME = 'icl_languages_translations';

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

		LanguageNames::resetCache();

		$this->result = LanguageNames::seed(
			$this->languageCodes( $wpdb, $table ),
			$this->displayCodes( $wpdb, $table )
		);

		return $this->result;
	}

	private function languageCodes( $wpdb, $table ) {
		$data      = LanguagesTranslationsData::data();
		$catalogue = isset( $data['en'] ) ? array_keys( $data['en'] ) : [];

		$stored = (array) $wpdb->get_col( "SELECT DISTINCT language_code FROM `{$table}`" );
		$known  = (array) $wpdb->get_col( "SELECT code FROM {$wpdb->prefix}icl_languages" );

		return array_values( array_filter( array_unique( array_merge( $catalogue, $stored, $known ) ), 'strlen' ) );
	}

	private function displayCodes( $wpdb, $table ) {
		return array_values( array_filter(
			(array) $wpdb->get_col( "SELECT code FROM {$wpdb->prefix}icl_languages WHERE active = 1" ),
			'strlen'
		) );
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
