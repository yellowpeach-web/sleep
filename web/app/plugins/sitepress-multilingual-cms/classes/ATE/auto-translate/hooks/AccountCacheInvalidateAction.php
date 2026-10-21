<?php

namespace WPML\TM\ATE\AutoTranslate\Hooks;

use WPML\TM\API\ATE\Account;

class AccountCacheInvalidateAction implements \IWPML_Backend_Action, \IWPML_REST_Action, \IWPML_AJAX_Action {
	public function add_hooks() {
		$clearCache = function () {
			Account::clearCache();
		};

		add_action( 'wpml_tm_ate_jobs_created', $clearCache, 10, 0 );
	}
}
