<?php

namespace WPML\Posts;

class ListScreenRows {

	public static function arePartial( array $rows ) {
		$first = reset( $rows );

		return is_object( $first ) && ! isset( $first->post_type ) && isset( $first->ID, $first->post_parent );
	}

	public static function of( array $rows ) {
		if ( ! self::arePartial( $rows ) ) {
			return $rows;
		}

		global $wp_list_table, $per_page, $wp_query;

		$query = is_object( $wp_query ) ? $wp_query : null;

		if ( is_object( $wp_list_table ) && method_exists( $wp_list_table, 'get_pagenum' ) ) {
			$pagenum = (int) $wp_list_table->get_pagenum();
		} else {
			$pagenum = $query && method_exists( $query, 'get' ) ? (int) $query->get( 'paged' ) : 1;
		}

		$rows_per_page = (int) $per_page > 0 ? (int) $per_page : 20;

		$searching = $query && isset( $query->query ) && is_array( $query->query ) && ! empty( $query->query['s'] );

		$ids = self::screenIds( $rows, max( 1, $pagenum ), $rows_per_page, $searching );

		return array_values( array_filter( array_map( 'get_post', $ids ) ) );
	}

	private static function screenIds( array $rows, $pagenum, $per_page, $searching ) {
		$start = ( $pagenum - 1 ) * $per_page;
		$end   = $start + $per_page;

		if ( $searching ) {
			$ids = array();
			foreach ( array_slice( $rows, $start, $per_page ) as $row ) {
				$ids[] = (int) $row->ID;
			}

			return $ids;
		}

		$parents  = array();
		$top      = array();
		$children = array();
		foreach ( $rows as $row ) {
			$id     = (int) $row->ID;
			$parent = (int) $row->post_parent;
			if ( $parent === $id ) {
				$parent = 0;
			}

			$parents[ $id ] = $parent;
			if ( $parent > 0 ) {
				$children[ $parent ][] = $id;
			} else {
				$top[] = $id;
			}
		}

		$count = 0;
		$ids   = array();
		foreach ( $top as $id ) {
			if ( $count >= $end ) {
				break;
			}
			if ( $count >= $start ) {
				$ids[ $id ] = true;
			}
			++$count;
			self::walkChildren( $children, $parents, $id, $start, $end, $count, $ids );
		}

		foreach ( $children as $orphans ) {
			foreach ( $orphans as $id ) {
				if ( $count >= $end ) {
					break 2;
				}
				if ( $count >= $start ) {
					$ids[ $id ] = true;
				}
				++$count;
			}
		}

		return array_keys( $ids );
	}

	private static function walkChildren( array &$children, array $parents, $parent_id, $start, $end, &$count, array &$ids ) {
		if ( ! isset( $children[ $parent_id ] ) ) {
			return;
		}

		foreach ( $children[ $parent_id ] as $id ) {
			if ( $count >= $end ) {
				break;
			}

			if ( $count === $start ) {
				$ancestor = $parents[ $id ];
				while ( $ancestor > 0 && ! isset( $ids[ $ancestor ] ) ) {
					$ids[ $ancestor ] = true;
					$ancestor         = isset( $parents[ $ancestor ] ) ? $parents[ $ancestor ] : 0;
				}
			}

			if ( $count >= $start ) {
				$ids[ $id ] = true;
			}
			++$count;
			self::walkChildren( $children, $parents, $id, $start, $end, $count, $ids );
		}

		unset( $children[ $parent_id ] );
	}
}
