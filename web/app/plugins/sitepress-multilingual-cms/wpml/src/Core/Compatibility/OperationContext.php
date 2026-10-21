<?php

namespace WPML\Core\Compatibility;

class OperationContext {

	const MAKE_DUPLICATES = 'make_duplicates';

	const SET_DUPLICATION = 'set_duplication';

	const SYNC_MENUS = 'sync_menus';

	private static $stack = [];

	public static function push( $operation ) {
		self::$stack[] = $operation;
	}

	public static function pop() {
		array_pop( self::$stack );
	}

	public static function is( $operation ) {
		return in_array( $operation, self::$stack, true );
	}

	public static function current() {
		$count = count( self::$stack );

		return $count > 0 ? self::$stack[ $count - 1 ] : null;
	}

	public static function within( $operation, callable $callback ) {
		self::push( $operation );
		try {
			return $callback();
		} finally {
			self::pop();
		}
	}

	public static function reset() {
		self::$stack = [];
	}
}
