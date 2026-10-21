<?php

namespace WPML\TM\API;

use Throwable;
use WPML\FP\Obj;
use WPML\FP\Relation;
use WPML\LIB\WP\Post;
use WPML\TM\ATE\API\RequestException;
use WPML\TM\ATE\Download\OrphanPostCleaner;
use WPML\TM\ATE\JobError\Recorder;
use WPML\TM\ATE\TranslateEverything\TaxonomyTermJobApplier;
use WPML\TM\Jobs\JobLog;
use WPML_TM_ATE_API;
use WPML_TM_ATE_Jobs;
use function WPML\FP\pipe;

class ATE {
	private $ateApi;

	private $ateJobs;

	private $orphanPostCleaner;

	private $jobErrorRecorder;

	private $getStatus;

	public function __construct(
		WPML_TM_ATE_API $ateApi,
		WPML_TM_ATE_Jobs $ateJobs,
		OrphanPostCleaner $orphanPostCleaner,
		Recorder $jobErrorRecorder,
		$getStatus = null
	) {
		$this->ateApi            = $ateApi;
		$this->ateJobs           = $ateJobs;
		$this->orphanPostCleaner = $orphanPostCleaner;
		$this->jobErrorRecorder  = $jobErrorRecorder;
		$this->getStatus         = $getStatus ?: [ Jobs::class, 'getStatus' ];
	}

	public function checkJobStatus( $wpmlJobId, array $options = [] ) {
		$ateJobId = $this->ateJobs->get_ate_job_id( $wpmlJobId );
		$response = empty( $options['bypassBreaker'] )
			? $this->ateApi->get_job_status_with_priority( $ateJobId )
			: $this->ateApi->get_job_status_with_priority(
				$ateJobId,
				[ WPML_TM_ATE_API::BYPASS_BREAKER_ARG => true ]
			);

		if ( is_wp_error( $response ) ) {
			return [];
		}

		$encoded = wp_json_encode( $response );
		if ( ! $encoded ) {
			return [];
		}

		return wpml_collect( json_decode( $encoded, true ) )
			->filter( fn( $item ) => is_array( $item ) )
			->first(
				pipe(
					Obj::prop( 'ate_job_id' ),
					Relation::equals( $ateJobId )
				)
			);
	}

