<?php

namespace WPML\TM\ATE\JobError;

require_once __DIR__ . '/../../../inc/constants-since-5-0.php';

use Throwable;
use WPML\Core\Component\Translation\Application\Service\TranslateJobErrorService;
use WPML\Core\Component\Translation\Domain\Entity\JobError;
use WPML\TM\API\Jobs;
use WPML\TM\ATE\PullDelivery\State;
use WPML\TM\Jobs\JobLog;
use WPML\Translation\TranslateJobErrorServiceFactory;

class Recorder {

	const TYPE_SYNC     = JobError::TYPE_SYNC;
	const TYPE_DOWNLOAD = JobError::TYPE_DOWNLOAD;
	const TYPE_APPLY    = JobError::TYPE_APPLY;

	private $service;

	private $setStatus;

	private $getStatus;

	public function __construct( $service = null, $setStatus = null, $getStatus = null ) {
		$this->service   = $service;
		$this->setStatus = $setStatus ?: [ Jobs::class, 'setStatus' ];
		$this->getStatus = $getStatus ?: [ Jobs::class, 'getStatus' ];
	}

	public function record( $jobId, $ateJobId, $type, $message, array $errorData = [] ) {
		try {
			$jobError = $this->getService()->logError( (int) $jobId, (int) $ateJobId, $type, $message, $errorData );
			State::onJobErrorChanged();
		} catch ( Throwable $error ) {
			$this->logPersistenceFailure( 'record', $jobId, $error );
			return;
		}

		$this->giveUpWhenBeyondRetryLimit( $jobError );
	}

	public function giveUp( $jobId, $ateJobId, $type, $message, array $errorData = [] ) {
		try {
			$jobError = $this->getService()->logError( (int) $jobId, (int) $ateJobId, $type, $message, $errorData );
			State::onJobErrorChanged();
		} catch ( Throwable $error ) {
			$this->logPersistenceFailure( 'record', $jobId, $error );
			return;
		}

		if ( $jobError instanceof JobError ) {
			$this->giveUpJob( $jobError );
		}
	}

	private function giveUpWhenBeyondRetryLimit( $jobError ) {
		if ( ! $jobError instanceof JobError || ! $jobError->isBeyondRetryLimit() ) {
			return;
		}

		$this->giveUpJob( $jobError );
	}

	private function giveUpJob( JobError $jobError ) {
		try {
			$fresh = call_user_func( $this->getStatus, $jobError->getJobId() );
			if ( ! in_array( (int) $fresh, [ ICL_TM_WAITING_FOR_TRANSLATOR, ICL_TM_IN_PROGRESS ], true ) ) {
				JobLog::add(
					'ate_job_given_up_row_left_alone',
					[
						'job_id'     => $jobError->getJobId(),
						'ate_job_id' => $jobError->getAteJobId(),
						'error_type' => $jobError->getErrorType(),
						'counter'    => $jobError->getCounter(),
						'status'     => $fresh,
					]
				);
				return;
			}
			call_user_func( $this->setStatus, $jobError->getJobId(), ICL_TM_ATE_UNSOLVABLE );
			JobLog::add(
				'ate_job_given_up',
				[
					'job_id'     => $jobError->getJobId(),
					'ate_job_id' => $jobError->getAteJobId(),
					'error_type' => $jobError->getErrorType(),
					'counter'    => $jobError->getCounter(),
					'status'     => ICL_TM_ATE_UNSOLVABLE,
				]
			);
		} catch ( Throwable $error ) {
			$this->logPersistenceFailure( 'give_up', $jobError->getJobId(), $error );
		}
	}

	public function recordThrowable( $jobId, $ateJobId, $type, $message, Throwable $error ) {
		$this->record( $jobId, $ateJobId, $type, $message, $this->convertThrowableToArray( $error ) );
	}

	public function clear( $jobId ) {
		try {
			if ( $this->getService()->deleteError( (int) $jobId ) > 0 ) {
				State::onJobErrorChanged();
			}
		} catch ( Throwable $error ) {
			$this->logPersistenceFailure( 'clear', $jobId, $error );
		}
	}

	private function getService() {
		if ( null === $this->service ) {
			$this->service = TranslateJobErrorServiceFactory::create();
		}

		return $this->service;
	}

	private function logPersistenceFailure( $operation, $jobId, Throwable $error ) {
		try {
			JobLog::add(
				'ate_job_error_persistence_failed',
				[
					'operation'   => $operation,
					'job_id'      => (int) $jobId,
					'error_class' => get_class( $error ),
				]
			);
		} catch ( Throwable $loggingError ) {
			return;
		}
	}

	private function convertThrowableToArray( Throwable $error ) {
		$stackTrace = [];

		foreach ( $error->getTrace() as $traceItem ) {
			$file = isset( $traceItem['file'] ) ? $traceItem['file'] : '[internal function]';
			$line = isset( $traceItem['line'] ) ? $traceItem['line'] : 0;

			$stackTrace[] = $file . ':' . $line;
		}

		return [
			'message' => $error->getMessage(),
			'code'    => $error->getCode(),
			'file'    => $error->getFile(),
			'line'    => $error->getLine(),
			'trace'   => $stackTrace,
		];
	}
}
