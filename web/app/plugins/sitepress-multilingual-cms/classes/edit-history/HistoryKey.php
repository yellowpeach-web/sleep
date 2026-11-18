<?php

namespace WPML\EditHistory;

class HistoryKey {

	const VERSION = 2;

	const BUDGET = 524288;

	const SCHEMA = __DIR__ . '/wpml_edit_history.v2.schema.json';

	private $normaliser;

	private $schema;

	public function __construct( Normaliser $normaliser, SchemaValidator $schema ) {
		$this->normaliser = $normaliser;
		$this->schema     = $schema;
	}

	public static function create(): self {
		return new self( Normaliser::create(), SchemaValidator::fromFile( self::SCHEMA ) );
	}

	public function build( array $history, array $job ): KeyResult {
		try {
			return $this->write( $history, $job );
		} catch ( \Throwable $error ) {
			return KeyResult::none( 'the key could not be built: ' . $error->getMessage() );
		}
	}

	private function write( array $history, array $job ): KeyResult {
		$inJob     = array_fill_keys( array_map( 'strval', $job['trans_units'] ?? [] ), true );
		$leftOut   = self::leftOut( $job['original'] ?? [], $inJob );
		$reconcile = fn( array $units ) => self::reconcile( $units, $leftOut );

		$base = [
			'units'    => $reconcile( $history['base']['units'] ?? [] ),
			'original' => $reconcile( $history['base']['original'] ?? [] ),
		];

		$entries  = [];
		$original = $history['base']['original'] ?? [];
		foreach ( $history['entries'] ?? [] as $entry ) {
			$original  = $entry['original'] ?? $original;
			$entries[] = [
				'id'       => (int) $entry['id'],
				'time'     => (int) $entry['time'],
				'source'   => (string) $entry['source'],
				'units'    => $reconcile( $entry['units'] ?? [] ),
				'original' => $reconcile( $original ),
			];
		}

		$frame = [
			'last_applied'   => self::lastApplied( $history['last_applied'] ?? State::BASE_UNKNOWN ),
			'dropped_job'    => self::droppedJob( $history['dropped_job'] ?? null ),
			'original_times' => $this->originalTimes( $history['stamps'] ?? [], $job, $inJob ),
		];

		$key = $this->fit( $frame, $base, $entries, (int) ( $job['budget'] ?? self::BUDGET ) );
		if ( is_string( $key ) ) {
			return KeyResult::none( $key );
		}

		$error = $this->check( $key );

		return null === $error ? KeyResult::key( $key ) : KeyResult::none( $error );
	}

	private function fit( array $frame, array $base, array $entries, int $budget ) {
		$key  = $this->key( $frame, $base, $entries, 0 );
		$size = self::size( $key );
		if ( null === $size ) {
			return 'the key does not encode as JSON';
		}
		if ( $size <= $budget ) {
			return $key;
		}

		$fitting = null;
		$low     = 1;
		$high    = count( $entries ) - 1;
		while ( $low <= $high ) {
			$cut       = intdiv( $low + $high, 2 );
			$candidate = $this->key( $frame, $base, $entries, $cut );
			if ( ( self::size( $candidate ) ?? PHP_INT_MAX ) <= $budget ) {
				$fitting = $candidate;
				$high    = $cut - 1;
			} else {
				$low = $cut + 1;
			}
		}

		return $fitting ?? sprintf( 'the key is %d bytes, over the budget of %d bytes even with its newest entry alone', $size, $budget );
	}

	private function key( array $frame, array $base, array $entries, int $cut ): array {
		$rows      = [];
		$baseItems = [
			'units'    => self::items( $base['units'], $rows ),
			'original' => self::items( $base['original'], $rows ),
		];

		$units    = $base['units'];
		$original = $base['original'];
		$carried  = [];
		foreach ( array_slice( $entries, $cut ) as $entry ) {
			$item = [
				'id'     => $entry['id'],
				'time'   => $entry['time'],
				'source' => $entry['source'],
				'units'  => self::items( UnitList::encode( $units, $entry['units'] ), $rows ),
			];
			if ( UnitList::hash( $original ) !== UnitList::hash( $entry['original'] ) ) {
				$item['original'] = self::items( UnitList::encode( $original, $entry['original'] ), $rows );
				$original         = $entry['original'];
			}

			$units     = $entry['units'];
			$carried[] = $item;
		}

		$key = [
			'version'      => self::VERSION,
			'last_applied' => $frame['last_applied'],
		];
		if ( $frame['dropped_job'] ) {
			$key['dropped_job'] = $frame['dropped_job'];
		}
		$key['watermark'] = $carried ? $carried[ count( $carried ) - 1 ]['id'] : 0;
		if ( $cut > 0 ) {
			$key['compacted'] = true;
		}

		return $key + [
			'texts'          => array_map( 'strval', array_keys( $rows ) ),
			'base'           => $baseItems,
			'entries'        => $carried,
			'original_times' => $frame['original_times'],
		];
	}

