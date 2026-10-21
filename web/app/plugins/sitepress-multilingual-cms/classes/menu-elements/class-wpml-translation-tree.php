<?php


class WPML_Translation_Tree extends WPML_SP_User {

	private $taxonomy;
	private $tree = array();
	private $language_order;

	private $trid_groups = array();

	private $trids_by_parent = array();

	private $placed_trids = array();

	private $trids_with_a_repeated_language = array();

	private $term_parents = array();

	private $terms_by_id = array();

	private $adjusted_language;

	private $adjust_id_url_filter_off;

	private $is_term_adjust_id_filtered;

	public function __construct( &$sitepress, $element_type, $terms ) {
		if ( ! $terms ) {
			throw new InvalidArgumentException( 'No terms to order given given' );
		}
		parent::__construct( $sitepress );
		$this->taxonomy       = $element_type;
		$this->language_order = $this->get_language_order( $terms );
		$this->tree           = $this->get_tree_from_terms_array( $terms );
	}

	public function get_alphabetically_ordered_list() {
		global $icl_adjust_id_url_filter_off;

		$this->adjusted_language          = $this->get_adjusted_language();
		$this->adjust_id_url_filter_off   = $icl_adjust_id_url_filter_off;
		$this->is_term_adjust_id_filtered = (bool) has_filter( 'wpml_disable_term_adjust_id' );

		$root_list              = $this->sort_trids_alphabetically( $this->tree );
		$ordered_list_flattened = array();
		foreach ( $root_list as $root_trid_group ) {
			$this->add_children_recursively( $root_trid_group, $ordered_list_flattened );
		}

		return $ordered_list_flattened;
	}

	private function get_tree_from_terms_array( $terms ) {
		$this->trid_groups = $this->generate_trid_groups( $terms );
		$this->index_trid_groups();

		$tree = array();
		foreach ( $this->trid_groups as $trid => $trid_group ) {
			if ( $this->is_root( $trid_group ) ) {
				$this->placed_trids[ $trid ] = true;
				$tree[ $trid ]               = $this->get_branch( $trid, $trid_group );
			}
		}

		return $tree;
	}

	private function generate_trid_groups( $terms ) {
		$trids = array();
		foreach ( $terms as $term ) {
			if ( isset( $trids[ $term->trid ][ $term->language_code ] ) ) {
				$this->trids_with_a_repeated_language[ $term->trid ] = true;
			}
			$trids [ $term->trid ] [ $term->language_code ] = array(
				'ttid'    => $term->term_taxonomy_id,
				'parent'  => $term->parent,
				'term_id' => $term->term_id,
			);
			if ( isset( $term->name ) ) {
				$trids [ $term->trid ] [ $term->language_code ]['name'] = $this->get_unique_term_name( $term->name );
			}
			$this->terms_by_id[ $term->term_id ] = $term;
		}

		return $trids;
	}

	private function index_trid_groups() {
		$position = 0;
		foreach ( $this->trid_groups as $trid => $trid_group ) {
			foreach ( $trid_group as $lang => $term ) {
				$parent = (int) $term['parent'];
				if ( $parent ) {
					$this->trids_by_parent[ $lang ][ $parent ][ $position ] = $trid;
				}
				if ( ! isset( $this->trids_with_a_repeated_language[ $trid ] ) ) {
					$this->term_parents[ $term['term_id'] ] = array( $trid, $term['parent'] );
				}
			}
			$position++;
		}
	}

	private function get_unique_term_name( $name ) {
		return uniqid( $name . '-' );
	}

	private function get_branch( $trid, $trid_group ) {
		return array(
			'trid'     => (string) $trid,
			'elements' => $trid_group,
			'children' => $this->get_children( $trid_group ),
		);
	}

	private function get_children( $parent_trid_group ) {
		$children = array();
		foreach ( $this->get_child_trids( $parent_trid_group ) as $trid ) {
			if ( ! isset( $this->placed_trids[ $trid ] ) ) {
				$this->placed_trids[ $trid ] = true;
				$children[ $trid ]           = $this->get_branch( $trid, $this->trid_groups[ $trid ] );
			}
		}
		foreach ( array_keys( $children ) as $trid ) {
			unset( $this->placed_trids[ $trid ] );
		}

		return $children ? $children : false;
	}

	private function get_child_trids( $parent_trid_group ) {
		$child_trids = array();
		$lists       = 0;
		foreach ( $parent_trid_group as $lang => $parent_term ) {
			if ( isset( $this->trids_by_parent[ $lang ][ (int) $parent_term['term_id'] ] ) ) {
				$child_trids += $this->trids_by_parent[ $lang ][ (int) $parent_term['term_id'] ];
				$lists++;
			}
		}
		if ( $lists > 1 ) {
			ksort( $child_trids );
		}

		return $child_trids;
	}

	private function is_root( $trid_group ) {
		foreach ( $this->language_order as $lang ) {
			if ( isset( $trid_group[ $lang ] ) ) {
				return 0 === (int) $trid_group[ $lang ]['parent'];
			}
		}

		return true;
	}

