<?php

namespace WPML\Upgrade\Commands;

use WPML\Core\Component\LanguageEditor\Domain\Bcp47;
use WPML\Core\Component\LanguageEditor\Domain\LanguagePairKey;
use WPML\Element\API\Entity\LanguageMapping;
use WPML\LanguageEditor\ActiveLanguages;
use WPML\LanguageEditor\EngineConfirmedHead;
use WPML\LanguageEditor\LanguageCodeResolution;
use WPML\Setup\Option;
use WPML\TM\API\ATE\LanguageMappings;
use WPML\TM\ATE\API\CachedATEAPI;
use WPML\TM\ATE\AutomaticTranslationCapabilities;

class AdoptCustomLanguagesOnCatalogueCodes implements \IWPML_Upgrade_Command {

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
		$wpdb = $this->wpdb;

		if ( ! SeedLanguageCatalogue::isCataloguePopulated( $wpdb ) ) {
			$this->result = false;

			return false;
		}

		$rows = $wpdb->get_results( "SELECT id, code, english_name FROM {$wpdb->prefix}icl_languages WHERE active = 1 AND is_custom = 1" );

		if ( ! is_array( $rows ) || $wpdb->last_error ) {
			$this->result = false;

			return false;
		}

		if ( ! $rows ) {
			$this->result = true;

			return true;
		}

		$activeKeys = array();
		foreach ( ActiveLanguages::pairs() as $pair ) {
			$activeKeys[ strtolower( (string) $pair['code'] ) ] = LanguagePairKey::forHead( LanguagePairKey::headOf( $pair ), $pair['country'] );
		}

		$failed  = false;
		$adopted = 0;
		$mapped  = $this->storedMappingTargets();
		$changed = array();

		foreach ( $rows as $row ) {
			$code = strtolower( trim( (string) $row->code ) );
			$pair = $this->cataloguePair( $code );
			if ( null === $pair ) {
				continue;
			}

			$key = LanguagePairKey::forPreset( $pair['language'], $pair['script'], $pair['country'] );
			foreach ( $activeKeys as $otherCode => $otherKey ) {
				if ( $otherCode !== $code && $otherKey === $key ) {
					continue 2;
				}
			}

			$updated = $wpdb->update(
				$wpdb->prefix . 'icl_languages',
				array(
					'is_custom' => 0,
					'country'   => $pair['country'],
					'type'      => $pair['type'],
					'bcp_47'    => $pair['tag'],
				),
				array( 'id' => (int) $row->id )
			);

			if ( false === $updated ) {
				$failed = true;
				continue;
			}

			$adopted ++;
			$activeKeys[ $code ] = $key;

			if ( isset( $mapped[ $code ] ) && EngineConfirmedHead::isCandidate( $code, $mapped[ $code ] ) ) {
				Option::removeLanguageMapping( (string) $row->code );
				$name      = isset( $row->english_name ) && '' !== (string) $row->english_name ? (string) $row->english_name : (string) $row->code;
				$changed[] = new LanguageMapping( (string) $row->code, $name, LanguageMappings::IGNORE_MAPPING_ID, '' );
			}
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

		if ( $adopted ) {
			if ( class_exists( LanguageCodeResolution::class ) ) {
				LanguageCodeResolution::resetStoredTags();
			}
			\icl_cache_clear( false );
		}

		$this->result = ! $failed;

		return $this->result;
	}

	private function storedMappingTargets() {
		$out = array();

		try {
			$mappings = Option::getLanguageMappings();
		} catch ( \Throwable $e ) {
			return $out;
		}

		foreach ( (array) $mappings as $mapping ) {
			$mapping = is_array( $mapping ) ? (object) $mapping : $mapping;
			if ( is_object( $mapping ) && isset( $mapping->sourceCode, $mapping->targetCode ) && '' !== (string) $mapping->targetCode ) {
				$out[ strtolower( (string) $mapping->sourceCode ) ] = (string) $mapping->targetCode;
			}
		}

		return $out;
	}

	private function cataloguePair( $code ) {
		$wpdb = $this->wpdb;

		$pairs = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT * FROM {$wpdb->prefix}icl_language_preset_countries WHERE LOWER(code) = %s",
				$code
			)
		);

		$found = array();
		foreach ( (array) $pairs as $pair ) {
			if ( ! empty( $pair->unvouched_at ) ) {
				continue;
			}

			$preset = $wpdb->get_row(
				$wpdb->prepare(
					"SELECT * FROM {$wpdb->prefix}icl_language_presets WHERE code = %s",
					(string) $pair->preset_code
				)
			);
			if ( ! $preset || ! empty( $preset->unvouched_at ) ) {
				continue;
			}

			$country = strtoupper( trim( (string) $pair->country_code ) );
			$country = '' === $country ? null : $country;
			$script  = isset( $preset->script ) && '' !== (string) $preset->script ? strtolower( (string) $preset->script ) : null;

			$language = isset( $preset->language ) && '' !== (string) $preset->language
				? (string) $preset->language
				: LanguagePairKey::languageSubtag( (string) $preset->code, $script );

			$tag = Bcp47::compose( $language, $script, $country );

			$found[ strtolower( (string) $preset->code ) . '|' . (string) $country ] = array(
				'country'  => $country,
				'language' => $language,
				'script'   => $script,
				'type'     => isset( $preset->type ) && '' !== (string) $preset->type ? (string) $preset->type : 'national',
				'tag'      => '' !== $tag ? $tag : Bcp47::fromLegacyCode( $code ),
			);
		}

		return 1 === count( $found ) ? reset( $found ) : null;
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
