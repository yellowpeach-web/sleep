<?php

class WPML_404_Guess extends WPML_Slug_Resolution {

	const CACHE_GROUP = 'WPML_404_Guess';

	const CACHE_VERSION = 2;

	private $query_filter;

	private $cache_factory;

	public function __construct( &$wpdb, &$sitepress, &$query_filter, ?WPML_WP_Cache_Factory $cache_factory = null ) {
		parent::__construct( $wpdb, $sitepress );
		$this->query_filter  = &$query_filter;
		$this->cache_factory = $cache_factory ? $cache_factory : new WPML_WP_Cache_Factory();
	}

	public function guess_cpt_by_name( $name, $query ) {
		$type  = $query->get( 'post_type' );
		$ret   = array( $name, $type, false );
		$types = (bool) $type === false
			? $this->sitepress->get_wp_api()->get_post_types( array( 'public' => true ) )
			: (array) $type;
		if ( (bool) $types === true ) {

			$date_snippet = $this->by_date_snippet( $query );
			$page_first   = (bool) $query->get( 'pagename' );

			$can_read_private = $this->current_user_can_read_private_posts();

			$cache_item = $this->cache_factory->create_language_aware_cache_item(
				self::CACHE_GROUP,
				array( 'guess_cpt', self::CACHE_VERSION, $name, $types, $page_first, $date_snippet, $can_read_private )
			);

			list( $ret, $found ) = $cache_item->get_with_found();

			if ( ! $found ) {
				$ret = $this->find_post_type( $name, $type, $types, $date_snippet, $page_first, $can_read_private );
				$cache_item->set( $ret );
			}
		}

		return $ret;
	}

	private function current_user_can_read_private_posts() {
		return $this->pluggable_user_api_loaded() && current_user_can( 'read_private_posts' );
	}

	protected function pluggable_user_api_loaded() {
		return function_exists( 'wp_get_current_user' );
	}

	private function find_post_type( $name, $type, $types, $date_snippet, $page_first, $can_read_private ) {
		$wpdb   = $this->wpdb;
		$ret    = array( $name, $type, false );
		$where  = $this->wpdb->prepare( 'post_name = %s ', $name );
		$where .= ' AND post_type IN (' . wpml_prepare_in( $types ) . ')';
		$where .= $date_snippet;

		$private_status_clause = $can_read_private
			? " OR post_status = 'private' "
			: '';

		$status_clause = " AND ( post_status = 'publish' {$private_status_clause}
				OR ( post_type = 'attachment' AND post_status = 'inherit' ) ) ";

		$matched_types = null;
		if ( $type && count( $types ) > 1 ) {
			$matched_types = $this->wpdb->get_col(
				"SELECT DISTINCT post_type
				 FROM {$this->wpdb->posts} p
				 WHERE $where
					{$status_clause}
				 LIMIT " . (int) count( $types )
			);
			if ( ! $matched_types ) {
				return $ret;
			}
		}

		$res    = $this->wpdb->get_row(
			"
										 SELECT post_type, post_name
											 FROM {$wpdb->posts} p
											 LEFT JOIN {$wpdb->prefix}icl_translations wpml_translations
											ON wpml_translations.element_id = p.ID
											    AND wpml_translations.element_type IN (" . wpml_prepare_in( wpml_post_element_types( $types ) ) . ')
										        AND ' . $this->query_filter->in_translated_types_snippet( false, 'p' ) . "
										 WHERE $where
										    AND ( post_status = 'publish' {$private_status_clause}
										        OR ( post_type = 'attachment'
										             AND post_status = 'inherit' ) )
										    " . $this->order_by_type_and_language_snippet( (bool) $date_snippet, $page_first ) . '
									     LIMIT 1'
		);
		if ( (bool) $res === true ) {
			$ret = array( $res->post_name, null === $matched_types ? array( $res->post_type ) : $matched_types, true );
		}

		return $ret;
	}


	private function by_date_snippet( $query ) {
		$snippet = '';
		$year    = $query->get( 'year' );
		$month   = $query->get( 'monthnum' );
		$day     = $query->get( 'day' );

		if ( $year ) {
			$snippet .= $this->wpdb->prepare( ' AND YEAR(post_date) = %d ', $year );
		}
		if ( $month ) {
			$snippet .= $this->wpdb->prepare( ' AND MONTH(post_date) = %d ', $month );
		}
		if ( $day ) {
			$snippet .= $this->wpdb->prepare( ' AND DAY(post_date) = %d ', $day );
		}

		return $snippet;
	}

	private function order_by_type_and_language_snippet( $has_date, $page_first ) {
		$lang_order   = $this->get_ordered_langs();
		$current_lang = array_shift( $lang_order );
		$best_score   = count( $lang_order ) + 2;
		$order_by     = sprintf( "ORDER BY post_type = 'page' %s", $page_first ? 'DESC' : 'ASC' );
		if ( $best_score > 2 ) {
			$order_by .= $this->wpdb->prepare(
				', CASE wpml_translations.language_code WHEN %s THEN %d ',
				$current_lang,
				$best_score
			);
			$score     = $best_score - 2;
			foreach ( $lang_order as $lang_code ) {
				$order_by .= $this->wpdb->prepare( ' WHEN %s THEN %d ', $lang_code, $score );
				$score    -= 1;
			}
			$order_by .= ' ELSE 0 END DESC ';
			if ( $has_date ) {
				$order_by .= ", CASE p.post_type WHEN 'post' THEN 0 ELSE 1 END ";
			}
			$order_by .= ' , ' . $this->order_by_post_type_snippet();
		}

		return $order_by;
	}

	private function order_by_post_type_snippet() {
		$post_types = array(
			'page' => 2,
			'post' => 1,
		);
		$order_by   = ' CASE p.post_type ';
		foreach ( $post_types as $type => $score ) {
			$order_by .= $this->wpdb->prepare( ' WHEN %s THEN %d ', $type, $score );
		}
		$order_by .= ' ELSE 0 END DESC ';

		return $order_by;
	}
}
