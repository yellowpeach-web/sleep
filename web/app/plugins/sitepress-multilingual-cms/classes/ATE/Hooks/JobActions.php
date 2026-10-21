<?php

namespace WPML\TM\ATE\Hooks;

require_once __DIR__ . '/../../../inc/constants-since-5-0.php';

use WPML\Core\Component\Translation\Application\Service\AutomaticJobsCancellation\AteResponse;
use WPML\Core\Component\Translation\Application\Service\AutomaticJobsCancellation\BatchResult;
use WPML\Core\Component\Translation\Application\Service\AutomaticJobsCancellation\ReleaseLedger;
use WPML\TM\ATE\Cancel\PendingCancels;
use WPML\TM\ATE\Release\InFlightChargedJobs;
use WPML\TM\ATE\Release\WordsLifetimeMax;
use WPML\Element\API\Languages;
use WPML\FP\Lst;
use WPML\TM\ATE\Retry\Process as RetryProcess;
use WPML\TM\ATE\TranslateEverything;
use WPML\TM\ATE\TranslateEverything\OfferedTypeDefaults;
use WPML\TM\Jobs\JobLog;
use WPML\Translation\CancelJobsServiceFactory;
use WPML\Translation\StringBatchCleanupServiceFactory;
use WPML\Translation\TranslateJobErrorServiceFactory;
use WPML\Utilities\Lock;
use function WPML\Container\make;
use function WPML\FP\invoke;

class JobActions implements \IWPML_Action {
	const CANCEL_BATCH_SIZE = 1000;

	const DISABLE_MAX_BATCHES = 5;

	const STEM_SUPERSEDE = 'translation_job_superseded';
	const STEM_CANCEL    = 'translation_job_cancelled';

	const SUFFIX_CANCELED            = '_canceled';
	const SUFFIX_CANCEL_UNCONFIRMED  = '_cancel_unconfirmed';
	const SUFFIX_RELEASED            = '_released';
	const SUFFIX_RELEASE_UNCONFIRMED = '_release_unconfirmed';

	const EVENT_SUPERSEDE_CANCELED = 'translation_job_superseded_canceled';

	const EVENT_SUPERSEDE_CANCEL_UNCONFIRMED = 'translation_job_superseded_cancel_unconfirmed';

	const EVENT_SUPERSEDE_RELEASED             = 'translation_job_superseded_released';
	const EVENT_SUPERSEDE_RELEASE_UNCONFIRMED  = 'translation_job_superseded_release_unconfirmed';

	const EVENT_CANCEL_CANCELED            = 'translation_job_cancelled_canceled';
	const EVENT_CANCEL_UNCONFIRMED         = 'translation_job_cancelled_cancel_unconfirmed';
	const EVENT_CANCEL_RELEASED            = 'translation_job_cancelled_released';
	const EVENT_CANCEL_RELEASE_UNCONFIRMED = 'translation_job_cancelled_release_unconfirmed';

	const DEFINITIVE_NOT_RELEASED_REASONS = [ 'delivered', 'not_charged', 'already_released' ];

	const RESPONSE_LOG_MAX_LENGTH = 500;

	private $apiClient;

	private $translateEverything;

	private $disableRelease = null;

	public function __construct( \WPML_TM_ATE_API $apiClient, TranslateEverything $translateEverything ) {
		$this->apiClient           = $apiClient;
		$this->translateEverything = $translateEverything;
	}

	public function add_hooks() {
		add_action( 'wpml_tm_job_cancelled', [ $this, 'cancelJobInATE' ], 10, 2 );
		add_action( 'wpml_tm_jobs_cancelled', [ $this, 'cancelJobsInATE' ] );
		add_action( 'wpml_set_translate_everything', [ $this, 'onTranslateEverythingModeChanged' ], 10, 2 );
		add_action( 'wpml_update_active_languages', [ $this, 'markRemovedLanguagesUncompleted' ] );
		add_action( 'wpml_cancel_all_automatic_jobs', [ $this, 'cancelAllAutomaticJobs' ], 10 );
		add_filter( 'wpml_tea_disable_release', [ $this, 'fillDisableRelease' ] );
	}

