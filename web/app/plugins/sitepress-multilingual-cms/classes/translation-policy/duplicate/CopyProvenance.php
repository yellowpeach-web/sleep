<?php

namespace WPML\TranslationPolicy\Duplicate;

use WPML\EditHistory\Entry;
use WPML\EditHistory\EntryLog;
use WPML\EditHistory\InternalWrite;
use WPML\EditHistory\PostUnits;
use WPML\EditHistory\Recorder;
use WPML\EditHistory\StateStore;
use WPML\EditHistory\Subject;
use WPML\EditHistory\UnitList;

class CopyProvenance {

	const EDITOR = 'duplicate';

	private $recorder;

	private $store;

	private $units;

	private $log;

	private $now;

	public function __construct( Recorder $recorder, StateStore $store, PostUnits $units, EntryLog $log, $now = null ) {
		$this->recorder = $recorder;
		$this->store    = $store;
		$this->units    = $units;
		$this->log      = $log;
		$this->now      = $now ?? 'time';
	}

	public static function create(): self {
		global $wpdb;

		$log   = new \WPML\EditHistory\WpdbEntryLog( $wpdb );
		$store = new \WPML\EditHistory\WpdbStateStore( $wpdb );

		return new self( new Recorder( $log, $store, \WPML\EditHistory\Normaliser::create() ), $store, PostUnits::forBlockEditor(), $log );
	}

	public function write( int $postId, string $language, int $originalId ): bool {
		$translation = $postId ? get_post( $postId ) : null;
		$original    = $originalId ? get_post( $originalId ) : null;
		if ( ! $translation instanceof \WP_Post || ! $original instanceof \WP_Post ) {
			return true;
		}

		$subject = Subject::post( $postId );
		InternalWrite::run(
			InternalWrite::COPY,
			fn() => $this->recorder->recordTranslation(
				$subject,
				$language,
				$originalId,
				$this->units->read( $translation, $original ),
				null,
				fn() => $this->units->read( $original, $original ),
				self::EDITOR,
				null,
				null,
				InternalWrite::COPY
			)
		);

		$state = $this->store->get( $subject );
		if ( ! $state || ! $state->isHeadOf( $language, $originalId ) ) {
			return false;
		}

		return $this->headIsCopy( $subject, $language, $state->headEntryId() ) || $this->markHead( $subject, $language, $originalId, $state );
	}

	private function headIsCopy( Subject $subject, string $language, int $headId ): bool {
		foreach ( $this->log->since( $subject, $language, $headId - 1 ) as $entry ) {
			return (int) $entry->id === $headId && InternalWrite::COPY === $entry->source;
		}

		return false;
	}

	private function markHead( Subject $subject, string $language, int $originalId, $state ): bool {
		$head    = (array) $state->head();
		$units   = (array) ( $head['units'] ?? [] );
		$entryId = $this->log->append(
			new Entry( $subject, $language, ( $this->now )(), InternalWrite::COPY, self::EDITOR, null, [ 'units' => UnitList::encode( $units, $units ) ] )
		);
		if ( ! $entryId ) {
			return false;
		}
		$this->store->saveHead( $subject, $language, $originalId, $entryId, $head, $state->headHash() );

		return true;
	}
}
