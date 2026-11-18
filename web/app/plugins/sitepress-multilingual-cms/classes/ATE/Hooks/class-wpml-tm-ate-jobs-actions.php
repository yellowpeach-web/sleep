<?php

use WPML\API\Sanitize;
use WPML\FP\Fns;
use WPML\FP\Json;
use WPML\FP\Logic;
use WPML\FP\Lst;
use WPML\FP\Obj;
use WPML\FP\Str;
use WPML\FP\Relation;
use WPML\TM\API\Jobs;
use WPML\FP\Wrapper;
use WPML\Settings\PostType\Automatic;
use WPML\Setup\Option;
use WPML\TM\ATE\ClonedSites\ApiCommunication;
use WPML\TM\AutomaticTranslation\Actions\DeferredDispatch;
use WPML\TranslationPolicy\Policy\Policy;
use WPML\TM\ATE\JobCreation\AteCreateReconciler;
use WPML\TM\ATE\JobCreation\CreateFailureRecord;
use WPML\TM\ATE\JobCreation\CreateFailureText;
use WPML\TM\ATE\JobCreation\LifetimeMaxRollback;
use WPML\TM\ATE\NothingToTranslateJob;
use WPML\TM\ATE\Log\Storage;
use WPML\TM\ATE\Log\Entry;
use function WPML\FP\pipe;
use WPML\TM\API\ATE\LanguageMappings;
use WPML\Element\API\Languages;
use WPML\TM\Jobs\JobLog;
use WPML\Translation\AteSyncOrderingServiceFactory;
use WPML\Core\Component\Translation\Application\Service\TranslationService;

class WPML_TM_ATE_Jobs_Actions implements IWPML_Action {
	const ATE_RECORD_SPENT_STATUSES = AteCreateReconciler::ATE_RECORD_SPENT_STATUSES;

	const IN_FLIGHT_MARKER_TTL = 120;

	const IN_FLIGHT_MARKER_REFRESH_BELOW = 90;

	const RESPONSE_ATE_NOT_ACTIVE_ERROR     = 403;
	const RESPONSE_ATE_DUPLICATED_SOURCE_ID = 417;
	const RESPONSE_ATE_CLIENT_RESTRICTED    = 428;
	const RESPONSE_ATE_UNEXPECTED_ERROR     = 500;

	const RESPONSE_ATE_ERROR_NOTICE_ID    = 'ate-update-error';
	const RESPONSE_ATE_ERROR_NOTICE_GROUP = 'default';

	const CREATE_ATE_JOB_CHUNK_WORDS_LIMIT = 2000;

	private $ate_api;
	private $ate_jobs;

	private $translator_activation_records;

	private $is_second_attempt_to_get_jobs_data = false;
	private $sitepress;
	private $current_screen;

	private $wp_api;

	private $reconciler;

	public function __construct(
		WPML_TM_ATE_API $ate_api,
		WPML_TM_ATE_Jobs $ate_jobs,
		SitePress $sitepress,
		WPML_Current_Screen $current_screen,
		WPML_TM_AMS_Translator_Activation_Records $translator_activation_records,
		WPML_WP_API $wp_api
	) {
		$this->ate_api                       = $ate_api;
		$this->ate_jobs                      = $ate_jobs;
		$this->sitepress                     = $sitepress;
		$this->current_screen                = $current_screen;
		$this->translator_activation_records = $translator_activation_records;
		$this->wp_api                        = $wp_api;
	}

	public function add_hooks() {
		add_action( 'wpml_added_translation_job', [ $this, 'added_translation_job' ], 10, 2 );
		add_action( 'wpml_added_translation_jobs', [ $this, 'added_translation_jobs' ], 10, 3 );

		if ( ! $this->wp_api->is_front_end() ) {
			add_action( 'admin_notices', [ $this, 'handle_messages' ] );

			add_filter( 'wpml_tm_ate_jobs_data', [ $this, 'get_ate_jobs_data_filter' ], 10, 2 );
			add_filter( 'wpml_tm_ate_jobs_editor_url', [ $this, 'get_editor_url' ], 10, 3 );
		}
	}

	public function handle_messages() {
		if ( $this->current_screen->id_ends_with( WPML_TM_FOLDER . '/menu/translations-queue' ) ) {

			if ( array_key_exists( 'message', $_GET ) ) {
				$message = Sanitize::stringProp( 'message', $_GET );
				?>

				<div class="error notice-error notice otgs-notice">
					<p><?php echo $message; ?></p>
				</div>

				<?php
			}
		}
	}

