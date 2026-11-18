<?php

namespace WPML\Collect\Support;

use ArrayAccess;
use ArrayIterator;
use CachingIterator;
use Closure;
use Countable;
use InvalidArgumentException;
use IteratorAggregate;
use JsonSerializable;
use Traversable;

if ( ! class_exists( 'WPML\\Collect\\Support\\HelperFunctions', false ) ) {
	require_once __DIR__ . '/../../HelperFunctions.php';
}
use WPML\Collect\Contracts\Support\Arrayable;
use WPML\Collect\Contracts\Support\Jsonable;
use WPML\Collect\Support\Traits\Macroable;

class Collection implements ArrayAccess, Arrayable, Countable, IteratorAggregate, Jsonable, JsonSerializable {

	use Macroable;

	protected $items = [];

	private static $releasedStaticCallbacks = [
		'WPML\\ST\\TranslationFile\\DomainsLocalesMapper::entityKey' => true,
	];

	public function __construct( $items = [] ) {
		$this->items = $this->getArrayableItems( $items );
	}

	public static function make( $items = [] ) {
		return new static( $items );
	}


	public static function isCallback( $value ) {
		if ( $value instanceof Closure ) {
			return true;
		}

		if ( is_object( $value ) ) {
			return method_exists( $value, '__invoke' );
		}

		if ( ! is_array( $value ) || count( $value ) !== 2 || ! array_key_exists( 0, $value ) || ! array_key_exists( 1, $value ) || ! is_string( $value[1] ) ) {
			return false;
		}

		if ( is_object( $value[0] ) ) {
			return is_callable( $value );
		}

		return is_string( $value[0] )
			&& isset( self::$releasedStaticCallbacks[ $value[0] . '::' . $value[1] ] )
			&& is_callable( $value );
	}

	protected function useAsCallable( $value ) {
		return static::isCallback( $value );
	}

	protected function valueRetriever( $value ) {
		if ( $this->useAsCallable( $value ) ) {
			return $value;
		}

		return function ( $item ) use ( $value ) {
			return HelperFunctions::dataGet( $item, $value );
		};
	}

	protected function operatorForWhere( $key, $operator, $value ) {
		return function ( $item ) use ( $key, $operator, $value ) {
			$found = HelperFunctions::dataGet( $item, $key );

			switch ( $operator ) {
				case '!=':
				case '<>':
					return $found != $value;
				case '<':
					return $found < $value;
				case '>':
					return $found > $value;
				case '<=':
					return $found <= $value;
				case '>=':
					return $found >= $value;
				case '===':
					return $found === $value;
				case '!==':
					return $found !== $value;
				default:
					return $found == $value;
			}
		};
	}

	protected function getArrayableItems( $items ) {
		if ( is_array( $items ) ) {
			return $items;
		}

		if ( $items instanceof self ) {
			return $items->all();
		}

		if ( $items instanceof Arrayable ) {
			return $items->toArray();
		}

		if ( $items instanceof Jsonable ) {
			return json_decode( $items->toJson(), true );
		}

		if ( $items instanceof JsonSerializable ) {
			return $items->jsonSerialize();
		}

		if ( $items instanceof Traversable ) {
			return iterator_to_array( $items );
		}

		return (array) $items;
	}


	public function all() {
		return $this->items;
	}

	public function get( $key, $default = null ) {
		if ( $this->offsetExists( $key ) ) {
			return $this->items[ $key ];
		}

		return HelperFunctions::value( $default );
	}

	public function has( $key ) {
		return $this->offsetExists( $key );
	}

	public function first( ?callable $callback = null, $default = null ) {
		return Arr::first( $this->items, $callback, $default );
	}

	public function last( ?callable $callback = null, $default = null ) {
		return Arr::last( $this->items, $callback, $default );
	}

	public function isEmpty() {
		return empty( $this->items );
	}

	public function isNotEmpty() {
		return ! empty( $this->items );
	}

	public function contains( $key, $value = null ) {
		if ( func_num_args() == 2 ) {
			return $this->contains(
				function ( $item ) use ( $key, $value ) {
					return HelperFunctions::dataGet( $item, $key ) == $value;
				}
			);
		}

		if ( $this->useAsCallable( $key ) ) {
			return ! is_null( $this->first( $key ) );
		}

		return in_array( $key, $this->items );
	}

