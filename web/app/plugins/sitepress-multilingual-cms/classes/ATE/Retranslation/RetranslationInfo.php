<?php

namespace WPML\TM\ATE\Retranslation;

use WPML\FP\Obj;

class RetranslationInfo {

	private $found;

	private $inProgress;

	private $requestId;

	private $suggestionsCount;

	private $jobCount;

	private $processedJobCount;

	public static function fromResponse( $response ): self {
		$self      = new self();
		$requestId = Obj::propOr( null, 'retranslation_request_id', $response );

		$self->found             = null !== $requestId;
		$self->requestId         = null !== $requestId ? (int) $requestId : null;
		$self->inProgress        = (bool) Obj::propOr( false, 'in_progress', $response );
		$self->suggestionsCount  = self::intOrNull( Obj::propOr( null, 'suggestions_count', $response ) );
		$self->jobCount          = self::intOrNull( Obj::propOr( null, 'job_count', $response ) );
		$self->processedJobCount = self::intOrNull( Obj::propOr( null, 'processed_job_count', $response ) );

		return $self;
	}

	public function isFound(): bool {
		return $this->found;
	}

	public function isInProgress(): bool {
		return $this->found && $this->inProgress;
	}

	public function isCompleted(): bool {
		return $this->found
			&& ! $this->inProgress
			&& null !== $this->jobCount
			&& $this->jobCount === $this->processedJobCount;
	}

	public function isCanceled(): bool {
		return $this->found && ! $this->inProgress && ! $this->isCompleted();
	}

	public function getRequestId() {
		return $this->requestId;
	}

	public function getSuggestionsCount() {
		return $this->suggestionsCount;
	}

	public function getJobCount() {
		return $this->jobCount;
	}

	public function getProcessedJobCount() {
		return $this->processedJobCount;
	}

	private static function intOrNull( $value ) {
		return null === $value ? null : (int) $value;
	}
}
