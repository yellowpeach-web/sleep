<?php

namespace WPML\Upgrade\Commands;

use WPML\Element\API\Entity\LanguageMapping;
use WPML\LanguageEditor\EngineConfirmedHead;
use WPML\Setup\Option;
use WPML\TM\API\ATE\LanguageMappings;
use WPML\TM\ATE\API\CachedATEAPI;
use WPML\TM\ATE\AutomaticTranslationCapabilities;

class ReconcileCustomLanguageHeadMappings implements \IWPML_Upgrade_Command {

	private $wpdb;

	private $result;

	private $pusher;

	public function __construct( array $args = array() ) {
		if ( isset( $args[0] ) && $args[0] instanceof \wpdb ) {
			$this->wpdb = $args[0];
		} else {
			global $wpdb;
			$this->wpdb = $wpdb;
		}

		$this->pusher = isset( $args[1] ) && is_callable( $args[1] )
			? $args[1]
			: array( LanguageMappings::class, 'saveMapping' );
	}

	private function run() {
		if ( \WPML_Settings_Failsafe_Loader::isUnrecoverable() ) {
			$this->result = false;

			return false;
		}

		$wpdb = $this->wpdb;

		if ( ! SeedLanguageCatalogue::isCataloguePopulated( $wpdb ) ) {
			$this->result = false;

			return false;
		}

		$rows = $wpdb->get_results(
			"SELECT id, code, english_name FROM {$wpdb->prefix}icl_languages WHERE is_custom = 1 ORDER BY id ASC"
		);

		if ( ! is_array( $rows ) ) {
			$this->result = false;

			return false;
		}

		$existing = $this->existing_mappings();
		$changed  = array();

		foreach ( $rows as $row ) {
			$code = (string) $row->code;
			$key  = strtolower( $code );
			$name = ( isset( $row->english_name ) && '' !== (string) $row->english_name ) ? (string) $row->english_name : $code;

			if ( isset( $existing[ $key ] ) ) {
				$stored = $existing[ $key ];

				if ( ! $this->is_registered_head( $code, $stored ) ) {
					continue;
				}

				if ( false !== EngineConfirmedHead::supports( $stored ) ) {
					continue;
				}

				Option::removeLanguageMapping( $code );
				$changed[] = new LanguageMapping( $code, $name, LanguageMappings::IGNORE_MAPPING_ID, '' );
			}

			$target = EngineConfirmedHead::resolve( $code );
			if ( '' === $target || strtolower( $target ) === $key ) {
				continue;
			}

			$mapping = new LanguageMapping( $code, $name, 0, $target );
			Option::addLanguageMapping( $mapping );
			$changed[] = $mapping;
		}

		if ( $changed ) {
			if ( AutomaticTranslationCapabilities::isAvailable() ) {
				try {
					call_user_func( $this->pusher, $changed );
				} catch ( \Throwable $e ) {
				}
			}

			CachedATEAPI::clearAllCaches();
		}

		$this->result = true;

		return true;
	}

	private function is_registered_head( $code, $target ) {
		return EngineConfirmedHead::isCandidate( $code, $target );
	}

	private function existing_mappings() {
		$out = array();

		try {
			$mappings = Option::getLanguageMappings();
		} catch ( \Throwable $e ) {
			return $out;
		}

		foreach ( (array) $mappings as $mapping ) {
			$mapping = is_array( $mapping ) ? (object) $mapping : $mapping;
			if ( is_object( $mapping ) && isset( $mapping->sourceCode ) && '' !== (string) $mapping->sourceCode ) {
				$out[ strtolower( (string) $mapping->sourceCode ) ] = isset( $mapping->targetCode ) ? (string) $mapping->targetCode : '';
			}
		}

		return $out;
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
