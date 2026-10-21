<?php

namespace WPML\TM\Settings;

class AffectedTermsQuery {

	private $wpdb;

	public function __construct( \wpdb $wpdb ) {
		$this->wpdb = $wpdb;
	}

	public function countAffected( array $fieldNames ): int {
		if ( ! $fieldNames ) {
			return 0;
		}

		$wpdb = $this->wpdb;

		return (int) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(DISTINCT tm.term_id)
				FROM {$wpdb->termmeta} tm
				INNER JOIN {$wpdb->term_taxonomy} tt
					ON tt.term_id = tm.term_id
				INNER JOIN {$wpdb->prefix}icl_translations orig
					ON orig.element_id = tt.term_taxonomy_id
					AND orig.element_type LIKE %s
					AND orig.source_language_code IS NULL
				WHERE tm.meta_key IN (" . implode( ', ', array_fill( 0, count( $fieldNames ), '%s' ) ) . ")
				  AND tm.meta_key <> ''
				  AND EXISTS (
				      SELECT 1 FROM {$wpdb->prefix}icl_translations tr
				      WHERE tr.trid = orig.trid
				        AND tr.source_language_code IS NOT NULL
				  )",
				...$this->taxonomyPrefixAndFieldNames( $fieldNames )
			)
		);
	}

	public function page( array $fieldNames, int $lastTermId, int $limit ): array {
		if ( ! $fieldNames || $limit < 1 ) {
			return [];
		}

		$wpdb = $this->wpdb;

		$ids = $wpdb->get_col(
			$wpdb->prepare(
				"SELECT DISTINCT tm.term_id
				FROM {$wpdb->termmeta} tm
				INNER JOIN {$wpdb->term_taxonomy} tt
					ON tt.term_id = tm.term_id
				INNER JOIN {$wpdb->prefix}icl_translations orig
					ON orig.element_id = tt.term_taxonomy_id
					AND orig.element_type LIKE %s
					AND orig.source_language_code IS NULL
				WHERE tm.meta_key IN (" . implode( ', ', array_fill( 0, count( $fieldNames ), '%s' ) ) . ")
				  AND tm.meta_key <> ''
				  AND EXISTS (
				      SELECT 1 FROM {$wpdb->prefix}icl_translations tr
				      WHERE tr.trid = orig.trid
				        AND tr.source_language_code IS NOT NULL
				  )
				  AND tm.term_id > %d
				ORDER BY tm.term_id ASC
				LIMIT %d",
				...array_merge( $this->taxonomyPrefixAndFieldNames( $fieldNames ), [ $lastTermId, $limit ] )
			)
		);

		return array_map( 'intval', (array) $ids );
	}

	public function termTaxonomyIds( int $termId ): array {
		$wpdb = $this->wpdb;
		$ids  = $wpdb->get_col(
			$wpdb->prepare( "SELECT term_taxonomy_id FROM {$wpdb->term_taxonomy} WHERE term_id = %d", $termId )
		);

		return array_map( 'intval', (array) $ids );
	}

	private function taxonomyPrefixAndFieldNames( array $fieldNames ): array {
		return array_merge(
			[ $this->wpdb->esc_like( 'tax_' ) . '%' ],
			array_map( 'strval', array_values( $fieldNames ) )
		);
	}
}
