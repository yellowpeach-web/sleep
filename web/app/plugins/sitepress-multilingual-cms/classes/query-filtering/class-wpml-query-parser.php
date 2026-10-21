<?php

use WPML\FP\Obj;
use WPML\QueryFiltering\NameFilterTypeResolver;

class WPML_Query_Parser {

	const LANG_VAR = 'wpml_lang';

	protected $post_translations;
	protected $term_translations;
	protected $sitepress;
	public $wpdb;

	private $query_filter;

	private NameFilterTypeResolver $name_filter_type_resolver;

	private $opt_out;

	public function __construct( $sitepress, $query_filter, ?WPML_Term_Query_Opt_Out $opt_out = null ) {
		$this->sitepress         = $sitepress;
		$this->wpdb              = $sitepress->wpdb();
		$this->post_translations = $sitepress->post_translations();
		$this->term_translations = $sitepress->term_translations();
		$this->query_filter      = $query_filter;
		$this->opt_out           = $opt_out ? $opt_out : new WPML_Term_Query_Opt_Out( $sitepress );

		$this->name_filter_type_resolver = new NameFilterTypeResolver();
	}

	private function adjust_default_taxonomies_query_vars( $q, $lang ) {
		$vars = array(
			'cat'              => array(
				'type' => 'ids',
				'tax'  => 'category',
			),
			'category_name'    => array(
				'type' => 'slugs',
				'tax'  => 'category',
			),
			'category__and'    => array(
				'type' => 'ids',
				'tax'  => 'category',
			),
			'category__in'     => array(
				'type' => 'ids',
				'tax'  => 'category',
			),
			'category__not_in' => array(
				'type' => 'ids',
				'tax'  => 'category',
			),
			'tag'              => array(
				'type' => 'slugs',
				'tax'  => 'post_tag',
			),
			'tag_id'           => array(
				'type' => 'ids',
				'tax'  => 'post_tag',
			),
			'tag__and'         => array(
				'type' => 'ids',
				'tax'  => 'post_tag',
			),
			'tag__in'          => array(
				'type' => 'ids',
				'tax'  => 'post_tag',
			),
			'tag__not_in'      => array(
				'type' => 'ids',
				'tax'  => 'post_tag',
			),
			'tag_slug__and'    => array(
				'type' => 'slugs',
				'tax'  => 'post_tag',
			),
			'tag_slug__in'     => array(
				'type' => 'slugs',
				'tax'  => 'post_tag',
			),
		);

		foreach ( $vars as $key => $args ) {

			if ( isset( $q->query_vars[ $key ] )
				 && ! ( empty( $q->query_vars[ $key ] ) || $q->query_vars[ $key ] === 0 )
			) {
				list( $values, $glue ) = $this->parse_scalar_values_in_query_vars( $q, $key, $args['type'] );
				$translated_values     = $this->translate_term_values( $values, $args['type'], $args['tax'], $lang );
				$q                     = $this->replace_query_vars_value( $q, $key, $translated_values, $glue );
			}
		}

		return $q;
	}

	private function parse_scalar_values_in_query_vars( $q, $key, $type ) {
		$glue   = false;
		$values = array();

		if ( is_scalar( $q->query_vars[ $key ] ) ) {
			if ( is_string( $q->query_vars[ $key ] ) ) {
				$glue = strpos( $q->query_vars[ $key ], ',' ) !== false ? ',' : $glue;
				$glue = strpos( $q->query_vars[ $key ], '+' ) !== false ? '+' : $glue;

				if ( $glue ) {
					$values = explode( $glue, $q->query_vars[ $key ] );
				}
			}

			if ( ! $glue ) {
				$values = array( $q->query_vars[ $key ] );
			}

			$values = array_map( 'trim', $values );
			$values = $type === 'ids' ? array_map( 'intval', $values ) : $values;
		} elseif ( is_array( $q->query_vars[ $key ] ) ) {
			$values = $q->query_vars[ $key ];
		}

		return array( $values, $glue );
	}

	private function translate_term_values( $values, $type, $taxonomy, $lang ) {
		$translated_values = array();

		if ( $type === 'ids' ) {
			$this->prefetch_term_translations( $values );

			foreach ( $values as $id ) {
				$sign                = (int) $id < 0 ? - 1 : 1;
				$id                  = abs( $id );
				$translated_values[] = $sign * (int) $this->term_translations->term_id_in( $id, $lang, true );
			}
		} elseif ( $type === 'slugs' ) {

			foreach ( $values as $slug ) {
				$slug_elements = explode( '/', $slug );

				foreach ( $slug_elements as &$slug_element ) {
					$slug_element = $this->translate_term_slug( $slug_element, $taxonomy, $lang );
				}

				$slug = implode( '/', $slug_elements );

				$translated_values[] = $slug;
			}
		}

		return $translated_values;
	}

