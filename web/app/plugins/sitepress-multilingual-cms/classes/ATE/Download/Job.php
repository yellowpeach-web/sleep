<?php

namespace WPML\TM\ATE\Download;

class Job {

	const NOT_ENOUGH_CREDIT_STATUS = \WPML_TM_ATE_AMS_Endpoints::ATE_JOB_STATUS_NOT_ENOUGH;

	const WAITING_AUTOMATIC_PAYMENT_STATUS = \WPML_TM_ATE_AMS_Endpoints::ATE_JOB_STATUS_WAITING_AUTOMATIC_PAYMENT;

	const ERROR_TYPE_CREDIT_EXHAUSTED = 'CreditExhausted';

	const ERROR_TYPE_PAYMENT_PENDING = 'PaymentPending';

	public $ateJobId;

	public $url;

	public $ateStatus;

	public $jobId;

	public $status = ICL_TM_IN_PROGRESS;

	public $isUnsolvable = false;

	public $message = '';

	public $errorType = null;

	public $errorData = null;

	public $originalElementId = null;

	public $elementId = null;

	public $needsReview = null;

	public $automatic = null;

	public $language_code = null;

	public $original_element_id = null;

	public static function fromAteResponse( \stdClass $item ) {
		$job               = new self();
		$job->ateJobId     = $item->ate_id;
		$job->url          = $item->download_link;
		$job->ateStatus    = (int) $item->status;
		$job->isUnsolvable = (bool) ( $item->is_unsolvable ?? false );
		$job->message      = $item->message ?? '';
		$job->jobId        = (int) $item->id;
		if ( $job->isUnsolvable ) {
			$job->errorType = 'SyncError';
		}

		if ( self::NOT_ENOUGH_CREDIT_STATUS === $job->ateStatus ) {
			$job->errorType = self::ERROR_TYPE_CREDIT_EXHAUSTED;
		}

		if ( self::WAITING_AUTOMATIC_PAYMENT_STATUS === $job->ateStatus ) {
			$job->errorType = self::ERROR_TYPE_PAYMENT_PENDING;
		}

		return $job;
	}


	public function isCreditExhausted(): bool {
		return self::ERROR_TYPE_CREDIT_EXHAUSTED === $this->errorType;
	}

	public function isPaymentPending(): bool {
		return self::ERROR_TYPE_PAYMENT_PENDING === $this->errorType;
	}

	public static function fromDb( \stdClass $row ) {
		$job           = new self();
		$job->ateJobId = $row->editor_job_id;
		$job->url      = $row->download_url;

		return $job;
	}
}
