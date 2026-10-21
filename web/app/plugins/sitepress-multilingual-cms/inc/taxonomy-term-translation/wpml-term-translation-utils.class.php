<?php

use WPML\FP\Fns;

class WPML_Term_Translation_Utils extends WPML_SP_User {

	const LINK_PAGE_SIZE = 200;

	function sync_terms( $original_post_id, $lang ) {
		$this->synchronize_terms( $original_post_id, $lang, false );
	}

	function duplicate_terms( $original_post_id, $lang ) {
		$this->synchronize_terms( $original_post_id, $lang, true );
	}

	private function synchronize_terms( $original_post_id, $lang, $duplicate ) {
		global $wpml_post_translations;

		$returnTrue = Fns::always( true );
		add_filter( 'wpml_disable_term_adjust_id', $returnTrue );

		$wpml_post_translations->reload();
		$translated_post_id = $wpml_post_translations->element_id_in( $original_post_id, $lang );
		if ( (bool) $translated_post_id === true ) {
			$taxonomies = get_post_taxonomies( $original_post_id );

			foreach ( $taxonomies as $tax ) {
				$tax               = (string) $tax;
				$terms_on_original = wp_get_object_terms( $original_post_id, $tax );
				if ( is_wp_error ( $terms_on_original ) ) {
					continue;
				}

				if ( ! $this->sitepress->is_translated_taxonomy( $tax ) ) {
					if ( $this->sitepress->get_setting( 'sync_post_taxonomies' ) ) {
						foreach ( $terms_on_original as $key => $term ) {
							$terms_on_original[ $key ] = $term->term_id;
						}
						wp_set_object_terms( $translated_post_id, $terms_on_original, $tax );
					}
				} else {

					$translated_terms = $this->get_translated_term_ids( $terms_on_original, $lang, $tax, $duplicate );
					wp_set_object_terms( $translated_post_id, $translated_terms, $tax );
				}
			}
		}

		remove_filter( 'wpml_disable_term_adjust_id', $returnTrue );
		$post_type = get_post_type( $original_post_id );
		$post_type && clean_object_term_cache( $original_post_id, $post_type );
	}

	public function link_translated_posts_to_term( $term_taxonomy_id, $lang, $taxonomy, $limit = 0 ) {
		global $wpdb;

		$term_taxonomy_id = (int) $term_taxonomy_id;
		if ( ! $term_taxonomy_id || ! $lang || ! $this->sitepress->is_translated_taxonomy( $taxonomy ) ) {
			return [];
		}

		$rows = $this->translated_posts_missing_term( $term_taxonomy_id, $lang, $taxonomy, $limit > 0 ? (int) $limit : PHP_INT_MAX );
		if ( ! $rows ) {
			return [];
		}

		$term_id = (int) $wpdb->get_var(
			$wpdb->prepare( "SELECT term_id FROM {$wpdb->term_taxonomy} WHERE term_taxonomy_id = %d", $term_taxonomy_id )
		);
		if ( ! $term_id ) {
			return [];
		}

		$siblings          = $this->other_language_versions( $term_taxonomy_id, $taxonomy );
		$carrying_siblings = $siblings ? array_flip( $this->posts_carrying( $rows, array_keys( $siblings ) ) ) : [];

		$returnTrue = Fns::always( true );
		add_filter( 'wpml_disable_term_adjust_id', $returnTrue );
		$linked = [];
		foreach ( $rows as $post_id ) {
			$post_id = (int) $post_id;
			if ( isset( $carrying_siblings[ $post_id ] ) ) {
				wp_remove_object_terms( $post_id, array_values( $siblings ), $taxonomy );
			}
			$result = wp_set_object_terms( $post_id, [ $term_id ], $taxonomy, true );
			if ( ! is_wp_error( $result ) ) {
				$linked[] = $post_id;
			}
		}
		remove_filter( 'wpml_disable_term_adjust_id', $returnTrue );

		if ( $linked ) {
			do_action( 'wpml_translated_posts_linked_to_term', $linked, $term_taxonomy_id, $taxonomy, $lang );
		}

		return $linked;
	}

