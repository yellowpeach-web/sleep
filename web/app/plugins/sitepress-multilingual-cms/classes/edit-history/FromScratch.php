<?php

namespace WPML\EditHistory;

class FromScratch {

	const SOURCE = 'from-scratch';
	const EDITOR = 'send';

	private $log;

	private $store;

	private $now;

	public function __construct( EntryLog $log, StateStore $store, $now = null ) {
		$this->log   = $log;
		$this->store = $store;
		$this->now   = $now ?? 'time';
	}

	public function mark( Subject $translation, string $language, int $originalId, State $state ): ?int {
		$headId = $state->headEntryId();
		foreach ( $this->log->since( $translation, $language, $headId - 1 ) as $entry ) {
			if ( (int) $entry->id === $headId && self::SOURCE === $entry->source ) {
				return null;
			}
		}

		$head    = (array) $state->head();
		$units   = (array) ( $head['units'] ?? [] );
		$entryId = $this->log->append(
			new Entry( $translation, $language, ( $this->now )(), self::SOURCE, self::EDITOR, null, [ 'units' => UnitList::encode( $units, $units ) ] )
		);
		$this->store->saveHead( $translation, $language, $originalId, $entryId, $head, $state->headHash() );

		return $entryId;
	}
}
