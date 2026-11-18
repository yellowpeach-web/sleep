<?php

namespace WPML\EditHistory;

class HistorySend {

	const LOG_ID = 'edit_history_not_sent';

	const JOB_UPDATE = 'update';

	const JOB_EDITOR_OPEN = 'editor-open';

	const JOB_RESUME = 'resume';

	const JOB_FROM_SCRATCH = 'from-scratch';

	public static $updateAfterDeliveryCarries = true;

	private $loader;

	private $writer;

	private $safetyNet;

	private $fromScratch;

	private $store;

	private $normaliser;

	private $posts;

	private $unrecordedDelivery;

	private $translationOf;

	private $log;

	private $now;

	private $isDecided;

	private $takeDropped;

	private $built = [];

	private $prepared = [];

	public function __construct(
		HistoryLoader $loader,
		HistoryKey $writer,
		SafetyNet $safetyNet,
		FromScratch $fromScratch,
		StateStore $store,
		Normaliser $normaliser,
		callable $posts,
		callable $unrecordedDelivery,
		callable $translationOf,
		callable $log,
		$now = null,
		$isDecided = null,
		$takeDropped = null
	) {
		$this->loader             = $loader;
		$this->writer             = $writer;
		$this->safetyNet          = $safetyNet;
		$this->fromScratch        = $fromScratch;
		$this->store              = $store;
		$this->normaliser         = $normaliser;
		$this->posts              = $posts;
		$this->unrecordedDelivery = $unrecordedDelivery;
		$this->translationOf      = $translationOf;
		$this->log                = $log;
		$this->now                = $now ?? 'time';
		$this->isDecided          = $isDecided;
		$this->takeDropped        = $takeDropped;
	}

	public function render( \WPML_TM_ATE_Models_Job_Create $job, int $translationId, string $language, int $originalId, callable $render, string $kind = self::JOB_UPDATE ) {
		$marking = $this->marking( (int) $job->id, $translationId, $language, $originalId, $kind );
		$xliff   = $render( $marking );
		if ( null === $xliff ) {
			return null;
		}

		$this->fill( $job, $translationId, $language, $originalId, $xliff, $kind );

		return $marking && null === $job->wpml_edit_history ? $render( null ) : $xliff;
	}

	private function marking( int $jobId, int $translationId, string $language, int $originalId, string $kind ) {
		unset( $this->prepared[ $jobId ] );
		if ( $translationId <= 0 ) {
			return null;
		}

		try {
			$job      = self::job( $jobId, 0, $translationId, $language, $originalId );
			$prepared = $this->prepare( $job );
			if ( ! $prepared ) {
				return null;
			}
			if ( $this->carries( $prepared['state'], $kind ) ) {
				$prepared['read'] = $this->loader->read( $job['translation'], $language, $originalId, Subject::post( $originalId ) );
			}
			$this->prepared[ $jobId ] = $prepared;
			$history                  = $prepared['read'][0] ?? null;

			return null === $history ? null : XliffMarking::of( $history['base']['units'], $prepared['posts']['units'], $prepared['posts']['original'], $this->normaliser );
		} catch ( \Throwable $error ) {
			return null;
		}
	}

	public function fill( \WPML_TM_ATE_Models_Job_Create $job, int $translationId, string $language, int $originalId, string $xliff, string $kind = self::JOB_UPDATE ) {
		$jobId                 = (int) $job->id;
		$this->built[ $jobId ] = 0;
		if ( $translationId <= 0 ) {
			return;
		}

		try {
			$job->wpml_edit_history = $this->build( self::job( $jobId, (int) $job->source_id, $translationId, $language, $originalId ), $xliff, $kind );
		} catch ( \Throwable $error ) {
			$job->wpml_edit_history = null;
			$this->logNone( $jobId, 'the history could not be read: ' . $error->getMessage() );
		}
	}

	public function carries( State $state, string $kind = self::JOB_UPDATE ): bool {
		if ( self::JOB_FROM_SCRATCH === $kind ) {
			return false;
		}

		return self::hasEntriesSinceBase( $state )
			|| ( self::JOB_UPDATE === $kind && self::$updateAfterDeliveryCarries && State::BASE_APPLIED === $state->baseState() );
	}

