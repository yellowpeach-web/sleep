<?php

use WPML\FP\Fns;
use WPML\FP\Maybe;

class WPML_Term_Translation extends WPML_Element_Translation {
	const CACHE_MAX_WARMUP_COUNT = 200;
	const CACHE_EXPIRE           = 0;
	const CACHE_GROUP            = 'wpml_term_translation';

	protected $cache_max_warmup_count = self::CACHE_MAX_WARMUP_COUNT;

	private static $keys_in_cache = array();

	public function get_cache_max_warmup_count() {
		return $this->cache_max_warmup_count;
	}

	public function set_cache_max_warmup_count( $cache_max_warmup_count ) {
		$this->cache_max_warmup_count = $cache_max_warmup_count;
	}

	public function add_hooks() {
		wp_cache_add_non_persistent_groups( self::CACHE_GROUP );

		add_action( 'delete_term', array( $this, 'invalidate_cache' ) );

		add_action( 'set_object_terms', array( $this, 'invalidate_cache' ) );

		add_action( 'saved_term', array( $this, 'invalidate_cache' ) );

		add_action( 'deleted_term_relationships', array( $this, 'invalidate_cache' ) );

		add_action( 'clean_object_term_cache', array( $this, 'invalidate_cache' ) );

		add_action( 'clean_term_cache', array( $this, 'invalidate_cache' ) );
	}

	public function invalidate_cache() {
		foreach ( array_keys( self::$keys_in_cache ) as $key ) {
			wp_cache_delete( $key, self::CACHE_GROUP );
		}
		self::$keys_in_cache = array();
	}

	private function get_from_cache( $key ) {
		$value = wp_cache_get( $key, self::CACHE_GROUP );

		return is_array( $value ) && array_key_exists( 'data', $value ) ? Maybe::just( $value['data'] ) : Maybe::nothing();
	}

	private function set_in_cache( $key, $value ) {
		self::$keys_in_cache[ $key ] = true;
		wp_cache_set( $key, array( 'data' => $value ), self::CACHE_GROUP, self::CACHE_EXPIRE );
	}

	public function lang_code_by_termid( $term_id ) {
		return $this->get_element_lang_code( (int) $this->adjust_ttid_for_term_id( $term_id ) );
	}

	public function term_ids_by_slug( $slug, $taxonomy ) {
		$wpdb      = $this->wpdb;
		$cache_key = $taxonomy . ':' . $slug;

		return WPML_Non_Persistent_Cache::execute_and_cache(
			$cache_key,
			function () use ( $slug, $taxonomy, $wpdb ) {
				return array_map(
					'intval',
					(array) $wpdb->get_col(
						$wpdb->prepare(
							"SELECT t.term_id
							FROM {$wpdb->terms} t
							JOIN {$wpdb->term_taxonomy} tt ON tt.term_id = t.term_id
							WHERE tt.taxonomy = %s AND t.slug = %s
							ORDER BY t.term_id",
							$taxonomy,
							$slug
						)
					)
				);
			},
			__CLASS__
		);
	}

	private function set_data_to_cache( array $data ) {
		foreach ( $data as $row ) {
			$this->set_in_cache( 'ttid_by_termid_' . $row['term_id'], (int) $row['element_id'] );
			$this->set_in_cache( 'ttid_by_termidtaxonomy_' . $row['term_id'] . $row['taxonomy'], (int) $row['element_id'] );
			$this->set_in_cache( 'termid_by_ttid_' . $row['element_id'], (int) $row['term_id'] );
		}
	}

	private function is_cache_type_select_all_items_in_one_query( $count ) {
		return $count <= $this->get_cache_max_warmup_count();
	}

	public function adjust_ttid_for_term_id( $term_id ) {
		$count = $this->maybe_warm_term_id_cache();
		if ( 0 === $count ) {
			return $term_id;
		}

		$key_prefix = 'ttid_by_termid_';
		$key        = $key_prefix . $term_id;

		$get_ttid_or_fallback_to_term_id = function() use ( $key_prefix, $key, $term_id, $count ) {
			if ( $this->is_cache_type_select_all_items_in_one_query( $count ) ) {
				return $term_id;
			}

			$data = $this->get_maybe_warm_term_id_cache_data( ' AND tax.term_id = %d', array( $term_id ) );
			if ( count( $data ) ) {
				$this->set_data_to_cache( $data );
			} else {
				$this->set_in_cache( $key_prefix . $term_id, $term_id );
			}

			return $this->get_from_cache( $key )->getOrElse( $term_id );
		};

		return $this->get_from_cache( $key )->getOrElse( $get_ttid_or_fallback_to_term_id );
	}

