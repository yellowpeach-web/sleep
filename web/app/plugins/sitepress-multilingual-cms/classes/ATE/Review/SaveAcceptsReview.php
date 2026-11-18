<?php

namespace WPML\TM\ATE\Review;

use WPML\FP\Obj;
use WPML\TM\API\Job\Map;
use WPML\TM\API\Jobs;
use WPML\TM\Jobs\JobLog;

class SaveAcceptsReview implements ReviewRules {

	const SOURCE_WORDPRESS_SAVE = 'wordpress_save';
	const SOURCE_APPLIED_JOB    = 'applied_job';
	const SOURCE_TERM_SAVE      = 'term_save';

	const PUBLISHED_STATUSES = [ 'publish', 'future', 'private' ];

	private $termReviewAcceptance;

	public function __construct( ?TermReviewAcceptance $termReviewAcceptance = null ) {
		$this->termReviewAcceptance = $termReviewAcceptance;
	}

	public function reviewAcceptedBySave( $statusRecord, $isPersonsSave, $postStatus ) {
		if ( ! $isPersonsSave ) {
			return 0;
		}

		$rid = (int) $statusRecord->rid();

		if ( self::isHeldForReview( $rid ) && ! in_array( $postStatus, self::PUBLISHED_STATUSES, true ) ) {
			return 0;
		}

		return $rid;
	}

	public function acceptOnSave( $rid ) {
		self::accept( (int) Map::jobIdFromRid( (int) $rid ), self::SOURCE_WORDPRESS_SAVE );
	}

	public function acceptOnCompletion( $job, callable $isPersonsCompletion ): bool {
		if ( ! $isPersonsCompletion() || self::isSentFromTheReview( $job ) ) {
			return false;
		}

		$acceptedStatuses = [ ReviewStatus::NEEDS_REVIEW ];
		if ( self::isAteOpenedOutsideTheReview( $job ) ) {
			$acceptedStatuses[] = ReviewStatus::EDITING;
		}

		return self::accept( (int) Obj::prop( 'job_id', $job ), self::SOURCE_APPLIED_JOB, $acceptedStatuses );
	}

	public function acceptOnTermSave( $termRid ) {
		return in_array( (int) $termRid, $this->acceptTermReview( $termRid, self::SOURCE_TERM_SAVE ), true );
	}

	public function acceptOnTermCompletion( $termRid ) {
		$this->acceptTermReview( $termRid, self::SOURCE_APPLIED_JOB );
	}

	private function acceptTermReview( $termRid, string $source ): array {
		if ( ! $this->termReviewAcceptance ) {
			$this->termReviewAcceptance = new TermReviewAcceptance();
		}
		$accepted = $this->termReviewAcceptance->accept( (int) $termRid );

		foreach ( $accepted as $rid ) {
			JobLog::add( 'review_accepted_by_save', [ 'rid' => $rid, 'source' => $source ] );
		}

		return $accepted;
	}

	private static function isAteOpenedOutsideTheReview( $job ): bool {
		return \WPML_TM_Editors::ATE === Obj::prop( 'editor', $job )
			&& Jobs::SENT_MANUALLY === (int) Obj::prop( 'sent_from', $job );
	}

	public function refusesSendOfReviewOpenInAte() {
		return true;
	}

	private static function isSentFromTheReview( $job ): bool {
		return Jobs::SENT_FROM_REVIEW === (int) Obj::prop( 'sent_from', $job );
	}

	private static function isHeldForReview( int $rid ): bool {
		$jobId = (int) Map::jobIdFromRid( $rid );

		return ApplyJob::isHeldForReviewUnderOwnMode( $jobId ? Jobs::get( $jobId ) : null );
	}

	private static function accept( int $jobId, string $source, array $acceptedStatuses = [ ReviewStatus::NEEDS_REVIEW ] ): bool {
		$job = $jobId ? Jobs::get( $jobId ) : null;

		if ( ! $job || ! in_array( Obj::prop( 'review_status', $job ), $acceptedStatuses, true ) ) {
			return false;
		}

		Jobs::setReviewStatus( $jobId, ReviewStatus::ACCEPTED );
		AcceptTranslation::completeJobRecord( $jobId );

		JobLog::add( 'review_accepted_by_save', [ 'job_id' => $jobId, 'source' => $source ] );

		return true;
	}
}
