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
use WPML\TM\ATE\JobCreation\AteCreateReconciler;
use WPML\TM\ATE\JobCreation\CreateFailureText;
use WPML\TM\ATE\NothingToTranslateJob;
use WPML\TM\ATE\Log\Storage;
use WPML\TM\ATE\Log\Entry;
use function WPML\FP\pipe;
use WPML\TM\API\ATE\LanguageMappings;
use WPML\Element\API\Languages;
use WPML\TM\Jobs\JobLog;
use WPML\Translation\AteSyncOrderingServiceFactory;

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
		$inFlightMarkers = [];

		try {
			$this->send_and_bind_jobs( $jobs, $sentFrom, $batch, $inFlightMarkers );
		} finally {
			$this->clear_in_flight_markers( $inFlightMarkers );
		}
	}

	private function send_and_bind_jobs( array $jobs, $sentFrom, $batch, array &$inFlightMarkers ) {
		$additionalErrorMsg            = '';
		$translationModeSetInDashboard = null;
		$created_jobs                  = [];
		$adopted_jobs                  = [];
		$job_ids                       = [];
		$job_ids_without_model         = [];
		$create_error_reported         = false;

		try {
			$oldEditor = wpml_tm_load_old_jobs_editor();
			$job_ids   = Fns::reject( [ $oldEditor, 'shouldStickToWPMLEditor' ], Obj::propOr( [], 'local', $jobs ) );

			if ( ! $job_ids ) {
				return;
			}

			if ( Jobs::SENT_RETRY === $sentFrom ) {
				$reconciled   = $this->reconciler()->reconcile( $job_ids );
				$adopted_jobs = $reconciled['adopted'];

				if ( AteCreateReconciler::OUTCOME_NO_ANSWER === $reconciled['outcome'] ) {
					$this->count_failed_retries( $job_ids );
					$job_ids = [];
				} else {
					$job_ids = array_values( array_diff( $job_ids, array_keys( $adopted_jobs ) ) );
				}

				if ( $adopted_jobs ) {
					ApiCommunication::noteCreateConfirmed();
				}
			}

			$this->write_in_flight_markers( $job_ids, $inFlightMarkers );

			if ( $job_ids ) {
				$applyTranslationMemory = $this->shouldApplyTranslationMemory( $batch );

				$jobs = [];
				foreach ( $job_ids as $jobId ) {
					$model = wpml_tm_create_ATE_job_creation_model( $jobId, $applyTranslationMemory );
					if ( $model ) {
						$jobs[] = $model;
					} else {
						$job_ids_without_model[] = (int) $jobId;
					}
				}

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
				$responses                     = [];
				foreach ( $chunks as $index => $chunk ) {
					$this->refresh_in_flight_markers( $this->job_ids_of_chunks( array_slice( $chunks, $index ) ), $inFlightMarkers );
					$responses[] = $this->create_jobs( $chunk, $sentFrom, $reviewMode );
				}
				$created_jobs = $this->getResponsesJobs( $responses, $chunks, $create_error_reported );
			}
		} catch ( \Throwable $throwable ) {
			$created_jobs       = [];
			$additionalErrorMsg = $this->getErrorMessage( $throwable );
			$this->maybeLogRuntimeError( $additionalErrorMsg );

			if ( empty( $oldEditor ) || ( empty( $job_ids ) && empty( $adopted_jobs ) ) ) {
				return;
			}
		}

		$bound_jobs = $adopted_jobs + ( $created_jobs ? $this->map_response_jobs( $created_jobs ) : [] );

		if ( $bound_jobs ) {
			$modeByJobId = $this->mode_by_job_id( array_keys( $bound_jobs ), $translationModeSetInDashboard );
			foreach ( array_keys( $adopted_jobs ) as $adopted_job_id ) {
				if ( AteCreateReconciler::markedByClone( $adopted_job_id ) ) {
					$modeByJobId[ (int) $adopted_job_id ] = 'manual';
				}
			}

			$this->reconciler()->bind( $bound_jobs, $modeByJobId, $sentFrom );

			if ( $sentFrom === Jobs::SENT_RETRY ) {
				foreach ( array_keys( $bound_jobs ) as $wpml_job_id ) {
					$isAutomatic = isset( $modeByJobId[ $wpml_job_id ] ) && 'auto' === $modeByJobId[ $wpml_job_id ];
					Jobs::setStatus(
						$wpml_job_id,
						$isAutomatic ? ICL_TM_IN_PROGRESS : ICL_TM_WAITING_FOR_TRANSLATOR
					);
				}
			}
		}

		$held_back = $this->held_back_job_ids( $job_ids_without_model );
		if ( $held_back ) {
			do_action( 'wpml_tm_ate_create_held_back', $held_back, $sentFrom );
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
		if ( $unbound && Lst::includes( $sentFrom, [ Jobs::SENT_AUTOMATICALLY, Jobs::SENT_RETRY ] ) ) {
			if ( $sentFrom === Jobs::SENT_RETRY ) {
				$updateJob = function ( $jobId ) {
					Jobs::incrementRetryCount( $jobId );
					$this->logRetryError( $jobId );
				};
			} else {
				$updateJob = function ( $jobId ) use ( $oldEditor, $additionalErrorMsg ) {
					$this->logError( $jobId, $additionalErrorMsg );

					Jobs::setStatus( $jobId, ICL_TM_ATE_NEEDS_RETRY );
					$oldEditor->set( $jobId, WPML_TM_Editors::ATE );
					wpml_tm_load_job_factory()->update_job_data( $jobId, [ 'automatic' => 1 ] );
				};
			}

			wpml_collect( $unbound )->map( $updateJob );
		}
	}

	private function held_back_job_ids( array $jobIdsWithoutModel ) {
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
				function ( $jobId ) use ( $failures ) {
					return isset( $failures[ $jobId ] );
				}
			)
		);
	}

	private function reconciler() {
		if ( ! $this->reconciler ) {
			$this->reconciler = new AteCreateReconciler( $this->ate_api, $this->ate_jobs );
		}

		return $this->reconciler;
	}

	private function count_failed_retries( array $job_ids ) {
		foreach ( $job_ids as $job_id ) {
			Jobs::incrementRetryCount( $job_id );
			$this->logRetryError( $job_id );
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
			$jobId = \WPML\TM\API\Job\Map::fromRid( $rid );
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

		if ( ( $isUserActivated && $canUseAutomatic ) || is_admin() ) {
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