	public function added_translation_job( $job_id, $translation_service ) {
		$this->added_translation_jobs( array( $translation_service => array( $job_id ) ) );
	}

	public function added_translation_jobs( array $jobs, $sentFrom = null, ?\WPML_TM_Translation_Batch $batch = null ) {
		$localJobIds = array_map( 'intval', (array) Obj::propOr( [], 'local', $jobs ) );

		if ( Jobs::SENT_AUTOMATICALLY === (int) $sentFrom && DeferredDispatch::isCollecting() ) {
			$toAte = array_values( Fns::reject( [ $this, 'stays_with_wpml_editor' ], $localJobIds ) );
			if ( $toAte ) {
				DeferredDispatch::deferCreate( $toAte );
			}

			return;
		}

		if ( Jobs::SENT_RETRY === (int) $sentFrom ) {
			DeferredDispatch::forgetSending( $localJobIds );
		}

		$inFlightMarkers = [];
		$lifetimeMax = new LifetimeMaxRollback();

		try {
			$this->send_and_bind_jobs( $jobs, $sentFrom, $batch, $inFlightMarkers, $lifetimeMax );
		} finally {
			$this->clear_in_flight_markers( $inFlightMarkers );
			$lifetimeMax->finish();
		}
	}

	public function stays_with_wpml_editor( $job_id ) {
		return Policy::translateEverythingGate()->dropsAtSend(
			(int) $job_id,
			function () use ( $job_id ) {
				return wpml_tm_load_old_jobs_editor()->shouldStickToWPMLEditor( $job_id );
			}
		);
	}

