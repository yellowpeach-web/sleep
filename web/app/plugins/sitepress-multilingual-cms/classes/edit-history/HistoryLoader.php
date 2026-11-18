<?php

namespace WPML\EditHistory;

class HistoryLoader {

	private $log;

	private $store;

	public function __construct( EntryLog $log, StateStore $store ) {
		$this->log   = $log;
		$this->store = $store;
	}

	public function read( Subject $translation, string $language, int $originalId, Subject $original ): array {
		$state = $this->store->get( $translation );
		if ( ! $state ) {
			return [ null, 'nothing is recorded for this translation' ];
		}
		if ( ! $state->belongsTo( $language, $originalId ) ) {
			return [ null, 'the recorded history belongs to another language or original' ];
		}

		$start = $state->replayStart();
		$lists = self::lists( $start['lists'] );

		$entries = $this->replay( $translation, $language, $start['entry_id'], $state->headEntryId(), $lists );
		if ( null === $entries ) {
			return [ null, 'a stored entry does not replay over the one before it' ];
		}
		if ( $entries && ! self::isHead( $entries[ count( $entries ) - 1 ], $state->head() ) ) {
			return [ null, 'the replayed entries do not end at the head' ];
		}

		$base = $lists;
		if ( $start['entry_id'] > $state->baseEntryId() ) {
			$stored = self::lists( (array) $state->base() );
			if ( State::BASE_APPLIED === $state->baseState() || [] !== $stored['units'] ) {
				$base = $stored;
				array_unshift( $entries, self::entry( $start['entry_id'], (int) $state->baseAppliedAt(), Recorder::SOURCE_CLIENT, $lists ) );
			}
		}
		if ( State::BASE_APPLIED !== $state->baseState() && [] === $base['units'] ) {
			if ( ! $entries ) {
				return [ null, 'nothing is recorded for this translation' ];
			}
			$base = self::lists( $entries[0] );
		}

		$originalState = $this->store->get( $original );

		return [
			[
				'last_applied' => self::lastApplied( $state ),
				'base'         => $base,
				'entries'      => $entries,
				'stamps'       => $originalState ? $originalState->stamps() : [],
			],
			null,
		];
	}

	private function replay( Subject $translation, string $language, int $startId, int $headId, array $lists ) {
		$entries = [];
		foreach ( $this->log->since( $translation, $language, $startId ) as $entry ) {
			if ( $entry->id > $headId ) {
				break;
			}

			$units    = UnitList::decode( $entry->lists['units'] ?? [], $lists['units'] );
			$original = isset( $entry->lists['original'] ) ? UnitList::decode( $entry->lists['original'], $lists['original'] ) : $lists['original'];
			if ( null === $units || null === $original ) {
				return null;
			}

			$lists     = [
				'units'    => $units,
				'original' => $original,
			];
			$entries[] = self::entry( (int) $entry->id, $entry->time, $entry->source, $lists );
		}

		return $entries;
	}

	private static function isHead( array $entry, $head ): bool {
		return null !== $head
			&& UnitList::hash( $entry['units'] ) === UnitList::hash( $head['units'] ?? [] )
			&& UnitList::hash( $entry['original'] ) === UnitList::hash( $head['original'] ?? [] );
	}

	private static function lastApplied( State $state ) {
		if ( State::BASE_APPLIED !== $state->baseState() ) {
			return State::BASE_UNKNOWN === $state->baseState() ? State::BASE_UNKNOWN : State::BASE_NONE;
		}

		$jobId = $state->baseAteJobId();
		if ( null === $jobId ) {
			return State::BASE_UNKNOWN;
		}

		$deliveryId = $state->baseDeliveryId();

		return [ 'job_id' => $jobId ] + ( null === $deliveryId ? [] : [ 'delivery_id' => $deliveryId ] );
	}

	private static function entry( int $id, int $time, string $source, array $lists ): array {
		return [
			'id'     => $id,
			'time'   => $time,
			'source' => $source,
		] + $lists;
	}

	private static function lists( array $lists ): array {
		return [
			'units'    => $lists['units'] ?? [],
			'original' => $lists['original'] ?? [],
		];
	}
}
