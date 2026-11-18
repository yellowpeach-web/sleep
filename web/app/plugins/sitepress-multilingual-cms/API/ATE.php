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
use WPML\TM\ATE\TranslateEverything\TermBundleMembers;
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

	private $bundleMembers;

	public function __construct(
		WPML_TM_ATE_API $ateApi,
		WPML_TM_ATE_Jobs $ateJobs,
		OrphanPostCleaner $orphanPostCleaner,
		Recorder $jobErrorRecorder,
		$getStatus = null,
		$bundleMembers = null
	) {
		$this->ateApi            = $ateApi;
		$this->ateJobs           = $ateJobs;
		$this->orphanPostCleaner = $orphanPostCleaner;
		$this->jobErrorRecorder  = $jobErrorRecorder;
		$this->getStatus         = $getStatus ?: [ Jobs::class, 'getStatus' ];
		$this->bundleMembers     = $bundleMembers ?: function ( $jobId ) {
			global $wpdb;

			return ( new TermBundleMembers( $wpdb ) )->membersOf( $jobId );
		};
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

	public function applyTranslation( $wpmlJobId, $postId, $xliffUrl, $ateAnswer = null ) {
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
				$applied = $this->ateJobs->apply( $xliffContent, $wpmlJobId ? (int) $wpmlJobId : null, $ateJobId ? (int) $ateJobId : null, $ateAnswer );
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

				if ( ICL_TM_ATE_UNSOLVABLE === (int) call_user_func( $this->getStatus, $wpmlJobId ) ) {
					$this->confirmGivenUpDelivery( $wpmlJobId, $ateJobId );
				}

				throw $error;
			}

			if ( ! $applied ) {
				if ( $cleanPosts ) {
					$this->orphanPostCleaner->markCleanupNeeded();
				}

				$termReason = TaxonomyTermJobApplier::lastFailureReason();
				$errorData  = [];
				if ( '' === $termReason ) {
					$termReason = \WPML_TM_ATE_Jobs::lastUnsavedReason();
					$errorData  = \WPML_TM_ATE_Jobs::lastUnsavedData();
				}
				$message    = implode( ' ', array_filter( [ 'The translation job could not be applied.', $termReason, self::saveErrors() ] ) );
				$errorData  = '' !== $termReason ? [ 'reason' => $termReason ] + $errorData : [];

				if ( TaxonomyTermJobApplier::lastFailureIsFinal() ) {
					$this->jobErrorRecorder->giveUp( $wpmlJobId, $ateJobId, Recorder::TYPE_APPLY, $message, $errorData );
				} else {
					$this->jobErrorRecorder->record( $wpmlJobId, $ateJobId, Recorder::TYPE_APPLY, $message, $errorData );
				}

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
				return $this->answerGivenUpDelivery( $wpmlJobId, $ateJobId );
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
				return $this->answerGivenUpDelivery( $wpmlJobId, $ateJobId );
			}

			return $this->confirmAndClearError( $wpmlJobId, $ateJobId );
		}

		return false;
	}

	private function answerGivenUpDelivery( $wpmlJobId, $ateJobId ) {
		$states = array_column( call_user_func( $this->bundleMembers, (int) $wpmlJobId ), 'state' );

		if ( $states && ! in_array( TermBundleMembers::STATE_APPLIED, $states, true ) ) {
			return $this->confirmGivenUpDelivery( $wpmlJobId, $ateJobId );
		}

		return $this->confirmKeepingError( $wpmlJobId, $ateJobId );
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

	public function confirmApplied( $wpmlJobId, $ateJobId ) {
		return $this->confirmAndClearError( $wpmlJobId, $ateJobId );
	}

	private static function saveErrors() {
		global $iclTranslationManagement;

		if ( ! is_object( $iclTranslationManagement ) || ! method_exists( $iclTranslationManagement, 'messages_by_type' ) ) {
			return '';
		}

		$messages = $iclTranslationManagement->messages_by_type( 'error' );

		return is_array( $messages ) ? trim( implode( ' ', array_filter( array_map( fn( $m ) => is_array( $m ) && isset( $m['text'] ) ? (string) $m['text'] : '', $messages ) ) ) ) : '';
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
		return \WPML\TM\ATE\Handshake\Decline::givenUp( (int) $wpmlJobId, (int) $ateJobId );
	}
}