	private function send_and_bind_jobs( array $jobs, $sentFrom, $batch, array &$inFlightMarkers, LifetimeMaxRollback $lifetimeMax ) {
		$additionalErrorMsg            = '';
		$translationModeSetInDashboard = null;
		$created_bound                 = [];
		$adopted_jobs                  = [];
		$job_ids                       = [];
		$job_ids_without_model         = [];
		$create_error_reported         = false;
		$failure_by_job                = [];

		try {
			$oldEditor = wpml_tm_load_old_jobs_editor();
			$policy    = Policy::reader();
			$gate      = Policy::translateEverythingGate();
			$sendMode  = Jobs::SENT_VIA_DASHBOARD === (int) $sentFrom ? (string) $this->getTranslationModeFromBatch( $batch ) : '';
			$job_ids   = Fns::reject(
				function ( $jobId ) use ( $policy, $gate, $sendMode ) {
					return $gate->dropsAtSend(
						(int) $jobId,
						function () use ( $policy, $jobId, $sendMode ) {
							return $policy->jobSticksToClassicEditor( (int) $jobId, $sendMode );
						}
					);
				},
				Obj::propOr( [], 'local', $jobs )
			);

			if ( ! $job_ids ) {
				return;
			}

			if ( Jobs::SENT_RETRY === $sentFrom ) {
				$reconciled   = $this->reconciler()->reconcile( $job_ids );
				$adopted_jobs = $reconciled['adopted'];

				if ( AteCreateReconciler::OUTCOME_NO_ANSWER === $reconciled['outcome'] ) {
					if ( CreateFailureRecord::isGated( $reconciled['error'] ) ) {
						JobLog::add( 'ate_retry_turn_gated', [ 'job_ids' => array_map( 'intval', $job_ids ) ] );
					} else {
						$this->count_failed_retries( $job_ids, $reconciled['error'] );
					}
					$job_ids = [];
				} else {
					$job_ids = array_values( array_diff( $job_ids, array_keys( $adopted_jobs ) ) );
				}

				if ( $adopted_jobs ) {
					ApiCommunication::noteCreateConfirmed();
				}

				$this->bind_jobs( $adopted_jobs, $adopted_jobs, $this->getTranslationModeFromBatch( $batch ), $sentFrom, $lifetimeMax );
			}

			$this->write_in_flight_markers( $job_ids, $inFlightMarkers );

			$lifetimeMax->remember( $job_ids );

			if ( $job_ids ) {
				$applyTranslationMemory = $this->shouldApplyTranslationMemory( $batch );
				$priceRequired = 'manual' !== $this->getTranslationModeFromBatch( $batch );

				$jobs = [];
				foreach ( $job_ids as $jobId ) {
					$heldSend = $batch ? null : AteCreateReconciler::heldSend( $jobId );
					$model    = $heldSend
						? wpml_tm_create_ATE_job_creation_model( $jobId, $heldSend['apply_memory'], 'manual' !== $heldSend['mode'] )
						: wpml_tm_create_ATE_job_creation_model( $jobId, $applyTranslationMemory, $priceRequired );
					if ( $model ) {
						$jobs[] = $model;
					} else {
						$job_ids_without_model[] = (int) $jobId;
					}
				}

				$this->keep_held_sends( $job_ids_without_model, $batch );

				list( $jobsWithNothingToTranslate, $jobs ) = Lst::partition(
					[ NothingToTranslateJob::class, 'isJobModel' ],
					$jobs
				);

				if ( $jobsWithNothingToTranslate ) {
					$emptyJobIds = [];
					foreach ( $jobsWithNothingToTranslate as $emptyJobModel ) {
						$this->complete_job_with_nothing_to_translate( (int) $emptyJobModel->id );
						$emptyJobIds[] = $emptyJobModel->id;
					}

					$job_ids = array_values( array_diff( $job_ids, $emptyJobIds ) );

					if ( ! $jobs ) {
						if ( ! $adopted_jobs ) {
							return;
						}

						$job_ids = [];
					}
				}
			}

			if ( $job_ids ) {
				$translationModeSetInDashboard = $this->getTranslationModeFromBatch( $batch );
				$reviewMode                    = $batch && $batch->getReviewMode() ? $batch->getReviewMode() : Option::getReviewMode();
				$chunks                        = array_values( $this->getChunkedJobs( $jobs, $translationModeSetInDashboard ) );
				foreach ( $chunks as $index => $chunk ) {
					$this->refresh_in_flight_markers( $this->job_ids_of_chunks( array_slice( $chunks, $index ) ), $inFlightMarkers );
					if ( ! $this->ate_api->isTransportBreakerOpen() ) {
						$lifetimeMax->sent( $this->job_ids_of_chunks( [ $chunk ] ) );
					}
					$response = $this->create_jobs( $chunk, $sentFrom, $reviewMode );
					if ( $response instanceof \WP_Error ) {
						foreach ( $this->job_ids_of_chunks( [ $chunk ] ) as $chunk_job_id ) {
							$failure_by_job[ $chunk_job_id ] = $response;
						}
					}

					$chunk_created = $this->getResponsesJobs( [ $response ], [ $chunk ], $create_error_reported );
					$chunk_bound   = [];
					if ( $chunk_created ) {
						$chunk_bound   = $this->map_response_jobs( $chunk_created );
						$created_bound = $created_bound + $chunk_bound;
						$this->bind_jobs( $chunk_bound, [], $translationModeSetInDashboard, $sentFrom, $lifetimeMax );
					}
					if ( ! $response instanceof \WP_Error ) {
						\WPML\TM\ATE\Handshake\Reports::add(
							\WPML\TM\ATE\Handshake\Reports::CREATE_ACK,
							[
								'ate_ids'  => array_values( array_map( 'intval', $chunk_bound ) ),
								'answered' => count( (array) $chunk_created ),
							]
						);
					}
				}
			}
		} catch ( \Throwable $throwable ) {
			$additionalErrorMsg = $this->getErrorMessage( $throwable );
			$this->maybeLogRuntimeError( $additionalErrorMsg );

			if ( empty( $oldEditor ) || ( empty( $job_ids ) && empty( $adopted_jobs ) ) ) {
				return;
			}
		}

		$bound_jobs = $adopted_jobs + $created_bound;

		if ( $bound_jobs ) {
			AteCreateReconciler::addedMessage( count( $bound_jobs ) );
		}

		$held_back       = $this->held_back_job_ids( $job_ids_without_model );
		$held_for_prices = $this->held_back_job_ids( $job_ids_without_model, TranslationService::PRICES_UNAVAILABLE );
		$held_for_counts = array_values( array_diff( $held_back, $held_for_prices ) );
		if ( $held_for_counts ) {
			do_action( 'wpml_tm_ate_create_held_back', $held_for_counts, $sentFrom );
		}
		if ( $held_for_prices ) {
			do_action( 'wpml_tm_ate_create_held_back_prices', $held_for_prices, $sentFrom );
		}

		$not_created = array_values(
			array_diff( array_map( 'intval', $job_ids ), array_map( 'intval', array_keys( $bound_jobs ) ), $held_back )
		);
		if ( $not_created ) {
			if ( Jobs::SENT_VIA_DASHBOARD === (int) $sentFrom ) {
				$modeByJobId = $this->mode_by_job_id( $not_created, $translationModeSetInDashboard );
				foreach ( $not_created as $not_created_job_id ) {
					update_option(
						AteCreateReconciler::UNCONFIRMED_OPTION_PREFIX . $not_created_job_id,
						[
							'at'   => time(),
							'mode' => isset( $modeByJobId[ $not_created_job_id ] ) ? $modeByJobId[ $not_created_job_id ] : '',
						],
						false
					);
				}
			}

			do_action( 'wpml_tm_ate_create_failed', $not_created, $sentFrom );

			if (
				Jobs::SENT_VIA_DASHBOARD === (int) $sentFrom
				&& ! $create_error_reported
				&& array_diff( $not_created, $job_ids_without_model )
			) {
				do_action(
					'wpml_tm_add_message',
					'error',
					/* translators: The reason shown in the dialog that reports what was sent for translation, for items whose sending WPML could not confirm. */
					__( 'WPML could not confirm that these items reached the translation server.', 'sitepress' )
				);
			}
		}

		$unbound = array_values(
			array_diff( array_map( 'intval', $job_ids ), array_map( 'intval', array_keys( $bound_jobs ) ) )
		);

		$this->keep_held_sends( array_values( array_diff( $unbound, $job_ids_without_model ) ), $batch );

		if ( $unbound && Lst::includes( $sentFrom, [ Jobs::SENT_AUTOMATICALLY, Jobs::SENT_RETRY ] ) ) {
			if ( $sentFrom === Jobs::SENT_RETRY ) {
				$updateJob = function ( $jobId ) use ( $failure_by_job ) {
					if ( isset( $failure_by_job[ $jobId ] ) && CreateFailureRecord::isGated( $failure_by_job[ $jobId ] ) ) {
						return;
					}
					Jobs::incrementRetryCount( $jobId );
					$this->logRetryError( $jobId );
				};
			} else {
				$updateJob = function ( $jobId ) use ( $oldEditor, $additionalErrorMsg ) {
					$this->logError( $jobId, $additionalErrorMsg );

					Jobs::setStatus( $jobId, ICL_TM_ATE_NEEDS_RETRY );
					Jobs::resetRetryCount( $jobId );
					$oldEditor->set( $jobId, WPML_TM_Editors::ATE );
					wpml_tm_load_job_factory()->update_job_data( $jobId, [ 'automatic' => 1 ] );
				};
			}

			wpml_collect( $unbound )->map( $updateJob );

			$this->record_create_failures(
				array_values( array_diff( $unbound, $held_back ) ),
				$failure_by_job,
				$additionalErrorMsg,
				$job_ids_without_model,
				$sentFrom
			);
		}
	}

