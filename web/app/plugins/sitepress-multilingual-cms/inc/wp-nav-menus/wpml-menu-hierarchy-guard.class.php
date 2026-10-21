<?php

class WPML_Menu_Hierarchy_Guard {

	const PARENT_META_KEY = '_menu_item_menu_item_parent';

	const MAX_DEPTH = 1000;

	private static $reported = [];

	const LOOP_REASONS = [ 'cycle', 'too-deep', 'self-parent' ];

	private static $broken_items = [];

	public static function depth_from_meta( $item_id, $menu_id = 0 ) {
		$item_id = (int) $item_id;
		$depth   = 0;
		$visited = [ $item_id => true ];

		while ( true ) {
			$parent = (int) get_post_meta( $item_id, self::PARENT_META_KEY, true );

			if ( $parent <= 0 ) {
				return $depth;
			}

			if ( isset( $visited[ $parent ] ) ) {
				self::report( $menu_id, $item_id, 'cycle', $parent );

				return 0;
			}

			$visited[ $parent ] = true;
			$item_id            = $parent;
			$depth ++;

			if ( $depth > self::MAX_DEPTH ) {
				self::report( $menu_id, $item_id, 'too-deep', $parent );

				return 0;
			}
		}
	}

	public static function depth_in_item_set( $items, $item_id, $menu_id = 0 ) {
		$parents = self::index_parents( $items );
		$item_id = (int) $item_id;
		$depth   = 0;
		$visited = [ $item_id => true ];

		while ( true ) {
			if ( ! array_key_exists( $item_id, $parents ) ) {
				if ( $depth > 0 ) {
					self::report( $menu_id, $item_id, 'orphan', 0 );
				}

				return 0;
			}

			$parent = $parents[ $item_id ];

			if ( $parent <= 0 ) {
				return $depth;
			}

			if ( isset( $visited[ $parent ] ) ) {
				self::report( $menu_id, $item_id, 'cycle', $parent );

				return 0;
			}

			$visited[ $parent ] = true;
			$item_id            = $parent;
			$depth ++;

			if ( $depth > self::MAX_DEPTH ) {
				self::report( $menu_id, $item_id, 'too-deep', $parent );

				return 0;
			}
		}
	}

	public static function is_broken_parent_relation( $item_id, $parent_id, $menu_id = 0 ) {
		$item_id   = (int) $item_id;
		$parent_id = (int) $parent_id;

		if ( $parent_id <= 0 ) {
			return false;
		}

		if ( $parent_id === $item_id ) {
			self::report( $menu_id, $item_id, 'self-parent', $parent_id );

			return true;
		}

		if ( ! get_post( $parent_id ) ) {
			self::report( $menu_id, $item_id, 'orphan-parent', $parent_id );

			return true;
		}

		$visited = [ $item_id => true, $parent_id => true ];
		$cursor  = $parent_id;
		$depth   = 0;

		while ( true ) {
			$next = (int) get_post_meta( $cursor, self::PARENT_META_KEY, true );

			if ( $next <= 0 ) {
				return false;
			}

			if ( isset( $visited[ $next ] ) ) {
				self::report( $menu_id, $item_id, 'cycle', $next );

				return true;
			}

			$visited[ $next ] = true;
			$cursor           = $next;
			$depth ++;

			if ( $depth > self::MAX_DEPTH ) {
				self::report( $menu_id, $item_id, 'too-deep', $next );

				return true;
			}
		}
	}

	public static function get_broken_items( $menu_id = null ) {
		$items = [];

		if ( null !== $menu_id ) {
			$menu_id = (int) $menu_id;
			$items   = isset( self::$broken_items[ $menu_id ] )
				? array_keys( self::$broken_items[ $menu_id ] )
				: [];
		} else {
			$unique = [];

			foreach ( self::$broken_items as $menu_items ) {
				foreach ( array_keys( $menu_items ) as $item_id ) {
					$unique[ $item_id ] = true;
				}
			}

			$items = array_keys( $unique );
		}

		sort( $items, SORT_NUMERIC );

		return $items;
	}

	public static function reset_reported() {
		self::$reported     = [];
		self::$broken_items = [];
	}

	private static function index_parents( $items ) {
		$parents = [];

		if ( $items instanceof Traversable ) {
			$items = iterator_to_array( $items, false );
		}

		foreach ( (array) $items as $item ) {
			if ( is_object( $item ) ) {
				$item = (array) $item;
			}

			if ( ! is_array( $item ) || ! isset( $item['ID'] ) ) {
				continue;
			}

			$parents[ (int) $item['ID'] ] = isset( $item['parent'] ) ? (int) $item['parent'] : 0;
		}

		return $parents;
	}

	private static function report( $menu_id, $item_id, $reason, $parent_id ) {
		if ( in_array( $reason, self::LOOP_REASONS, true ) ) {
			self::$broken_items[ (int) $menu_id ][ (int) $item_id ] = true;
		}

		$key = $menu_id . ':' . $item_id . ':' . $reason;

		if ( isset( self::$reported[ $key ] ) ) {
			return;
		}

		self::$reported[ $key ] = true;

		\WPML\PHP\Logger\error(
			sprintf(
				'WPML menu sync: broken hierarchy (%s) - menu %d, item %d, parent %d. The item is treated as top level.',
				$reason,
				(int) $menu_id,
				(int) $item_id,
				(int) $parent_id
			)
		);
	}
}