	public function cancelJobInATE( \WPML_TM_Post_Job_Entity $job, $reason = null ) {
		if ( ! $job->is_ate_editor() ) {
			return;
		}

		$ateJobId = (int) $job->get_editor_job_id();

		if ( $ateJobId <= 0 ) {
			return;
		}

		$reason = $reason ? (string) $reason : \WPML_TM_ATE_API::CANCEL_REASON_SUPERSEDED;
		$stem   = self::eventStem( $reason );

		$wpmlJobId = (int) $job->get_translate_job_id();
		$rid       = (int) $job->get_rid();

		$money   = self::readJobMoney( $job );
		$context = [
			'ate_job_id'  => $ateJobId,
			'wpml_job_id' => $wpmlJobId,
			'rid'         => $rid,
			'reason'      => $reason,
			'words'       => $money['words'],
			'credits'     => $money['credits'],
		];

		$answered = false;

		try {
			$response  = $this->apiClient->hideJobs( [ $ateJobId ], true, $reason );
			$answered  = ! PendingCancels::isUnanswered( $response );
			$confirmed = AteResponse::getConfirmedJobIds( $response );

			if ( is_array( $confirmed ) && in_array( $ateJobId, $confirmed, true ) ) {
				JobLog::add( $stem . self::SUFFIX_CANCELED, array_merge( $context, [
					'confirmed' => $confirmed,
				] ) );

				$this->clearJobError( $wpmlJobId );

				$this->recordRelease( $response, [ $ateJobId ], $stem, [ $ateJobId => $context ] );

				return;
			}

			JobLog::addError( $stem . self::SUFFIX_CANCEL_UNCONFIRMED, array_merge( $context, [
				'response' => self::describeResponse( $response ),
			] ) );

			$this->recordRelease( null, [ $ateJobId ], $stem, [ $ateJobId => $context ] );
		} catch ( \Throwable $e ) {
			JobLog::addError( $stem . self::SUFFIX_CANCEL_UNCONFIRMED, array_merge( $context, [
				'response' => null,
				'error'    => $e->getMessage(),
			] ) );

			$this->recordRelease( null, [ $ateJobId ], $stem, [ $ateJobId => $context ] );
		}

		if ( ! $answered ) {
			PendingCancels::remember( [ $ateJobId ], PendingCancels::ENDPOINT_HIDE, $reason, true, $context );
		}
	}


	private function clearJobError( $wpmlJobId ) {
		if ( (int) $wpmlJobId <= 0 ) {
			return;
		}

		$recorder = new \WPML\TM\ATE\JobError\Recorder( TranslateJobErrorServiceFactory::create() );
		$recorder->clear( (int) $wpmlJobId );
	}

	private static function eventStem( $reason ) {
		return \WPML_TM_ATE_API::CANCEL_REASON_SUPERSEDED === $reason
			? self::STEM_SUPERSEDE
			: self::STEM_CANCEL;
	}


	private static function readJobMoney( \WPML_TM_Post_Job_Entity $job ) {
		$words   = $job->get_words_to_translate_count();
		$credits = $job->get_automatic_translation_costs();

		if ( null !== $words || null !== $credits ) {
			return [
				'words'   => $words,
				'credits' => $credits,
			];
		}

		$wpmlJobId = (int) $job->get_translate_job_id();

		if ( $wpmlJobId <= 0 ) {
			return [
				'words'   => null,
				'credits' => null,
			];
		}

		$counts = InFlightChargedJobs::forJobIds( [ $wpmlJobId ] );

		return $counts['charged'] > 0
			? [
				'words'   => $counts['words'],
				'credits' => null,
			]
			: [
				'words'   => null,
				'credits' => null,
			];
	}