	private function record_create_failures( array $jobIds, array $failureByJob, $additionalErrorMsg, array $jobIdsWithoutModel, $sentFrom ) {
		foreach ( $jobIds as $jobId ) {
			$failure = isset( $failureByJob[ $jobId ] ) ? $failureByJob[ $jobId ] : null;

			if ( $failure ) {
				if ( Jobs::SENT_RETRY === $sentFrom && CreateFailureRecord::isGated( $failure ) ) {
					continue;
				}
				CreateFailureRecord::write( $jobId, CreateFailureRecord::kindOf( $failure ), CreateFailureRecord::messageOf( $failure ) );
			} elseif ( $additionalErrorMsg ) {
				CreateFailureRecord::write( $jobId, CreateFailureRecord::KIND_ERROR, $additionalErrorMsg );
			} elseif ( in_array( (int) $jobId, array_map( 'intval', $jobIdsWithoutModel ), true ) ) {
				CreateFailureRecord::write( $jobId, CreateFailureRecord::KIND_ERROR, 'The job could not be loaded.' );
			} else {
				CreateFailureRecord::write( $jobId, CreateFailureRecord::KIND_REFUSED, 'The answer to the create did not include this job.' );
			}
		}
	}

	private function bind_jobs( array $bound, array $adopted, $translationModeSetInDashboard, $sentFrom, LifetimeMaxRollback $lifetimeMax ) {
		if ( ! $bound ) {
			return;
		}

		$modeByJobId = $this->mode_by_job_id( array_keys( $bound ), $translationModeSetInDashboard );
		foreach ( array_keys( $adopted ) as $adopted_job_id ) {
			if ( AteCreateReconciler::markedByClone( $adopted_job_id ) ) {
				$modeByJobId[ (int) $adopted_job_id ] = 'manual';
			}
		}

		$lifetimeMax->confirmed( array_keys( $bound ) );

		$this->reconciler()->bind( $bound, $modeByJobId, $sentFrom, false );

		if ( $sentFrom === Jobs::SENT_RETRY ) {
			foreach ( array_keys( $bound ) as $wpml_job_id ) {
				$isAutomatic = isset( $modeByJobId[ $wpml_job_id ] ) && 'auto' === $modeByJobId[ $wpml_job_id ];
				Jobs::setStatus(
					$wpml_job_id,
					$isAutomatic ? ICL_TM_IN_PROGRESS : ICL_TM_WAITING_FOR_TRANSLATOR
				);
			}
		}
	}

