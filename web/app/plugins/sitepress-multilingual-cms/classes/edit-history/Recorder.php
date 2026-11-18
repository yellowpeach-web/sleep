<?php

namespace WPML\EditHistory;

class Recorder {

	const SOURCE_CLIENT = 'client';

	private $log;

	private $store;

	private $normaliser;

	private $now;

	private $cap;

	public function __construct( EntryLog $log, StateStore $store, Normaliser $normaliser, $now = null, ?Cap $cap = null ) {
		$this->log        = $log;
		$this->store      = $store;
		$this->normaliser = $normaliser;
		$this->now        = $now ?? 'time';
		$this->cap        = $cap;
	}

	public function needsBefore( Subject $translation, string $language, int $originalId ): bool {
		return ! $this->hasHead( $this->store->get( $translation ), $language, $originalId );
	}

	public function recordTranslation(
		Subject $translation,
		string $language,
		int $originalId,
		array $units,
		$before,
		callable $original,
		string $editor,
		$userId,
		$sameClick,
		string $source = self::SOURCE_CLIENT
	) {
		$state   = $this->store->get( $translation );
		$hasHead = $this->hasHead( $state, $language, $originalId );
		$hash    = $this->normaliser->translationHash( $units );

		if ( $hasHead ? $hash === $state->headHash() : ( null !== $before && $hash === $this->normaliser->translationHash( $before ) ) ) {
			return null;
		}

		$previous = $hasHead && $sameClick === $state->headEntryId() && ! $this->sent( $state, $sameClick )
			? $this->listsBefore( $state, $translation, $language, $sameClick )
			: null;
		$replaces = null !== $previous;

		$originalUnits = $original();
		$time          = ( $this->now )();

		if ( ! $replaces && $hasHead ) {
			$head     = $state->head();
			$previous = [
				'units'    => $head['units'] ?? [],
				'original' => $head['original'] ?? [],
			];
		} elseif ( ! $replaces ) {
			$previous = [
				'units'    => $before ?? [],
				'original' => null === $before ? [] : $originalUnits,
			];
			if ( null !== $before ) {
				$this->store->saveStart( $translation, $language, $originalId, $previous, $time );
			}
		}

		$lists = [ 'units' => UnitList::encode( $previous['units'], $units ) ];
		if ( $this->normaliser->originalHash( $originalUnits ) !== $this->normaliser->originalHash( $previous['original'] ) ) {
			$lists['original'] = UnitList::encode( $previous['original'], $originalUnits );
		}

		if ( $replaces ) {
			$this->log->delete( $translation, $sameClick );
		}

		$entryId = $this->log->append(
			new Entry( $translation, $language, $time, $source, $editor, $userId, $lists )
		);
		$this->store->saveHead(
			$translation,
			$language,
			$originalId,
			$entryId,
			[
				'units'    => $units,
				'original' => $originalUnits,
			],
			$hash
		);

		if ( $this->cap ) {
			$this->cap->apply( $translation, $language );
		}

		return $entryId;
	}

	public function stampOriginal( Subject $original, $before, array $units ) {
		$before = null === $before ? null : $this->normaliser->original( $before );
		$units  = $this->normaliser->original( $units );
		if ( null !== $before && UnitList::hash( $before ) === UnitList::hash( $units ) ) {
			return;
		}

		$state  = $this->store->get( $original );
		$old    = $state ? $state->stamps() : [];
		$stamps = self::stamps( $old, self::byKey( $before ?? [] ), self::byKey( $units ), ( $this->now )() );

		if ( $stamps !== $old ) {
			$this->store->saveStamps( $original, $stamps );
		}
	}

	private function hasHead( $state, string $language, int $originalId ): bool {
		return $state && $state->isHeadOf( $language, $originalId );
	}

	private function sent( State $state, int $entryId ): bool {
		foreach ( $state->sends() as $send ) {
			if ( (int) ( $send['watermark'] ?? 0 ) >= $entryId ) {
				return true;
			}
		}

		return false;
	}

	private function listsBefore( State $state, Subject $translation, string $language, int $entryId ) {
		$start    = $state->replayStart();
		$units    = $start['lists']['units'] ?? [];
		$original = $start['lists']['original'] ?? [];
		$entries  = $this->log->since( $translation, $language, $start['entry_id'] );
		$newest   = array_pop( $entries );

		if ( ! $newest || $newest->id !== $entryId ) {
			return null;
		}

		foreach ( $entries as $entry ) {
			$units = UnitList::decode( $entry->lists['units'] ?? [], $units );
			if ( isset( $entry->lists['original'] ) ) {
				$original = UnitList::decode( $entry->lists['original'], $original );
			}
			if ( null === $units || null === $original ) {
				return null;
			}
		}

		return [
			'units'    => $units,
			'original' => $original,
		];
	}

	private static function byKey( array $units ): array {
		$texts  = [];
		$blocks = 0;
		foreach ( $units as $unit ) {
			$key           = isset( $unit['k'] ) ? (string) $unit['k'] : '#' . ( $blocks++ );
			$texts[ $key ] = (string) ( $unit['x'] ?? '' );
		}

		return $texts;
	}

	private static function stamps( array $old, array $before, array $now, int $time ): array {
		$blockTexts  = [];
		$movedStamps = [];
		foreach ( $before as $key => $text ) {
			if ( self::isBlock( $key ) ) {
				$blockTexts[ $text ] = true;
				if ( isset( $old[ $key ] ) && ! isset( $movedStamps[ $text ] ) ) {
					$movedStamps[ $text ] = $old[ $key ];
				}
			}
		}

		$stamps = [];
		foreach ( $now as $key => $text ) {
			if ( array_key_exists( $key, $before ) && $before[ $key ] === $text ) {
				$stamp = $old[ $key ] ?? null;
			} elseif ( self::isBlock( $key ) && isset( $blockTexts[ $text ] ) ) {
				$stamp = $movedStamps[ $text ] ?? null;
			} else {
				$stamp = [ $time, md5( $text ) ];
			}

			if ( null !== $stamp ) {
				$stamps[ $key ] = $stamp;
			}
		}

		return $stamps;
	}

	private static function isBlock( $key ): bool {
		return 0 === strpos( (string) $key, '#' );
	}
}
