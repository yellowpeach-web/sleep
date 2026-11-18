<?php

namespace WPML\Upgrade\Commands;

use WPML\LanguageEditor\Presets\CatalogueVersionStore;
use WPML\LanguageEditor\Presets\PresetsSeeder;

class RefreshLanguageCatalogueForWpCodeColumn implements \IWPML_Upgrade_Command, \IWPML_Pre_Setup_Upgrade_Command {

	const PRESETS_TABLE = 'icl_language_presets';

	private $schema;

	public function __construct( array $args ) {
		$this->schema = $args[0];
	}

	private function run() {
		$unfilled = $this->unfilledCodes();
		if ( null === $unfilled ) {
			return false;
		}
		if ( ! $unfilled ) {
			return true;
		}

		if ( $this->shippedAmong( $unfilled ) ) {
			if ( true !== $this->seedCatalogue() ) {
				return false;
			}

			$unfilled = $this->unfilledCodes();
			if ( null === $unfilled || $this->shippedAmong( $unfilled ) ) {
				return false;
			}
		}

		if ( $unfilled ) {
			$this->logUnfilledRows( $unfilled );
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
		$wpdb = $this->schema->get_wpdb();

		$hasColumn = (bool) $wpdb->get_var(
			$wpdb->prepare( "SHOW COLUMNS FROM `{$wpdb->prefix}icl_language_presets` LIKE %s", 'wp_code' )
		);
		if ( ! $hasColumn ) {
			return null;
		}

		$codes = $wpdb->get_col( "SELECT `code` FROM `{$wpdb->prefix}icl_language_presets` WHERE `wp_code` IS NULL OR `wp_code` = ''" );

		return is_array( $codes ) ? array_map( 'strval', $codes ) : [];
	}

	private function shippedAmong( array $codes ) {
		$shipped = self::shippedWpCodes();
		foreach ( $codes as $code ) {
			if ( isset( $shipped[ strtolower( trim( (string) $code ) ) ] ) ) {
				return true;
			}
		}

		return false;
	}

	private static function shippedWpCodes() {
		$codes = [];
		foreach ( PresetsSeeder::data() as $row ) {
			$row = PresetsSeeder::normalize( $row );
			if ( null !== $row && null !== $row['wp_code'] && '' !== (string) $row['wp_code'] ) {
				$codes[ strtolower( trim( (string) $row['code'] ) ) ] = (string) $row['wp_code'];
			}
		}

		return $codes;
	}

	protected function logUnfilledRows( array $codes ) {
		if ( function_exists( '\WPML\PHP\Logger\notice' ) ) {
			\WPML\PHP\Logger\notice(
				sprintf(
					'RefreshLanguageCatalogueForWpCodeColumn: settled with %d language preset row(s) the shipped catalogue does not carry, left with no WordPress locale: %s',
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
