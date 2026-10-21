<?php

namespace WPML\Upgrade\Commands;

use WPML\LanguageEditor\LanguageCodeResolution;
use WPML\LanguageEditor\Presets\LanguagePresetsData;
use WPML\LanguageEditor\Presets\PresetsSeeder;

class RemoveNationalAnchorPairRows extends \WPML_Upgrade_Run_All {

	private $schema;

	public function __construct( array $args ) {
		$this->schema = $args[0];
	}

	public static function anchorlessPresets() {
		$codes = [];
		foreach ( LanguagePresetsData::data() as $raw ) {
			$row = PresetsSeeder::normalize( $raw );
			if ( null === $row ) {
				continue;
			}
			if ( empty( $row['pair_codes'][''] ) ) {
				$codes[] = (string) $row['code'];
			}
		}

		return $codes;
	}

	protected function run() {
		$wpdb  = $this->schema->get_wpdb();
		$table = $wpdb->prefix . CreateLanguagePresetCountriesTable::TABLE_NAME;

		$exists = (string) $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $table ) );
		if ( 0 !== strcasecmp( $exists, $table ) ) {
			return true;
		}

		$presets = self::anchorlessPresets();
		if ( ! $presets ) {
			return true;
		}

		$withdrawn = [];
		foreach ( $presets as $preset ) {
			$withdrawn[ strtolower( (string) $preset ) ] = true;
		}

		$stale = (array) $wpdb->get_col( "SELECT preset_code FROM {$wpdb->prefix}icl_language_preset_countries WHERE country_code = ''" );

		$deleted = 0;
		$seen    = [];
		foreach ( $stale as $storedCode ) {
			$code = (string) $storedCode;
			if ( isset( $seen[ $code ] ) || ! isset( $withdrawn[ strtolower( $code ) ] ) ) {
				continue;
			}
			$seen[ $code ] = true;

			$deleted += (int) $wpdb->delete(
				$table,
				[
					'preset_code'  => $code,
					'country_code' => '',
				],
				[ '%s', '%s' ]
			);
		}

		if ( $deleted > 0 ) {
			LanguageCodeResolution::resetCache();
		}

		return true;
	}
}