	private function recordRelease(
		$response,
		array $ateJobIds,
		$stem,
		array $contextByAteJobId = [],
		$logPerJob = true
	) {
		$released    = null === $response ? null : AteResponse::getReleasedJobs( $response );
		$notReleased = null === $response ? null : AteResponse::getNotReleasedJobs( $response );
		$ledger      = ReleaseLedger::instance();
		$chargedJobs = InFlightChargedJobs::chargedAteJobIds( $ateJobIds );

		$batchMark = $ledger->mark();

		foreach ( $ateJobIds as $ateJobId ) {
			$ateJobId = (int) $ateJobId;
			$context  = isset( $contextByAteJobId[ $ateJobId ] )
				? $contextByAteJobId[ $ateJobId ]
				: [ 'ate_job_id' => $ateJobId ];

			if ( is_array( $released ) && isset( $released[ $ateJobId ] ) ) {
				$row = $released[ $ateJobId ];

				$ledger->recordReleased( $ateJobId, (int) $row['words'], $row['ledger_id'] );

				WordsLifetimeMax::releaseJob( $ateJobId, (int) $row['words'] );

				if ( $logPerJob ) {
					JobLog::add( $stem . self::SUFFIX_RELEASED, array_merge( $context, [
						'released_words'   => (int) $row['words'],
						'released_credits' => (int) $row['credits'],
						'ledger_id'        => $row['ledger_id'],
					] ) );
				}

				continue;
			}

			$notReleasedReason = is_array( $notReleased ) && isset( $notReleased[ $ateJobId ] )
				? $notReleased[ $ateJobId ]
				: null;

			$isDefinitive = in_array( $notReleasedReason, self::DEFINITIVE_NOT_RELEASED_REASONS, true );

			if ( ! $isDefinitive && isset( $chargedJobs[ $ateJobId ] ) ) {
				$ledger->recordUnconfirmed( $ateJobId );
			}

			if ( ! $logPerJob ) {
				continue;
			}

			$payload = array_merge( $context, [
				'release_reason' => $notReleasedReason ?: 'no_release_fields',
			] );

			if ( $isDefinitive ) {
				JobLog::add( $stem . self::SUFFIX_RELEASE_UNCONFIRMED, $payload );
			} else {
				JobLog::addError( $stem . self::SUFFIX_RELEASE_UNCONFIRMED, $payload );
			}
		}

		if ( $logPerJob ) {
			return;
		}

		$summary = $ledger->summaryFrom( $batchMark );
		$payload = [
			'jobs'             => count( $ateJobIds ),
			'released_jobs'    => $summary->getReleasedJobs(),
			'released_words'   => $summary->getReleasedWords(),
			'unconfirmed_jobs' => $summary->getUnconfirmedJobs(),
		];

		if ( $summary->getUnconfirmedJobs() > 0 ) {
			JobLog::addError( $stem . self::SUFFIX_RELEASE_UNCONFIRMED, $payload );
		} else {
			JobLog::add( $stem . self::SUFFIX_RELEASED, $payload );
		}
	}

	private static function describeResponse( $response ) {
		if ( null === $response || is_scalar( $response ) ) {
			return $response;
		}

		$encoded = wp_json_encode( $response );

		if ( ! is_string( $encoded ) ) {
			return gettype( $response );
		}

		return strlen( $encoded ) > self::RESPONSE_LOG_MAX_LENGTH
			? substr( $encoded, 0, self::RESPONSE_LOG_MAX_LENGTH ) . '…'
			: $encoded;
	}