	private function other_language_versions( $term_taxonomy_id, $taxonomy ) {
		global $wpdb;

		$rows = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT tt.term_id, tt.term_taxonomy_id
				FROM {$wpdb->prefix}icl_translations term_translation
				JOIN {$wpdb->prefix}icl_translations sibling
					ON sibling.trid = term_translation.trid
					AND sibling.element_type = term_translation.element_type
					AND sibling.language_code <> term_translation.language_code
				JOIN {$wpdb->term_taxonomy} tt
					ON tt.term_taxonomy_id = sibling.element_id
				WHERE term_translation.element_id = %d
					AND term_translation.element_type = %s",
				(int) $term_taxonomy_id,
				'tax_' . $taxonomy
			)
		);

		$versions = [];
		foreach ( is_array( $rows ) ? $rows : [] as $row ) {
			$versions[ (int) $row->term_taxonomy_id ] = (int) $row->term_id;
		}

		return $versions;
	}

	private function posts_carrying( $post_ids, $term_taxonomy_ids ) {
		global $wpdb;

		$post_ids          = array_map( 'intval', $post_ids );
		$term_taxonomy_ids = array_map( 'intval', $term_taxonomy_ids );

		return array_map(
			'intval',
			$wpdb->get_col(
				$wpdb->prepare(
					"SELECT DISTINCT object_id FROM {$wpdb->term_relationships}
					WHERE object_id IN (" . implode( ',', array_fill( 0, count( $post_ids ), '%d' ) ) . ')
						AND term_taxonomy_id IN (' . implode( ',', array_fill( 0, count( $term_taxonomy_ids ), '%d' ) ) . ')',
					array_merge( $post_ids, $term_taxonomy_ids )
				)
			)
		);
	}

	public function link_created_term_translation( $term_taxonomy_id, $lang, $taxonomy ) {
		$linked = $this->link_translated_posts_to_term( $term_taxonomy_id, $lang, $taxonomy, self::LINK_PAGE_SIZE );

		if ( count( $linked ) >= self::LINK_PAGE_SIZE ) {
			$tasks = \WPML\Container\make( \WPML\Core\BackgroundTask\Service\BackgroundTaskService::class );
			$tasks->add(
				\WPML\Container\make( \WPML\TM\Taxonomy\LinkTranslatedPostsToTermTask::class ),
				wpml_collect(
					[
						'term_taxonomy_id' => (int) $term_taxonomy_id,
						'lang'             => (string) $lang,
						'taxonomy'         => (string) $taxonomy,
					]
				)
			);
		}

		return $linked;
	}

	public function count_translated_posts_missing_term( $term_taxonomy_id, $lang, $taxonomy ) {
		if ( ! $term_taxonomy_id || ! $lang || ! $taxonomy ) {
			return 0;
		}

		return count( $this->translated_posts_missing_term( (int) $term_taxonomy_id, (string) $lang, (string) $taxonomy, PHP_INT_MAX ) );
	}

	private function translated_posts_missing_term( $term_taxonomy_id, $lang, $taxonomy, $limit ) {
		global $wpdb;

		return $wpdb->get_col(
			$wpdb->prepare(
				"SELECT DISTINCT translated_post.element_id
				FROM {$wpdb->prefix}icl_translations term_translation
				JOIN {$wpdb->prefix}icl_translations sibling
					ON sibling.trid = term_translation.trid
					AND sibling.element_type = term_translation.element_type
					AND sibling.language_code <> term_translation.language_code
					AND sibling.element_id IS NOT NULL
				JOIN {$wpdb->term_relationships} carried
					ON carried.term_taxonomy_id = sibling.element_id
				JOIN {$wpdb->prefix}icl_translations original_post
					ON original_post.element_id = carried.object_id
					AND original_post.element_type LIKE %s
					AND original_post.source_language_code IS NULL
					AND original_post.language_code = sibling.language_code
				JOIN {$wpdb->prefix}icl_translations translated_post
					ON translated_post.trid = original_post.trid
					AND translated_post.element_type = original_post.element_type
					AND translated_post.language_code = %s
					AND translated_post.element_id IS NOT NULL
				LEFT JOIN {$wpdb->term_relationships} linked
					ON linked.object_id = translated_post.element_id
					AND linked.term_taxonomy_id = term_translation.element_id
				WHERE term_translation.element_id = %d
					AND term_translation.element_type = %s
					AND term_translation.language_code = %s
					AND linked.object_id IS NULL
				ORDER BY translated_post.element_id
				LIMIT %d",
				$wpdb->esc_like( 'post_' ) . '%',
				$lang,
				(int) $term_taxonomy_id,
				'tax_' . $taxonomy,
				$lang,
				(int) $limit
			)
		);
	}

	private function get_translated_term_ids( $terms, $lang, $taxonomy, $duplicate ) {
		global $wpml_term_translations;

		$term_utils = new WPML_Terms_Translations();
		$wpml_term_translations->reload();
		$ttids = array();
		foreach ( $terms as $orig_term ) {
			if ( ! empty( $orig_term->term_taxonomy_id ) ) {
				$ttids[] = (int) $orig_term->term_taxonomy_id;
			}
		}
		if ( count( $ttids ) > 1 ) {
			$wpml_term_translations->prefetch_ids( $ttids );
		}
		$translated_terms = array();
		foreach ( $terms as $orig_term ) {
			$translated_id = (int) $wpml_term_translations->term_id_in( $orig_term->term_id, $lang );
			if ( ! $translated_id && $duplicate ) {
				$translation   = $term_utils->create_automatic_translation(
					array(
						'lang_code' => $lang,
						'taxonomy'  => $taxonomy,
						'trid'      => $wpml_term_translations->get_element_trid( $orig_term->term_taxonomy_id ),
					)
				);
				$translated_id = isset( $translation['term_id'] ) ? $translation['term_id'] : false;
			}
			if ( $translated_id ) {
				$translated_terms[] = $translated_id;
			}
		}

		return $translated_terms;
	}
}
