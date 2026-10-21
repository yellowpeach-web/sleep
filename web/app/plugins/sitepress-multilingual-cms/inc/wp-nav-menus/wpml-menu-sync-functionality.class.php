<?php

use WPML\FP\Lst;

abstract class WPML_Menu_Sync_Functionality extends WPML_Full_Translation_API {

	const STRING_CONTEXT_SUFFIX    = ' menu';
	const STRING_NAME_LABEL_PREFIX = 'Menu Item Label ';
	const STRING_NAME_URL_PREFIX   = 'Menu Item URL ';

	private $menu_items_cache;

	private $orphan_map;

	private $language_conflicts_fixed = false;

	function __construct( &$sitepress, &$wpdb, &$post_translations, &$term_translations ) {
		parent::__construct( $sitepress, $wpdb, $post_translations, $term_translations );
		$this->menu_items_cache = array();
		$this->orphan_map       = new WPML_Menu_Item_Orphan_Map( $wpdb );
	}

	function get_menu_items( $menu_id, $translations = true ) {
		$key = $menu_id . '-';
		if ( $translations ) {
			$key .= 'trans';
		} else {
			$key .= 'no-trans';
		}

		if ( ! isset( $this->menu_items_cache[ $key ] ) ) {

			if ( ! isset( $this->menu_items_cache[ $menu_id ] ) ) {
				$this->menu_items_cache[ $menu_id ] = wp_get_nav_menu_items( (int) $menu_id );
			}
			$items      = $this->menu_items_cache[ $menu_id ];
			$menu_items = array();

			if ( $translations && is_array( $items ) ) {
				$this->preload_menu_item_translation_data( $items );
			}

			foreach ( $items as $item ) {
				$item->object_type = get_post_meta( $item->ID, '_menu_item_type', true );
				$_item_add         = array(
					'ID'          => $item->ID,
					'menu_order'  => $item->menu_order,
					'parent'      => $item->menu_item_parent,
					'object'      => $item->object,
					'url'         => $item->url,
					'object_type' => $item->object_type,
					'object_id'   => empty( $item->object_id ) ? get_post_meta(
						$item->ID,
						'_menu_item_object_id',
						true
					) : $item->object_id,
					'title'       => $item->title,
					'depth'       => $this->get_menu_item_depth( $item->ID, $menu_id ),
				);

				if ( $translations ) {
					$_item_add['translations'] = $this->get_menu_item_translations( $item, $menu_id );
				}
				$menu_items[ $item->ID ] = $_item_add;
			}

			$this->menu_items_cache[ $key ] = $menu_items;
		}

		return $this->menu_items_cache[ $key ];
	}

	function sync_menu_translations( $menu_trans_data, $menus ) {
		global $wpdb;

		foreach ( $menu_trans_data as $menu_id => $translations ) {
			foreach ( $translations as $language => $name ) {
				$existing_menu_id = $this->term_translations->term_id_in( $menu_id, $language );
				if ( $existing_menu_id ) {
					$menus[ $menu_id ]['translations'][ $language ] = array( 'id' => (int) $existing_menu_id );
					continue;
				}

				$original_menu_tt_id            = $wpdb->get_var(
					$wpdb->prepare(
						"	SELECT term_taxonomy_id
																					FROM {$wpdb->term_taxonomy}
																					WHERE term_id=%d
																						AND taxonomy='nav_menu'
																					LIMIT 1",
						$menu_id
					)
				);
				$_POST['icl_translation_of']    = $original_menu_tt_id;
				$_POST['icl_nav_menu_language'] = $language;

				$menu_indentation = '';
				$menu_increment   = 0;
				do {
					$new_menu_id      = wp_update_nav_menu_object(
						0,
						array(
							'menu-name' => $name . $menu_indentation
										   . ( $menu_increment
									? $menu_increment : '' ),
						)
					);
					$menu_increment   = $menu_increment != '' ? $menu_increment + 1 : 2;
					$menu_indentation = '-';
				} while ( is_wp_error( $new_menu_id ) && $menu_increment < 10 );

				if ( ! is_wp_error( $new_menu_id ) ) {
					$this->link_new_menu_translation( (int) $new_menu_id, (int) $original_menu_tt_id, $language );
				}

				$menus[ $menu_id ]['translations'][ $language ] = array( 'id' => $new_menu_id );
			}
		}

		return $menus;
	}

