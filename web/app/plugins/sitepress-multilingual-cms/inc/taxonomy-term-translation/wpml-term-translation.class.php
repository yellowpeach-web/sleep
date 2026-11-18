<?php

use WPML\FP\Fns;
use WPML\FP\Maybe;

class WPML_Term_Translation extends WPML_Element_Translation {
	const CACHE_MAX_WARMUP_COUNT = 200;
	const CACHE_EXPIRE           = 0;
	const CACHE_GROUP            = 'wpml_term_translation';

	const SLUG_WARMUP_MAX = 1000;

	const SLUG_WARM_STATE_PREFIX     = '#slug_warm_state:';
	const SLUG_WARM_STATE_FIRST_MISS = 'first_miss';
	const SLUG_WARM_STATE_WARMED     = 'warmed';
	const SLUG_WARM_STATE_PER_SLUG   = 'per_slug';

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

		WPML_Non_Persistent_Cache::flush_group( __CLASS__ );
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
		$cache_key = $taxonomy . ':' . $slug;
		$found     = false;

		$ids = WPML_Non_Persistent_Cache::get( $cache_key, __CLASS__, $found );
		if ( $found ) {
			return $ids;
		}

		$state_key = self::SLUG_WARM_STATE_PREFIX . $taxonomy;
		$state     = WPML_Non_Persistent_Cache::get( $state_key, __CLASS__ );

		if ( self::SLUG_WARM_STATE_FIRST_MISS === $state ) {
			$this->warm_slugs( $taxonomy, $state_key );
			$ids = WPML_Non_Persistent_Cache::get( $cache_key, __CLASS__, $found );
			if ( $found ) {
				return $ids;
			}
		} elseif ( false === $state ) {
			WPML_Non_Persistent_Cache::set( $state_key, self::SLUG_WARM_STATE_FIRST_MISS, __CLASS__ );
		}

		$ids = $this->read_term_ids_by_slug( $slug, $taxonomy );
		WPML_Non_Persistent_Cache::set( $cache_key, $ids, __CLASS__ );