	private function sort_trids_alphabetically( $trid_groups ) {
		$terms_in_trids = array();
		$ordered_trids  = array();
		foreach ( $trid_groups as $trid_group ) {
			foreach ( $trid_group['elements'] as $lang => $term ) {
				if ( ! isset( $terms_in_trids[ $lang ] ) ) {
					$terms_in_trids[ $lang ] = array();
				}
				$trid                               = $trid_group['trid'];
				$term['trid']                       = $trid;
				$terms_in_trids[ $lang ][ $trid ][] = $term;
			}
		}

		$sorted = array();
		foreach ( $this->language_order as $lang ) {
			if ( isset( $terms_in_trids[ $lang ] ) ) {
				$terms_in_lang_and_trids = $terms_in_trids[ $lang ];
				$term_names              = array();
				$term_names_numerical    = array();
				foreach ( $terms_in_lang_and_trids as $trid => $terms_in_lang_and_trid ) {
					if ( isset( $sorted[ $trid ] ) ) {
						continue;
					}
					$term_in_lang_and_trid     = array_pop( $terms_in_lang_and_trid );
					$term_name                 = $term_in_lang_and_trid['name'];
					$term_names [ $term_name ] = $trid;
					$term_names_numerical []   = $term_name;
				}
				natsort( $term_names_numerical );
				foreach ( $term_names_numerical as $name ) {
					$ordered_trids []               = $trid_groups[ $term_names[ $name ] ];
					$sorted[ $term_names[ $name ] ] = true;
				}
			}
		}

		return $ordered_trids;
	}

	private function add_children_recursively( $trid_group, array &$list ) {
		$children = $trid_group['children'];
		unset( $trid_group['children'] );
		$list [] = $this->add_level_information_to_terms( $trid_group );
		if ( is_array( $children ) ) {
			foreach ( $this->sort_trids_alphabetically( $children ) as $child ) {
				$this->add_children_recursively( $child, $list );
			}
		}
	}

	private function add_level_information_to_terms( $tridgroup ) {
		foreach ( $tridgroup['elements'] as $lang => &$term ) {
			$term['level'] = $this->get_level( $term['term_id'] );
		}

		return $tridgroup;
	}

	private function get_level( $term_id ) {
		$level   = 0;
		$visited = array( $term_id => true );
		$term_id = $this->get_parent_id( $term_id );
		while ( $term_id ) {
			if ( isset( $visited[ $term_id ] ) ) {
				return 0;
			}
			$visited[ $term_id ] = true;
			$level ++;
			$term_id = $this->get_parent_id( $term_id );
		}

		return $level;
	}

	private function get_parent_id( $term_id ) {
		if ( ! isset( $this->term_parents[ $term_id ] ) ) {
			return wp_get_term_taxonomy_parent_id( (int) $term_id, $this->taxonomy );
		}

		list( $trid, $parent ) = $this->term_parents[ $term_id ];
		if ( $this->adjusted_language && isset( $this->trid_groups[ $trid ][ $this->adjusted_language ] ) ) {
			$translation = $this->trid_groups[ $trid ][ $this->adjusted_language ];
			if ( (int) $translation['term_id'] !== (int) $term_id && ! $this->is_adjustment_disabled( $term_id ) ) {
				$parent = $translation['parent'];
			}
		}

		return (int) $parent;
	}

	private function is_adjustment_disabled( $term_id ) {
		if ( ! $this->is_term_adjust_id_filtered ) {
			return (bool) $this->adjust_id_url_filter_off;
		}

		return (bool) apply_filters( 'wpml_disable_term_adjust_id', $this->adjust_id_url_filter_off, $this->get_wp_term( $term_id ) );
	}

	private function get_wp_term( $term_id ) {
		$row = $this->terms_by_id[ $term_id ];

		return new WP_Term(
			(object) array(
				'term_id'          => (int) $row->term_id,
				'name'             => isset( $row->name ) ? $row->name : '',
				'slug'             => isset( $row->slug ) ? $row->slug : '',
				'term_taxonomy_id' => (int) $row->term_taxonomy_id,
				'taxonomy'         => $this->taxonomy,
				'description'      => isset( $row->description ) ? $row->description : '',
				'parent'           => (int) $row->parent,
			)
		);
	}

	private function get_adjusted_language() {
		if (
			! has_filter( 'get_term', array( $this->sitepress, 'get_term_adjust_id' ) )
			|| ( new WPML_Term_Query_Opt_Out( $this->sitepress ) )->is_id_adjustment_opted_out()
		) {
			return null;
		}

		return $this->sitepress->get_current_language();
	}

	private function get_language_order( $terms ) {
		$langs        = array();
		$default_lang = $this->sitepress->get_default_language();
		foreach ( $terms as $term ) {
			$term_lang = $term->language_code;
			if ( $term_lang === $default_lang ) {
				continue;
			}
			if ( isset( $langs[ $term_lang ] ) ) {
				$langs[ $term_lang ] += 1;
			} else {
				$langs[ $term_lang ] = 1;
			}
		}
		natsort( $langs );
		$return    = array_keys( $langs );
		$return [] = $default_lang;
		$return    = array_reverse( $return );

		return $return;
	}
}
