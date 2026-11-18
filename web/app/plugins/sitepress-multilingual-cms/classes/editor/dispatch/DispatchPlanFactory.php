<?php

namespace WPML\TM\Editor\Dispatch;

use WPML\TM\ATE\AutoTranslate\Eligibility;
use WPML\TM\ATE\AutoTranslate\PolicyEligibility;
use WPML\TM\ATE\PullDelivery\ReturnDelivery;
use WPML\TranslationPolicy\FeatureSwitch;
use WPML\TranslationPolicy\Policy\Policy;

class DispatchPlanFactory {

	public static function create() {
		global $sitepress;

		if ( ! FeatureSwitch::isOn() ) {
			return new TodaysPlan( new Eligibility( $sitepress ) );
		}

		$reader = Policy::reader();

		return new PolicyPlan( new PolicyEligibility( $sitepress, $reader ), $reader, new ReturnDelivery() );
	}
}
