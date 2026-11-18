<?php

namespace WPML\TM\ATE\JobError;

require_once __DIR__ . '/../../../inc/constants-since-5-0.php';

use Throwable;
use WPML\Core\Component\Translation\Application\Repository\TranslateEverythingCompletedMapsInterface;
use WPML\Core\Component\Translation\Application\Service\TranslateJobErrorService;
use WPML\Core\Component\Translation\Domain\Entity\JobError;
use WPML\TM\API\Jobs;
use WPML\TM\Jobs\JobLog;
use WPML\Translation\TranslateJobErrorServiceFactory;

class Recorder {

	const TYPE_SYNC     = JobError::TYPE_SYNC;
	const TYPE_DOWNLOAD = JobError::TYPE_DOWNLOAD;
	const TYPE_APPLY    = JobError::TYPE_APPLY;
	const TYPE_CREATE   = JobError::TYPE_CREATE;

	private $service;

	private $setStatus;

	private $getStatus;

	private $completedMaps;

	public function __construct( $service = null, $setStatus = null, $getStatus = null, ?TranslateEverythingCompletedMapsInterface $completedMaps = null ) {
		$this->service       = $service;
		$this->setStatus     = $setStatus ?: [ Jobs::class, 'setStatus' ];
		$this->getStatus     = $getStatus ?: [ Jobs::class, 'getStatus' ];
		$this->completedMaps = $completedMaps;
	}

	public function record( $jobId, $ateJobId, $type, $message, array $errorData = [] ) {
		try {
			$jobError = $this->getService()->logError( (int) $jobId, (int) $ateJobId, $type, $message, $errorData );
		} catch ( Throwable $error ) {
			$this->logPersistenceFailure( 'record', $jobId, $error );
			return;
		}

		$this->giveUpWhenBeyondRetryLimit( $jobError );
	}

	public function giveUp( $jobId, $ateJobId, $type, $message, array $errorData = [] ) {
		try {
			$jobError = $this->getService()->logError( (int) $jobId, (int) $ateJobId, $type, $message, $errorData );
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
			$fresh    = call_user_func( $this->getStatus, $jobError->getJobId() );
			$inFlight = [ ICL_TM_WAITING_FOR_TRANSLATOR, ICL_TM_IN_PROGRESS ];
			if ( self::TYPE_CREATE === $jobError->getErrorType() ) {
				$inFlight[] = ICL_TM_ATE_NEEDS_RETRY;
			}
			if ( ! in_array( (int) $fresh, $inFlight, true ) ) {
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
			$this->completedMaps()->reopenTaxonomiesForJobs( [ $jobError->getJobId() ] );
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
			$this->getService()->deleteError( (int) $jobId );
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

	private function completedMaps() {
		if ( null === $this->completedMaps ) {
			global $wpml_dic;
			if ( ! $wpml_dic ) {
				throw new \RuntimeException( 'The WPML container is not available, so the taxonomy completed maps cannot be reopened.' );
			}
			$this->completedMaps = $wpml_dic->make( TranslateEverythingCompletedMapsInterface::class );
		}

		return $this->completedMaps;
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
		] + self::refusalDetails( $error );
	}

	private static function refusalDetails( Throwable $error ) {
		if ( ! method_exists( $error, 'getData' ) ) {
			return [];
		}

		$data = $error->getData();
		if ( ! is_array( $data ) ) {
			return [];
		}

		$details = [];
		if ( isset( $data['reasonCode'] ) && is_string( $data['reasonCode'] ) && preg_match( '/^[a-z_]{1,64}$/', $data['reasonCode'] ) ) {
			$details['reasonCode'] = $data['reasonCode'];
		}

		$jobData = [];
		foreach ( [ 'original_doc_id', 'elementId' ] as $key ) {
			if ( isset( $data['jobData'][ $key ] ) && is_numeric( $data['jobData'][ $key ] ) && (int) $data['jobData'][ $key ] > 0 ) {
				$jobData[ $key ] = (int) $data['jobData'][ $key ];
			}
		}
		if ( $jobData ) {
			$details['jobData'] = $jobData;
		}

		return $details;
	}
}
