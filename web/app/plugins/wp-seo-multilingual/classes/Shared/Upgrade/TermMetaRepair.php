<?php

namespace WPML\WPSEO\Shared\Upgrade;

use SitePress;
use wpdb;
use WPML\WPSEO\YoastSEO\Terms\Meta\Hooks;
use WPML\WPSEO\YoastSEO\Utils;

class TermMetaRepair {

	const OPTION               = 'wpseo_taxonomy_meta';
	const KEY_COMPLETE         = 'wpmlseo_repair_term_meta_complete';
	const KEY_COMPLETE_ST_COPY = 'wpmlseo_repair_term_meta_st_copy_complete';
	const BACKUP_PREFIX        = 'wpmlseo_term_meta_backup_';

	const LEAKED_FIELDS = [
		Utils::KEY_META_TITLE,
		Utils::KEY_META_DESC,
		Utils::KEY_BREADCRUMB_TITLE,
		Utils::KEY_FOCUS_KEYWORD,
	];

	private $sitepress;

	private $wpdb;

	private $clearedTermIds = [];

	public function __construct( SitePress $sitepress, wpdb $wpdb ) {
		$this->sitepress = $sitepress;
		$this->wpdb      = $wpdb;
	}

	public function run() {
		$this->repair(
			self::KEY_COMPLETE,
			function ( $storedValue, $sourceValue ) {
				return self::isLeakResidue( $storedValue, $sourceValue );
			}
		);
	}

	public function runStringTranslationCopies() {
		$translations = null;

		$this->repair(
			self::KEY_COMPLETE_ST_COPY,
			function ( $storedValue, $sourceValue, $stringName, $languageCode ) use ( &$translations ) {
				if ( null === $translations ) {
					$translations = $this->readStringTranslations();
				}

				return self::isStringTranslationCopy( $storedValue, $translations[ $stringName ][ $languageCode ] ?? null );
			}
		);
	}

	private function repair( $completeFlag, callable $isResidue ) {
		if ( get_option( $completeFlag, false ) ) {
			return;
		}

		$option   = $this->readOption();
		$snapshot = $option;
		$changed  = false;

		foreach ( $this->sourceTerms( $option ) as $source ) {
			$changed = $this->repairSourceTerm( $option, $source['taxonomy'], $source['termId'], $isResidue ) || $changed;
		}

		if ( $changed ) {
			$this->backupOnce( $snapshot );
			$this->writeOption( $option );
			$this->invalidateIndexables();
		}

		update_option( $completeFlag, true, false );
	}

	private function repairSourceTerm( array &$option, $taxonomy, $sourceTermId, callable $isResidue ) {
		$sourceTerm = $this->unadjustedTerm( $sourceTermId, $taxonomy );
		if ( ! $sourceTerm ) {
			return false;
		}

		$sourceTtId = (int) $sourceTerm->term_taxonomy_id;
		if ( ! $this->sitepress->is_original_content_filter( false, $sourceTtId, 'tax_' . $taxonomy ) ) {
			return false;
		}

		$trid         = $this->sitepress->get_element_trid( $sourceTtId, 'tax_' . $taxonomy );
		$translations = $this->sitepress->get_element_translations( $trid, 'tax_' . $taxonomy );

		$changed = false;
		foreach ( $translations as $translation ) {
			if ( $sourceTtId === (int) $translation->element_id ) {
				continue;
			}
			$targetTermId = $this->translationTermId( $translation, $taxonomy );
			if ( ! $targetTermId ) {
				continue;
			}

			foreach ( self::LEAKED_FIELDS as $field ) {
				$sourceValue = $option[ $taxonomy ][ $sourceTermId ][ $field ] ?? '';
				$storedValue = $option[ $taxonomy ][ $targetTermId ][ $field ] ?? '';
				$stringName  = Hooks::buildStringName( $taxonomy, $sourceTermId, $field );

				if ( ! $isResidue( (string) $storedValue, (string) $sourceValue, $stringName, (string) $translation->language_code ) ) {
					continue;
				}

				unset( $option[ $taxonomy ][ $targetTermId ][ $field ] );
				$this->clearedTermIds[ $targetTermId ] = $targetTermId;
				$changed                               = true;
			}
		}

		return $changed;
	}

