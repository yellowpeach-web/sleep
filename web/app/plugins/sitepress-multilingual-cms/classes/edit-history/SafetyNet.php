<?php

namespace WPML\EditHistory;

class SafetyNet {

	const SOURCE = 'unseen';
	const EDITOR = 'unseen';

	private $log;

	private $store;

	private $normaliser;

	private $now;

	public function __construct( EntryLog $log, StateStore $store, Normaliser $normaliser, $now = null ) {
		$this->log        = $log;
		$this->store      = $store;
		$this->normaliser = $normaliser;
		$this->now        = $now ?? 'time';
	}

	public function check( Subject $translation, string $language, int $originalId, array $units ) {
		$state = $this->store->get( $translation );
		if ( ! $state || ! $state->isHeadOf( $language, $originalId ) ) {
			return null;
		}

		$hash = $this->normaliser->translationHash( $units );
		if ( $hash === $state->headHash() ) {
			return null;
		}

		$head    = $state->head();
		$entryId = $this->log->append(
			new Entry(
				$translation,
				$language,
				( $this->now )(),
				self::SOURCE,
				self::EDITOR,
				null,
				[ 'units' => UnitList::encode( $head['units'] ?? [], $units ) ]
			)
		);

		$this->store->saveHead(
			$translation,
			$language,
			$originalId,
			$entryId,
			[
				'units'    => $units,
				'original' => $head['original'] ?? [],
			],
			$hash
		);

		return $entryId;
	}
}
