<?php

class WPML_Taxonomy_Translation_Screen_Data extends WPML_WPDB_And_SP_User {

	private $taxonomy;

	public function __construct( &$sitepress, $taxonomy ) {
		$wpdb = $sitepress->wpdb();
		parent::__construct( $wpdb, $sitepress );
		$this->taxonomy = $taxonomy;
	}

	public function terms( $id_term = null ) {
		$terms_data = array(
			'terms' => array(),
		);

		$term_rows = $this->wpdb->get_results( $this->build_terms_query( $id_term ) );

		if ( ! is_array( $term_rows ) ) {
			return $terms_data;
		}

		$terms_with_meta = function_exists( 'get_term_meta' )
			? $this->add_metadata( $term_rows )
			: $term_rows;

		if ( $terms_with_meta ) {
			$terms_data['terms'] = $this->order_terms_list( $this->index_terms_array( $terms_with_meta ) );
		}

		return $terms_data;
	}

	private function build_terms_query( $id_term ) {
		$attributes_to_select        = array();
		$icl_translations_table_name = $this->wpdb->prefix . 'icl_translations';

		$attributes_to_select[ $this->wpdb->terms ]           = array(
			'alias' => 't',
			'vars'  => array( 'name', 'slug', 'term_id' ),
		);
		$attributes_to_select[ $this->wpdb->term_taxonomy ]   = array(
			'alias' => 'tt',
			'vars'  => array(
				'term_taxonomy_id',
				'parent',
				'description',
			),
		);
		$attributes_to_select[ $icl_translations_table_name ] = array(
			'alias' => 'i',
			'vars'  => array(
				'language_code',
				'trid',
				'source_language_code',
			),
		);

		$join_statements   = array();
		$as                = $this->alias_statements( $attributes_to_select );
		$join_statements[] = "{$as['t']} JOIN {$as['tt']} ON tt.term_id = t.term_id";
		$join_statements[] = "{$as['i']} ON i.element_id = tt.term_taxonomy_id";
		$from_clause       = $this->build_from_clause( join( ' JOIN ', $join_statements ), $attributes_to_select, $id_term );
		$select_clause     = $this->build_select_vars( $attributes_to_select );
		$where_clause      = $this->build_where_clause( $attributes_to_select );

		return "SELECT {$select_clause} FROM {$from_clause} WHERE {$where_clause}";
	}

	private function index_terms_array( $terms ) {
		$terms_indexed = array();

		foreach ( $terms as $term ) {
			$terms_indexed[ $term->term_id ] = $term;
		}

		return $terms_indexed;
	}

	private function set_language_information(
		$trid_group,
		$terms
	) {

		foreach ( $trid_group['elements'] as $lang => $term ) {

			$term_object         = $terms[ $term['term_id'] ];
			$term_object->level  = $term['level'];
			$trid_group[ $lang ] = $term_object;
		}

		unset( $trid_group['elements'] );

		return $trid_group;
	}

	private function order_terms_list( $terms ) {
		$terms_tree    = new WPML_Translation_Tree(
			$this->sitepress,
			$this->taxonomy,
			$terms
		);
		$ordered_terms = $terms_tree->get_alphabetically_ordered_list();
		foreach ( $ordered_terms as $key => $trid_group ) {
			$ordered_terms[ $key ] = self::set_language_information(
				$trid_group,
				$terms
			);
		}

		return $ordered_terms;
	}

	private function build_select_vars( $selects ) {
		$output = '';

		if ( is_array( $selects ) ) {
			$coarse_selects = array();

			foreach ( $selects as $select ) {

				$vars  = $select['vars'];
				$table = $select['alias'];

				foreach ( $vars as $key => $var ) {
					$vars[ $key ] = $table . '.' . $var;
				}
				$coarse_selects[] = join( ', ', $vars );
			}

			$output = join( ', ', $coarse_selects );
		}

		return $output;
	}

	private function alias_statements( $selects ) {
		$output = array();
		foreach ( $selects as $key => $select ) {
			$output[ $select['alias'] ] = $key . ' AS ' . $select['alias'];
		}

		return $output;
	}

	private function build_where_clause( $selects ) {

		$where_clauses[] = $selects[ $this->wpdb->term_taxonomy ]['alias']
						. $this->wpdb->prepare(
							'.taxonomy = %s ',
							$this->taxonomy
						);
		$where_clauses[] = $selects[ $this->wpdb->prefix . 'icl_translations' ]['alias']
						. $this->wpdb->prepare(
							'.element_type = %s ',
							'tax_' . $this->taxonomy
						);

		$where_clause = join( ' AND  ', $where_clauses );

		return $where_clause;
	}

	private function build_from_clause( $from, $selects, $id_term ) {
		$id_term_clause = $id_term
			? (string) $this->wpdb->prepare( 'element_id = %d AND ', $id_term )
			: '';

		return $from . sprintf(
			' INNER JOIN (
					SELECT trid FROM %s WHERE %s%s AND source_language_code IS NULL
				  ) lm on lm.trid = %s.trid',
			$this->wpdb->prefix . 'icl_translations',
			$id_term_clause,
			(string) $this->wpdb->prepare( 'element_type = %s', 'tax_' . $this->taxonomy ),
			$selects[ $this->wpdb->prefix . 'icl_translations' ]['alias']
		);
	}

	private function add_metadata( $term_rows ) {

		$setting_factory = $this->sitepress->core_tm()->settings_factory();

		update_termmeta_cache( wp_list_pluck( $term_rows, 'term_id' ) );

		foreach ( $term_rows as $term ) {
			$term_meta = get_term_meta( $term->term_id );
			foreach ( $term_meta as $meta_key => $meta_values ) {
				if ( in_array( $setting_factory->term_meta_setting( $meta_key )->status(), array( WPML_TRANSLATE_CUSTOM_FIELD, WPML_COPY_ONCE_CUSTOM_FIELD ), true ) ) {
					$term->meta_data[ $meta_key ] = $meta_values;
				}
			}
		}

		return $term_rows;
	}
}