	private function held_back_job_ids( array $jobIdsWithoutModel, $reason = null ) {
		if ( ! $jobIdsWithoutModel ) {
			return [];
		}

		$failures = get_option( 'wpml_words_to_translate_failures', [] );
		if ( ! is_array( $failures ) ) {
			return [];
		}

		return array_values(
			array_filter(
				array_map( 'intval', $jobIdsWithoutModel ),
				function ( $jobId ) use ( $failures, $reason ) {
					if ( ! isset( $failures[ $jobId ] ) ) {
						return false;
					}

					return null === $reason
						|| ( is_array( $failures[ $jobId ] ) && $reason === ( $failures[ $jobId ]['reason'] ?? null ) );
				}
			)
		);
	}

	private function keep_held_sends( array $jobIds, $batch ) {
		$mode = $this->getTranslationModeFromBatch( $batch );
		if ( ! $jobIds || ! $mode ) {
			return;
		}

		$applyMemory = $this->shouldApplyTranslationMemory( $batch );
		foreach ( $jobIds as $jobId ) {
			AteCreateReconciler::keepHeldSend( $jobId, $mode, $applyMemory );
		}
	}

	private function reconciler() {
		if ( ! $this->reconciler ) {
			$this->reconciler = new AteCreateReconciler( $this->ate_api, $this->ate_jobs );
		}

		return $this->reconciler;
	}

	private function count_failed_retries( array $job_ids, $error = null ) {
		foreach ( $job_ids as $job_id ) {
			Jobs::incrementRetryCount( $job_id );
			$this->logRetryError( $job_id );
			if ( $error instanceof \WP_Error ) {
				CreateFailureRecord::write( $job_id, CreateFailureRecord::kindOf( $error ), CreateFailureRecord::messageOf( $error ) );
			}
		}
	}

	private function mode_by_job_id( array $wpml_job_ids, $translationModeSetInDashboard ) {
		$modes = [];
		foreach ( $wpml_job_ids as $wpml_job_id ) {
			$translationJob = wpml_tm_load_job_factory()->get_translation_job( $wpml_job_id, false, 0, true );
			if ( $translationJob ) {
				$modes[ (int) $wpml_job_id ] = AteCreateReconciler::jobTypeOf( $translationJob, $translationModeSetInDashboard );
			}
		}

		return $modes;
	}

	private function write_in_flight_markers( array $job_ids, array &$inFlightMarkers ) {
		$expiry = time() + self::IN_FLIGHT_MARKER_TTL;
		foreach ( $job_ids as $job_id ) {
			$job_id = (int) $job_id;
			$name   = AteCreateReconciler::IN_FLIGHT_OPTION_PREFIX . $job_id;
			wp_cache_delete( $name, 'options' );
			update_option( $name, $expiry, false );
			$inFlightMarkers[ $job_id ] = $expiry;
		}
	}

	private function refresh_in_flight_markers( array $job_ids, array &$inFlightMarkers ) {
		$writeBelow = time() + self::IN_FLIGHT_MARKER_REFRESH_BELOW;
		$due        = [];
		foreach ( $job_ids as $job_id ) {
			$job_id = (int) $job_id;
			if ( ! isset( $inFlightMarkers[ $job_id ] ) || $inFlightMarkers[ $job_id ] < $writeBelow ) {
				$due[] = $job_id;
			}
		}

		$this->write_in_flight_markers( $due, $inFlightMarkers );
	}

