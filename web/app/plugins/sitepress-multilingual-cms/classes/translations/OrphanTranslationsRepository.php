<?php

namespace WPML\Translation;

class OrphanTranslationsRepository {

	private $wpdb;

	public function __construct( \wpdb $wpdb ) {
		$this->wpdb = $wpdb;
	}

	public function hasOrphans( $element_type, $source_language ) {
		$wpdb = $this->wpdb;

		return (bool) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT 1
					FROM {$wpdb->posts} p
					INNER JOIN {$wpdb->prefix}icl_translations t
						ON p.ID = t.element_id
						AND t.element_type = %s
						AND t.language_code <> %s
					LEFT JOIN {$wpdb->prefix}icl_translations s
						ON s.trid = t.trid
						AND s.element_type = %s
						AND s.language_code = %s
					WHERE s.translation_id IS NULL
						AND NOT EXISTS (
							SELECT 1 FROM {$wpdb->prefix}icl_translations o
							WHERE o.trid = t.trid AND o.element_type <> t.element_type
						)
					LIMIT 1",
				array( $element_type, $source_language, $element_type, $source_language )
			)
		);
	}

	public function getOrphanCandidates( $element_type, $source_language ) {
		$wpdb = $this->wpdb;

		return $wpdb->get_results(
			$wpdb->prepare(
				"SELECT t.trid, t.element_id, t.language_code
					FROM {$wpdb->posts} p
					INNER JOIN {$wpdb->prefix}icl_translations t
						ON p.ID = t.element_id
						AND t.element_type = %s
						AND t.language_code <> %s
					LEFT JOIN {$wpdb->prefix}icl_translations s
						ON s.trid = t.trid
						AND s.element_type = %s
						AND s.language_code = %s
					WHERE s.translation_id IS NULL
						AND NOT EXISTS (
							SELECT 1 FROM {$wpdb->prefix}icl_translations o
							WHERE o.trid = t.trid AND o.element_type <> t.element_type
						)
					ORDER BY t.trid",
				array( $element_type, $source_language, $element_type, $source_language )
			)
		);
	}
}
