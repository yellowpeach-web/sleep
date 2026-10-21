<?php

class WPML_Fix_Type_Assignments extends WPML_WPDB_And_SP_User {

	private $errors = array();

	public function __construct( $sitepress ) {
		$wpdb = $sitepress->wpdb();
		parent::__construct( $wpdb, $sitepress );
	}

	public function run( $data = [] ) {
		$rows_left    = 0;
		$this->errors = array();

		$rows_fixed  = $this->fix_broken_duplicate_rows();
		$rows_fixed += $this->fix_missing_original();
		$rows_fixed += $this->fix_wrong_source_language();
		$rows_fixed += $this->fix_broken_type_assignments();
		$rows_fixed += $this->fix_broken_taxonomy_assignments();
		$rows_fixed += $this->fix_broken_post_assignments();
		$rows_fixed += $this->fix_mismatched_types();

		$res = $this->fix_orphan_attachments( $data );

		$rows_fixed += $res[0];
		$rows_left  += $res[1];

		icl_cache_clear();
		wp_cache_init();

		$result = [
			'rowsFixed' => $rows_fixed,
			'rowsLeft'  => $rows_left,
		];
		if ( $this->errors ) {
			$result['errors'] = $this->errors;
		}

		return $result;
	}

	private function record_refusal( $step ) {
		$reason         = $this->wpdb->last_error ? $this->wpdb->last_error : 'query failed';
		$this->errors[] = $step . ': ' . $reason;
	}

	private function fix_broken_duplicate_rows() {
		$wpdb = $this->wpdb;

		$rows_fixed = $this->wpdb->query(
			"
			DELETE t
			FROM {$wpdb->prefix}icl_translations i
			  JOIN {$wpdb->prefix}icl_translations t
			    ON i.element_id = t.element_id
			       AND SUBSTRING_INDEX(i.element_type, '_', 1) =
			           SUBSTRING_INDEX(t.element_type, '_', 1)
			       AND i.element_type != t.element_type
			       AND i.translation_id != t.translation_id
			  JOIN (SELECT
			          CONCAT('post_', p.post_type) AS element_type,
			          p.ID                         AS element_id
			        FROM {$wpdb->posts} p
			        UNION ALL
			        SELECT
			          CONCAT('tax_', tt.taxonomy) AS element_type,
			          tt.term_taxonomy_id         AS element_id
			        FROM {$wpdb->term_taxonomy} tt) AS data
			    ON data.element_id = i.element_id
			       AND data.element_type = i.element_type"
		);
		if ( false === $rows_fixed ) {
			$this->record_refusal( 'fix_broken_duplicate_rows' );
		}

		if ( 0 < $rows_fixed ) {
			do_action(
				'wpml_translation_update',
				array(
					'type'          => 'delete',
					'rows_affected' => $rows_fixed,
				)
			);
		}

		return $rows_fixed;
	}

	private function fix_broken_taxonomy_assignments() {
		$wpdb = $this->wpdb;

		$rows_fixed = $this->wpdb->query(
			"UPDATE {$wpdb->prefix}icl_translations t
								JOIN {$wpdb->term_taxonomy} tt
										ON tt.term_taxonomy_id = t.element_id
											AND t.element_type LIKE 'tax%'
											AND t.element_type <> CONCAT('tax_', tt.taxonomy)
									SET t.element_type = CONCAT('tax_', tt.taxonomy)"
		);
		if ( false === $rows_fixed ) {
			$this->record_refusal( 'fix_broken_taxonomy_assignments' );
		}

		if ( 0 < $rows_fixed ) {
			do_action(
				'wpml_translation_update',
				array(
					'context'       => 'tax',
					'type'          => 'element_type_update',
					'rows_affected' => $rows_fixed,
				)
			);
		}

		return $rows_fixed;
	}

	private function fix_broken_post_assignments() {
		$wpdb = $this->wpdb;

		$rows_fixed = $this->wpdb->query(
			"UPDATE {$wpdb->prefix}icl_translations t
								JOIN {$wpdb->posts} p
										ON p.ID = t.element_id
											AND t.element_type LIKE 'post%'
											AND t.element_type <> CONCAT('post_', p.post_type)
									SET t.element_type = CONCAT('post_', p.post_type)"
		);
		if ( false === $rows_fixed ) {
			$this->record_refusal( 'fix_broken_post_assignments' );
		}

		if ( 0 < $rows_fixed ) {
			do_action(
				'wpml_translation_update',
				array(
					'context'       => 'tax',
					'type'          => 'element_type_update',
					'rows_affected' => $rows_fixed,
				)
			);
		}

		return $rows_fixed;
	}

