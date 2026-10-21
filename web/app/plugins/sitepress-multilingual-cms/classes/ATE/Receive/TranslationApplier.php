<?php

namespace WPML\TM\ATE\Receive;

use WPML\FP\Obj;
use WPML\TM\API\ATE;
use WPML\TM\API\Job\Map;
use WPML\TM\API\Jobs;
use WPML\TM\ATE\ClonedSites\ApiCommunication;
use WPML\TM\ATE\JobError\Recorder;
use WPML\TM\Jobs\JobLog;

class TranslationApplier {

	const APPLIED = 'applied';

	const XLIFF_NOT_READY = 'xliff_not_ready';

	const APPLY_FAILED = 'apply_failed';

	const WRONG_ORIGINAL = 'wrong_original';

	private $ateApi;

	private $grant;

	private $jobErrorRecorder;

	private $jobIdForRid;

	public function __construct( ATE $ateApi, ?UnfilteredHtmlGrant $grant = null, ?Recorder $jobErrorRecorder = null, $jobIdForRid = null ) {
		$this->ateApi           = $ateApi;
		$this->grant            = $grant ?: new UnfilteredHtmlGrant();
		$this->jobErrorRecorder = $jobErrorRecorder;
		$this->jobIdForRid      = $jobIdForRid ?: [ Map::class, 'fromRid' ];
	}

	public function applyForPost( $jobId, $postId ) {
		return $this->apply( $jobId, $postId, true );
	}

	public function applyForTerm( $jobId ) {
		return $this->apply( $jobId, 0, false );
	}

	private function apply( $jobId, $postId, $requiresPost ) {
		try {
			return $this->readAndApply( $jobId, $postId, $requiresPost );
		} finally {
			ApiCommunication::endDelivery();
		}
	}

	private function readAndApply( $jobId, $postId, $requiresPost ) {
		$jobView = ApiCommunication::probeThroughSilence(
			function () use ( $jobId ) {
				return $this->ateApi->checkJobStatus( $jobId, [ 'bypassBreaker' => true ] );
			}
		);

		$xliffUrl = Obj::prop( 'translated_xliff', $jobView );

		if ( ! $xliffUrl ) {
			return self::XLIFF_NOT_READY;
		}

		if ( $this->namesAnotherOriginal( $jobId, $postId, is_array( $jobView ) ? $jobView : [] ) ) {
			return $this->refuseMismatchedDelivery( $jobId, $postId, (array) $jobView );
		}

		$this->grant->arm( $jobId );

		$applied = ( ! $requiresPost || $postId ) && $this->ateApi->applyTranslation( $jobId, $postId, $xliffUrl );

		return $applied ? self::APPLIED : self::APPLY_FAILED;
	}

	private function namesAnotherOriginal( $jobId, $postId, array $jobView ) {
		$namedRid = (int) Obj::prop( 'cms_id', $jobView );

		if ( ! $namedRid || ! $postId ) {
			return false;
		}

		$namedJobId = (int) call_user_func( $this->jobIdForRid, $namedRid );

		if ( ! $namedJobId || $namedJobId === (int) $jobId ) {
			return false;
		}

		$namedJob = Jobs::get( $namedJobId );

		if ( ! $namedJob ) {
			return false;
		}

		$namedOriginal = (int) Obj::prop( 'original_doc_id', $namedJob );

		return $namedOriginal && $namedOriginal !== (int) $postId;
	}

	private function refuseMismatchedDelivery( $jobId, $postId, array $jobView ) {
		$ateJobId   = (int) Obj::prop( 'ate_job_id', $jobView );
		$namedJobId = (int) call_user_func( $this->jobIdForRid, (int) Obj::prop( 'cms_id', $jobView ) );

		JobLog::add(
			'delivery_refused_wrong_original',
			[
				'jobId'      => (int) $jobId,
				'ateJobId'   => $ateJobId,
				'namedJobId' => $namedJobId,
			]
		);

		$message = sprintf(
			'The delivery of ATE job %1$d was refused: our translation system reports the job\'s source as the translation of a different content (original %2$d) than the local job holding this ATE job id (job %3$d, original %4$d). Applying it would write another original\'s translation, so nothing was written. Repair the local job-to-ATE mapping (WPML > Support > Troubleshooting > Synchronize local job ids with ATE jobs) and resend the content for translation.',
			$ateJobId,
			(int) Obj::prop( 'original_doc_id', Jobs::get( $namedJobId ) ),
			(int) $jobId,
			(int) $postId
		);

		$this->recorder()->giveUp( (int) $jobId, $ateJobId, Recorder::TYPE_APPLY, $message, [ 'ate_named_job_id' => $namedJobId ] );

		return self::WRONG_ORIGINAL;
	}

	private function recorder() {
		if ( ! $this->jobErrorRecorder ) {
			$this->jobErrorRecorder = new Recorder();
		}

		return $this->jobErrorRecorder;
	}
}