	public function cancelJobsInATE( $jobs ) {
		if ( is_object( $jobs ) ) {
			$jobs = [ $jobs ];
		}

		$normalizedJobs = array_map( function( $job ) {
			if ( $job instanceof \WPML_TM_Post_Job_Entity ) {
				return (object) [
					'editor'           => $job->get_editor(),
					'editor_job_id'    => $job->get_editor_job_id(),
					'translate_job_id' => $job->get_translate_job_id(),
				];
			}

			return $job;
		}, $jobs );

		$ateJobIds = array_values( array_filter( array_map( function( $job ) {
			return ( isset( $job->editor ) && $job->editor === 'ate' && isset( $job->editor_job_id ) )
				? $job->editor_job_id
				: null;
		}, $normalizedJobs ) ) );

		if ( ! empty( $ateJobIds ) ) {
			JobLog::add( 'cancel_jobs_in_ate', [
				'ate_job_ids' => array_map(
					function ( $id ) { return [ 'ate_job_id' => $id ]; },
					$ateJobIds
				),
			] );

			$ateJobIds = array_map( 'intval', $ateJobIds );
			$context   = self::cancelContexts( $normalizedJobs, $ateJobIds );
			$keepErrorRecord = self::ateJobIdsKeepingTheirErrorRecord( $normalizedJobs );
			$answered  = false;

			try {
				$response = $this->apiClient->cancelJobs(
					$ateJobIds,
					false,
					\WPML_TM_ATE_API::CANCEL_REASON_CANCELLED
				);
				$answered = ! PendingCancels::isUnanswered( $response );

				$confirmed = AteResponse::getConfirmedJobIds( $response );
				$confirmed = is_array( $confirmed ) ? $confirmed : [];

				foreach ( $ateJobIds as $ateJobId ) {
					if ( in_array( $ateJobId, $confirmed, true ) ) {
						JobLog::add(
							self::STEM_CANCEL . self::SUFFIX_CANCELED,
							array_merge( $context[ $ateJobId ], [ 'confirmed' => $confirmed ] )
						);

						if ( ! in_array( $ateJobId, $keepErrorRecord, true ) ) {
							$this->clearJobError( $context[ $ateJobId ]['wpml_job_id'] ?? null );
						}
					} else {
						JobLog::addError(
							self::STEM_CANCEL . self::SUFFIX_CANCEL_UNCONFIRMED,
							array_merge( $context[ $ateJobId ], [
								'response' => self::describeResponse( $response ),
							] )
						);
					}
				}

				$this->recordRelease( $response, $ateJobIds, self::STEM_CANCEL, $context );
			} catch ( \Throwable $e ) {
				foreach ( $ateJobIds as $ateJobId ) {
					JobLog::addError(
						self::STEM_CANCEL . self::SUFFIX_CANCEL_UNCONFIRMED,
						array_merge( $context[ $ateJobId ], [
							'response' => null,
							'error'    => $e->getMessage(),
						] )
					);
				}

				$this->recordRelease( null, $ateJobIds, self::STEM_CANCEL, $context );
			}

			if ( ! $answered ) {
				PendingCancels::remember( $ateJobIds, PendingCancels::ENDPOINT_CANCEL, \WPML_TM_ATE_API::CANCEL_REASON_CANCELLED );
			}

			foreach ( $keepErrorRecord as $keptAteJobId ) {
				$this->recordDiscardedJobSourceChecksum( $context[ $keptAteJobId ]['wpml_job_id'] ?? null );
			}
		}
	}