	private function prefetch_term_translations( $values ) {
		$ttids = array();
		foreach ( $values as $id ) {
			$ttid = (int) $this->term_translations->adjust_ttid_for_term_id( abs( (int) $id ) );
			if ( $ttid > 0 ) {
				$ttids[] = $ttid;
			}
		}

		if ( count( $ttids ) > 1 ) {
			$this->term_translations->prefetch_ids( $ttids );
		}
	}

	private function translate_term_slug( $slug, $taxonomy, $lang ) {
		$wpdb = $this->wpdb;

		$ids = $this->term_translations->term_ids_by_slug( $slug, $taxonomy );

		foreach ( $ids as $candidate ) {
			if ( $this->term_translations->lang_code_by_termid( $candidate ) === $lang ) {
				return $slug;
			}
		}

		$id      = $ids ? (int) $ids[0] : 0;
		$term_id = (int) $this->term_translations->term_id_in( $id, $lang, true );

		if ( $term_id !== $id ) {

			$slug = $wpdb->get_var(
				$wpdb->prepare(
					"SELECT slug FROM {$wpdb->terms}
								 WHERE term_id = %d LIMIT 1",
					$term_id
				)
			);
		}

		return $slug;
	}

	private function replace_query_vars_value( $q, $key, $translated_values, $glue ) {
		if ( ! empty( $translated_values ) && ! empty( $translated_values[0] ) ) {

			$translated_values = array_unique( $translated_values );

			if ( is_scalar( $q->query_vars[ $key ] ) ) {
				$q->query_vars[ $key ] = implode( $glue, $translated_values );
			} elseif ( is_array( $q->query_vars[ $key ] ) ) {
				$q->query_vars[ $key ] = $translated_values;
			}
		}

		return $q;
	}

	private function adjust_taxonomy_query( $q ) {
		if ( isset( $q->query_vars['tax_query'], $q->tax_query->queries, $q->query['tax_query'] ) &&
			 is_array( $q->query_vars['tax_query'] ) &&
			 is_array( $q->tax_query->queries ) &&
			 is_array( $q->query['tax_query'] ) &&
			 ! $this->opt_out->is_stand_down_requested( $q )
		) {

			$new_conditions             = $this->adjust_tax_query_conditions( $q->query['tax_query'] );
			$q->query['tax_query']      = $new_conditions;
			$q->tax_query->queries      = $new_conditions;
			$q->query_vars['tax_query'] = $new_conditions;
		}

		return $q;
	}

	private function adjust_tax_query_conditions( $conditions ) {

		foreach ( $conditions as $key => $condition ) {

			if ( ! is_array( $condition ) ) {
				continue;
			} elseif ( ! isset( $condition['terms'] ) ) {
				$conditions[ $key ] = $this->adjust_tax_query_conditions( $condition );
			} elseif ( is_array( $condition['terms'] ) ) {

				foreach ( $condition['terms'] as $value ) {

					$field = isset( $condition['field'] ) ? $condition['field'] : 'term_id';
					$term  = $this->sitepress->get_wp_api()->get_term_by( $field, $value, $condition['taxonomy'] );

					if ( is_object( $term ) ) {

						if ( $field === 'id' && ! isset( $term->id ) ) {
							$translated_value = isset( $term->term_id ) ? $term->term_id : null;
						} else {
							$translated_value = isset( $term->{$field} ) ? $term->{$field} : null;
						}

						$index                        = array_search( $value, $condition['terms'] );
						$condition['terms'][ $index ] = $translated_value;
					}
				}

				$conditions[ $key ] = $condition;

			} elseif ( is_scalar( $condition['terms'] ) ) {
				$field = isset( $condition['field'] ) ? $condition['field'] : 'id';
				$term  = $this->sitepress->get_wp_api()->get_term_by( $field, $condition['terms'], $condition['taxonomy'] );

				if ( is_object( $term ) ) {
					$field                       = $field == 'id' ? 'term_id' : $field;
					$conditions[ $key ]['terms'] = isset( $term->{$field} ) ? $term->{$field} : null;
				}
			}
		}

		return $conditions;
	}

