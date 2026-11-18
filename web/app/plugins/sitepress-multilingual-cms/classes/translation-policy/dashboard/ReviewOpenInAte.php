<?php

namespace WPML\TranslationPolicy\Dashboard;

use WPML\TM\ATE\Review\ReviewRules;
use WPML\TM\ATE\Review\ReviewRulesFactory;

class ReviewOpenInAte implements \IWPML_Backend_Action {

	const SCRIPT_DATA_KEY = 'refusesSendOfReviewOpenInAte';

	private $rules;

	public function __construct( ?ReviewRules $rules = null ) {
		$this->rules = $rules;
	}

	public function add_hooks() {
		add_filter( 'wpml_dashboard_initial_script_data', [ $this, 'refuseSendOfReviewOpenInAte' ] );
	}

	public function refuseSendOfReviewOpenInAte( $data ) {
		if ( ! is_array( $data ) ) {
			return $data;
		}

		$rules = $this->rules ?? ReviewRulesFactory::create();
		if ( ! $rules->refusesSendOfReviewOpenInAte() ) {
			return $data;
		}

		$data[ self::SCRIPT_DATA_KEY ] = true;

		return $data;
	}
}
