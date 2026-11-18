<?php

namespace WPML\EditHistory;

class RebuildGuard {

	const SKIP    = 'skip';
	const OVERLAY = 'overlay';

	const OVERWRITES = [
		InternalWrite::ATE_DELIVERY,
		InternalWrite::XLIFF_IMPORT,
		InternalWrite::SERVICE,
		InternalWrite::CLASSIC_EDITOR,
		InternalWrite::COPY,
		InternalWrite::PUBLIC_API,
	];

	public static $shape = self::SKIP;

	private $store;

	private $log;

	public function __construct( StateStore $store, EntryLog $log ) {
		$this->store = $store;
		$this->log   = $log;
	}

	public function holds( Subject $translation, string $language, int $originalId ) {
		if ( in_array( InternalWrite::writer(), self::OVERWRITES, true ) ) {
			return null;
		}

		$state = $this->store->get( $translation );
		if ( ! $state || ! $state->belongsTo( $language, $originalId ) ) {
			return null;
		}

		$uncarried = $state->isHeadOf( $language, $originalId ) && $state->headEntryId() > $state->baseEntryId();

		return $uncarried || $state->deletedElements() ? $state : null;
	}

	public function overlays(): bool {
		return self::OVERLAY === self::$shape && InternalWrite::AUTO_RESAVE === InternalWrite::writer();
	}

	public function headTime( Subject $translation, string $language, State $state ) {
		$entryId = $state->headEntryId();
		if ( $entryId <= $state->baseEntryId() ) {
			return null;
		}

		foreach ( $this->log->since( $translation, $language, $entryId - 1 ) as $entry ) {
			if ( $entry->id === $entryId ) {
				return $entry->time;
			}
		}

		return null;
	}
}
