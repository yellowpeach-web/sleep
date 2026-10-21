<?php

namespace WPML\Upgrade\Commands;

use WPML\Core\Component\LanguageEditor\Domain\LanguagePairKey;
use WPML\LanguageEditor\ActiveLanguages;
use WPML\LanguageEditor\Presets\LanguagePresetsData;

class AdoptCatalogueCodedCustomLanguages implements \IWPML_Upgrade_Command {

	private $wpdb;

	private $result;

	public function __construct( array $args = array() ) {
		if ( isset( $args[0] ) && $args[0] instanceof \wpdb ) {
			$this->wpdb = $args[0];
		} else {
			global $wpdb;
			$this->wpdb = $wpdb;
		}
	}

	private function run() {
		$wpdb = $this->wpdb;

		if ( ! SeedLanguageCatalogue::isCataloguePopulated( $wpdb ) ) {
			$this->result = false;

			return false;
		}

		$rows = $wpdb->get_results( "SELECT id, code, country, is_custom FROM {$wpdb->prefix}icl_languages WHERE active = 1" );

		if ( ! is_array( $rows ) || $wpdb->last_error ) {
			$this->result = false;

			return false;
		}

		$custom = array_filter(
			$rows,
			function ( $row ) {
				return 1 === (int) $row->is_custom;
			}
		);

		if ( ! $custom ) {
			$this->result = true;

			return true;
		}

		$pairs        = ActiveLanguages::pairs();
		$pairByCode   = array();
		$keysByCode   = array();
		foreach ( $pairs as $pair ) {
			$code                = strtolower( (string) $pair['code'] );
			$pairByCode[ $code ] = $pair;
			$keysByCode[ $code ] = LanguagePairKey::forHead( LanguagePairKey::headOf( $pair ), $pair['country'] );
		}

		$pairCodes = self::pairCodesByCountry();
		$failed    = false;
		$adopted   = 0;

		foreach ( $custom as $row ) {
			$code = strtolower( (string) $row->code );

			if ( ! isset( $pairByCode[ $code ], $pairCodes[ $code ] ) ) {
				continue;
			}

			$country = strtoupper( trim( (string) $row->country ) );
			if ( '' === $country ) {
				continue;
			}

			if ( ! in_array( $country, $pairCodes[ $code ], true ) ) {
				continue;
			}

			$key = $keysByCode[ $code ];
			foreach ( $keysByCode as $otherCode => $otherKey ) {
				if ( $otherCode !== $code && $otherKey === $key ) {
					continue 2;
				}
			}

			$updated = $wpdb->update(
				$wpdb->prefix . 'icl_languages',
				array( 'is_custom' => 0 ),
				array( 'id' => (int) $row->id )
			);

			if ( false === $updated ) {
				$failed = true;
			} else {
				$adopted ++;
			}
		}

		if ( $adopted ) {
			\icl_cache_clear( false );
		}

		$this->result = ! $failed;

		return $this->result;
	}

	private static function pairCodesByCountry() {
		$map = array();

		foreach ( LanguagePresetsData::data() as $preset ) {
			if ( empty( $preset['pair_codes'] ) || ! is_array( $preset['pair_codes'] ) ) {
				continue;
			}

			foreach ( $preset['pair_codes'] as $country => $pairCode ) {
				$map[ strtolower( (string) $pairCode ) ][] = strtoupper( (string) $country );
			}
		}

		return $map;
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
		return $this->result;
	}
}