	private function recordDiscardedJobSourceChecksum( $wpmlJobId ) {
		if ( (int) $wpmlJobId <= 0 ) {
			return;
		}

		global $wpdb;

		$row = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT ts.md5, t.element_type
				   FROM {$wpdb->prefix}icl_translate_job j
				   INNER JOIN {$wpdb->prefix}icl_translation_status ts ON ts.rid = j.rid
				   INNER JOIN {$wpdb->prefix}icl_translations t ON t.translation_id = ts.translation_id
				  WHERE j.job_id = %d",
				(int) $wpmlJobId
			)
		);

		if ( ! is_object( $row ) || ! isset( $row->md5, $row->element_type ) ) {
			return;
		}

		$md5         = (string) $row->md5;
		$elementType = (string) $row->element_type;

		if ( 0 === strpos( $elementType, 'post_' ) ) {
			if ( '' === $md5 ) {
				return;
			}
		} elseif ( 0 !== strpos( $elementType, 'tax_' ) && 0 !== strpos( $elementType, 'package_' ) ) {
			return;
		}

		TranslateJobErrorServiceFactory::create()->recordDiscardedSourceMd5( (int) $wpmlJobId, $md5 );
	}


	private static function ateJobIdsKeepingTheirErrorRecord( array $normalizedJobs ) {
		$keep = [];
		foreach ( $normalizedJobs as $job ) {
			if ( ! empty( $job->keep_error_record ) && isset( $job->editor_job_id ) && (int) $job->editor_job_id > 0 ) {
				$keep[] = (int) $job->editor_job_id;
			}
		}

		return $keep;
	}

	private static function cancelContexts( array $normalizedJobs, array $ateJobIds ) {
		$wpmlJobIdByAteId = [];
		foreach ( $normalizedJobs as $job ) {
			$ateJobId = isset( $job->editor_job_id ) ? (int) $job->editor_job_id : 0;

			if ( $ateJobId <= 0 ) {
				continue;
			}

			foreach ( [ 'translate_job_id', 'job_id', 'tm_job_id' ] as $property ) {
				if ( isset( $job->{$property} ) && (int) $job->{$property} > 0 ) {
					$wpmlJobIdByAteId[ $ateJobId ] = (int) $job->{$property};
					break;
				}
			}
		}

		$money = InFlightChargedJobs::forJobIds( array_values( $wpmlJobIdByAteId ) );

		$contexts = [];
		foreach ( $ateJobIds as $ateJobId ) {
			$contexts[ $ateJobId ] = [
				'ate_job_id'  => $ateJobId,
				'wpml_job_id' => isset( $wpmlJobIdByAteId[ $ateJobId ] ) ? $wpmlJobIdByAteId[ $ateJobId ] : null,
				'reason'      => \WPML_TM_ATE_API::CANCEL_REASON_CANCELLED,
			];
		}

		$first = reset( $ateJobIds );
		if ( false !== $first && $money['charged'] > 0 ) {
			$contexts[ $first ]['batch_words']         = $money['words'];
			$contexts[ $first ]['batch_charged_jobs']  = $money['charged'];
		}

		return $contexts;
	}

	public function markRemovedLanguagesUncompleted( $oldLanguages = [] ) {
		$oldLanguagesArray = is_array( $oldLanguages ) ? array_keys( $oldLanguages ) : [];
		$removedLanguages = Lst::diff( $oldLanguagesArray, array_keys( Languages::getActive() ) );

		if ( $removedLanguages ) {
			$this->translateEverything->markLanguagesAsUncompleted( $removedLanguages );
		}
	}

	public function onTranslateEverythingModeChanged( $translateEverythingActive, $options = [] ) {
		JobLog::maybeInitRequest();
		JobLog::createNewGroup(
			JobLog::GROUP_ID_TRANSLATE_EVERYTHING,
			'Translate Everything mode change',
			[
				'newState'          => $translateEverythingActive ? 'on' : 'off',
				'translateExisting' => $options['translateExisting'] ?? null,
				'reviewMode'        => $options['reviewMode'] ?? null,
			]
		);

		try {
			JobLog::add( 'tea_mode_changed', [
				'active'             => (bool) $translateEverythingActive,
				'translate_existing' => $options['translateExisting'] ?? false,
				'review_mode'        => $options['reviewMode'] ?? null,
			] );

			if ( $translateEverythingActive ) {
				if ( wpml_is_setup_complete() ) {
					( new OfferedTypeDefaults() )->persist();
				}

				$translateExistingContent = $options['translateExisting'] ?? false;
				if ( $translateExistingContent ) {
					JobLog::add( 'tea_kickoff_marking_uncompleted', [] );
					$this->translateEverything->markEverythingAsUncompleted();
					$this->translateEverything->markSkippedPostTypesAsCompleted();
				} else {
					JobLog::add( 'tea_kickoff_no_existing', [] );
					$this->translateEverything->markEverythingAsCompleted();
				}
			} else {
				$this->cancelAllAutomaticJobsOnDisable();
			}
		} finally {
			JobLog::finishCurrentGroup();
		}
	}

	private function cancelAllAutomaticJobsOnDisable() {
		JobLog::add( 'tea_disabled_cancelling_in_flight', [] );

		$disableResult = new BatchResult();

		$retryLock = make( Lock::class, [ ':name' => RetryProcess::LOCK_NAME ] );
		$lockHeld  = $retryLock->create( RetryProcess::LOCK_RELEASE_TIMEOUT );

		if ( ! $lockHeld ) {
			JobLog::add( 'tea_disabled_retry_lock_busy', [] );
		}

		try {
			for ( $batch = 0; $batch < self::DISABLE_MAX_BATCHES; $batch ++ ) {
				$batchResult = new BatchResult();
				$this->cancelAllAutomaticJobs( $batchResult );
				$disableResult->addRelease( $batchResult->getRelease() );

				if ( ! $batchResult->hasMore() ) {
					return;
				}
			}

			JobLog::add( 'tea_disabled_cancel_incomplete', [
				'batches' => self::DISABLE_MAX_BATCHES,
			] );
		} catch ( \Throwable $e ) {
			JobLog::addError( 'tea_disabled_cancel_failed', [
				'error' => $e->getMessage(),
			] );
		} finally {
			if ( $lockHeld ) {
				$retryLock->release();
			}

			$this->disableRelease = $disableResult->getRelease();
		}
	}


	public function fillDisableRelease( $release ) {
		return $this->disableRelease && ! $this->disableRelease->isEmpty()
			? $this->disableRelease
			: $release;
	}


	public function cancelAllAutomaticJobs( $batchResult = null ) {
		$batchResult = $batchResult instanceof BatchResult ? $batchResult : new BatchResult();

		JobLog::maybeInitRequest();
		$ownsGroup = ! JobLog::isGroupOpen();
		if ( $ownsGroup ) {
			JobLog::createNewGroup(
				JobLog::GROUP_ID_TRANSLATE_EVERYTHING,
				'TEA cancel all automatic jobs',
				[]
			);
		}

		try {
			JobLog::add( 'tea_cancel_all_started', [] );
			$foundJobs = $this->hideJobs( self::getInProgressSearch(), $batchResult );

			if ( self::CANCEL_BATCH_SIZE > $foundJobs ) {
				$this->cancelOrphanedAutomaticJobs( self::getInProgressSearch(), $batchResult );
			}

			JobLog::add( 'tea_cancel_all_finished', [] );
		} catch ( \Throwable $e ) {
			JobLog::addError( 'tea_cancel_all_failed', [
				'error' => $e->getMessage(),
				'file'  => $e->getFile(),
				'line'  => $e->getLine(),
			] );
			throw $e;
		} finally {
			if ( $ownsGroup ) {
				JobLog::finishCurrentGroup();
			}
		}
	}

	private static function getInProgressSearch() {
		$searchParams = ( new \WPML_TM_Jobs_Search_Params() )
			->set_status( [
				ICL_TM_WAITING_FOR_TRANSLATOR,
				ICL_TM_IN_PROGRESS,
				ICL_TM_ATE_NEEDS_RETRY,
				ICL_TM_ATE_UNSOLVABLE,
			] )
			->set_limit( self::CANCEL_BATCH_SIZE );
		$searchParams->set_exclude_manual( true );

		return $searchParams;
	}

	private function hideJobs( \WPML_TM_Jobs_Search_Params $jobsSearchParams, BatchResult $batchResult ) {
		$translationJobs = wpml_collect( wpml_tm_get_jobs_repository()->get( $jobsSearchParams ) )
			->filter( invoke( 'is_automatic' ) );
		$foundJobs = $translationJobs->count();

		if ( self::CANCEL_BATCH_SIZE === $foundJobs ) {
			$batchResult->markHasMore();
		}

		$ateJobs   = $translationJobs->filter( invoke( 'is_ate_editor' ) );
		$ateJobIds = $ateJobs->map( invoke( 'get_editor_job_id' ) )
		                     ->map( function ( $jobId ) {
			                     return (int) $jobId;
		                     } )
		                     ->filter()
		                     ->values()
		                     ->toArray();

		$jobsHiddenInATE   = [];
		$jobsReportedByATE = null;
		if ( $ateJobIds ) {
			$releaseMark = ReleaseLedger::instance()->mark();

			try {
				$response        = $this->apiClient->hideJobs(
					$ateJobIds,
					true,
					\WPML_TM_ATE_API::CANCEL_REASON_CANCELLED
				);
				$confirmedJobIds = AteResponse::getConfirmedJobIds( $response );
				if ( null !== $confirmedJobIds ) {
					$jobsHiddenInATE   = $confirmedJobIds;
					$jobsReportedByATE = AteResponse::getReportedJobIds( $response );
				} else {
					JobLog::addError( 'tea_cancel_all_ate_invalid_response', [] );
				}

				$this->recordRelease( $response, $ateJobIds, self::STEM_CANCEL, [], false );
			} catch ( \Throwable $e ) {
				JobLog::addError( 'tea_cancel_all_ate_request_failed', [
					'error' => $e->getMessage(),
				] );

				$this->recordRelease( null, $ateJobIds, self::STEM_CANCEL, [], false );
			}

			$batchResult->addRelease( ReleaseLedger::instance()->summaryFrom( $releaseMark ) );
		}

		$updateJob       = make( \WPML_TP_Sync_Update_Job::class );
		$processedJobs   = 0;
		$unconfirmedJobs = 0;
		$alreadyFinished = [];

		$translationJobs->each( function ( \WPML_TM_Post_Job_Entity $job ) use ( $jobsHiddenInATE, $jobsReportedByATE, $updateJob, &$processedJobs, &$unconfirmedJobs, &$alreadyFinished ) {
			$ateJobId = (int) $job->get_editor_job_id();
			if ( $job->is_ate_editor() && $ateJobId > 0 ) {
				if ( ! in_array( $ateJobId, $jobsHiddenInATE, true ) ) {
					if ( is_array( $jobsReportedByATE ) && ! in_array( $ateJobId, $jobsReportedByATE, true ) ) {
						$alreadyFinished[] = $ateJobId;

						return;
					}

					$unconfirmedJobs ++;

					return;
				}

				$cancelled = $updateJob->cancel( $job, ICL_TM_ATE_CANCELLED );
			} else {
				$cancelled = $updateJob->cancel( $job, ICL_TM_NOT_TRANSLATED );
			}

			$this->cleanupCancelledStringBatch( $cancelled instanceof \WPML_TM_Job_Entity ? $cancelled : $job );

			$processedJobs ++;
		} );

		if ( $alreadyFinished ) {
			JobLog::add( 'tea_cancel_all_already_translated_in_ate', [
				'ate_job_ids' => $alreadyFinished,
			] );
		}

		$batchResult->addProcessed( $processedJobs );
		if ( $unconfirmedJobs ) {
			$batchResult->addFailure( 'post_jobs_not_confirmed_by_ate' );
			$batchResult->markHasMore();
		}

		return $foundJobs;
	}

	private function cleanupCancelledStringBatch( \WPML_TM_Job_Entity $job ) {
		if ( \WPML_TM_Job_Entity::STRING_BATCH !== $job->get_type() ) {
			return;
		}

		if ( ICL_TM_COMPLETE === (int) $job->get_status() ) {
			return;
		}

		StringBatchCleanupServiceFactory::create()->cleanupBatch(
			(int) $job->get_original_element_id(),
			(string) $job->get_target_language()
		);
	}

	private function cancelOrphanedAutomaticJobs( \WPML_TM_Jobs_Search_Params $jobsSearchParams, BatchResult $batchResult ) {
		$statuses = $jobsSearchParams->get_status();
		if ( empty( $statuses ) ) {
			return;
		}

		global $wpdb;

		$placeholders = implode( ', ', array_fill( 0, count( $statuses ), '%d' ) );

		$sql = "SELECT j.job_id AS job_id, j.editor_job_id AS editor_job_id
			FROM {$wpdb->prefix}icl_translate_job j
			INNER JOIN {$wpdb->prefix}icl_translation_status ts
			        ON ts.rid = j.rid
			LEFT JOIN {$wpdb->prefix}icl_translations t
			       ON t.translation_id = ts.translation_id
			LEFT JOIN {$wpdb->prefix}icl_translations o
			       ON o.trid = t.trid AND o.language_code = t.source_language_code
			WHERE j.automatic = 1
			  AND j.editor = %s
			  AND ts.status IN ( {$placeholders} )
			  AND (
			      t.translation_id IS NULL
			      OR o.translation_id IS NULL
			      OR (
			          o.element_type LIKE 'post\\_%%'
			          AND NOT EXISTS (
			              SELECT 1 FROM {$wpdb->posts} p WHERE p.ID = o.element_id
			          )
			      )
			  )
			ORDER BY j.job_id
			LIMIT %d";

		$rows = $wpdb->get_results(
			$wpdb->prepare(
				$sql,
				array_merge( [ \WPML_TM_Editors::ATE ], array_map( 'intval', $statuses ), [ self::CANCEL_BATCH_SIZE ] )
			)
		);

		if ( ! is_array( $rows ) || empty( $rows ) ) {
			return;
		}
		if ( self::CANCEL_BATCH_SIZE === count( $rows ) ) {
			$batchResult->markHasMore();
		}

		$jobIds = array_map(
			function ( $row ) {
				return (int) $row->job_id;
			},
			$rows
		);

		$ateJobIds = array_values(
			array_unique(
				array_filter(
					array_map(
						function ( $row ) {
							return (int) $row->editor_job_id;
						},
						$rows
					)
				)
			)
		);

		JobLog::add(
			'tea_cancel_all_orphan_sweep',
			[
				'job_ids'     => $jobIds,
				'ate_job_ids' => $ateJobIds,
			]
		);

		$ateAnswered     = true;
		$jobsHiddenInATE = [];
		if ( $ateJobIds ) {
			$ateAnswered = false;
			$releaseMark = ReleaseLedger::instance()->mark();

			try {
				$response        = $this->apiClient->hideJobs(
					$ateJobIds,
					true,
					\WPML_TM_ATE_API::CANCEL_REASON_CANCELLED
				);
				$confirmedJobIds = AteResponse::getConfirmedJobIds( $response );
				if ( null !== $confirmedJobIds ) {
					$jobsHiddenInATE = $confirmedJobIds;
					$ateAnswered     = true;
				} else {
					JobLog::addError( 'tea_cancel_all_orphan_ate_invalid_response', [] );
				}

				$this->recordRelease( $response, $ateJobIds, self::STEM_CANCEL, [], false );
			} catch ( \Throwable $e ) {
				JobLog::addError( 'tea_cancel_all_orphan_ate_request_failed', [
					'error' => $e->getMessage(),
				] );

				$this->recordRelease( null, $ateJobIds, self::STEM_CANCEL, [], false );
			}

			$batchResult->addRelease( ReleaseLedger::instance()->summaryFrom( $releaseMark ) );
		}

		if ( ! $ateAnswered ) {
			$batchResult->addFailure( 'orphan_jobs_not_confirmed_by_ate' );
			$batchResult->markHasMore();

			return;
		}

		$notConfirmedByAte = [];
		foreach ( $rows as $row ) {
			$ateJobId = (int) $row->editor_job_id;
			if ( $ateJobId > 0 && ! in_array( $ateJobId, $jobsHiddenInATE, true ) ) {
				$notConfirmedByAte[] = $ateJobId;
			}
		}

		if ( $notConfirmedByAte ) {
			JobLog::addError( 'tea_cancel_all_orphan_ate_not_confirmed', [
				'ate_job_ids' => $notConfirmedByAte,
			] );
		}

		CancelJobsServiceFactory::create()->cancelJobs( $jobIds );
		$batchResult->addProcessed( count( $jobIds ) );
	}
}
