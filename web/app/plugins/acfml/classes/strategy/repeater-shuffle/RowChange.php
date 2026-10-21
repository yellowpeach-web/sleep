<?php

namespace ACFML\Repeater\Shuffle;

class RowChange {

	const NONE = 'none';

	const MOVE = 'move';

	const INSERT = 'insert';

	const REMOVE = 'remove';

	const AMBIGUOUS = 'ambiguous';

	private $kind;

	private $map;

	private function __construct( $kind, array $map ) {
		$this->kind = $kind;
		$this->map  = $map;
	}

	public static function between( array $before, array $after ) {
		$map = self::matchRows( $before, $after );

		$added   = count( array_filter( $map, 'is_null' ) );
		$kept    = count( $map ) - $added;
		$removed = count( $before ) - $kept;

		if ( $added && $removed ) {
			return new self( self::AMBIGUOUS, [] );
		}

		if ( $added ) {
			return new self( self::INSERT, $map );
		}

		if ( $removed ) {
			return new self( self::REMOVE, $map );
		}

		return new self( self::isInOrder( $map ) ? self::NONE : self::MOVE, $map );
	}

	private static function matchRows( array $before, array $after ) {
		$unclaimed = $before;
		$map       = [];

		foreach ( $after as $newIndex => $hash ) {
			$oldIndex = array_search( $hash, $unclaimed, true );

			if ( false === $oldIndex ) {
				$map[ $newIndex ] = null;
				continue;
			}

			$map[ $newIndex ] = (int) $oldIndex;
			unset( $unclaimed[ $oldIndex ] );
		}

		return $map;
	}

	private static function isInOrder( array $map ) {
		foreach ( $map as $newIndex => $oldIndex ) {
			if ( $newIndex !== $oldIndex ) {
				return false;
			}
		}

		return true;
	}

	public function getKind() {
		return $this->kind;
	}

	public function getMap() {
		return $this->map;
	}

	public function movesRows() {
		return in_array( $this->kind, [ self::MOVE, self::REMOVE ], true );
	}

	public function needsTranslating() {
		return in_array( $this->kind, [ self::INSERT, self::AMBIGUOUS ], true );
	}

	public function outdatesJob() {
		return self::NONE !== $this->kind;
	}
}