	public function containsStrict( $key, $value = null ) {
		if ( func_num_args() == 2 ) {
			return $this->contains(
				function ( $item ) use ( $key, $value ) {
					return HelperFunctions::dataGet( $item, $key ) === $value;
				}
			);
		}

		if ( $this->useAsCallable( $key ) ) {
			return ! is_null( $this->first( $key ) );
		}

		return in_array( $key, $this->items, true );
	}

	public function search( $value, $strict = false ) {
		if ( ! $this->useAsCallable( $value ) ) {
			return array_search( $value, $this->items, $strict );
		}

		foreach ( $this->items as $key => $item ) {
			if ( call_user_func( $value, $item, $key ) ) {
				return $key;
			}
		}

		return false;
	}

	public function random( $amount = 1 ) {
		$count = $this->count();

		if ( $amount > $count ) {
			throw new InvalidArgumentException( "You requested {$amount} items, but there are only {$count} items in the collection" );
		}

		$keys = array_rand( $this->items, $amount );

		if ( $amount == 1 ) {
			return $this->items[ $keys ];
		}

		return new static( array_intersect_key( $this->items, array_flip( $keys ) ) );
	}

	public function implode( $value, $glue = null ) {
		$first = $this->first();

		if ( is_array( $first ) || is_object( $first ) ) {
			return implode( $glue, $this->pluck( $value )->all() );
		}

		return implode( $value, $this->items );
	}


	public function sum( $callback = null ) {
		if ( is_null( $callback ) ) {
			return array_sum( $this->items );
		}

		$read  = $this->valueRetriever( $callback );
		$total = 0;

		foreach ( $this->items as $item ) {
			$total = $total + $read( $item );
		}

		return $total;
	}

	public function avg( $callback = null ) {
		$count = $this->count();

		if ( $count ) {
			return $this->sum( $callback ) / $count;
		}
	}

	public function average( $callback = null ) {
		return $this->avg( $callback );
	}

	public function max( $callback = null ) {
		$read   = $this->valueRetriever( $callback );
		$result = null;

		foreach ( $this->items as $item ) {
			$value = $read( $item );

			if ( is_null( $result ) || $value > $result ) {
				$result = $value;
			}
		}

		return $result;
	}

	public function min( $callback = null ) {
		$read   = $this->valueRetriever( $callback );
		$result = null;

		foreach ( $this->items as $item ) {
			$value = $read( $item );

			if ( is_null( $result ) || $value < $result ) {
				$result = $value;
			}
		}

		return $result;
	}

	public function median( $key = null ) {
		$count = $this->count();

		if ( $count == 0 ) {
			return;
		}

		$source = isset( $key ) ? $this->pluck( $key ) : $this;
		$sorted = $source->sort()->values();
		$middle = (int) ( $count / 2 );

		if ( $count % 2 ) {
			return $sorted->get( $middle );
		}

		return ( new static( [ $sorted->get( $middle - 1 ), $sorted->get( $middle ) ] ) )->average();
	}

	public function mode( $key = null ) {
		if ( $this->count() == 0 ) {
			return;
		}

		$source = isset( $key ) ? $this->pluck( $key ) : $this;
		$counts = new self();

		foreach ( $source->all() as $value ) {
			$counts[ $value ] = isset( $counts[ $value ] ) ? $counts[ $value ] + 1 : 1;
		}

		$sorted  = $counts->sort();
		$highest = $sorted->last();

		return $sorted
			->filter(
				function ( $times ) use ( $highest ) {
					return $times == $highest;
				}
			)
			->sort()
			->keys()
			->all();
	}


	public function each( callable $callback ) {
		foreach ( $this->items as $key => $item ) {
			if ( $callback( $item, $key ) === false ) {
				break;
			}
		}

		return $this;
	}

	public function eachWithTimeout( callable $fn, $timeout ) {
		$endTime     = time() + $timeout;
		$unProcessed = new self();

		foreach ( $this->items as $item ) {
			if ( time() > $endTime ) {
				$unProcessed->push( $item );
			} else {
				$fn( $item );
			}
		}

		return $unProcessed;
	}

