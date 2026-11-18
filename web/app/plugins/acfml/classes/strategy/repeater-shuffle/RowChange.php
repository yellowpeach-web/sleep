<?php

namespace ACFML\Repeater\Shuffle;

class RowChange {

	const NONE = 'none';

	const MOVE = 'move';

	const INSERT = 'insert';

	const REMOVE = 'remove';

	const EDIT = 'edit';

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
			return self::editsInPlace( $before, $after )
				? new self( self::EDIT, self::inPlace( $after ) )
				: new self( self::AMBIGUOUS, [] );
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

	private static function editsInPlace( array $before, array $after ) {
		$before = array_values( $before );
		$after  = array_values( $after );

		if ( count( $before ) !== count( $after ) ) {
			return false;
		}

		$changedBefore = [];
		$changedAfter  = [];

		foreach ( $after as $index => $hash ) {
			if ( $before[ $index ] !== $hash ) {
				$changedBefore[] = $before[ $index ];
				$changedAfter[]  = $hash;
			}
		}

		return ! array_intersect( $changedAfter, $changedBefore );
	}

	private static function inPlace( array $after ) {
		$indexes = array_keys( array_values( $after ) );

		return array_combine( $indexes, $indexes );
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
		return in_array( $this->kind, [ self::INSERT, self::EDIT, self::AMBIGUOUS ], true );
	}

	public function editsRowsInPlace() {
		return self::EDIT === $this->kind;
	}

	public function outdatesJob() {
		return self::NONE !== $this->kind;
	}
}
