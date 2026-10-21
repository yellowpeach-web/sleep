<?php

namespace WPML\ATE\Proxies;

use WPML_TM_ATE_AMS_Endpoints;

class ProxyRoutingRules {

	public static function getAllowedDomains() {
		return \WPML\Remote\TrustedDestinations::forAteAndAms( new WPML_TM_ATE_AMS_Endpoints() )->hosts();
	}

	public function getBypassedHttpRequests() {
		$ateEndpoints = new WPML_TM_ATE_AMS_Endpoints();
		return [
			$ateEndpoints->get_AMS_base_url() . '/api/wpml',
		];
	}
}
