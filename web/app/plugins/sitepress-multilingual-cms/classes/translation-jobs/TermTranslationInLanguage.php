<?php

namespace WPML\TM\Jobs;

use WPML\Core\SharedKernel\Component\Translation\Domain\TranslationStatus;

class TermTranslationInLanguage {

	public static function covered( array $termTaxonomyIds, $lang ) {
		$covered = [];
		foreach ( self::read( $termTaxonomyIds, $lang ) as $row ) {
			if ( $row['translated'] || in_array( $row['status'], TranslationStatus::CARRIES_UNITS, true ) ) {
				$covered[ $row['original'] ] = $row['original'];
			}
		}

		return array_values( $covered );
	}

	public static function translations( array $termTaxonomyIds, $lang ) {
		$translations = [];
		foreach ( self::read( $termTaxonomyIds, $lang ) as $row ) {
			if ( $row['translated'] && ! isset( $translations[ $row['original'] ] ) ) {
				$translations[ $row['original'] ] = $row['translated'];
			}
		}

		return $translations;
	}

	private static function read( array $termTaxonomyIds, $lang ) {
		global $wpdb;

		$termTaxonomyIds = array_values( array_unique( array_filter( array_map( 'intval', $termTaxonomyIds ) ) ) );
		$lang            = (string) $lang;
		if ( ! $termTaxonomyIds || '' === $lang ) {
			return [];
		}

		$taxTypes = $wpdb->esc_like( 'tax_' ) . '%';
		$rows     = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT term.term_taxonomy_id original, translated_term.term_taxonomy_id translated, status.status
				FROM {$wpdb->term_taxonomy} term
				JOIN {$wpdb->prefix}icl_translations original
					ON original.element_id = term.term_taxonomy_id
					AND original.element_type LIKE %s
				JOIN {$wpdb->prefix}icl_translations translation
					ON translation.trid = original.trid
					AND translation.language_code = %s
					AND translation.element_type LIKE %s
				LEFT JOIN {$wpdb->term_taxonomy} translated_term
					ON translated_term.term_taxonomy_id = translation.element_id
					AND CONCAT( 'tax_', translated_term.taxonomy ) = translation.element_type
				LEFT JOIN {$wpdb->prefix}icl_translation_status status
					ON status.translation_id = translation.translation_id
				WHERE term.term_taxonomy_id IN (" . implode( ', ', array_fill( 0, count( $termTaxonomyIds ), '%d' ) ) . ')
					AND ( translated_term.term_taxonomy_id IS NOT NULL OR translation.element_id IS NULL )
				ORDER BY term.term_taxonomy_id, CONCAT( \'tax_\', term.taxonomy ) = original.element_type DESC',
				array_merge( [ $taxTypes, $lang, $taxTypes ], $termTaxonomyIds )
			),
			ARRAY_A
		);

		return array_map(
			function ( $row ) {
				return [
					'original'   => (int) $row['original'],
					'translated' => (int) $row['translated'],
					'status'     => null === $row['status'] ? -1 : (int) $row['status'],
				];
			},
			(array) $rows
		);
	}
}
