<?php

namespace WPML\TM\ATE\Review;

class TodaysReviewRules implements ReviewRules {

	public function reviewAcceptedBySave( $statusRecord, $isPersonsSave, $postStatus ) {
		return 0;
	}

	public function acceptOnSave( $rid ) {
	}

	public function acceptOnCompletion( $job, callable $isPersonsCompletion ): bool {
		return false;
	}

	public function acceptOnTermSave( $termRid ) {
		return false;
	}

	public function acceptOnTermCompletion( $termRid ) {
	}

	public function refusesSendOfReviewOpenInAte() {
		return false;
	}
}