	private function link_new_menu_translation( $new_menu_id, $original_menu_tt_id, $language ) {
		$wpdb = $this->wpdb;

		$new_menu_tt_id = (int) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT term_taxonomy_id FROM {$wpdb->term_taxonomy} WHERE term_id = %d AND taxonomy = 'nav_menu' LIMIT 1",
				$new_menu_id
			)
		);
		$trid           = $original_menu_tt_id ? $this->term_translations->get_element_trid( $original_menu_tt_id ) : null;
		if ( ! $new_menu_tt_id || ! $trid ) {
			return;
		}

		$source_language = $this->term_translations->get_element_lang_code( $original_menu_tt_id );
		$this->sitepress->set_element_language_details(
			$new_menu_tt_id,
			'tax_nav_menu',
			$trid,
			$language,
			$source_language ? $source_language : $this->sitepress->get_default_language()
		);
		$this->term_translations->reload();
	}

	function get_menu_item_translations( $item, $menu_id ) {
		$languages         = array_keys( $this->sitepress->get_active_languages() );
		$item_translations = $this->post_translations->get_element_translations( $item->ID );
		$languages         = array_diff( $languages, array( $this->sitepress->get_default_language() ) );
		$translations      = array_fill_keys( $languages, false );

		$item->object_type = property_exists( $item, 'object_type' ) ? $item->object_type : $item->type;
		$this->prime_translated_post_caches( $item, $languages, $item_translations );

		foreach ( $languages as $lang_code ) {

			$item->object_type    = property_exists( $item, 'object_type' ) ? $item->object_type : $item->type;
			$translated_object_id = (int) icl_object_id(
				$item->object_type === 'post_type_archive' ? $item->ID : $item->object_id,
				Lst::includes( $item->object_type, [ 'custom', 'post_type_archive' ] ) ? 'nav_menu_item' : $item->object,
				false,
				$lang_code
			);
			if ( ! $translated_object_id && $item->object_type !== 'custom' && $item->object_type !== 'post_type_archive' ) {
				continue;
			}

			$translated_object_title = '';
			$translated_object_url   = $item->url;
			$icl_st_label_exists     = true;
			$icl_st_url_exists       = true;
			$label_changed           = false;
			$url_changed             = false;
			$object_not_public       = false;

			if ( $item->object_type === 'post_type' ) {
				list( $translated_object_id, $item_translations ) = $this->maybe_reload_post_item(
					$translated_object_id,
					$item_translations,
					$item,
					$lang_code
				);
				$translated_object                                = get_post( $translated_object_id );
				if ( $translated_object->post_status === 'trash' ) {
					$translated_object_id = false;
				} else {
					$translated_object_title = $translated_object->post_title;
					$object_not_public = ! is_post_publicly_viewable( $translated_object );
				}
			} elseif ( $item->object_type === 'taxonomy' ) {
				$translated_object       = get_term(
					$translated_object_id,
					get_post_meta( $item->ID, '_menu_item_object', true )
				);
				$translated_object_title = $translated_object->name;
			} elseif ( $item->object_type === 'custom' ) {
				$translated_object_title = $item->post_title;
				if ( defined( 'WPML_ST_PATH' ) ) {
					list( $translated_object_url, $translated_object_title, $url_changed, $label_changed ) = $this->st_actions(
						$lang_code,
						$menu_id,
						$item,
						$translated_object_id,
						$translated_object_title,
						$translated_object_url,
						$icl_st_label_exists,
						$icl_st_url_exists
					);
				}
			} elseif ( $item->object_type === 'post_type_archive' ) {
				if ( $translated_object_id ) {
					$translated_object = get_post( $translated_object_id );
					$translated_object_title = $translated_object->post_title;
				} else {
					$translated_object_title = $item->post_title;
				}
			}
			$this->fix_assignment_to_menu( $item_translations, (int) $menu_id );
			$this->fix_language_conflicts_once();

			$translated_item_id = isset( $item_translations[ $lang_code ] ) ? (int) $item_translations[ $lang_code ] : false;
			$item_depth         = $this->get_menu_item_depth( $translated_item_id, $menu_id );
			if ( $translated_item_id ) {
				$translated_item               = get_post( $translated_item_id );
				$translated_object_title       = ! empty( $translated_item->post_title ) && ! $icl_st_label_exists ? $translated_item->post_title : $translated_object_title;
				$translate_item_parent_item_id = (int) get_post_meta(
					$translated_item_id,
					'_menu_item_menu_item_parent',
					true
				);
				if ( $item->menu_item_parent > 0
					&& $translate_item_parent_item_id != $this->post_translations->element_id_in(
						$item->menu_item_parent,
						$lang_code
					)
				) {
					$translate_item_parent_item_id = 0;
					$item_depth                    = 0;
				}
				$translation = array(
					'menu_order' => $translated_item->menu_order,
					'parent'     => $translate_item_parent_item_id,
				);
			} else {
				$translation = array(
					'menu_order' => ( $item->object_type === 'custom' ? $item->menu_order : 0 ),
					'parent'     => 0,
				);
			}

			$translation['ID']                    = $translated_item_id;
			$translation['depth']                 = $item_depth;
			$translation['parent_not_translated'] = $this->is_parent_not_translated( $item, $lang_code );
			$translation['object']                = $item->object;
			$translation['object_type']           = $item->object_type;
			$translation['object_id']             = $translated_object_id;
			$translation['object_not_public']     = $object_not_public;
			$translation['title']                 = $translated_object_title;
			$translation['url']                   = $translated_object_url;
			$translation['target']                = $item->target;
			$translation['classes']               = $item->classes;
			$translation['xfn']                   = $item->xfn;
			$translation['attr-title']            = $item->attr_title;
			$translation['label_changed']         = $label_changed;
			$translation['url_changed']           = $url_changed;
			$translation['label_missing']         = ! $icl_st_label_exists;
			$translation['url_missing']           = ! $icl_st_url_exists;

			$translations[ $lang_code ] = $translation;
		}

		return $translations;
	}

	function sync_page_menu_item_trids( $menu_item ) {
		$changed = 0;
		if ( $menu_item->object_type === 'post_type' ) {
			$translations = $this->post_translations->get_element_translations( $menu_item->ID );
			if ( (bool) $translations === true ) {
				$orphans = $this->orphan_map->take_orphans( (int) $menu_item->ID, array_keys( $translations ) );
				if ( (bool) $orphans === true ) {
					$trid = $this->post_translations->get_element_trid( $menu_item->ID );
					foreach ( $orphans as $orphan ) {
						$this->sitepress->set_element_language_details(
							$orphan->element_id,
							WPML_Menu_Item_Orphan_Map::MENU_ITEM_ELEMENT_TYPE,
							$trid,
							$orphan->language_code
						);
						$changed ++;
					}
				}
			}
		}

		return $changed;
	}

	private function preload_menu_item_translation_data( $items ) {
		$item_ids        = array();
		$ids_to_prefetch = array();

		foreach ( $items as $item ) {
			$item_ids[]        = (int) $item->ID;
			$ids_to_prefetch[] = (int) $item->ID;
			if ( 'post_type' === $item->type && $item->object_id ) {
				$ids_to_prefetch[] = (int) $item->object_id;
			}
		}

		$this->post_translations->prefetch_ids( $ids_to_prefetch );
		$this->orphan_map->preload( $item_ids );
		$this->fix_language_conflicts_once();
	}

	private function prime_translated_post_caches( $item, array $languages, array $item_translations ) {
		$ids = array();

		foreach ( $languages as $lang_code ) {
			if ( ! empty( $item_translations[ $lang_code ] ) ) {
				$ids[] = (int) $item_translations[ $lang_code ];
			}
		}

		if ( 'post_type' === $item->object_type && ! empty( $item->object_id ) ) {
			$object_translations = $this->post_translations->get_element_translations( (int) $item->object_id );
			foreach ( $languages as $lang_code ) {
				if ( ! empty( $object_translations[ $lang_code ] ) ) {
					$ids[] = (int) $object_translations[ $lang_code ];
				}
			}
		}

		$ids = array_values( array_unique( array_filter( $ids ) ) );

		if ( $ids && function_exists( '_prime_post_caches' ) ) {
			_prime_post_caches( $ids, false, true );
		}
	}

	function get_menu_translations( $menu_id, $include_original = false ) {
		$wpdb = $this->wpdb;

		$languages    = array_keys( $this->sitepress->get_active_languages() );
		$translations = array();
		foreach ( $languages as $lang_code ) {
			if ( $include_original || $lang_code !== $this->sitepress->get_default_language() ) {
				$menu_translated_id = $this->term_translations->term_id_in( $menu_id, $lang_code );
				$menu_data          = array();
				if ( $menu_translated_id ) {
					$menu_object  = $wpdb->get_row(
						$wpdb->prepare(
							"
                        SELECT t.term_id, t.name
                        FROM {$wpdb->terms} t
                        JOIN {$wpdb->term_taxonomy} x
                        	ON t.term_id = t.term_id
                        WHERE t.term_id = %d
                        	AND x.taxonomy='nav_menu'
                        LIMIT 1",
							$menu_translated_id
						)
					);
					$this->sitepress->switch_lang( $lang_code, false );
					try {
						$menu_data = array(
							'id'    => $menu_object->term_id,
							'name'  => $menu_object->name,
							'items' => $this->get_menu_items( $menu_translated_id, false ),
						);
					} finally {
						$this->sitepress->switch_lang();
					}
				}
				$translations[ $lang_code ] = $menu_data;
			}
		}

		return $translations;
	}

	protected function get_menu_name( $menu_id ) {
		$menu = wp_get_nav_menu_object( $menu_id );

		return $menu ? $menu->name : false;
	}

	protected function get_translated_menu( $menu_id, $language_code = false ) {
		$language_code = $language_code ? $language_code : $this->sitepress->get_default_language();
		$menus         = $this->get_menu_translations( $menu_id, true );

		return isset( $menus[ $language_code ] ) ? $menus[ $language_code ] : false;
	}

	protected function icl_t_menu_item( $menu_name, $item, $lang, &$has_label_translation, &$has_url_translation ) {
		$default_lang = $this->sitepress->get_default_language();
		$label        = $item->post_title;
		$url          = $item->url;

		if ( $lang !== $default_lang ) {

			icl_register_string(
				$menu_name . self::STRING_CONTEXT_SUFFIX,
				self::STRING_NAME_LABEL_PREFIX . $item->ID,
				$label,
				false,
				$default_lang
			);

			$label = icl_t(
				$menu_name . self::STRING_CONTEXT_SUFFIX,
				self::STRING_NAME_LABEL_PREFIX . $item->ID,
				$label,
				$has_label_translation,
				true,
				$lang
			);

			icl_register_string(
				$menu_name . self::STRING_CONTEXT_SUFFIX,
				self::STRING_NAME_URL_PREFIX . $item->ID,
				$url,
				false,
				$default_lang
			);

			$url = icl_t(
				$menu_name . self::STRING_CONTEXT_SUFFIX,
				self::STRING_NAME_URL_PREFIX . $item->ID,
				$url,
				$has_url_translation,
				true,
				$lang
			);
		}

		return array( $label, $url );
	}

	private function is_parent_not_translated( $item, $lang_code ) {

		if ( $item->menu_item_parent > 0 ) {
			$item_parent_object_id = get_post_meta( $item->menu_item_parent, '_menu_item_object_id', true );
			$item_parent_object    = get_post_meta( $item->menu_item_parent, '_menu_item_object', true );

			if ( 'custom' === $item_parent_object ) {
				return 0;
			}

			$parent_translated = icl_object_id( $item_parent_object_id, $item_parent_object, false, $lang_code );

			if ( $parent_translated && post_type_exists( $item_parent_object ) && ! is_post_publicly_viewable( (int) $parent_translated ) ) {
				$parent_translated = 0;
			}

			return $parent_translated ? 0 : 1;
		}

		return 0;
	}

	private function maybe_reload_post_item( $translated_object_id, $item_translations, $item, $lang_code ) {
		if ( $this->sync_page_menu_item_trids( $item ) > 0 ) {
			$item_translations    = $this->post_translations->get_element_translations( $item->ID );
			$translated_object_id = $this->post_translations->element_id_in(
				$item->object_id,
				$lang_code
			);
			$translated_object_id = $translated_object_id === null ? false : $translated_object_id;
		}

		return array( $translated_object_id, $item_translations );
	}

	private function get_menu_item_depth( $item_id, $menu_id = 0 ) {
		return WPML_Menu_Hierarchy_Guard::depth_from_meta( $item_id, $menu_id );
	}

	private function st_actions( $lang_code,
								 $menu_id,
								 $item,
								 $translated_object_id,
								 $translated_object_title,
								 $translated_object_url,
								 &$icl_st_label_exists,
								 &$icl_st_url_exists ) {
		if ( ! function_exists( 'icl_translate' ) ) {
			require WPML_ST_PATH . '/inc/functions.php';
		}

		$label_changed = false;
		$url_changed   = false;

		$this->sitepress->switch_lang( $lang_code );

		try {
			$menu_name                 = $this->get_menu_name( $menu_id );
			$translated_object_title_t = '';
			$translated_object_url_t   = '';
			$translated_menu_id        = $this->term_translations->term_id_in( $menu_id, $lang_code );

			if ( function_exists( 'icl_t' ) ) {
				list( $translated_object_title_t, $translated_object_url_t ) = $this->icl_t_menu_item(
					$menu_name,
					$item,
					$lang_code,
					$icl_st_label_exists,
					$icl_st_url_exists
				);
			} else {
				$translated_object_title_t = $item->post_title . ' @' . $lang_code;
				$translated_object_url_t   = $item->url;
			}
		} finally {
			$this->sitepress->switch_lang();
		}

		if ( $translated_object_id ) {
			$translated_object       = get_post( $translated_object_id );
			$label_changed           = $translated_object_title_t != $translated_object->post_title;
			$url_changed             = $translated_object_url_t != get_post_meta( $translated_object_id, '_menu_item_url', true );
			$translated_object_title = $icl_st_label_exists ? $translated_object_title_t : $translated_object_title;
			$translated_object_url   = $icl_st_url_exists ? $translated_object_url_t : $translated_object_url;
		}

		return array(
			$translated_object_url,
			$translated_object_title,
			$url_changed,
			$label_changed,
		);
	}

	private function fix_assignment_to_menu( $item_translations, $menu_id ) {
		$wpdb = $this->wpdb;

		foreach ( $item_translations as $lang_code => $item_id ) {
			$correct_menu_id = $this->term_translations->term_id_in( $menu_id, $lang_code );
			if ( $correct_menu_id ) {
				$ttid_trans = $wpdb->get_var(
					$wpdb->prepare(
						"	SELECT tt.term_taxonomy_id
																			FROM {$wpdb->term_taxonomy} tt
																			LEFT JOIN {$wpdb->term_relationships} tr
																				ON tt.term_taxonomy_id = tr.term_taxonomy_id
																					AND tr.object_id = %d
																			WHERE tt.taxonomy = 'nav_menu'
																				AND tt.term_id = %d
																				AND tr.term_taxonomy_id IS NULL
																			LIMIT 1",
						$item_id,
						$correct_menu_id
					)
				);
				if ( $ttid_trans ) {
					$this->wpdb->insert(
						$this->wpdb->term_relationships,
						array(
							'object_id'        => $item_id,
							'term_taxonomy_id' => $ttid_trans,
						)
					);
				}
			}
		}
	}

	private function fix_language_conflicts_once() {
		if ( $this->language_conflicts_fixed ) {
			return;
		}
		$this->language_conflicts_fixed = true;
		$this->fix_language_conflicts();
	}

	private function fix_language_conflicts() {
		$wpdb = $this->wpdb;

		$wrong_items = $this->wpdb->get_results(
			"	SELECT r.object_id, t.term_taxonomy_id
													FROM {$wpdb->term_relationships} r
													  JOIN {$wpdb->prefix}icl_translations ip
													  JOIN {$wpdb->posts} p
														ON ip.element_type = CONCAT('post_', p.post_type)
														   AND ip.element_id = p.ID
														   AND ip.element_id = r.object_id
													  JOIN {$wpdb->prefix}icl_translations it
													  JOIN {$wpdb->term_taxonomy} t
														ON it.element_type = CONCAT('tax_', t.taxonomy)
														   AND it.element_id = t.term_taxonomy_id
														   AND it.element_id = r.term_taxonomy_id
													WHERE p.post_type = 'nav_menu_item'
													  AND t.taxonomy = 'nav_menu'
													  AND ip.language_code != it.language_code"
		);
		foreach ( $wrong_items as $item ) {
			$this->wpdb->delete(
				$this->wpdb->term_relationships,
				array(
					'object_id'        => $item->object_id,
					'term_taxonomy_id' => $item->term_taxonomy_id,
				)
			);
		}
	}
}
