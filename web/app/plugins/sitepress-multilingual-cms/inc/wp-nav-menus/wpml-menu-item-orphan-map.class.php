<?php

class WPML_Menu_Item_Orphan_Map {

	const NAV_MENU_ITEM_POST_TYPE = 'nav_menu_item';
	const MENU_ITEM_ELEMENT_TYPE  = 'post_nav_menu_item';
	const PAGE_ELEMENT_TYPE       = 'post_page';
	const OBJECT_ID_META_KEY      = '_menu_item_object_id';
	const NAV_MENU_TAXONOMY       = 'nav_menu';
	const MENU_ELEMENT_TYPE       = 'tax_nav_menu';

	private $wpdb;

	private $orphans = array();

	private $loaded = array();

	private $taken = array();

	public function __construct( wpdb $wpdb ) {
		$this->wpdb = $wpdb;
	}

	public function preload( array $menu_item_ids ) {
		$menu_item_ids = array_diff( array_map( 'intval', $menu_item_ids ), array_keys( $this->loaded ) );
		if ( ! $menu_item_ids ) {
			return;
		}

		foreach ( $menu_item_ids as $menu_item_id ) {
			$this->loaded[ $menu_item_id ]  = true;
			$this->orphans[ $menu_item_id ] = array();
		}

		$menu_ttids = $this->menu_scope_ttids( $menu_item_ids );

		$rows = $this->wpdb->get_results( $this->build_orphan_sql( $menu_item_ids, $menu_ttids ) );
		foreach ( $rows as $row ) {
			$this->orphans[ (int) $row->owner_id ][] = $row;
		}
	}

	private function menu_scope_ttids( array $menu_item_ids ) {
		$wpdb      = $this->wpdb;
		$owners_in = wpml_prepare_in( $menu_item_ids, '%d' );
		$menu_type = self::MENU_ELEMENT_TYPE;

		$ttids = $wpdb->get_col(
			"SELECT DISTINCT COALESCE( menu_t.element_id, tt.term_taxonomy_id )
			FROM {$wpdb->term_relationships} tr
			JOIN {$wpdb->term_taxonomy} tt
				ON tt.term_taxonomy_id = tr.term_taxonomy_id
				AND tt.taxonomy = '" . self::NAV_MENU_TAXONOMY . "'
			LEFT JOIN {$wpdb->prefix}icl_translations menu_o
				ON menu_o.element_id = tt.term_taxonomy_id
				AND menu_o.element_type = '{$menu_type}'
			LEFT JOIN {$wpdb->prefix}icl_translations menu_t
				ON menu_t.trid = menu_o.trid
				AND menu_t.element_type = '{$menu_type}'
			WHERE tr.object_id IN ({$owners_in})"
		);


		return array_values( array_filter( array_map( 'intval', (array) $ttids ) ) );
	}

	public function take_orphans( $menu_item_id, array $existing_languages ) {
		$menu_item_id = (int) $menu_item_id;
		if ( ! isset( $this->loaded[ $menu_item_id ] ) ) {
			$this->preload( array( $menu_item_id ) );
		}

		$orphans = array_filter(
			$this->orphans[ $menu_item_id ],
			function ( $row ) use ( $existing_languages ) {
				return ! isset( $this->taken[ (int) $row->element_id ] )
					&& ! in_array( $row->language_code, $existing_languages, true );
			}
		);

		foreach ( $orphans as $orphan ) {
			$this->taken[ (int) $orphan->element_id ] = true;
		}

		$this->orphans[ $menu_item_id ] = array();

		return array_values( $orphans );
	}

	private function build_orphan_sql( array $menu_item_ids, array $menu_ttids ) {
		$wpdb = $this->wpdb;

		$owners_in_clause = wpml_prepare_in( $menu_item_ids, '%d' );
		$menus_in_clause = $menu_ttids ? wpml_prepare_in( $menu_ttids, '%d' ) : '0';

		return "SELECT DISTINCT io.element_id AS owner_id, it.element_id, it.language_code
        FROM {$wpdb->prefix}icl_translations it
        JOIN {$wpdb->posts} pt
            ON pt.ID = it.element_id
            AND pt.post_type = '" . self::NAV_MENU_ITEM_POST_TYPE . "'
            AND it.element_type = '" . self::MENU_ITEM_ELEMENT_TYPE . "'
        JOIN {$wpdb->prefix}icl_translations io
            ON io.trid != it.trid
        JOIN {$wpdb->posts} po
            ON po.ID = io.element_id
            AND po.post_type = '" . self::NAV_MENU_ITEM_POST_TYPE . "'
        JOIN {$wpdb->postmeta} mo
            ON mo.post_id = po.ID
            AND mo.meta_key = '" . self::OBJECT_ID_META_KEY . "'
        LEFT JOIN {$wpdb->term_relationships} candidate_menu_rel
            ON candidate_menu_rel.object_id = pt.ID
        LEFT JOIN {$wpdb->term_taxonomy} candidate_menu
            ON candidate_menu.term_taxonomy_id = candidate_menu_rel.term_taxonomy_id
            AND candidate_menu.taxonomy = '" . self::NAV_MENU_TAXONOMY . "'
        JOIN {$wpdb->postmeta} mt
            ON mt.post_id = pt.ID
            AND mt.meta_key = '" . self::OBJECT_ID_META_KEY . "'
        JOIN {$wpdb->prefix}icl_translations page_t
            ON mt.meta_value = page_t.element_id
            AND page_t.element_type = '" . self::PAGE_ELEMENT_TYPE . "'
        JOIN {$wpdb->prefix}icl_translations page_o
            ON mo.meta_value = page_o.element_id
            AND page_o.element_type = '" . self::PAGE_ELEMENT_TYPE . "'
            AND page_o.trid = page_t.trid
        WHERE io.element_id IN ({$owners_in_clause})
        AND io.element_type = '" . self::MENU_ITEM_ELEMENT_TYPE . "'
        AND ( candidate_menu.term_taxonomy_id IS NULL OR candidate_menu.term_taxonomy_id IN ({$menus_in_clause}) )
        AND (
            SELECT COUNT(count.element_id)
            FROM {$wpdb->prefix}icl_translations count
            WHERE count.trid = it.trid
        ) = 1";
	}
}