	private function clear_in_flight_markers( array $inFlightMarkers ) {
		foreach ( array_keys( $inFlightMarkers ) as $job_id ) {
			delete_option( AteCreateReconciler::IN_FLIGHT_OPTION_PREFIX . $job_id );
		}
	}

	private function job_ids_of_chunks( array $chunks ) {
		$job_ids = [];
		foreach ( $chunks as $chunk ) {
			foreach ( $chunk['jobs'] as $job ) {
				$job_id = is_object( $job ) ? ( $job->id ?? null ) : ( $job['id'] ?? null );
				if ( $job_id ) {
					$job_ids[] = (int) $job_id;
				}
			}
		}

		return $job_ids;
	}

	private function complete_job_with_nothing_to_translate( $jobId ) {
		try {
			NothingToTranslateJob::completeJob( $jobId );
		} catch ( \Throwable $throwable ) {
			$this->maybeLogRuntimeError( $this->getErrorMessage( $throwable ) );
		}
	}

	private function map_response_jobs( $responseJobs ) {
		$result = [];
		foreach ( $responseJobs as $rid => $ate_job_id ) {
			$jobId = \WPML\TM\API\Job\Map::jobIdFromRid( $rid );
			if ( $jobId ) {
				$result[ $jobId ] = $ate_job_id;
			}
		}

		return $result;
	}

	public function create_jobs( array $jobsData, $sentFrom, $reviewMode = null ) {
		$setJobType = Logic::ifElse( Fns::always( $sentFrom ), Obj::assoc( 'job_type', $sentFrom ), Fns::identity() );

		list( $existing, $new ) = Lst::partition(
			pipe( Obj::propOr( null, 'existing_ate_id' ), Logic::isNotNull() ),
			$jobsData['jobs']
		);

		$isAuto = Relation::propEq( 'type', 'auto', $jobsData );

		$ordering = $this->buildOrderingForJobs( $jobsData['jobs'] );

		return Wrapper::of( [ 'jobs' => $new, 'existing_jobs' => Lst::pluck( 'existing_ate_id', $existing ) ] )
			->map( Obj::assoc( 'auto_translate', $isAuto ) )
			->map( Obj::assoc( 'preview', $isAuto && Option::NO_REVIEW !== ( $reviewMode ?: Option::getReviewMode() ) ) )
			->map( $setJobType )
			->map( $this->addOrderingToPayload( $ordering ) )
			->map( 'wp_json_encode' )
			->map( Json::toArray() )
			->map( [ $this->ate_api, 'create_jobs' ] )
			->get();
	}

	private function buildOrderingForJobs( array $jobs ): array {
		$postIds    = [];
		$stringIds  = [];
		$packageIds = [];
		$jobFactory = wpml_tm_load_job_factory();

		foreach ( $jobs as $job ) {
			$jobId = is_object( $job ) ? ( $job->id ?? null ) : ( $job['id'] ?? null );
			if ( ! $jobId ) {
				continue;
			}

			$translationJob = $jobFactory->get_translation_job( $jobId, false, 0, true );
			if ( $translationJob instanceof \WPML_Post_Translation_Job ) {
				$postIds[] = $translationJob->get_original_element_id();
			} elseif ( $translationJob instanceof \WPML_Package_Translation_Job ) {
				$packageIds[] = $translationJob->get_original_element_id();
			} elseif ( strpos( $translationJob->get_basic_data_property( 'original_post_type' ) ?? '', 'st-batch_' ) === 0 ) {
				$stringIds[] = $translationJob->get_original_element_id();
			}
		}

		if ( empty( $postIds ) && empty( $stringIds ) && empty( $packageIds ) ) {
			return [];
		}

		$orderingService = AteSyncOrderingServiceFactory::create();
		return $orderingService->getOrderingPayloadArrayForPosts(
			array_unique( $postIds ),
			array_unique( $stringIds ),
			array_unique( $packageIds )
		);
	}

	private function addOrderingToPayload( array $ordering ): callable {
		return function ( $payload ) use ( $ordering ) {
			if ( ! empty( $ordering['positions'] ) ) {
				$payload['ordering'] = $ordering;
			}
			return $payload;
		};
	}