		return $ids;
	}

	private function read_term_ids_by_slug( $slug, $taxonomy ) {
		$wpdb = $this->wpdb;

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
	}

	private function warm_slugs( $taxonomy, $state_key ) {
		$wpdb = $this->wpdb;

		$rows = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT t.term_id, t.slug, tt.term_taxonomy_id
				FROM {$wpdb->terms} t
				JOIN {$wpdb->term_taxonomy} tt ON tt.term_id = t.term_id
				WHERE tt.taxonomy = %s
				ORDER BY t.term_id
				LIMIT %d",
				$taxonomy,
				self::SLUG_WARMUP_MAX + 1
			),
			ARRAY_A
		);

		if ( ! is_array( $rows ) || $this->last_query_failed() || count( $rows ) > self::SLUG_WARMUP_MAX ) {
			WPML_Non_Persistent_Cache::set( $state_key, self::SLUG_WARM_STATE_PER_SLUG, __CLASS__ );

			return;
		}

		$ids_by_slug = [];
		$ttids       = [];
		foreach ( $rows as $row ) {
			$ids_by_slug[ (string) $row['slug'] ][] = (int) $row['term_id'];
			$ttids[ (int) $row['term_id'] ]         = (int) $row['term_taxonomy_id'];
		}

		foreach ( $ids_by_slug as $slug => $ids ) {
			WPML_Non_Persistent_Cache::set( $taxonomy . ':' . $slug, $ids, __CLASS__ );
		}
		WPML_Non_Persistent_Cache::set( $state_key, self::SLUG_WARM_STATE_WARMED, __CLASS__ );

		$this->prefetch_ids( array_values( $ttids ) );

		foreach ( $ttids as $term_id => $ttid ) {
			$key = 'ttid_by_termid_' . $term_id;
			if ( $this->get_from_cache( $key )->isJust() ) {
				continue;
			}
			$this->set_in_cache( $key, $ttid );
		}
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

		$key = 'ttid_by_termid_' . $term_id;

		$read_missing_term = function() use ( $key, $term_id ) {
			return $this->read_missing_term( $key, $term_id, 'term_taxonomy_id', 'tax.term_id = %d', array( $term_id ) );
		};

		return $this->get_from_cache( $key )->getOrElse( $read_missing_term );
	}

	public function adjust_term_id_for_ttid( $ttid ) {
		$count = $this->maybe_warm_term_id_cache();
		if ( 0 === $count ) {
			return $ttid;
		}

		$key = 'termid_by_ttid_' . $ttid;

		$read_missing_term = function() use ( $key, $ttid ) {
			return $this->read_missing_term( $key, $ttid, 'term_id', 'tax.term_taxonomy_id = %d', array( $ttid ) );
		};

		return $this->get_from_cache( $key )->getOrElse( $read_missing_term );
	}

	public function term_id_in( $term_id, $lang_code, $original_fallback = false, $taxonomy = null ) {
		$ttid = is_string( $taxonomy ) && '' !== $taxonomy
			? $this->adjust_ttid_for_term_id_in_taxonomy( $term_id, $taxonomy )
			: $this->adjust_ttid_for_term_id( $term_id );

		return $this->adjust_term_id_for_ttid(
			$this->element_id_in( (int) $ttid, $lang_code, $original_fallback )
		);
	}

	public function trid_from_tax_and_id( $term_id, $taxonomy ) {
		return $this->get_element_trid( $this->adjust_ttid_for_term_id_in_taxonomy( $term_id, $taxonomy ) );
	}

	private function adjust_ttid_for_term_id_in_taxonomy( $term_id, $taxonomy ) {
		$count = $this->maybe_warm_term_id_cache();
		if ( 0 === $count ) {
			return $term_id;
		}

		$key = 'ttid_by_termidtaxonomy_' . $term_id . $taxonomy;

		$read_missing_term = function() use ( $key, $term_id, $taxonomy ) {
			return $this->read_missing_term(
				$key,
				$term_id,
				'term_taxonomy_id',
				'tax.term_id = %d AND tax.taxonomy = %s',
				array( $term_id, $taxonomy )
			);
		};

		return $this->get_from_cache( $key )->getOrElse( $read_missing_term );
	}

	private function read_missing_term( $key, $fallback, $column, $where, array $params ) {
		$wpdb = $this->wpdb;

		$rows = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT tax.term_taxonomy_id, tax.term_id, tax.taxonomy, wpml_translations.translation_id
				FROM {$wpdb->term_taxonomy} tax
				LEFT JOIN {$wpdb->prefix}icl_translations wpml_translations
					ON wpml_translations.element_id = tax.term_taxonomy_id
						AND wpml_translations.element_type = CONCAT('tax_', tax.taxonomy)
				WHERE {$where}",
				$params
			),
			ARRAY_A
		);

		if ( $this->last_query_failed() ) {
			return $fallback;
		}
		$rows = is_array( $rows ) ? $rows : array();

		foreach ( $this->missing_term_writes( $key, $fallback, $column, $rows ) as $write_key => $value ) {
			$this->set_in_cache( $write_key, $value );
		}

		return $this->get_from_cache( $key )->getOrElse( $fallback );
	}

	private function missing_term_writes( $key, $fallback, $column, array $rows ) {
		$rows   = array_values( $rows );
		$writes = array();
		foreach ( $rows as $row ) {
			if ( null !== $row['translation_id'] && (int) $row['term_id'] !== (int) $row['term_taxonomy_id'] ) {
				$writes[ 'ttid_by_termid_' . $row['term_id'] ]                        = (int) $row['term_taxonomy_id'];
				$writes[ 'ttid_by_termidtaxonomy_' . $row['term_id'] . $row['taxonomy'] ] = (int) $row['term_taxonomy_id'];
				$writes[ 'termid_by_ttid_' . $row['term_taxonomy_id'] ]               = (int) $row['term_id'];
			}
		}

		if ( ! $writes ) {
			$writes[ $key ] = 1 === count( $rows ) ? (int) $rows[0][ $column ] : $fallback;
		}

		return $writes;
	}

	private function read_missing_terms( array $ids, $by = 'term_taxonomy_id' ) {
		$wpdb = $this->wpdb;
		$by   = 'term_id' === $by ? 'term_id' : 'term_taxonomy_id';

		foreach ( $this->prefetch_batches( $ids ) as $batch ) {
			if ( 'term_id' === $by ) {
				$rows = $wpdb->get_results(
					$wpdb->prepare(
						"SELECT DISTINCT tax.term_taxonomy_id, tax.term_id, tax.taxonomy, wpml_translations.translation_id
					FROM {$wpdb->term_taxonomy} sel
					JOIN {$wpdb->term_taxonomy} tax ON tax.term_id = sel.term_id
					LEFT JOIN {$wpdb->prefix}icl_translations wpml_translations
						ON wpml_translations.element_id = tax.term_taxonomy_id
							AND wpml_translations.element_type = CONCAT('tax_', tax.taxonomy)
					WHERE sel.term_id IN (" . implode( ',', array_fill( 0, count( $batch ), '%d' ) ) . ')',
						$batch
					),
					ARRAY_A
				);
			} else {
				$rows = $wpdb->get_results(
					$wpdb->prepare(
						"SELECT DISTINCT tax.term_taxonomy_id, tax.term_id, tax.taxonomy, wpml_translations.translation_id
					FROM {$wpdb->term_taxonomy} sel
					JOIN {$wpdb->term_taxonomy} tax ON tax.term_id = sel.term_id
					LEFT JOIN {$wpdb->prefix}icl_translations wpml_translations
						ON wpml_translations.element_id = tax.term_taxonomy_id
							AND wpml_translations.element_type = CONCAT('tax_', tax.taxonomy)
					WHERE sel.term_taxonomy_id IN (" . implode( ',', array_fill( 0, count( $batch ), '%d' ) ) . ')',
						$batch
					),
					ARRAY_A
				);
			}

			if ( ! is_array( $rows ) || $this->last_query_failed() ) {
				continue;
			}

			$by_term = array();
			foreach ( $rows as $row ) {
				$by_term[ (int) $row['term_id'] ][ (int) $row['term_taxonomy_id'] ] = $row;
			}

			$found = array();
			foreach ( $by_term as $term_id => $term_rows ) {
				$this->write_missing_term( 'ttid_by_termid_' . $term_id, $term_id, 'term_taxonomy_id', $term_rows );

				$by_taxonomy = array();
				foreach ( $term_rows as $ttid => $row ) {
					$found[ $ttid ]                         = true;
					$by_taxonomy[ $row['taxonomy'] ][ $ttid ] = $row;
					$this->write_missing_term( 'termid_by_ttid_' . $ttid, $ttid, 'term_id', array( $row ) );
				}
				foreach ( $by_taxonomy as $taxonomy => $taxonomy_rows ) {
					$this->write_missing_term( 'ttid_by_termidtaxonomy_' . $term_id . $taxonomy, $term_id, 'term_taxonomy_id', $taxonomy_rows );
				}
			}

			foreach ( $batch as $id ) {
				$id = (int) $id;
				if ( 'term_id' === $by ) {
					if ( ! isset( $by_term[ $id ] ) ) {
						$this->write_missing_term( 'ttid_by_termid_' . $id, $id, 'term_taxonomy_id', array() );
					}
				} elseif ( ! isset( $found[ $id ] ) ) {
					$this->write_missing_term( 'termid_by_ttid_' . $id, $id, 'term_id', array() );
				}
			}
		}
	}

	private function write_missing_term( $key, $fallback, $column, array $rows ) {
		if ( $this->get_from_cache( $key )->isJust() ) {
			return;
		}
		$writes = $this->missing_term_writes( $key, $fallback, $column, $rows );
		if ( array_key_exists( $key, $writes ) ) {
			$this->set_in_cache( $key, $writes[ $key ] );
		}
	}

	public function prefetch_ids( $element_ids ) {
		parent::prefetch_ids( $element_ids );

		if ( 0 === $this->maybe_warm_term_id_cache() ) {
			return;
		}

		$missing = array();
		foreach ( (array) $element_ids as $ttid ) {
			$ttid = (int) $ttid;
			if ( $ttid > 0 && ! isset( $missing[ $ttid ] ) && ! $this->get_from_cache( 'termid_by_ttid_' . $ttid )->isJust() ) {
				$missing[ $ttid ] = $ttid;
			}
		}

		if ( $missing ) {
			$this->read_missing_terms( array_values( $missing ) );
		}
	}

	public function ttids_for_term_ids( array $term_ids ) {
		if ( 0 !== $this->maybe_warm_term_id_cache() ) {
			$missing = array();
			foreach ( $term_ids as $term_id ) {
				$term_id = (int) $term_id;
				if ( $term_id > 0 && ! isset( $missing[ $term_id ] ) && ! $this->get_from_cache( 'ttid_by_termid_' . $term_id )->isJust() ) {
					$missing[ $term_id ] = $term_id;
				}
			}
			if ( $missing ) {
				$this->read_missing_terms( array_values( $missing ), 'term_id' );
			}
		}

		$ttids = array();
		foreach ( $term_ids as $term_id ) {
			$ttids[] = (int) $this->adjust_ttid_for_term_id( (int) $term_id );
		}

		return $ttids;
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

			if ( $this->last_query_failed() ) {
				$count = $this->get_cache_max_warmup_count() + 1;
			}

			$this->set_in_cache( $key, $count );

			if ( $count > 0 && $this->is_cache_type_select_all_items_in_one_query( $count ) ) {
				$data = $this->get_maybe_warm_term_id_cache_data();
				$this->set_data_to_cache( $data );
			}

			return $count;
		};

		return $this->get_from_cache( $key )->getOrElse( $get_count );
	}

	private function last_query_failed() {
		return isset( $this->wpdb->last_error ) && '' !== (string) $this->wpdb->last_error;
	}

	private function get_maybe_warm_term_id_cache_data() {
		$sql = $this->get_query_sql();

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