	private function fix_broken_type_assignments() {
		$wpdb = $this->wpdb;

		$rows_fixed = $this->wpdb->query(
			"UPDATE {$wpdb->prefix}icl_translations t
								JOIN {$wpdb->prefix}icl_translations c
										ON c.trid = t.trid
											AND c.language_code != t.language_code
									SET t.element_type = c.element_type
									WHERE c.source_language_code IS NULL
										AND t.source_language_code IS NOT NULL"
		);
		if ( false === $rows_fixed ) {
			$this->record_refusal( 'fix_broken_type_assignments' );
		}

		if ( 0 < $rows_fixed ) {
			do_action(
				'wpml_translation_update',
				array(
					'type'          => 'element_type_update',
					'rows_affected' => $rows_fixed,
				)
			);
		}

		return $rows_fixed;
	}

	private function fix_wrong_source_language() {
		$wpdb = $this->wpdb;

		$rows_fixed = $this->wpdb->query(
			"UPDATE {$wpdb->prefix}icl_translations t
									LEFT JOIN {$wpdb->prefix}icl_translations o
											ON o.trid = t.trid
												AND o.language_code != t.language_code
												AND o.source_language_code IS NULL
									SET t.source_language_code = o.language_code
									WHERE t.source_language_code = ''
										OR t.source_language_code = t.language_code"
		);
		if ( false === $rows_fixed ) {
			$this->record_refusal( 'fix_wrong_source_language' );
		}

		return $rows_fixed;
	}

	private function fix_missing_original() {
		$wpdb            = $this->wpdb;
		$broken_elements = $this->wpdb->get_results(
			"	SELECT MIN(iclt.element_id) AS element_id, iclt.trid
				FROM {$wpdb->prefix}icl_translations iclt
				LEFT JOIN {$wpdb->prefix}icl_translations iclo
					ON iclt.trid = iclo.trid
					AND iclo.source_language_code IS NULL
				WHERE iclo.translation_id IS NULL
				GROUP BY iclt.trid"
		);
		$rows_affected   = 0;
		foreach ( $broken_elements as $element ) {
			$rows_affected_per_element = $wpdb->query(
				$wpdb->prepare(
					"UPDATE {$wpdb->prefix}icl_translations
					 SET source_language_code = NULL
					 WHERE trid = %d AND element_id = %d",
					$element->trid,
					$element->element_id
				)
			);
			if ( false === $rows_affected_per_element ) {
				$this->record_refusal( 'fix_missing_original' );
			}

			if ( 0 < $rows_affected_per_element ) {
				do_action(
					'wpml_translation_update',
					array( 'trid' => $element->trid )
				);
			}

			$rows_affected += $rows_affected_per_element;
		}

		return $rows_affected;
	}

	private function fix_mismatched_types() {
		$wpdb = $this->wpdb;

		$rows_affected = $this->wpdb->query(
			"DELETE t
				FROM {$wpdb->prefix}icl_translations t
				JOIN {$wpdb->prefix}icl_translations o
					ON o.trid = t.trid
					AND o.language_code != t.language_code
					AND o.source_language_code IS NULL
					AND t.source_language_code IS NOT NULL
					AND o.element_type <> t.element_type"
		);
		if ( false === $rows_affected ) {
			$this->record_refusal( 'fix_mismatched_types' );
		}

		if ( 0 < $rows_affected ) {
			do_action(
				'wpml_translation_update',
				array(
					'type'          => 'delete',
					'rows_affected' => $rows_affected,
				)
			);
		}

		return $rows_affected;
	}

	private function fix_orphan_attachments( $data ) {
		$wpdb = $this->wpdb;

		$has_orphan_attachments = $wpdb->get_var(
			"SELECT ID
			FROM {$wpdb->posts} as posts
			LEFT JOIN {$wpdb->prefix}icl_translations as translations
			ON posts.ID = translations.element_id
			WHERE posts.post_type = 'attachment'
			AND translations.element_id IS NULL
			LIMIT 0, 1"
		);

		if ( ! $has_orphan_attachments ) {
			return [ 0, 0 ];
		}

		$default_language     = $this->sitepress->get_default_language();
		$limit                = array_key_exists( 'limit', $data ) ? (int) $data['limit'] : 10;
		$attachments          = $wpdb->get_col(
			$wpdb->prepare(
				"
        SELECT SQL_CALC_FOUND_ROWS ID FROM {$wpdb->posts} WHERE post_type = %s AND ID NOT IN
        (SELECT element_id FROM {$wpdb->prefix}icl_translations WHERE element_type=%s) LIMIT %d",
				array(
					'attachment',
					'post_attachment',
					$limit,
				)
			)
		);

		$found = (int) $wpdb->get_var( 'SELECT FOUND_ROWS()' );

		foreach ( $attachments as $attachment_id ) {
			$this->sitepress->set_element_language_details( $attachment_id, 'post_attachment', false, $default_language );
		}

		$fixed = count( $attachments );
		$left  = max( $found - $fixed, 0 );

		return [ $fixed, $left ];
	}
}
