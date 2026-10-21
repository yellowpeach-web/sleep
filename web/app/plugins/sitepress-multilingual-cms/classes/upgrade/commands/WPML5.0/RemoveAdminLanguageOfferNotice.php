<?php

namespace WPML\Upgrade\Commands;

class RemoveAdminLanguageOfferNotice implements \IWPML_Upgrade_Command {

	const NOTICE_GROUP = 'WPML_User_Language';
	const NOTICE_ID    = 'WPML_User_Languagehow_to_set_notice';

	private $results;

	public function run() {
		wpml_get_admin_notices()->remove_notice( self::NOTICE_GROUP, self::NOTICE_ID );

		$this->results = true;

		return $this->results;
	}

	public function run_admin() {
		return $this->run();
	}

	public function run_ajax() {
		return null;
	}

	public function run_frontend() {
		return null;
	}

	public function get_results() {
		return $this->results;
	}
}