	private static function items( array $list, array &$rows ): array {
		$items = [];
		foreach ( $list as $unit ) {
			if ( isset( $unit['span'] ) ) {
				$items[] = $unit;
				continue;
			}

			$text = (string) ( $unit['x'] ?? '' );
			if ( ! isset( $rows[ $text ] ) ) {
				$rows[ $text ] = count( $rows );
			}
			$items[] = ( isset( $unit['k'] ) ? [ 'k' => (string) $unit['k'] ] : [] ) + [ 't' => $rows[ $text ] ];
		}

		return $items;
	}

	private static function reconcile( array $units, array $leftOut ): array {
		return array_values( array_filter( $units, fn( array $unit ) => ! isset( $unit['k'], $leftOut[ (string) $unit['k'] ] ) ) );
	}

	private static function leftOut( array $original, array $inJob ): array {
		$keys = [];
		foreach ( $original as $unit ) {
			if ( isset( $unit['k'] ) && ! isset( $inJob[ (string) $unit['k'] ] ) ) {
				$keys[ (string) $unit['k'] ] = true;
			}
		}

		return $keys;
	}

	private function originalTimes( array $stamps, array $job, array $inJob ): array {
		$times = [];
		foreach ( $this->stampedUnits( $job['original'] ?? [] ) as [ $stampKey, $block, $hash ] ) {
			$name = null === $block ? $stampKey : ( $job['block_units'][ $block ] ?? null );

			if ( null !== $name && isset( $inJob[ (string) $name ] ) ) {
				$times[ (string) $name ] = self::originalTime( $stamps[ $stampKey ] ?? null, $hash, null === $block ? 'field' : 'element', $job );
			}
		}

		return $times;
	}

	public function restamped( array $stamps, array $original, int $now ): array {
		foreach ( $this->stampedUnits( $original ) as [ $stampKey, , $hash ] ) {
			if ( is_array( $stamps[ $stampKey ] ?? null ) && ( $stamps[ $stampKey ][1] ?? null ) !== $hash ) {
				$stamps[ $stampKey ] = [ $now, $hash ];
			}
		}

		return $stamps;
	}

	private function stampedUnits( array $original ): array {
		$units  = [];
		$blocks = 0;
		foreach ( $this->normaliser->original( $original ) as $unit ) {
			$block   = isset( $unit['k'] ) ? null : $blocks++;
			$units[] = [ null === $block ? (string) $unit['k'] : '#' . $block, $block, md5( (string) $unit['x'] ) ];
		}

		return $units;
	}

	private static function originalTime( $stamp, string $hash, string $kind, array $job ): array {
		if ( is_array( $stamp ) ) {
			return ( $stamp[1] ?? null ) === $hash
				? [ 'kind' => $kind, 'time' => (int) $stamp[0] ]
				: [ 'kind' => 'hint', 'time' => (int) ( $job['now'] ?? 0 ) ];
		}

		return isset( $job['hint'] ) ? [ 'kind' => 'hint', 'time' => (int) $job['hint'] ] : [ 'kind' => 'unknown' ];
	}

	private static function lastApplied( $value ) {
		if ( ! is_array( $value ) ) {
			return (string) $value;
		}

		return [ 'job_id' => (int) ( $value['job_id'] ?? 0 ) ]
			+ ( isset( $value['delivery_id'] ) ? [ 'delivery_id' => (int) $value['delivery_id'] ] : [] );
	}

	private static function droppedJob( $value ) {
		if ( ! is_array( $value ) || (int) ( $value['job_id'] ?? 0 ) <= 0 || (int) ( $value['delivery_id'] ?? 0 ) <= 0 ) {
			return null;
		}

		return [
			'job_id'      => (int) $value['job_id'],
			'delivery_id' => (int) $value['delivery_id'],
		];
	}

	private function check( array $key ) {
		$json  = (string) wp_json_encode( $key );
		$error = $this->schema->validate( json_decode( $json ) );
		if ( null !== $error ) {
			return 'the key fails the version 2 schema at ' . $error;
		}

		[ , $error ] = HistoryKeyReader::read( (array) json_decode( $json, true ) );

		return null === $error ? null : 'the key does not read back at ' . $error;
	}

	private static function size( array $key ) {
		$json = wp_json_encode( $key );

		return false === $json ? null : strlen( $json );
	}
}