	public function pipe( callable $callback ) {
		return $callback( $this );
	}

	public function reduce( callable $callback, $initial = null ) {
		return array_reduce( $this->items, $callback, $initial );
	}


	public function map( callable $callback ) {
		$keys = array_keys( $this->items );

		return new static( array_combine( $keys, array_map( $callback, $this->items, $keys ) ) );
	}

	public function transform( callable $callback ) {
		$this->items = $this->map( $callback )->all();

		return $this;
	}

	public function flatMap( callable $callback ) {
		return $this->map( $callback )->collapse();
	}

	public function mapWithKeys( callable $callback ) {
		return $this->flatMap( $callback );
	}

	public function mapToDictionary( callable $callback ) {
		$dictionary = [];

		foreach ( $this->items as $key => $item ) {
			$pair     = $callback( $item, $key );
			$pairKey  = key( $pair );
			$pairItem = reset( $pair );

			if ( ! isset( $dictionary[ $pairKey ] ) ) {
				$dictionary[ $pairKey ] = [];
			}

			$dictionary[ $pairKey ][] = $pairItem;
		}

		return new static( $dictionary );
	}

	public function mapToGroups( callable $callback ) {
		return $this->mapToDictionary( $callback )->map(
			function ( $group ) {
				return static::make( $group );
			}
		);
	}

	public function pluck( $value, $key = null ) {
		return new static( Arr::pluck( $this->items, $value, $key ) );
	}

	public function assocToPair() {
		$pairs = [];

		foreach ( $this->items as $key => $item ) {
			$pairs[] = [ $key, $item ];
		}

		return new static( $pairs );
	}

	public function pairToAssoc() {
		return $this->mapWithKeys(
			function ( $pair ) {
				return [ $pair[0] => $pair[1] ];
			}
		);
	}

	public function keys() {
		return new static( array_keys( $this->items ) );
	}

	public function values() {
		return new static( array_values( $this->items ) );
	}

	public function flip() {
		return new static( array_flip( $this->items ) );
	}

	public function collapse() {
		return new static( Arr::collapse( $this->items ) );
	}

	public function flatten( $depth = INF ) {
		return new static( Arr::flatten( $this->items, $depth ) );
	}


	public function filter( ?callable $callback = null ) {
		if ( $callback ) {
			return new static( Arr::where( $this->items, $callback ) );
		}

		return new static( array_filter( $this->items ) );
	}

	public function reject( $callback ) {
		if ( $this->useAsCallable( $callback ) ) {
			return $this->filter(
				function ( $item, $key ) use ( $callback ) {
					return ! $callback( $item, $key );
				}
			);
		}

		return $this->filter(
			function ( $item ) use ( $callback ) {
				return $item != $callback;
			}
		);
	}

	public function where( $key, $operator, $value = null ) {
		if ( func_num_args() == 2 ) {
			$value    = $operator;
			$operator = '=';
		}

		return $this->filter( $this->operatorForWhere( $key, $operator, $value ) );
	}

	public function whereStrict( $key, $value ) {
		return $this->where( $key, '===', $value );
	}

	public function whereIn( $key, $values, $strict = false ) {
		$values = $this->getArrayableItems( $values );

		return $this->filter(
			function ( $item ) use ( $key, $values, $strict ) {
				return in_array( HelperFunctions::dataGet( $item, $key ), $values, $strict );
			}
		);
	}

	public function whereInStrict( $key, $values ) {
		return $this->whereIn( $key, $values, true );
	}

	public function unique( $key = null, $strict = false ) {
		if ( is_null( $key ) ) {
			return new static( array_unique( $this->items, SORT_REGULAR ) );
		}

		$read = $this->valueRetriever( $key );
		$seen = [];

		return $this->reject(
			function ( $item ) use ( $read, $strict, &$seen ) {
				$id = $read( $item );

				if ( in_array( $id, $seen, $strict ) ) {
					return true;
				}

				$seen[] = $id;
			}
		);
	}

	public function uniqueStrict( $key = null ) {
		return $this->unique( $key, true );
	}