	private static function hasEntriesSinceBase( State $state ): bool {
		return $state->headEntryId() > $state->baseEntryId();
	}

	public function carriesFor( int $translationId, string $language, int $originalId, string $kind ): bool {
		if ( $translationId <= 0 ) {
			return false;
		}

		try {
			$prepared = $this->prepare( self::job( 0, 0, $translationId, $language, $originalId ) );

			return null !== $prepared && $this->carries( $prepared['state'], $kind );
		} catch ( \Throwable $error ) {
			return false;
		}
	}

	public function bind( array $ateJobIds ) {
		foreach ( $ateJobIds as $jobId => $ateJobId ) {
			try {
				$translationId = $this->built[ (int) $jobId ] ?? ( $this->translationOf )( (int) $jobId );
				$subject       = Subject::post( $translationId );
				$state         = $translationId > 0 ? $this->store->get( $subject ) : null;
				if ( ! $state ) {
					continue;
				}

				$sends = $state->sends();
				$bound = SendRows::bind( $sends, (int) $jobId, (int) $ateJobId );
				if ( $bound !== $sends ) {
					$this->store->saveSends( $subject, $bound );
				}
			} catch ( \Throwable $error ) {
				( $this->log )( self::LOG_ID, [ 'job_id' => (int) $jobId, 'reason' => 'the send row could not take the ATE job id: ' . $error->getMessage() ] );
			}
		}
	}

	private function build( array $job, string $xliff, string $kind ) {
		$prepared = $this->prepared[ $job['id'] ] ?? $this->prepare( $job );
		unset( $this->prepared[ $job['id'] ] );
		if ( ! $prepared ) {
			return null;
		}

		$state   = $prepared['state'];
		$posts   = $prepared['posts'];
		$now     = ( $this->now )();
		$key     = null;
		$dropped = null;
		if ( self::JOB_FROM_SCRATCH === $kind && self::hasEntriesSinceBase( $state ) && null !== $this->fromScratch->mark( $job['translation'], $job['language'], $job['original'], $state ) ) {
			$state = $this->store->get( $job['translation'] );
		}
		if ( $this->carries( $state, $kind ) ) {
			$dropped = $this->droppedJob( $job, $state );
			$read    = $prepared['read'] ?? $this->loader->read( $job['translation'], $job['language'], $job['original'], Subject::post( $job['original'] ) );
			$result  = $this->key( $job, $read, $posts, $xliff, $now, $dropped );
			$key     = $result->value();
			if ( null === $key ) {
				$this->logNone( $job['id'], (string) $result->reason() );
			}
		}

		$row = new SendRow(
			$job['id'],
			null,
			$key && $key['entries'] ? (int) $key['watermark'] : $state->headEntryId(),
			$this->normaliser->translationHash( $posts['units'] ),
			$key ? self::lastAppliedOnRow( $key['last_applied'] ) : self::lastAppliedOfState( $state ),
			$now,
			$dropped,
			null !== $key
		);
		$this->store->saveSends( $job['translation'], SendRows::add( $state->sends(), $row, $this->isDecided ) );
		$this->built[ $job['id'] ] = $job['translation']->id();

		return $key;
	}

	private function droppedJob( array $job, State $state ) {
		foreach ( $state->sends() as $row ) {
			if ( (int) ( $row['job_id'] ?? 0 ) === $job['id'] && is_array( $row['dropped_job'] ?? null ) ) {
				return $row['dropped_job'];
			}
		}

		return $this->takeDropped && $job['rid'] > 0 ? ( $this->takeDropped )( $job['rid'] ) : null;
	}

	private function prepare( array $job ) {
		$state = $this->store->get( $job['translation'] );
		if ( ! $state || ! $state->isHeadOf( $job['language'], $job['original'] ) ) {
			return null;
		}

		$posts = ( $this->posts )( $job['translation']->id(), $job['original'] );
		if ( null === $posts ) {
			return null;
		}

		if ( null !== $this->safetyNet->check( $job['translation'], $job['language'], $job['original'], $posts['units'] ) ) {
			$state = $this->store->get( $job['translation'] );
		}

		return [
			'state' => $state,
			'posts' => $posts,
		];
	}

