<?php

namespace WPML\EditHistory;

class Cap {

	const MAX_ENTRIES = 200;
	const MAX_BYTES   = 1048576;

	const SLACK = 0.9;

	private $log;

	private $store;

	private $maxEntries;

	private $maxBytes;

	public function __construct( EntryLog $log, StateStore $store, int $maxEntries = self::MAX_ENTRIES, int $maxBytes = self::MAX_BYTES ) {
		$this->log        = $log;
		$this->store      = $store;
		$this->maxEntries = $maxEntries;
		$this->maxBytes   = $maxBytes;
	}

	public function apply( Subject $translation, string $language ): int {
		$size = $this->log->size( $translation );
		if ( ! $this->isOver( $size['count'], $size['bytes'] ) ) {
			return 0;
		}

		$state = $this->store->get( $translation );
		if ( ! $state ) {
			return 0;
		}

		$start    = $state->replayStart();
		$limit    = self::watermarkLimit( $state->sends(), $start['entry_id'] );
		$units    = $start['lists']['units'] ?? [];
		$original = $start['lists']['original'] ?? [];
		$entries  = $this->log->since( $translation, $language, $start['entry_id'] );
		array_pop( $entries );

		$moved = 0;
		$floor = null;
		foreach ( $entries as $entry ) {
			if ( $entry->id > $limit || Recorder::SOURCE_CLIENT !== $entry->source ) {
				break;
			}

			$nextUnits    = UnitList::decode( $entry->lists['units'] ?? [], $units );
			$nextOriginal = isset( $entry->lists['original'] ) ? UnitList::decode( $entry->lists['original'], $original ) : $original;
			if (
				null === $nextUnits
				|| null === $nextOriginal
				|| self::changesOriginal( $original, $nextOriginal )
				|| self::removesOrEmpties( $units, $nextUnits )
			) {
				break;
			}

			$units    = $nextUnits;
			$original = $nextOriginal;
			$floor    = $entry->id;
			$moved++;
			$size['count']--;
			$size['bytes'] -= strlen( Codec::encode( $entry->lists ) );

			if ( ! $this->isOver( $size['count'], $size['bytes'], self::SLACK ) ) {
				break;
			}
		}

		if ( null !== $floor ) {
			$this->store->saveFloor(
				$translation,
				$floor,
				[
					'units'    => $units,
					'original' => $original,
				]
			);
			$this->log->deleteUpTo( $translation, $floor );
		}

		return $moved;
	}

	private function isOver( int $count, int $bytes, float $share = 1.0 ): bool {
		return $count > $this->maxEntries * $share || $bytes > $this->maxBytes * $share;
	}

	private static function watermarkLimit( array $sends, int $startId ): int {
		$limit = PHP_INT_MAX;
		foreach ( $sends as $send ) {
			$watermark = (int) ( $send['watermark'] ?? 0 );
			if ( $watermark > $startId ) {
				$limit = min( $limit, $watermark );
			}
		}

		return $limit;
	}

	private static function changesOriginal( array $before, array $after ): bool {
		return [] !== $before && UnitList::hash( $before ) !== UnitList::hash( $after );
	}

	private static function removesOrEmpties( array $before, array $after ): bool {
		$keyedAfter = [];
		foreach ( $after as $unit ) {
			if ( isset( $unit['k'] ) ) {
				$keyedAfter[ (string) $unit['k'] ] = (string) ( $unit['x'] ?? '' );
			}
		}

		foreach ( $before as $unit ) {
			if ( ! isset( $unit['k'] ) ) {
				continue;
			}
			$key = (string) $unit['k'];
			if ( ! array_key_exists( $key, $keyedAfter ) || ( '' !== (string) ( $unit['x'] ?? '' ) && '' === $keyedAfter[ $key ] ) ) {
				return true;
			}
		}

		[ $blocksBefore, $withTextBefore ] = self::blockCounts( $before );
		[ $blocksAfter, $withTextAfter ]   = self::blockCounts( $after );

		return $blocksAfter < $blocksBefore || $withTextAfter < $withTextBefore;
	}

	private static function blockCounts( array $units ): array {
		$blocks   = 0;
		$withText = 0;
		foreach ( $units as $unit ) {
			if ( ! isset( $unit['k'] ) ) {
				$blocks++;
				if ( '' !== (string) ( $unit['x'] ?? '' ) ) {
					$withText++;
				}
			}
		}

		return [ $blocks, $withText ];
	}
}