	public function partition( $key, $operator = null, $value = null ) {
		$partitions = [ new static(), new static() ];

		$callback = func_num_args() === 1
			? $this->valueRetriever( $key )
			: $this->operatorForWhere( ...func_get_args() );

		foreach ( $this->items as $itemKey => $item ) {
			$partitions[ (int) ! $callback( $item, $itemKey ) ][ $itemKey ] = $item;
		}

		return new static( $partitions );
	}

	public function prioritize( callable $condition ) {
		$rest = $this->reject( $condition );

		return $this
			->filter( $condition )
			->toBase()
			->merge( $rest );
	}

	public function only( $keys ) {
		if ( is_null( $keys ) ) {
			return new static( $this->items );
		}

		$keys = is_array( $keys ) ? $keys : func_get_args();

		return new static( Arr::only( $this->items, $keys ) );
	}

	public function except( $keys ) {
		$keys = is_array( $keys ) ? $keys : func_get_args();

		return new static( Arr::except( $this->items, $keys ) );
	}

	public function every( $step, $offset = 0 ) {
		$picked   = [];
		$position = 0;

		foreach ( $this->items as $item ) {
			if ( $position % $step === $offset ) {
				$picked[] = $item;
			}

			$position++;
		}

		return new static( $picked );
	}


	public function diff( $items ) {
		return new static( array_diff( $this->items, $this->getArrayableItems( $items ) ) );
	}

	public function diffKeys( $items ) {
		return new static( array_diff_key( $this->items, $this->getArrayableItems( $items ) ) );
	}

	public function intersect( $items ) {
		return new static( array_intersect( $this->items, $this->getArrayableItems( $items ) ) );
	}

	public function merge( $items ) {
		return new static( array_merge( $this->items, $this->getArrayableItems( $items ) ) );
	}

	public function combine( $values ) {
		return new static( array_combine( $this->all(), $this->getArrayableItems( $values ) ) );
	}

	public function union( $items ) {
		return new static( $this->items + $this->getArrayableItems( $items ) );
	}

	public function crossJoin( ...$lists ) {
		$arrays = [];

		foreach ( $lists as $list ) {
			$arrays[] = $this->getArrayableItems( $list );
		}

		return new static( Arr::crossJoin( $this->items, ...$arrays ) );
	}

	public function zip( $items ) {
		$arrays = [ $this->items ];

		foreach ( func_get_args() as $list ) {
			$arrays[] = $this->getArrayableItems( $list );
		}

		$tuple = function () {
			return new static( func_get_args() );
		};

		return new static( array_map( $tuple, ...$arrays ) );
	}


	public function groupBy( $groupBy, $preserveKeys = false ) {
		$read   = $this->valueRetriever( $groupBy );
		$groups = [];

		foreach ( $this->items as $key => $item ) {
			$groupKeys = $read( $item, $key );

			if ( ! is_array( $groupKeys ) ) {
				$groupKeys = [ $groupKeys ];
			}

			foreach ( $groupKeys as $groupKey ) {
				if ( ! array_key_exists( $groupKey, $groups ) ) {
					$groups[ $groupKey ] = new static();
				}

				$groups[ $groupKey ]->offsetSet( $preserveKeys ? $key : null, $item );
			}
		}

		return new static( $groups );
	}

	public function keyBy( $keyBy ) {
		$read  = $this->valueRetriever( $keyBy );
		$keyed = [];

		foreach ( $this->items as $key => $item ) {
			$newKey = $read( $item, $key );

			if ( is_object( $newKey ) ) {
				$newKey = (string) $newKey;
			}

			$keyed[ $newKey ] = $item;
		}

		return new static( $keyed );
	}

	public function chunk( $size ) {
		$chunks = [];

		foreach ( array_chunk( $this->items, $size, true ) as $chunk ) {
			$chunks[] = new static( $chunk );
		}

		return new static( $chunks );
	}

	public function split( $numberOfGroups ) {
		if ( $this->isEmpty() ) {
			return new static();
		}

		return $this->chunk( ceil( $this->count() / $numberOfGroups ) );
	}


	public function sort( ?callable $callback = null ) {
		$items = $this->items;

		if ( $callback ) {
			uasort( $items, $callback );
		} else {
			asort( $items );
		}

		return new static( $items );
	}