	private function get_ate_jobs_data( array $translation_jobs ) {
		$ate_jobs_data      = array();
		$skip_getting_data  = false;
		$ate_jobs_to_create = array();

		$this->ate_jobs->warm_cache( wpml_collect( $translation_jobs )->pluck( 'job_id' )->toArray() );

		foreach ( $translation_jobs as $translation_job ) {
			if ( $this->is_ate_translation_job( $translation_job ) ) {
				$ate_job_id = $this->get_ate_job_id( $translation_job->job_id );
				if ( ! $ate_job_id ) {
					$ate_jobs_to_create[] = $translation_job->job_id;
					$skip_getting_data    = true;
				}

				if ( ! $skip_getting_data ) {
					$ate_jobs_data[ $translation_job->job_id ] = [ 'ate_job_id' => $ate_job_id ];
				}
			}
		}

		if (
			! $this->is_second_attempt_to_get_jobs_data &&
			$ate_jobs_to_create
		) {
			$this->added_translation_jobs( array( 'local' => $ate_jobs_to_create ) );
			$ate_jobs_data                            = $this->get_ate_jobs_data( $translation_jobs );
			$this->is_second_attempt_to_get_jobs_data = true;
		}

		return $ate_jobs_data;
	}

	public function get_editor_url( $default_url, $job_id, $return_url = null ) {
		$isUserActivated = $this->translator_activation_records->is_current_user_activated();

		$canUseAutomatic = \WPML\TM\Jobs\EditAccess\AutomaticTranslationPermission::isAllowedForCurrentUser();

		$isTranslateRequest = \WPML\TM\Editor\TranslateRequestContext::isActive();

		if ( ( $isUserActivated && $canUseAutomatic ) || is_admin() || $isTranslateRequest ) {
			$ate_job_id = $this->ate_jobs->get_ate_job_id( $job_id );
			if ( $ate_job_id ) {
				if ( ! $return_url ) {
					$return_url = add_query_arg(
						array(
							'page' => WPML_TM_FOLDER . '/menu/main.php',
							'tab'  => 'tasks',
						),
						admin_url( '/admin.php' )
					);
				}

				$return_url = add_query_arg( 'ate-return-job', $job_id, $return_url );

				$return_url  = \WPML\TM\ATE\ReturnToken::sign( $return_url, $job_id );
				$ate_job_url = $this->ate_api->get_editor_url( $ate_job_id, $return_url );
				if ( $ate_job_url && ! is_wp_error( $ate_job_url ) ) {
					return $ate_job_url;
				}
			}
		}

		return $default_url;
	}

	public function get_ate_jobs_data_filter( $ignore, array $translation_jobs ) {
		return $this->get_ate_jobs_data( $translation_jobs );
	}

	private function get_ate_job_id( $job_id ) {
		return $this->ate_jobs->get_ate_job_id( $job_id );
	}

	protected function check_response_error( $response ) {
		if ( is_wp_error( $response ) ) {
			$message = CreateFailureText::from( $response );
			$code    = CreateFailureText::statusOf( $response );
			throw new RuntimeException( $message, $code );
		}
	}

	private function is_ate_translation_job( $translation_job ) {
		return 'local' === $translation_job->translation_service
			   && WPML_TM_Editors::ATE === $translation_job->editor;
	}

	private function getResponsesJobs( $responses, $chunks, &$errorReported = false ) {
		$jobs     = [];
		$existing = [];

		foreach ( $responses as $index => $response ) {
			try {
				$this->check_response_error( $response );
			} catch ( RuntimeException $ex ) {
				do_action( 'wpml_tm_add_message', 'error', $ex->getMessage() );
				$errorReported = true;

				continue;
			}

			if ( $response && isset( $response->jobs ) ) {
				$jobs = $jobs + (array) $response->jobs;
			}

			$existing = $existing + wpml_collect( isset( $chunks[ $index ]['jobs'] ) ? $chunks[ $index ]['jobs'] : [] )
				->filter( Obj::prop( 'existing_ate_id' ) )
				->map( Obj::pick( [ 'source_id', 'existing_ate_id' ] ) )
				->keyBy( 'source_id' )
				->map( Obj::prop( 'existing_ate_id' ) )
				->toArray();
		}

		return $jobs + $existing;
	}