	public function adjust_term_id_for_ttid( $ttid ) {
		$count = $this->maybe_warm_term_id_cache();
		if ( 0 === $count ) {
			return $ttid;
		}

		$key_prefix = 'termid_by_ttid_';
		$key        = $key_prefix . $ttid;

		$get_term_id_or_fallback_to_ttid = function() use ( $key_prefix, $key, $ttid, $count ) {
			if ( $this->is_cache_type_select_all_items_in_one_query( $count ) ) {
				return $ttid;
			}

			$data = $this->get_maybe_warm_term_id_cache_data( ' AND tax.term_taxonomy_id = %d', array( $ttid ) );
			if ( count( $data ) ) {
				$this->set_data_to_cache( $data );
			} else {
				$this->set_in_cache( $key_prefix . $ttid, $ttid );
			}

			return $this->get_from_cache( $key )->getOrElse( $ttid );
		};

		return $this->get_from_cache( $key )->getOrElse( $get_term_id_or_fallback_to_ttid );
	}

	public function term_id_in( $term_id, $lang_code, $original_fallback = false ) {

		return $this->adjust_term_id_for_ttid(
			$this->element_id_in( (int) $this->adjust_ttid_for_term_id( $term_id ), $lang_code, $original_fallback )
		);
	}

	public function trid_from_tax_and_id( $term_id, $taxonomy ) {
		$count = $this->maybe_warm_term_id_cache();
		if ( 0 === $count ) {
			return $this->get_element_trid( $term_id );
		}

		$key_prefix = 'ttid_by_termidtaxonomy_';
		$key        = $key_prefix . $term_id . $taxonomy;

		$get_term_id = function() use ( $key_prefix, $key, $term_id, $taxonomy, $count ) {
			if ( $this->is_cache_type_select_all_items_in_one_query( $count ) ) {
				return $term_id;
			}

			$data = $this->get_maybe_warm_term_id_cache_data( ' AND tax.term_id = %d AND tax.taxonomy = %s', array( $term_id, $taxonomy ) );
			if ( count( $data ) ) {
				$this->set_data_to_cache( $data );
			} else {
				$this->set_in_cache( $key_prefix . $term_id . $taxonomy, $term_id );
			}

			return $this->get_from_cache( $key )->getOrElse( $term_id );
		};

		$ttid = $this->get_from_cache( $key )->getOrElse( $get_term_id );

		return $this->get_element_trid( $ttid );
	}

	public function get_taxonomy_post_types( $taxonomy ) {
		return WPML_WP_Taxonomy::get_linked_post_types( $taxonomy );
	}

	protected function get_element_join() {

		return "
				JOIN {$this->wpdb->term_taxonomy} tax
					ON wpml_translations.element_id = tax.term_taxonomy_id
						AND wpml_translations.element_type = CONCAT('tax_', tax.taxonomy)
		";
	}

	protected function get_query_sql( $cols = 'wpml_translations.element_id, tax.term_id, tax.taxonomy' ) {
		$sql  = '';
		$sql .= "SELECT {$cols} FROM {$this->wpdb->prefix}icl_translations wpml_translations" . $this->get_element_join();
		$sql .= " JOIN {$this->wpdb->terms} terms";
		$sql .= ' ON terms.term_id = tax.term_id';
		$sql .= ' WHERE tax.term_id != tax.term_taxonomy_id';

		return $sql;
	}

	protected function get_type_prefix() {
		return 'tax_';
	}

	private function maybe_warm_term_id_cache() {
		$key = 'items_count_for_cache';

		$get_count = function() use ( $key ) {
			$sql = $this->get_query_sql( 'tax.term_id' );
			$sql = $sql . ' LIMIT 0, ' . ( $this->get_cache_max_warmup_count() + 1 );

			$data  = $this->wpdb->get_results( $sql, ARRAY_A );
			$count = is_array( $data ) ? count( $data ) : 0;

			$this->set_in_cache( $key, $count );

			if ( $count > 0 && $count <= $this->get_cache_max_warmup_count() ) {
				$data = $this->get_maybe_warm_term_id_cache_data();
				$this->set_data_to_cache( $data );
			}

			return $count;
		};

		return $this->get_from_cache( $key )->getOrElse( $get_count );
	}

	private function get_maybe_warm_term_id_cache_data( $where_sql = '', $where_sql_params = array() ) {
		$sql = $this->get_query_sql();

		if ( count( $where_sql_params ) > 0 ) {
			$sql = $this->wpdb->prepare( $sql . $where_sql, $where_sql_params );
		}

		$data = $this->wpdb->get_results( $sql, ARRAY_A );

		return $data;
	}

	public function generate_unique_term_slug( $term, $slug, $taxonomy, $lang_code ) {
		if ( '' === trim( $slug ) ) {
			$slug = sanitize_title( $term );
		}
		return WPML_Terms_Translations::term_unique_slug( $slug, $taxonomy, $lang_code );
	}

	public static function getGlobalInstance() {
		global $wpml_term_translations, $wpdb;

		if ( ! isset( $wpml_term_translations ) ) {
			$wpml_term_translations = new WPML_Term_Translation( $wpdb );
		}

		return $wpml_term_translations;
	}
}