	public function sortBy( $callback, $options = SORT_REGULAR, $descending = false ) {
		$read  = $this->valueRetriever( $callback );
		$order = [];

		foreach ( $this->items as $key => $item ) {
			$order[ $key ] = $read( $item, $key );
		}

		if ( $descending ) {
			arsort( $order, $options );
		} else {
			asort( $order, $options );
		}

		foreach ( array_keys( $order ) as $key ) {
			$order[ $key ] = $this->items[ $key ];
		}

		return new static( $order );
	}

	public function sortByDesc( $callback, $options = SORT_REGULAR ) {
		return $this->sortBy( $callback, $options, true );
	}

	public function reverse() {
		return new static( array_reverse( $this->items, true ) );
	}

	public function shuffle( $seed = null ) {
		$items = $this->items;

		if ( is_null( $seed ) ) {
			shuffle( $items );
		} else {
			srand( $seed );

			usort(
				$items,
				function () {
					return rand( -1, 1 );
				}
			);
		}

		return new static( $items );
	}

	public function slice( $offset, $length = null ) {
		return new static( array_slice( $this->items, $offset, $length, true ) );
	}

	public function take( $limit ) {
		if ( $limit < 0 ) {
			return $this->slice( $limit, abs( $limit ) );
		}

		return $this->slice( 0, $limit );
	}

	public function forPage( $page, $perPage ) {
		return $this->slice( ( $page - 1 ) * $perPage, $perPage );
	}


	public function push( $value ) {
		$this->offsetSet( null, $value );

		return $this;
	}

	public function put( $key, $value ) {
		$this->offsetSet( $key, $value );

		return $this;
	}

	public function prepend( $value, $key = null ) {
		$this->items = Arr::prepend( $this->items, $value, $key );

		return $this;
	}

	public function pop() {
		return array_pop( $this->items );
	}

	public function shift() {
		return array_shift( $this->items );
	}

	public function pull( $key, $default = null ) {
		return Arr::pull( $this->items, $key, $default );
	}

	public function forget( $keys ) {
		foreach ( (array) $keys as $key ) {
			$this->offsetUnset( $key );
		}

		return $this;
	}

	public function splice( $offset, $length = null, $replacement = [] ) {
		if ( func_num_args() == 1 ) {
			return new static( array_splice( $this->items, $offset ) );
		}

		return new static( array_splice( $this->items, $offset, $length, $replacement ) );
	}


	public function toArray() {
		$array = [];

		foreach ( $this->items as $key => $item ) {
			$array[ $key ] = $item instanceof Arrayable ? $item->toArray() : $item;
		}

		return $array;
	}

	#[\ReturnTypeWillChange]
	public function jsonSerialize() {
		$array = [];

		foreach ( $this->items as $key => $item ) {
			if ( $item instanceof JsonSerializable ) {
				$array[ $key ] = $item->jsonSerialize();
			} elseif ( $item instanceof Jsonable ) {
				$array[ $key ] = json_decode( $item->toJson(), true );
			} elseif ( $item instanceof Arrayable ) {
				$array[ $key ] = $item->toArray();
			} else {
				$array[ $key ] = $item;
			}
		}

		return $array;
	}

	public function toJson( $options = 0 ) {
		return json_encode( $this->jsonSerialize(), $options );
	}

	public function toBase() {
		return new self( $this );
	}

	public function __toString() {
		return $this->toJson();
	}


	#[\ReturnTypeWillChange]
	public function getIterator() {
		return new ArrayIterator( $this->items );
	}

	public function getCachingIterator( $flags = CachingIterator::CALL_TOSTRING ) {
		return new CachingIterator( $this->getIterator(), $flags );
	}

	#[\ReturnTypeWillChange]
	public function count() {
		return count( $this->items );
	}

	#[\ReturnTypeWillChange]
	public function offsetExists( $key ) {
		return null !== $key && array_key_exists( $key, $this->items );
	}

	#[\ReturnTypeWillChange]
	public function offsetGet( $key ) {
		return $this->items[ $key ];
	}

	#[\ReturnTypeWillChange]
	public function offsetSet( $key, $value ) {
		if ( is_null( $key ) ) {
			$this->items[] = $value;
		} else {
			$this->items[ $key ] = $value;
		}
	}

	#[\ReturnTypeWillChange]
	public function offsetUnset( $key ) {
		unset( $this->items[ $key ] );
	}
}