	private function maybe_redirect_to_translated_taxonomy( $q, $current_lang ) {
		if ( ! $q->is_main_query() ) {
			return $q;
		}

		foreach ( $this->get_query_taxonomy_term_slugs( $q ) as $slug => $taxonomy ) {
			$translated_slugs = $this->translate_term_values( array( $slug ), 'slugs', $taxonomy, $current_lang );

			if ( $translated_slugs && (string) $slug !== $translated_slugs[0] ) {
				$translated_term = get_term_by(
					'slug',
					$translated_slugs[0],
					$taxonomy
				);

				$new_url = $translated_term
					? get_term_link( $translated_term, $taxonomy )
					: null;

				if ( $new_url && ! is_wp_error( $new_url ) ) {
					global $wpml_wp_api;
					$wpml_wp_api->wp_safe_redirect( $new_url, 301 );

					return null;
				}
			}
		}

		return $q;
	}

	private function get_page_at_path_in_language( $path, $post_type, $language ) {
		$slugs = array_values( array_filter( explode( '/', trim( (string) $path, '/' ) ), 'strlen' ) );

		if ( ! $slugs ) {
			return null;
		}

		foreach ( $this->get_path_lookup_scopes( $language ) as $scope ) {
			$candidates = $this->get_slug_path_candidates( $slugs, $post_type, $scope );

			if ( ! $candidates ) {
				continue;
			}

			$scorer = new WPML_Score_Hierarchy( $candidates, $slugs );
			$scored = $scorer->get_possible_ids_ordered();

			if ( ! empty( $scored['matching_ids'] ) ) {
				$page = get_post( (int) $scored['matching_ids'][0] );

				if ( $page ) {
					return $page;
				}
			}
		}

		return null;
	}

	private function get_path_lookup_scopes( $language ) {
		$scopes  = array( $language );
		$default = $this->sitepress->get_default_language();

		if ( $default && $default !== $language ) {
			$scopes[] = $default;
		}

		return $scopes;
	}

