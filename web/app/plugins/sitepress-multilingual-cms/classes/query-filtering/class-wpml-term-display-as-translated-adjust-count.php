<?php

class WPML_Term_Display_As_Translated_Adjust_Count {

	private $sitepress;

	private $wpdb;

	private $taxonomies_display_as_translated = null;

	private $taxonomies_signature = null;

	private $adjusted_counts = array();

	private $prefetch_is_complete = array();

	const PREFETCH_LIMIT = 1000;

	public function __construct(
		SitePress $sitepress,
		wpdb $wpdb
    ) {
        if (
            ! isset( $GLOBALS['wp_version'] )
			|| version_compare( $GLOBALS['wp_version'], '6.0', '<' )
        ) {
            return;
        }

        if ( is_admin() && ! WPML_Ajax::is_frontend_ajax_request() ) {
            return;
        }

		$this->sitepress = $sitepress;
		$this->wpdb      = $wpdb;

		add_filter( 'get_term', [ $this, 'get_term_adjust_count' ], 10, 2 );
	}

	public function get_term_adjust_count( $term, $taxonomy = null ) {
		if ( ! is_object( $term ) || ! isset( $term->taxonomy, $term->term_id ) ) {
			return $term;
		}

		if ( ! in_array( (string) $term->taxonomy, $this->get_taxonomies_display_as_translated(), true ) ) {
			return $term;
		}

		$originalTermCount = (int) $this->get_adjusted_count( $term );

        if ( $originalTermCount > $term->count ) {
            $term->count = $originalTermCount;
        }

        return $term;
    }

	private function get_taxonomies_display_as_translated() {
		$signature = isset( $GLOBALS['wp_taxonomies'] ) ? count( (array) $GLOBALS['wp_taxonomies'] ) : 0;

		if ( null !== $this->taxonomies_display_as_translated && $signature === $this->taxonomies_signature ) {
			return $this->taxonomies_display_as_translated;
		}

		$this->taxonomies_display_as_translated = (array) $this->sitepress->get_display_as_translated_taxonomies();
		$this->taxonomies_signature             = $signature;

		return $this->taxonomies_display_as_translated;
	}

	private function get_adjusted_count( $term ) {
		$taxonomy = (string) $term->taxonomy;
		$term_id  = (int) $term->term_id;

		$this->prefetch_adjusted_counts( $taxonomy );

		if ( array_key_exists( $term_id, $this->adjusted_counts[ $taxonomy ] ) ) {
			return $this->adjusted_counts[ $taxonomy ][ $term_id ];
		}

		if ( ! empty( $this->prefetch_is_complete[ $taxonomy ] ) ) {
			$this->adjusted_counts[ $taxonomy ][ $term_id ] = null;

			return null;
		}

		$table_prefix = $this->wpdb->prefix;

		$count = $this->wpdb->get_var(
			$this->wpdb->prepare(
				"SELECT
                (
                    SELECT term_taxonomy.count
                    FROM {$table_prefix}term_taxonomy term_taxonomy
                    INNER JOIN {$table_prefix}icl_translations translations
                        ON translations.element_id = term_taxonomy.term_taxonomy_id
                    WHERE translations.trid = icl_t.trid
                    AND translations.language_code = %s
                ) as `originalCount`
                FROM {$table_prefix}terms AS t
                INNER JOIN {$table_prefix}term_taxonomy AS tt
                    ON t.term_id = tt.term_id
                LEFT JOIN {$table_prefix}icl_translations icl_t
                    ON icl_t.element_id = tt.term_taxonomy_id
                WHERE t.term_id = %d AND icl_t.element_type = %s
                ",
				$this->sitepress->get_default_language(),
				$term_id,
				'tax_' . $taxonomy
			)
		);


		$this->adjusted_counts[ $taxonomy ][ $term_id ] = null === $count ? null : (int) $count;

		return $this->adjusted_counts[ $taxonomy ][ $term_id ];
	}

	private function prefetch_adjusted_counts( $taxonomy ) {
		if ( isset( $this->adjusted_counts[ $taxonomy ] ) ) {
			return;
		}

		$this->adjusted_counts[ $taxonomy ] = array();

		$limit = (int) apply_filters(
			'wpml_display_as_translated_count_prefetch_limit',
			self::PREFETCH_LIMIT,
			$taxonomy
		);
		if ( $limit < 1 ) {
			return;
		}

		$table_prefix = $this->wpdb->prefix;

		$rows = $this->wpdb->get_results(
			$this->wpdb->prepare(
				"SELECT tt.term_id,
                (
                    SELECT term_taxonomy.count
                    FROM {$table_prefix}term_taxonomy term_taxonomy
                    INNER JOIN {$table_prefix}icl_translations translations
                        ON translations.element_id = term_taxonomy.term_taxonomy_id
                    WHERE translations.trid = icl_t.trid
                    AND translations.language_code = %s
                ) as `original_count`
                FROM {$table_prefix}term_taxonomy AS tt
                INNER JOIN {$table_prefix}icl_translations icl_t
                    ON icl_t.element_id = tt.term_taxonomy_id
                WHERE tt.taxonomy = %s AND icl_t.element_type = %s
                LIMIT %d
                ",
				$this->sitepress->get_default_language(),
				$taxonomy,
				'tax_' . $taxonomy,
				$limit
			)
		);


		foreach ( (array) $rows as $row ) {
			$this->adjusted_counts[ $taxonomy ][ (int) $row->term_id ] =
				null === $row->original_count ? null : (int) $row->original_count;
		}

		$this->prefetch_is_complete[ $taxonomy ] = count( (array) $rows ) < $limit;
	}
}