	public function applyTranslation( $wpmlJobId, $postId, $xliffUrl ) {
		$ateJobId = $this->ateJobs->get_ate_job_id( $wpmlJobId );

		try {
			$xliffContent = $this->ateApi->get_remote_xliff_content(
				$xliffUrl,
				[ 'jobId' => $wpmlJobId, 'ateJobId' => $ateJobId ]
			);
		} catch ( RequestException $error ) {
			$this->jobErrorRecorder->recordThrowable(
				$wpmlJobId,
				$ateJobId,
				Recorder::TYPE_DOWNLOAD,
				$error->getMessage(),
				$error
			);

			throw $error;
		}

		if ( ! function_exists( 'wpml_tm_save_data' ) ) {
			require_once WPML_TM_PATH . '/inc/wpml-private-actions.php';
		}

		$prevPostStatus = Post::getStatus( $postId );
		$cleanPosts     = (bool) $postId;

		if ( $cleanPosts ) {
			$this->orphanPostCleaner->incrementProcessCounter();
			$this->orphanPostCleaner->recordStateBeforeInsert();
		}

		try {
			try {
				$applied = $this->ateJobs->apply( $xliffContent, $wpmlJobId ? (int) $wpmlJobId : null, $ateJobId ? (int) $ateJobId : null );
			} catch ( RequestException $error ) {
				$this->jobErrorRecorder->recordThrowable(
					$wpmlJobId,
					$ateJobId,
					Recorder::TYPE_DOWNLOAD,
					$error->getMessage(),
					$error
				);

				throw $error;
			} catch ( Throwable $error ) {
				if ( $cleanPosts ) {
					$this->orphanPostCleaner->markCleanupNeeded();
				}

				$this->jobErrorRecorder->recordThrowable(
					$wpmlJobId,
					$ateJobId,
					Recorder::TYPE_APPLY,
					$error->getMessage(),
					$error
				);

				throw $error;
			}

			if ( ! $applied ) {
				if ( $cleanPosts ) {
					$this->orphanPostCleaner->markCleanupNeeded();
				}

				$termReason = TaxonomyTermJobApplier::lastFailureReason();

				$this->jobErrorRecorder->record(
					$wpmlJobId,
					$ateJobId,
					Recorder::TYPE_APPLY,
					trim( 'The translation job could not be applied. ' . $termReason )
				);

				if ( false === $applied && ICL_TM_ATE_UNSOLVABLE === (int) call_user_func( $this->getStatus, $wpmlJobId ) ) {
					return $this->confirmGivenUpDelivery( $wpmlJobId, $ateJobId );
				}
			}
		} finally {
			if ( $cleanPosts ) {
				$this->orphanPostCleaner->decrementProcessCounter();
				$this->orphanPostCleaner->tryCleanup();
			}
		}

		if ( WPML_TM_ATE_Jobs::SKIPPED_NOT_AWAITING_DELIVERY === $applied ) {
			if ( ICL_TM_ATE_UNSOLVABLE === (int) call_user_func( $this->getStatus, (int) $wpmlJobId ) ) {
				return $this->confirmKeepingError( $wpmlJobId, $ateJobId );
			}

			return $this->confirmAndClearError( $wpmlJobId, $ateJobId );
		}

		if ( $applied ) {
			try {
				if ( Post::getStatus( $postId ) !== $prevPostStatus ) {
					Post::setStatus( $postId, $prevPostStatus );
				}
			} catch ( Throwable $error ) {
				$this->jobErrorRecorder->recordThrowable(
					$wpmlJobId,
					$ateJobId,
					Recorder::TYPE_APPLY,
					$error->getMessage(),
					$error
				);

				throw $error;
			}

			if ( TaxonomyTermJobApplier::lastDeliveryGivenUp() ) {
				return $this->confirmKeepingError( $wpmlJobId, $ateJobId );
			}

			return $this->confirmAndClearError( $wpmlJobId, $ateJobId );
		}

		return false;
	}

	private function confirmKeepingError( $wpmlJobId, $ateJobId ) {
		$failure = null;
		try {
			$response = $this->ateApi->confirm_received_job( $ateJobId );
			if ( is_wp_error( $response ) ) {
				$failure = (string) $response->get_error_code();
			}
		} catch ( Throwable $error ) {
			$failure = get_class( $error );
		}

		if ( null === $failure ) {
			return true;
		}

		JobLog::add(
			'ate_given_up_delivery_confirm_failed',
			[
				'job_id'     => (int) $wpmlJobId,
				'ate_job_id' => (int) $ateJobId,
				'error'      => $failure,
			]
		);

		return false;
	}

	private function confirmAndClearError( $wpmlJobId, $ateJobId ) {
		try {
			$response = $this->ateApi->confirm_received_job( $ateJobId );
		} catch ( Throwable $error ) {
			$this->jobErrorRecorder->recordThrowable(
				$wpmlJobId,
				$ateJobId,
				Recorder::TYPE_DOWNLOAD,
				$error->getMessage(),
				$error
			);

			throw $error;
		}

		if ( is_wp_error( $response ) ) {
			$this->jobErrorRecorder->record(
				$wpmlJobId,
				$ateJobId,
				Recorder::TYPE_DOWNLOAD,
				$response->get_error_message(),
				[
					'code'    => $response->get_error_code(),
					'message' => $response->get_error_message(),
				]
			);

			return false;
		}

		$this->jobErrorRecorder->clear( $wpmlJobId );

		return true;
	}

	private function confirmGivenUpDelivery( $wpmlJobId, $ateJobId ) {
		try {
			$response = $this->ateApi->confirm_received_job( $ateJobId );
		} catch ( Throwable $error ) {
			JobLog::add(
				'ate_given_up_delivery_confirm_failed',
				[
					'job_id'      => (int) $wpmlJobId,
					'ate_job_id'  => (int) $ateJobId,
					'error_class' => get_class( $error ),
				]
			);

			return false;
		}

		return ! is_wp_error( $response );
	}
}
