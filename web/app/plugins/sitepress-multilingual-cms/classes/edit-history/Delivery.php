<?php

namespace WPML\EditHistory;

use WPML\TM\API\Jobs;

class Delivery {

	const REASON_OVERWRITE = 'overwrite';
	const REASON_UNCLEAR   = 'unclear';
	const REASON_NO_FLAG   = 'no-flag';
	const REASON_LATE      = 'edited-after-send';

	const SKIP_TAKEN_OVER = 'its ATE job sits on a row a takeover cancelled';
	const SKIP_DROPPED    = 'an automatic delivery after a client edit made since the send: dropped and re-sent';

	const NOT_CLIENT_EDITS = [ SafetyNet::SOURCE, InternalWrite::AUTO_RESAVE, InternalWrite::COPY, FromScratch::SOURCE ];

	const LOG_APPLIED = 'edit_history_delivery_applied';

	public static $baseAtEveryDelivery = false;

	private $store;

	private $log;

	private $rows;

	private $normaliser;

	private $posts;

	private $copyOf;

	private $dropped;

	private $jobLog;

	private $now;

	public function __construct( StateStore $store, EntryLog $log, JobRows $rows, Normaliser $normaliser, callable $posts, callable $copyOf, callable $dropped, callable $jobLog, $now = null ) {
		$this->store      = $store;
		$this->log        = $log;
		$this->rows       = $rows;
		$this->normaliser = $normaliser;
		$this->posts      = $posts;
		$this->copyOf     = $copyOf;
		$this->dropped    = $dropped;
		$this->jobLog     = $jobLog;
		$this->now        = $now ?? 'time';
	}

	public function carriedHistory( int $jobId ): bool {
		$translationId = $this->rows->translationOf( $jobId );
		$state         = $translationId > 0 ? $this->store->get( Subject::post( $translationId ) ) : null;

		return $state && self::carried( $state, $jobId );
	}

	private static function carried( State $state, int $jobId ): bool {
		foreach ( $state->sends() as $row ) {
			if ( (int) ( $row['job_id'] ?? 0 ) === $jobId && ! empty( $row['history'] ) ) {
				return true;
			}
		}

		return false;
	}

	public function decide( int $jobId, int $ateJobId, DeliveryAnswer $answer ): DeliveryDecision {
		$decision = DeliveryDecision::apply( $jobId, $ateJobId, $answer );
		$job      = $this->rows->ofJob( $jobId );
		if ( $job && $decision->ateJobId <= 0 ) {
			$decision->ateJobId = $job['ate_job_id'];
		}
		if ( $decision->ateJobId > 0 && $this->rows->takenOver( $decision->ateJobId ) ) {
			return $decision->skip( self::SKIP_TAKEN_OVER );
		}

		$element = $job ? $this->rows->element( $job['rid'] ) : null;
		if ( ! $element || $element['translation'] <= 0 ) {
			return $decision;
		}

		$subject = Subject::post( $element['translation'] );
		$state   = $this->store->get( $subject );
		if ( $state ? ! $state->belongsTo( $element['language'], $element['original'] ) : ! self::$baseAtEveryDelivery ) {
			return $decision;
		}

		$decision->subject    = $subject;
		$decision->language   = $element['language'];
		$decision->originalId = $element['original'];
		$decision->state      = $state;
		if ( ! $state ) {
			return $decision;
		}
		if ( self::carried( $state, $jobId ) ) {
			$decision->removedUnits = $answer->removedUnits();
		}

		if ( $this->editedAfterSend( $state, $subject, $element['language'], $jobId ) ) {
			if ( self::isAutomatic( $job, $answer ) ) {
				( $this->dropped )( $jobId, (int) $answer->deliveryId );

				return $decision->skip( self::SKIP_DROPPED );
			}

			$decision->itemReason = DeliveryAnswer::UNCLEAR === $answer->flag ? self::REASON_UNCLEAR : self::REASON_LATE;
		} elseif ( $this->edited( $state, $subject, $element['language'] ) ) {
			$decision->itemReason = self::itemReason( $answer );
		}

		if ( null !== $decision->itemReason ) {
			$decision->copy = ( $this->copyOf )( $subject->id(), $element['original'] );
		}

		return $decision;
	}

	public function apply( DeliveryDecision $decision, callable $write ) {
		if ( ! $decision->subject ) {
			return $write();
		}

		[ $saved, $decision->dropped ] = Removals::run( $decision->originalId, $decision->language, $decision->removedUnits, $write );

		return $saved;
	}

