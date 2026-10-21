<?php

namespace WPML\Upgrade\Commands;

use WPML\LanguageEditor\RtlLanguages;

class BackfillRtlColumns implements \IWPML_Upgrade_Command, \IWPML_Pre_Setup_Upgrade_Command {

	private $schema;

	public function __construct( array $args ) {
		$this->schema = $args[0];
	}

	public function run() {
		global $wpdb;

		$rtl = RtlLanguages::DEFAULT_RTL_LANGUAGES;

		if (
			$this->schema->does_table_exist( 'icl_language_presets' )
			&& $this->schema->does_column_exist( 'icl_language_presets', 'rtl' )
		) {
			foreach ( $rtl as $language ) {
				$wpdb->query(
					$wpdb->prepare(
						"UPDATE {$wpdb->prefix}icl_language_presets SET rtl = 1 WHERE rtl IS NULL AND language = %s",
						$language
					)
				);
			}
		}

		if (
			$this->schema->does_table_exist( 'icl_languages' )
			&& $this->schema->does_column_exist( 'icl_languages', 'is_rtl' )
		) {
			$codes = $wpdb->get_col( "SELECT code FROM {$wpdb->prefix}icl_languages WHERE is_rtl IS NULL" );
			foreach ( (array) $codes as $code ) {
				if ( ! RtlLanguages::isRtl( (string) $code ) ) {
					continue;
				}
				$wpdb->query(
					$wpdb->prepare(
						"UPDATE {$wpdb->prefix}icl_languages SET is_rtl = 1 WHERE is_rtl IS NULL AND code = %s",
						$code
					)
				);
			}
		}

		RtlLanguages::resetStoredOverrides();

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
