<?php

namespace WPML\Posts;

class TranslatedContentOfLanguages {

	const TYPE_POSTS    = 'posts';
	const TYPE_PRODUCTS = 'products';
	const TYPE_MEDIA    = 'media';
	const TYPE_TERMS    = 'terms';
	const TYPE_MENUS    = 'menus';

	const ROUTE_TRASH     = 'trash';
	const ROUTE_PERMANENT = 'permanent';

	const MEDIA_POST_TYPE     = 'attachment';
	const MENU_ITEM_POST_TYPE = 'nav_menu_item';
	const MENU_TAXONOMY       = 'nav_menu';

	private static $ownGroupPostTypes = array( 'product', 'product_variation', self::MEDIA_POST_TYPE, self::MENU_ITEM_POST_TYPE );

	private static $productPostTypes = array( 'product', 'product_variation' );

	public static function allTypes() {
		return array(
			self::TYPE_POSTS    => true,
			self::TYPE_PRODUCTS => true,
			self::TYPE_MEDIA    => true,
			self::TYPE_TERMS    => true,
			self::TYPE_MENUS    => true,
		);
	}

	public static function normalizeTypes( array $include ) {
		$normalized = array();

		foreach ( array_keys( self::allTypes() ) as $key ) {
			$normalized[ $key ] = ! empty( $include[ $key ] );
		}

		return $normalized;
	}

	public static function counts( array $langs ) {
		return self::countsScoped( $langs, null, null );
	}

	public static function countsFiltered( array $langs, array $include, $route = self::ROUTE_PERMANENT ) {
		return self::countsScoped( $langs, self::normalizeTypes( $include ), $route );
	}