	public function record( DeliveryDecision $decision ) {
		if ( ! $decision->writes() || ! $decision->subject ) {
			return;
		}

		try {
			$this->write( $decision );
		} catch ( \Throwable $error ) {
			( $this->jobLog )( self::LOG_APPLIED, [ 'job_id' => $decision->jobId, 'recorded' => false, 'reason' => $error->getMessage() ] );
		}
	}

	private function write( DeliveryDecision $decision ) {
		$posts = ( $this->posts )( $decision->subject->id(), $decision->originalId );
		if ( null === $posts ) {
			( $this->jobLog )( self::LOG_APPLIED, [ 'job_id' => $decision->jobId, 'recorded' => false, 'reason' => 'a post is gone' ] );

			return;
		}

		$state   = $decision->state;
		$answer  = $decision->answer;
		$hash    = $this->normaliser->translationHash( $posts['units'] );
		$kept    = $state && null !== $decision->copy && $hash !== $state->headHash();
		$entryId = $state ? max( $state->headEntryId(), $state->baseEntryId() ) : 0;

		$this->store->applyDelivery(
			$decision->subject,
			new AppliedDelivery(
				$decision->language,
				$decision->originalId,
				$decision->jobId,
				$decision->ateJobId,
				$answer->deliveryId,
				$entryId,
				( $this->now )(),
				[
					'units'    => $posts['units'],
					'original' => $posts['original'],
					'pairing'  => $answer->units,
					'absent'   => $answer->absent,
					'deleted'  => $decision->dropped ?? ( $state ? $state->deletedElements() : [] ),
				],
				$hash,
				$kept ? $decision->itemReason : null,
				$kept ? $decision->copy : null
			)
		);
		$this->log->deleteUpTo( $decision->subject, $entryId );

		( $this->jobLog )(
			self::LOG_APPLIED,
			[
				'job_id'      => $decision->jobId,
				'ate_job_id'  => $decision->ateJobId,
				'delivery_id' => $answer->deliveryId,
				'flag'        => $answer->flag,
				'item'        => $kept ? $decision->itemReason : null,
				'absent'      => count( $answer->absent ),
				'dropped'     => null === $decision->dropped ? null : count( $decision->dropped ),
			]
		);
	}

	private function edited( State $state, Subject $subject, string $language ): bool {
		if ( $state->headEntryId() <= $state->baseEntryId() ) {
			return false;
		}

		$client = $state->replayStart()['entry_id'] > $state->baseEntryId()
			|| $this->hasClientEntry( $subject, $language, $state->baseEntryId(), $state->headEntryId() );
		if ( ! $client ) {
			return false;
		}

		$base = $state->base()['units'] ?? [];

		return [] === $base || $this->normaliser->translationHash( $base ) !== $state->headHash();
	}

	private function editedAfterSend( State $state, Subject $subject, string $language, int $jobId ): bool {
		$sent = null;
		foreach ( $state->sends() as $row ) {
			if ( (int) ( $row['job_id'] ?? 0 ) === $jobId && ( null === $sent || (int) $row['watermark'] < (int) $sent['watermark'] ) ) {
				$sent = $row;
			}
		}

		$after = max( $sent ? (int) $sent['watermark'] : 0, $state->baseEntryId() );
		if ( $state->headEntryId() <= $after || ( $sent && (string) ( $sent['units_hash'] ?? '' ) === $state->headHash() ) ) {
			return false;
		}

		return $this->hasClientEntry( $subject, $language, $after, $state->headEntryId() );
	}

	private function hasClientEntry( Subject $subject, string $language, int $after, int $upTo ): bool {
		foreach ( $this->log->since( $subject, $language, $after ) as $entry ) {
			if ( $entry->id <= $upTo && self::isClientEdit( $entry ) ) {
				return true;
			}
		}

		return false;
	}

	private static function isAutomatic( array $job, DeliveryAnswer $answer ): bool {
		return $answer->retranslation || ( $job['automatic'] && Jobs::SENT_FROM_REVIEW !== $job['sent_from'] );
	}

	private static function isClientEdit( Entry $entry ): bool {
		return ! in_array( $entry->source, self::NOT_CLIENT_EDITS, true );
	}

	private static function itemReason( DeliveryAnswer $answer ): string {
		if ( DeliveryAnswer::UNCLEAR === $answer->flag ) {
			return self::REASON_UNCLEAR;
		}

		return DeliveryAnswer::OVERWRITE === $answer->flag ? self::REASON_OVERWRITE : self::REASON_NO_FLAG;
	}
}