	public static function isLeakResidue( $storedValue, $sourceValue ) {
		return '' !== $storedValue && $storedValue === $sourceValue;
	}

	public static function isStringTranslationCopy( $storedValue, $translation ) {
		return '' !== $storedValue && $storedValue === $translation;
	}

	private function sourceTerms( array $option ) {
		$sources = [];
		foreach ( $option as $taxonomy => $terms ) {
			if ( ! is_array( $terms ) || ! $this->sitepress->is_translated_taxonomy( $taxonomy ) ) {
				continue;
			}
			foreach ( array_keys( $terms ) as $termId ) {
				$sources[] = [
					'taxonomy' => (string) $taxonomy,
					'termId'   => (int) $termId,
				];
			}
		}
		return $sources;
	}

	private function readOption() {
		$wpdb  = $this->wpdb;
		$raw   = $wpdb->get_var( $wpdb->prepare( "SELECT option_value FROM {$wpdb->options} WHERE option_name = %s", self::OPTION ) );
		$value = $raw ? maybe_unserialize( $raw ) : [];
		return is_array( $value ) ? $value : [];
	}

	private function readStringTranslations() {
		$wpdb = $this->wpdb;
		$rows = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT s.name, st.language, st.value
					FROM {$wpdb->prefix}icl_strings s
					INNER JOIN {$wpdb->prefix}icl_string_translations st ON st.string_id = s.id
					WHERE s.context = %s
						AND st.status = %d
						AND st.value <> ''",
				Hooks::stringContext(),
				ICL_TM_COMPLETE
			)
		);

		$translations = [];
		foreach ( (array) $rows as $row ) {
			$translations[ $row->name ][ $row->language ] = (string) $row->value;
		}
		return $translations;
	}

	private function writeOption( array $option ) {
		$wpdb = $this->wpdb;
		$wpdb->query( $wpdb->prepare( "UPDATE {$wpdb->options} SET option_value = %s WHERE option_name = %s", maybe_serialize( $option ), self::OPTION ) );
		wp_cache_delete( self::OPTION, 'options' );
		wp_cache_delete( 'alloptions', 'options' );
	}

	private function backupOnce( array $option ) {
		$wpdb   = $this->wpdb;
		$exists = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT option_id FROM {$wpdb->options} WHERE option_name LIKE %s LIMIT 1",
				$wpdb->esc_like( self::BACKUP_PREFIX ) . '%'
			)
		);
		if ( ! $exists ) {
			add_option( self::BACKUP_PREFIX . gmdate( 'Ymd-His' ), $option, '', false );
		}
	}

	private function unadjustedTerm( $termId, $taxonomy ) {
		$term = Hooks::withoutTermAdjustId(
			function () use ( $termId, $taxonomy ) {
				return get_term( (int) $termId, $taxonomy );
			}
		);
		return ( $term instanceof \WP_Term ) ? $term : null;
	}

	private function translationTermId( $translation, $taxonomy ) {
		if ( isset( $translation->term_id ) ) {
			return (int) $translation->term_id;
		}
		$term = Hooks::withoutTermAdjustId(
			function () use ( $translation, $taxonomy ) {
				return get_term_by( 'term_taxonomy_id', (int) $translation->element_id, $taxonomy );
			}
		);
		return ( $term instanceof \WP_Term ) ? (int) $term->term_id : 0;
	}

	private function invalidateIndexables() {
		\WPML\Container\make( \WPML\WPSEO\YoastSEO\Indexable\Hooks::class )
			->invalidateTermIndexables( array_values( $this->clearedTermIds ) );
		$this->clearedTermIds = [];
	}
}