	private static function job( int $jobId, int $rid, int $translationId, string $language, int $originalId ): array {
		return [
			'id'          => $jobId,
			'rid'         => $rid,
			'translation' => Subject::post( $translationId ),
			'language'    => $language,
			'original'    => $originalId,
		];
	}

	private function key( array $job, array $read, array $posts, string $xliff, int $now, $dropped ): KeyResult {
		$original = Subject::post( $job['original'] );

		[ $history, $reason ] = $read;
		if ( null === $history ) {
			return KeyResult::none( (string) $reason );
		}

		$sources = self::transUnits( $xliff );
		if ( null === $sources ) {
			return KeyResult::none( 'the job\'s XLIFF does not parse' );
		}

		if ( State::BASE_NONE === $history['last_applied'] ) {
			$history['last_applied'] = ( $this->unrecordedDelivery )( $job['rid'], $job['id'] );
		}
		$history['dropped_job'] = $dropped;

		$result = $this->writer->build(
			$history,
			[
				'trans_units' => array_map( 'strval', array_keys( $sources ) ),
				'original'    => $posts['original'],
				'block_units' => self::blockUnits( $posts['original'], $sources ),
				'hint'        => $posts['modified'],
				'now'         => $now,
			]
		);

		$key = $result->value();
		if ( null === $key ) {
			return $result;
		}
		if ( [] === $key['original_times'] ) {
			return KeyResult::none( 'the key names no original time, and an empty original_times would reach ATE as a list' );
		}

		$stamps = $this->writer->restamped( $history['stamps'], $posts['original'], $now );
		if ( $stamps !== $history['stamps'] ) {
			$this->store->saveStamps( $original, $stamps );
		}

		return $result;
	}

	private static function transUnits( string $xliff ) {
		$document = new \DOMDocument();
		$internal = libxml_use_internal_errors( true );
		$parsed   = '' !== $xliff && $document->loadXML( $xliff, LIBXML_NONET );
		libxml_clear_errors();
		libxml_use_internal_errors( $internal );
		if ( ! $parsed ) {
			return null;
		}

		$sources = [];
		foreach ( $document->getElementsByTagName( 'trans-unit' ) as $unit ) {
			if ( 'no' === $unit->getAttribute( 'translate' ) ) {
				continue;
			}
			$source = $unit->getElementsByTagName( 'source' )->item( 0 );

			$sources[ $unit->getAttribute( 'id' ) ] = $source ? $source->textContent : '';
		}

		return $sources;
	}

	private static function blockUnits( array $original, array $sources ): array {
		$keys = [];
		foreach ( $original as $unit ) {
			if ( isset( $unit['k'] ) ) {
				$keys[ (string) $unit['k'] ] = true;
			}
		}

		$byText = [];
		foreach ( $sources as $id => $source ) {
			if ( ! isset( $keys[ (string) $id ] ) && ! isset( $byText[ $source ] ) ) {
				$byText[ $source ] = (string) $id;
			}
		}

		$names = [];
		foreach ( $original as $unit ) {
			if ( ! isset( $unit['k'] ) ) {
				$names[] = $byText[ (string) ( $unit['x'] ?? '' ) ] ?? null;
			}
		}

		return $names;
	}

	private static function lastAppliedOnRow( $lastApplied ) {
		return is_array( $lastApplied ) ? (int) $lastApplied['job_id'] : (string) $lastApplied;
	}

	private static function lastAppliedOfState( State $state ) {
		if ( State::BASE_APPLIED === $state->baseState() ) {
			return $state->baseAteJobId() ?? State::BASE_UNKNOWN;
		}

		return State::BASE_UNKNOWN === $state->baseState() ? State::BASE_UNKNOWN : State::BASE_NONE;
	}

	private function logNone( int $jobId, string $reason ) {
		( $this->log )(
			self::LOG_ID,
			[
				'job_id' => $jobId,
				'reason' => $reason,
			]
		);
	}
}
