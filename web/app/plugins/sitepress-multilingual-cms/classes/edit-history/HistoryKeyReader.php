<?php

namespace WPML\EditHistory;

class HistoryKeyReader {

	private $texts;

	private $fileText;

	private $error;

	private function __construct( array $texts, $fileText ) {
		$this->texts    = $texts;
		$this->fileText = $fileText;
	}

	public static function read( array $key, $fileText = null ): array {
		$reader = new self( (array) ( $key['texts'] ?? [] ), $fileText );
		$lists  = $reader->lists( $key );

		return null === $reader->error ? [ $lists, null ] : [ null, $reader->error ];
	}

	private function lists( array $key ): array {
		$base = [
			'units'    => $this->list( $key['base']['units'] ?? null, null, '/base/units' ),
			'original' => $this->list( $key['base']['original'] ?? null, null, '/base/original' ),
		];

		$units    = $base['units'];
		$original = $base['original'];
		$entries  = [];
		$newestId = 0;
		foreach ( (array) ( $key['entries'] ?? [] ) as $index => $entry ) {
			$where = '/entries/' . $index;
			$id    = (int) ( $entry['id'] ?? 0 );
			if ( $id <= $newestId ) {
				return $this->fail( $where . '/id: the entry ids do not increase' );
			}

			$newestId = $id;
			$units    = $this->list( $entry['units'] ?? null, $units, $where . '/units' );
			if ( isset( $entry['original'] ) ) {
				$original = $this->list( $entry['original'], $original, $where . '/original' );
			}
			$entries[] = [
				'id'       => $id,
				'units'    => $units,
				'original' => $original,
			];
		}

		if ( ( $key['watermark'] ?? null ) !== $newestId ) {
			return $this->fail( '/watermark: is not the id of the newest entry' );
		}

		return [
			'base'    => $base,
			'entries' => $entries,
		];
	}

	private function list( $items, $previous, string $where ): array {
		if ( ! is_array( $items ) ) {
			return $this->fail( $where . ': is not a list of items' );
		}

		$units = [];
		foreach ( $items as $index => $item ) {
			if ( ! isset( $item['span'] ) ) {
				$units[] = $this->item( (array) $item, $where . '/' . $index );
				continue;
			}

			[ $from, $count ] = array_map( 'intval', (array) $item['span'] ) + [ 0, 0 ];
			if ( null === $previous || $from < 0 || $count < 1 || $from + $count > count( $previous ) ) {
				return $this->fail( $where . '/' . $index . ': the span leaves the previous list' );
			}
			array_push( $units, ...array_slice( $previous, $from, $count ) );
		}

		$keys = array_column( $units, 'k' );

		return count( $keys ) === count( array_unique( $keys ) ) ? $units : $this->fail( $where . ': holds a key twice' );
	}

	private function item( array $item, string $where ): array {
		if ( array_key_exists( 't', $item ) ) {
			if ( ! is_int( $item['t'] ) || ! array_key_exists( $item['t'], $this->texts ) ) {
				return $this->fail( $where . '/t: names no row of texts' );
			}
			$text = (string) $this->texts[ $item['t'] ];
		} else {
			$transUnit = $item['u'] ?? $item['k'] ?? null;
			$text      = $this->fileText && null !== $transUnit && isset( $item['s'] ) ? ( $this->fileText )( (string) $transUnit, (string) $item['s'] ) : null;
			if ( null === $text || '' === $text ) {
				return $this->fail( $where . ': names no non-empty text of a trans-unit of the file' );
			}
		}

		return ( isset( $item['k'] ) ? [ 'k' => (string) $item['k'] ] : [] ) + [ 'x' => (string) $text ];
	}

	private function fail( string $error ): array {
		$this->error = $this->error ?? $error;

		return [];
	}
}