	private function get_slug_path_candidates( array $slugs, $post_type, $language ) {
		$element_type = 'post_' . $post_type;
		$in_slugs     = implode( ',', array_fill( 0, count( $slugs ), '%s' ) );

		$sql = $this->wpdb->prepare(
			"SELECT p.ID, p.post_name, p.post_parent, par.post_name AS parent_name
				FROM {$this->wpdb->posts} p
				JOIN {$this->wpdb->prefix}icl_translations t
					ON t.element_id = p.ID AND t.element_type = %s AND t.language_code = %s
				LEFT JOIN {$this->wpdb->posts} par ON par.ID = p.post_parent
				WHERE p.post_type = %s AND p.post_name IN ( " . $in_slugs . ' )',
			array_merge( array( $element_type, $language, $post_type ), $slugs )
		);

		return (array) $this->wpdb->get_results( $sql );
	}

	private function get_query_taxonomy_term_slugs( WP_Query $q ) {
		$result = array();

		if ( isset( $q->tax_query->queries ) && count( $q->tax_query->queries ) ) {
			foreach ( $q->tax_query->queries as $taxonomy_data ) {
				if ( isset( $taxonomy_data['terms'] ) && isset( $taxonomy_data['field'] ) && $taxonomy_data['field'] === 'slug' ) {
					foreach ( $taxonomy_data['terms'] as $slug ) {
						$result[ $slug ] = $taxonomy_data['taxonomy'];
					}
				}
			}
		}

		return $result;
	}

	function parse_query( $q ) {
		$wpdb = $this->wpdb;

		if ( $this->sitepress->get_wp_api()->is_admin()
			 && ! $this->sitepress->get_wp_api()->constant( 'DOING_AJAX' )
		) {
			return $q;
		}

		$q = apply_filters( 'wpml_pre_parse_query', $q );

		list( $q, $redir_pid ) = $this->maybe_adjust_name_var( $q );

		if ( $q->is_main_query() && $redir_pid ) {
			if ( (bool) ( $redir_target = $this->is_redirected( $redir_pid, $q ) ) ) {
				$this->sitepress->get_wp_api()->wp_safe_redirect( $redir_target, 301 );
			}
		}

		$post_type = 'post';
		if ( ! empty( $q->query_vars['post_type'] ) ) {
			$post_type = $q->query_vars['post_type'];
		}

		$current_language = Obj::path( [ 'query_vars', self::LANG_VAR ], $q ) ?: $this->sitepress->get_current_language();
		$q                = $this->maybe_redirect_to_translated_taxonomy( $q, $current_language );
		if ( ! $q ) {
			return $q;
		}
		if ( 'attachment' === $post_type || $current_language !== $this->sitepress->get_default_language() ) {
			if ( ! $this->opt_out->is_stand_down_requested( $q ) ) {
				$q = $this->adjust_default_taxonomies_query_vars( $q, $current_language );
			}

			if ( ! is_array( $post_type ) ) {
				$post_type = (array) $post_type;
			}
			if ( ! empty( $q->query_vars['page_id'] ) ) {
				$q->query_vars['page_id'] = $this->get_translated_post( $q->query_vars['page_id'], $current_language );
			}
			$q = $this->adjust_query_ids( $q, 'include' );
			$q = $this->adjust_query_ids( $q, 'exclude' );
			if ( isset( $q->query_vars['p'] ) && ! empty( $q->query_vars['p'] ) ) {
				$q->query_vars['p'] = $this->get_translated_post( $q->query_vars['p'], $current_language );
			}

			if ( $post_type ) {
				$first_post_type = reset( $post_type );

				if ( $this->sitepress->is_translated_post_type( $first_post_type ) && ! empty( $q->query_vars['name'] ) ) {
					if ( is_post_type_hierarchical( $first_post_type ) ) {
						$requested_page = $this->get_page_at_path_in_language(
							$q->query_vars['name'],
							$first_post_type,
							$current_language
						);

						if ( ! $requested_page ) {
							$requested_page = get_page_by_path( $q->query_vars['name'], OBJECT, $first_post_type );
						}

						if ( $requested_page && 'attachment' !== $requested_page->post_type ) {
							$q->query_vars['p'] = $this->post_translations->element_id_in( $requested_page->ID, $current_language, true );
							unset( $q->query_vars['name'] );
							$q->query_vars[ $first_post_type ] = '';
						}
					} else {
						$pid = $wpdb->get_var(
							$wpdb->prepare(
								"
							SELECT ID FROM {$wpdb->posts} p
							JOIN {$wpdb->prefix}icl_translations t
								ON t.element_id = p.ID AND t.element_type=CONCAT('post_', %s)
							WHERE post_name=%s AND post_type=%s AND t.language_code=%s
							LIMIT 1
							",
								$first_post_type,
								$q->query_vars['name'],
								$first_post_type,
								$current_language
							)
						);
						if ( ! empty( $pid ) ) {
							$q->query_vars['p'] = $this->post_translations->element_id_in( $pid, $current_language, true );
							unset( $q->query_vars['name'] );
						}
					}
				}
				$q = $this->adjust_q_var_pids( $q, $post_type, 'post__in' );
				$q = $this->adjust_q_var_pids( $q, $post_type, 'post__not_in' );
				$q = $this->maybe_adjust_parent( $q, $post_type, $current_language );
			}
			$q->query_vars['meta_query'] = isset( $q->query_vars['meta_query'] ) ? $q->query_vars['meta_query'] : array();

			$q = $this->adjust_taxonomy_query( $q );
		}

		$q = apply_filters( 'wpml_post_parse_query', $q );

		return $q;
	}

	private function maybe_adjust_parent( $q, $post_type, $current_language ) {
		$post_type = ! is_scalar( $post_type ) && count( $post_type ) === 1 ? end( $post_type ) : $post_type;

		$has_parent_filter = ! empty( $q->query_vars['post_parent'] )
							 || ! empty( $q->query_vars['post_parent__in'] )
							 || ! empty( $q->query_vars['post_parent__not_in'] );

		$queried_post_type = isset( $q->query_vars['post_type'] ) ? $q->query_vars['post_type'] : '';

		if ( ! $has_parent_filter
			 || 'attachment' === $queried_post_type
			 || ! $post_type
			 || ! is_scalar( $post_type )
			 || ! $this->sitepress->is_translated_post_type( $post_type )
		) {
			return $q;
		}

		if ( ! empty( $q->query_vars['post_parent'] ) ) {
			$q->query_vars['post_parent'] = $this->post_translations->element_id_in(
				$q->query_vars['post_parent'],
				$current_language,
				true
			);
		}

		foreach ( array( 'post_parent__in', 'post_parent__not_in' ) as $parent_list ) {
			if ( empty( $q->query_vars[ $parent_list ] ) || ! is_array( $q->query_vars[ $parent_list ] ) ) {
				continue;
			}

			$translated = array();
			foreach ( $q->query_vars[ $parent_list ] as $parent_id ) {
				$translated[] = $this->post_translations->element_id_in(
					$parent_id,
					$current_language,
					true
				);
			}

			$q->query_vars[ $parent_list ] = $translated;
		}

		return $q;
	}

	private function maybe_adjust_name_var( $q ) {
		$redirect = false;
		if ( ( (bool) ( $name_in_q = $q->get( 'name' ) ) === true
			 || (bool) ( $name_in_q = $q->get( 'pagename' ) ) === true )
			 && (bool) $q->get( 'page_id' ) === false
			|| ( (bool) ( $post_type = $q->get( 'post_type' ) ) === true
				&& is_scalar( $post_type )
				&& (bool) ( $name_in_q = $q->get( $post_type ) ) === true )
		) {
			list( $name_found, $type, $altered ) = $this->query_filter->get_404_util()->guess_cpt_by_name(
				$name_in_q,
				$q
			);
			$type = $this->name_filter_type_resolver->resolve( $type, $q, $name_in_q );
			if ( $altered === true ) {
				$name_before = $q->get( 'name' );
				$q->set( 'name', $name_found );
			}
			list( $q, $redirect ) = $type
				? $this->query_filter->get_page_name_filter( $type )->filter_page_name( $q ) : array( $q, false );
			if ( isset( $name_before ) ) {
				$q->set( 'name', $name_before );
			}
		}

		return array( $q, $redirect );
	}


	private function adjust_query_ids( $q, $index ) {
		if ( ! empty( $q->query_vars[ $index ] ) ) {
			$untranslated = is_array( $q->query_vars[ $index ] ) ? $q->query_vars[ $index ] : explode(
				',',
				$q->query_vars[ $index ]
			);
			$this->post_translations->prefetch_ids( $untranslated );
			$ulanguage_code = $this->sitepress->get_current_language();
			$translated     = array();
			foreach ( $untranslated as $element_id ) {
				$translated[] = $this->post_translations->element_id_in( $element_id, $ulanguage_code );
			}
			$q->query_vars[ $index ] = is_array( $q->query_vars[ $index ] ) ? $translated : implode( ',', $translated );
		}

		return $q;
	}

	private function adjust_q_var_pids( $q, $post_types, $index ) {
		if ( ! empty( $q->query_vars[ $index ] ) && (bool) $post_types !== false ) {

			$untranslated = $q->query_vars[ $index ];
			$this->post_translations->prefetch_ids( $untranslated );
			$current_lang = $this->sitepress->get_current_language();
			$pid          = array();
			foreach ( $q->query_vars[ $index ] as $p ) {
				$pid[] = $this->post_translations->element_id_in( $p, $current_lang, true );
			}
			$q->query_vars[ $index ] = $pid;
		}

		return $q;
	}

	private function is_redirected( $post_id, $q ) {
		$request_uri = explode( '?', $_SERVER['REQUEST_URI'] );
		$redirect    = false;
		$permalink   = $this->sitepress->get_wp_api()->get_permalink( $post_id );
		if ( ! $this->is_permalink_part_of_request( $permalink, $request_uri[0] ) ) {
			if ( isset( $request_uri[1] ) ) {
				$args = array();
				parse_str( $request_uri[1], $args );
				if ( array_key_exists( 'lang', $args ) ) {
					$permalink = add_query_arg( array( 'lang' => $args['lang'] ), $permalink );
				}
			}
			$redirect = $permalink;
		}

		return apply_filters( 'wpml_is_redirected', $redirect, $post_id, $q );
	}

	public static function is_permalink_part_of_request( $permalink, $request_uri ) {
		$permalink_path = trailingslashit( urldecode( wpml_parse_url( $permalink, PHP_URL_PATH ) ) );
		$request_uri    = trailingslashit( urldecode( $request_uri ) );
		return 0 === strcasecmp( substr( $request_uri, 0, strlen( $permalink_path ) ), $permalink_path );
	}

	private function get_translated_post( $element_id, $current_language ) {
		$translated_id = $this->post_translations->element_id_in( $element_id, $current_language, true );
		$type          = $this->post_translations->get_type( $element_id );
		if ( $this->sitepress->is_display_as_translated_post_type( $type ) ) {
			$post = get_post( $translated_id );
			if ( 'publish' != $post->post_status ) {
				$translated_id = $element_id;
			}
		}
		return $translated_id;
	}
}
