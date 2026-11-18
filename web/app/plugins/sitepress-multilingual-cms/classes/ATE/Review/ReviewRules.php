<?php

namespace WPML\TM\ATE\Review;

interface ReviewRules {

	public function reviewAcceptedBySave( $statusRecord, $isPersonsSave, $postStatus );

	public function acceptOnSave( $rid );

	public function acceptOnCompletion( $job, callable $isPersonsCompletion ): bool;

	public function acceptOnTermSave( $termRid );

	public function acceptOnTermCompletion( $termRid );

	public function refusesSendOfReviewOpenInAte();
}