	private function getChunkedJobs( $jobs, $translationModeSetInDashboard ) {
		usort(
			$jobs,
			function ( $a, $b ) {
				$tierA = isset( $a->tier ) ? (int) $a->tier : PHP_INT_MAX;
				$tierB = isset( $b->tier ) ? (int) $b->tier : PHP_INT_MAX;
				if ( $tierA !== $tierB ) {
					return $tierA - $tierB;
				}
				$rankA = $a->rank ?? [];
				$rankB = $b->rank ?? [];
				foreach ( array_keys( array_merge( $rankA, $rankB ) ) as $i ) {
					$ra = $rankA[ $i ] ?? PHP_INT_MAX;
					$rb = $rankB[ $i ] ?? PHP_INT_MAX;
					if ( $ra !== $rb ) {
						return $ra <=> $rb;
					}
				}
				return 0;
			}
		);
		$chunkedJobs      = [];
		$currentChunk     = -1;
		$currentWordCount = 0;
		$chunkType = 'auto';

		$newChunk = function( $chunkType ) use ( &$chunkedJobs, &$currentChunk, &$currentWordCount ) {
			$currentChunk ++;
			$currentWordCount             = 0;
			$chunkedJobs[ $currentChunk ] = [ 'type' => $chunkType, 'jobs' => [] ];
		};

		$newChunk( $chunkType );

		foreach ( $jobs as $job ) {

			$translationJob = wpml_tm_load_job_factory()->get_translation_job( $job->id, false, 0, true );
			if ( $translationJob ) {

				$jobWords = Obj::prop( 'existing_ate_id', $job )
					? 0
					: $this->getJobSizeInWords( $translationJob );

				$currentWordCount += $jobWords;

				$jobType = AteCreateReconciler::jobTypeOf( $translationJob, $translationModeSetInDashboard );
				if ( $jobType !== $chunkType ) {
					$chunkType = $jobType;
					$newChunk( $chunkType );
					$currentWordCount = $jobWords;
				}
				if (
					$currentWordCount > self::CREATE_ATE_JOB_CHUNK_WORDS_LIMIT
					&& count( $chunkedJobs[ $currentChunk ]['jobs'] ) > 0
				) {
					$newChunk( $chunkType );
					$currentWordCount = $jobWords;
				}
			}

			$chunkedJobs[ $currentChunk ]['jobs'] [] = $job;

		}

		$hasJobs = pipe( Obj::prop( 'jobs' ), Lst::length() );

		return Fns::filter( $hasJobs, $chunkedJobs );
	}

	private function getJobSizeInWords( $translationJob ) {
		return (int) $translationJob->estimate_word_count();
	}

	private function logRetryError( $jobId ) {
		$job = Jobs::get( $jobId );

		if ( isset( $job->ate_comm_retry_count ) && $job->ate_comm_retry_count ) {
			Storage::add( Entry::retryJob( $jobId,
				[
					'retry_count' => $job->ate_comm_retry_count
				]
			) );
		}
	}

	private function logError( $jobId, string $additionalErrorMsg = '' ) {
		$job = Jobs::get( $jobId );
		if ( $job ) {
			$extraData = [
				'retry_count' => 0,
				'comment'     => 'Sending job to ate failed, queued to be sent again.',
			];
			if ( $additionalErrorMsg ) {
				$extraData['errorMessage'] = $additionalErrorMsg;
			}

			Storage::add( Entry::retryJob( $jobId, $extraData ) );
		}
	}

	private function shouldApplyTranslationMemory(?\WPML_TM_Translation_Batch $batch = null) {
		if ( $this->getTranslationModeFromBatch( $batch ) === 'auto' ) {
			return ! ( $batch &&  $batch->getHowToHandleExisting() === \WPML_TM_Translation_Batch::HANDLE_EXISTING_OVERRIDE );
		} else {
			return true;
		}
	}

	private function getTranslationModeFromBatch(?\WPML_TM_Translation_Batch $batch = null) {
		return $batch ? $batch->getTranslationMode() : null;
	}

	private function maybeLogRuntimeError( string $message ) {
		JobLog::addError(
			'WPML_TM_ATE_Jobs_Actions error',
			[
				'message' => $message,
			]
		);

		if ( $this->wp_api->constant( 'WP_DEBUG' ) ) {
			$this->wp_api->error_log( $message );
		}
	}

	private function getErrorMessage( \Throwable $throwable ): string {
		return 'Error: ' . $throwable->getMessage() . ' in ' . $throwable->getFile() . ':' . $throwable->getLine();
	}
}