	private static function countsScoped( array $langs, $include, $route ) {
		global $wpdb;

		$types = array();
		$total = 0;

		if ( ! $langs ) {
			return array( 'total' => 0, 'types' => array() );
		}

		$langs     = array_values( $langs );
		$postWhere = self::postTypeClause( $include ) . self::routeClause( $route );
		$taxWhere  = self::taxonomyClause( $include, 'tt' );

		$posts = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT posts.post_type AS slug, COUNT(posts.ID) AS c
			 FROM {$wpdb->posts} posts
			 INNER JOIN {$wpdb->prefix}icl_translations t
				ON t.element_id = posts.ID AND t.element_type = CONCAT('post_', posts.post_type)
			 WHERE t.language_code IN (" . implode( ',', array_fill( 0, count( $langs ), '%s' ) ) . "){$postWhere}
			 GROUP BY posts.post_type",
				$langs
			)
		);

		foreach ( (array) $posts as $row ) {
			$count   = (int) $row->c;
			$total  += $count;
			$types[] = array(
				'kind'  => 'post',
				'slug'  => (string) $row->slug,
				'label' => self::postTypeLabel( (string) $row->slug, $count ),
				'count' => $count,
			);
		}

		$terms = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT tt.taxonomy AS slug, COUNT(tt.term_taxonomy_id) AS c
			 FROM {$wpdb->term_taxonomy} tt
			 INNER JOIN {$wpdb->prefix}icl_translations t
				ON t.element_id = tt.term_taxonomy_id AND t.element_type = CONCAT('tax_', tt.taxonomy)
			 WHERE t.language_code IN (" . implode( ',', array_fill( 0, count( $langs ), '%s' ) ) . "){$taxWhere}
			 GROUP BY tt.taxonomy",
				$langs
			)
		);

		foreach ( (array) $terms as $row ) {
			$count   = (int) $row->c;
			$total  += $count;
			$types[] = array(
				'kind'  => 'taxonomy',
				'slug'  => (string) $row->slug,
				'label' => self::taxonomyLabel( (string) $row->slug, $count ),
				'count' => $count,
			);
		}

		return array( 'total' => $total, 'types' => $types );
	}

	public static function itemsFiltered( array $langs, array $include, $route = self::ROUTE_PERMANENT, $offset = 0, $limit = 25 ) {
		global $wpdb;

		$offset = max( 0, (int) $offset );
		$limit  = max( 1, (int) $limit );

		$answer = array(
			'total'  => 0,
			'offset' => $offset,
			'items'  => array(),
		);

		if ( ! $langs ) {
			return $answer;
		}

		$include = self::normalizeTypes( $include );
		$route   = self::ROUTE_TRASH === $route ? self::ROUTE_TRASH : self::ROUTE_PERMANENT;

		$counts           = self::countsScoped( $langs, $include, $route );
		$answer['total']  = (int) $counts['total'];

		$langs     = array_values( $langs );
		$postWhere = self::postTypeClause( $include ) . self::routeClause( $route );
		$taxWhere  = self::taxonomyClause( $include, 'tt' );

		$postTotal = (int) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(posts.ID)
			 FROM {$wpdb->posts} posts
			 INNER JOIN {$wpdb->prefix}icl_translations t
				ON t.element_id = posts.ID AND t.element_type = CONCAT('post_', posts.post_type)
			 WHERE t.language_code IN (" . implode( ',', array_fill( 0, count( $langs ), '%s' ) ) . "){$postWhere}",
				$langs
			)
		);

		if ( $offset < $postTotal ) {
			$rows = (array) $wpdb->get_results(
				$wpdb->prepare(
					"SELECT posts.ID, posts.post_type, posts.post_title
					 FROM {$wpdb->posts} posts
					 INNER JOIN {$wpdb->prefix}icl_translations t
						ON t.element_id = posts.ID AND t.element_type = CONCAT('post_', posts.post_type)
					 WHERE t.language_code IN (" . implode( ',', array_fill( 0, count( $langs ), '%s' ) ) . "){$postWhere}
					 ORDER BY posts.ID ASC
					 LIMIT %d OFFSET %d",
					array_merge( $langs, array( $limit, $offset ) )
				)
			);

			foreach ( $rows as $row ) {
				$slug              = (string) $row->post_type;
				$answer['items'][] = array(
					'kind'  => 'post',
					'slug'  => $slug,
					'label' => self::postTypeLabel( $slug, 2 ),
					'id'    => (int) $row->ID,
					'title' => self::titleOf( (string) $row->post_title ),
				);
			}
		}

		$remaining = $limit - count( $answer['items'] );

		if ( $remaining > 0 ) {
			$rows = (array) $wpdb->get_results(
				$wpdb->prepare(
					"SELECT tt.term_taxonomy_id, tt.taxonomy, terms.name
					 FROM {$wpdb->term_taxonomy} tt
					 INNER JOIN {$wpdb->terms} terms ON terms.term_id = tt.term_id
					 INNER JOIN {$wpdb->prefix}icl_translations t
						ON t.element_id = tt.term_taxonomy_id AND t.element_type = CONCAT('tax_', tt.taxonomy)
					 WHERE t.language_code IN (" . implode( ',', array_fill( 0, count( $langs ), '%s' ) ) . "){$taxWhere}
					 ORDER BY tt.term_taxonomy_id ASC
					 LIMIT %d OFFSET %d",
					array_merge( $langs, array( $remaining, max( 0, $offset - $postTotal ) ) )
				)
			);

			foreach ( $rows as $row ) {
				$slug              = (string) $row->taxonomy;
				$answer['items'][] = array(
					'kind'  => 'taxonomy',
					'slug'  => $slug,
					'label' => self::taxonomyLabel( $slug, 2 ),
					'id'    => (int) $row->term_taxonomy_id,
					'title' => self::titleOf( (string) $row->name ),
				);
			}
		}

		return $answer;
	}

	private static function titleOf( $title ) {
		$title = trim( (string) $title );

		if ( '' !== $title ) {
			return $title;
		}

		/* translators: Shown in place of the title of a piece of content that has none. The brackets show that it is not a title of its own. */
		return __( '(no title)', 'sitepress' );
	}

	public static function totalsByLanguage( array $langs ) {
		global $wpdb;

		if ( ! $langs ) {
			return array();
		}

		$langs  = array_values( $langs );
		$totals = array();

		$posts = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT t.language_code AS code, COUNT(posts.ID) AS c
			 FROM {$wpdb->posts} posts
			 INNER JOIN {$wpdb->prefix}icl_translations t
				ON t.element_id = posts.ID AND t.element_type = CONCAT('post_', posts.post_type)
			 WHERE t.language_code IN (" . implode( ',', array_fill( 0, count( $langs ), '%s' ) ) . ')
			 GROUP BY t.language_code',
				$langs
			)
		);

		foreach ( (array) $posts as $row ) {
			$totals[ (string) $row->code ] = (int) $row->c;
		}

		$terms = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT t.language_code AS code, COUNT(tt.term_taxonomy_id) AS c
			 FROM {$wpdb->term_taxonomy} tt
			 INNER JOIN {$wpdb->prefix}icl_translations t
				ON t.element_id = tt.term_taxonomy_id AND t.element_type = CONCAT('tax_', tt.taxonomy)
			 WHERE t.language_code IN (" . implode( ',', array_fill( 0, count( $langs ), '%s' ) ) . ')
			 GROUP BY t.language_code',
				$langs
			)
		);

		foreach ( (array) $terms as $row ) {
			$code            = (string) $row->code;
			$totals[ $code ] = ( isset( $totals[ $code ] ) ? $totals[ $code ] : 0 ) + (int) $row->c;
		}

		return $totals;
	}

	public static function hasAny( array $langs ) {
		global $wpdb;

		if ( ! $langs ) {
			return false;
		}

		$langs = array_values( $langs );

		$post = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT 1
			 FROM {$wpdb->posts} posts
			 INNER JOIN {$wpdb->prefix}icl_translations t
				ON t.element_id = posts.ID AND t.element_type = CONCAT('post_', posts.post_type)
			 WHERE t.language_code IN (" . implode( ',', array_fill( 0, count( $langs ), '%s' ) ) . ')
			 LIMIT 1',
				$langs
			)
		);

		if ( $post ) {
			return true;
		}

		$term = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT 1
			 FROM {$wpdb->term_taxonomy} tt
			 INNER JOIN {$wpdb->prefix}icl_translations t
				ON t.element_id = tt.term_taxonomy_id AND t.element_type = CONCAT('tax_', tt.taxonomy)
			 WHERE t.language_code IN (" . implode( ',', array_fill( 0, count( $langs ), '%s' ) ) . ')
			 LIMIT 1',
				$langs
			)
		);

		return (bool) $term;
	}

	public static function deleteChunk( array $langs, $limit ) {
		$limit = max( 1, (int) $limit );
		if ( ! $langs ) {
			return 0;
		}

		self::forgetDefaultCategories( $langs );

		$deleted = 0;

		foreach ( self::postIds( $langs, $limit ) as $id ) {
			if ( \wp_delete_post( (int) $id, true ) ) {
				$deleted++;
			}
			if ( $deleted >= $limit ) {
				return $deleted;
			}
		}

		$remaining = $limit - $deleted;
		if ( $remaining > 0 ) {
			$deleted += self::deleteTermsChunk( $langs, $remaining );
		}

		return $deleted;
	}

	public static function deleteChunkFiltered( array $langs, $limit, array $include, $route = self::ROUTE_PERMANENT, array $excludeIds = array() ) {
		$limit   = max( 1, (int) $limit );
		$include = self::normalizeTypes( $include );
		$route   = self::ROUTE_TRASH === $route ? self::ROUTE_TRASH : self::ROUTE_PERMANENT;

		$result = array(
			'deleted' => 0,
			'counts'  => array(),
			'trashed' => array(),
			'skipped' => array(),
		);

		if ( ! $langs ) {
			return $result;
		}

		if ( $include[ self::TYPE_TERMS ] ) {
			self::forgetDefaultCategories( $langs );
		}

		$excludeIds = array_values( array_unique( array_filter( array_map( 'intval', $excludeIds ) ) ) );

		foreach ( self::postRowsScoped( $langs, $limit + count( $excludeIds ), $include, $route ) as $row ) {
			$id   = (int) $row->ID;
			$type = (string) $row->post_type;

			if ( in_array( $id, $excludeIds, true ) ) {
				continue;
			}

			if ( self::removeOnePost( $id, $type, $route ) ) {
				$result['deleted']++;
				$result['counts'][ $type ] = ( isset( $result['counts'][ $type ] ) ? $result['counts'][ $type ] : 0 ) + 1;

				if ( self::ROUTE_TRASH === $route ) {
					$result['trashed'][] = $id;
				}
			} else {
				$result['skipped'][] = array(
					'id'     => $id,
					'reason' => self::ROUTE_TRASH === $route ? 'trash-refused' : 'delete-refused',
					'type'   => $type,
				);
			}

			if ( $result['deleted'] >= $limit ) {
				return $result;
			}
		}

		$remaining = $limit - $result['deleted'];

		if ( $remaining > 0 ) {
			$terms = self::deleteTermRows( self::termRowsScoped( $langs, $remaining, $include ), true );

			$result['deleted'] += $terms['deleted'];
			$result['skipped']  = array_merge( $result['skipped'], $terms['skipped'] );

			foreach ( $terms['counts'] as $taxonomy => $count ) {
				$result['counts'][ $taxonomy ] = ( isset( $result['counts'][ $taxonomy ] ) ? $result['counts'][ $taxonomy ] : 0 ) + $count;
			}
		}

		return $result;
	}

	public static function deleteAll( array $langs ) {
		$total = 0;
		do {
			$n      = self::deleteChunk( $langs, 100 );
			$total += $n;
		} while ( $n > 0 );

		return $total;
	}

	public static function deleteAllPosts( array $langs, $chunkSize = 100 ) {
		if ( ! $langs ) {
			return array();
		}

		$chunkSize = max( 1, (int) $chunkSize );
		$failed    = array();

		do {
			$deleted = 0;

			foreach ( self::postIds( $langs, $chunkSize + count( $failed ) ) as $id ) {
				$id = (int) $id;
				if ( isset( $failed[ $id ] ) ) {
					continue;
				}
				if ( \wp_delete_post( $id, true ) ) {
					$deleted++;
				} else {
					$failed[ $id ] = true;
				}
			}
		} while ( $deleted > 0 );

		return array_keys( $failed );
	}

	public static function deleteAllTerms( array $langs, $chunkSize = 100 ) {
		if ( ! $langs ) {
			return 0;
		}

		$chunkSize = max( 1, (int) $chunkSize );
		$total     = 0;

		do {
			$n      = self::deleteTermsChunk( $langs, $chunkSize );
			$total += $n;
		} while ( $n > 0 );

		return $total;
	}

	private static function forgetDefaultCategories( array $langs ) {
		global $sitepress;

		if ( ! is_object( $sitepress ) || ! method_exists( $sitepress, 'get_setting' ) ) {
			return;
		}

		$defaults = $sitepress->get_setting( 'default_categories', array() );
		if ( ! is_array( $defaults ) ) {
			return;
		}

		$changed = false;
		foreach ( $langs as $lang ) {
			$lang = (string) $lang;
			if ( array_key_exists( $lang, $defaults ) ) {
				unset( $defaults[ $lang ] );
				$changed = true;
			}
		}

		if ( $changed ) {
			$sitepress->set_default_categories( $defaults );
		}
	}

	private static function postIds( array $langs, $limit ) {
		global $wpdb;

		$langs = array_values( $langs );

		return $wpdb->get_col(
			$wpdb->prepare(
				"SELECT posts.ID
			FROM {$wpdb->posts} posts
			INNER JOIN {$wpdb->prefix}icl_translations t
				ON t.element_id = posts.ID AND t.element_type = CONCAT('post_', posts.post_type)
			WHERE t.language_code IN (" . implode( ',', array_fill( 0, count( $langs ), '%s' ) ) . ')
			LIMIT %d',
				array_merge( $langs, array( (int) $limit ) )
			)
		);
	}

	private static function deleteTermsChunk( array $langs, $limit ) {
		$terms = self::termRowsScoped( $langs, $limit, null );

		return self::deleteTermRows( $terms, false )['deleted'];
	}

	private static function termRowsScoped( array $langs, $limit, $include ) {
		global $wpdb;

		$langs = array_values( $langs );
		$where = self::taxonomyClause( $include, 'wptt' );

		$rows = (array) $wpdb->get_results(
			$wpdb->prepare(
				"SELECT wptt.term_id, wptt.taxonomy
			FROM {$wpdb->term_taxonomy} wptt
			INNER JOIN {$wpdb->prefix}icl_translations t
				ON t.element_id = wptt.term_taxonomy_id AND t.element_type = CONCAT('tax_', wptt.taxonomy)
			WHERE t.language_code IN (" . implode( ',', array_fill( 0, count( $langs ), '%s' ) ) . "){$where}
			LIMIT %d",
				array_merge( $langs, array( (int) $limit ) )
			)
		);

		return $rows;
	}

	private static function deleteTermRows( array $terms, $strict ) {
		global $sitepress;

		$outcome = array(
			'deleted' => 0,
			'counts'  => array(),
			'skipped' => array(),
		);

		if ( ! $terms ) {
			return $outcome;
		}

		$argsFilterRemoved     = remove_filter( 'get_terms_args', array( $sitepress, 'get_terms_args_filter' ) );
		$getTermsFilterRemoved = remove_filter( 'get_term', array( $sitepress, 'get_term_adjust_id' ), 1 );
		$clausesFilterRemoved  = remove_filter( 'terms_clauses', array( $sitepress, 'terms_clauses' ) );

		try {
			foreach ( $terms as $term ) {
				$taxonomy = (string) $term->taxonomy;
				$answer   = \wp_delete_term( (int) $term->term_id, $taxonomy );

				if ( $strict && true !== $answer ) {
					$outcome['skipped'][] = array(
						'id'     => (int) $term->term_id,
						'reason' => 'delete-refused',
						'type'   => $taxonomy,
					);
					continue;
				}

				$outcome['deleted']++;
				$outcome['counts'][ $taxonomy ] = ( isset( $outcome['counts'][ $taxonomy ] ) ? $outcome['counts'][ $taxonomy ] : 0 ) + 1;
			}
		} finally {
			if ( $argsFilterRemoved ) {
				add_filter( 'get_terms_args', array( $sitepress, 'get_terms_args_filter' ), 10, 2 );
			}
			if ( $getTermsFilterRemoved ) {
				add_filter( 'get_term', array( $sitepress, 'get_term_adjust_id' ), 1, 1 );
			}
			if ( $clausesFilterRemoved ) {
				add_filter( 'terms_clauses', array( $sitepress, 'terms_clauses' ), 10, 3 );
			}
		}

		return $outcome;
	}

	private static function postRowsScoped( array $langs, $limit, array $include, $route ) {
		global $wpdb;

		$langs = array_values( $langs );
		$where = self::postTypeClause( $include ) . self::routeClause( $route );

		$rows = (array) $wpdb->get_results(
			$wpdb->prepare(
				"SELECT posts.ID, posts.post_type
			FROM {$wpdb->posts} posts
			INNER JOIN {$wpdb->prefix}icl_translations t
				ON t.element_id = posts.ID AND t.element_type = CONCAT('post_', posts.post_type)
			WHERE t.language_code IN (" . implode( ',', array_fill( 0, count( $langs ), '%s' ) ) . "){$where}
			LIMIT %d",
				array_merge( $langs, array( (int) $limit ) )
			)
		);

		return $rows;
	}

	private static function removeOnePost( $id, $postType, $route ) {
		$keepFile = self::MEDIA_POST_TYPE === $postType;

		if ( $keepFile ) {
			add_filter( 'wp_delete_file', '__return_false', PHP_INT_MAX );
		}

		try {
			return self::ROUTE_TRASH === $route
				? (bool) \wp_trash_post( $id )
				: (bool) \wp_delete_post( $id, true );
		} finally {
			if ( $keepFile ) {
				remove_filter( 'wp_delete_file', '__return_false', PHP_INT_MAX );
			}
		}
	}

	private static function postTypeClause( $include ) {
		if ( null === $include ) {
			return '';
		}

		$clauses = array();

		if ( ! empty( $include[ self::TYPE_POSTS ] ) ) {
			$clauses[] = "posts.post_type NOT IN ('" . implode( "','", self::$ownGroupPostTypes ) . "')";
		}
		if ( ! empty( $include[ self::TYPE_PRODUCTS ] ) ) {
			$clauses[] = "posts.post_type IN ('" . implode( "','", self::$productPostTypes ) . "')";
		}
		if ( ! empty( $include[ self::TYPE_MEDIA ] ) ) {
			$clauses[] = "posts.post_type = '" . self::MEDIA_POST_TYPE . "'";
		}
		if ( ! empty( $include[ self::TYPE_MENUS ] ) ) {
			$clauses[] = "posts.post_type = '" . self::MENU_ITEM_POST_TYPE . "'";
		}

		if ( ! $clauses ) {
			return ' AND 0 = 1';
		}

		return ' AND ( ' . implode( ' OR ', $clauses ) . ' )';
	}

	private static function taxonomyClause( $include, $alias ) {
		if ( null === $include ) {
			return '';
		}

		$clauses = array();

		if ( ! empty( $include[ self::TYPE_TERMS ] ) ) {
			$clauses[] = "{$alias}.taxonomy <> '" . self::MENU_TAXONOMY . "'";
		}
		if ( ! empty( $include[ self::TYPE_MENUS ] ) ) {
			$clauses[] = "{$alias}.taxonomy = '" . self::MENU_TAXONOMY . "'";
		}

		if ( ! $clauses ) {
			return ' AND 0 = 1';
		}

		return ' AND ( ' . implode( ' OR ', $clauses ) . ' )';
	}

	private static function routeClause( $route ) {
		return self::ROUTE_TRASH === $route ? " AND posts.post_status <> 'trash'" : '';
	}

	private static function postTypeLabel( $postType, $count = 2 ) {
		$obj = get_post_type_object( $postType );

		return self::labelFor( $obj, $postType, $count );
	}

	private static function taxonomyLabel( $taxonomy, $count = 2 ) {
		$obj = get_taxonomy( $taxonomy );

		return self::labelFor( $obj, $taxonomy, $count );
	}

	private static function labelFor( $obj, $fallback, $count ) {
		if ( 1 === (int) $count && $obj && ! empty( $obj->labels->singular_name ) ) {
			return (string) $obj->labels->singular_name;
		}
		if ( $obj && ! empty( $obj->labels->name ) ) {
			return (string) $obj->labels->name;
		}

		return $fallback;
	}
}
