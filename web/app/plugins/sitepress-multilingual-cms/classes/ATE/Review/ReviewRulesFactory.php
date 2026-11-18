<?php

namespace WPML\TM\ATE\Review;

use WPML\TranslationPolicy\FeatureSwitch;

class ReviewRulesFactory {

	public static function create() {
		if ( ! FeatureSwitch::isOn() ) {
			return new TodaysReviewRules();
		}

		return new SaveAcceptsReview();
	}
}
