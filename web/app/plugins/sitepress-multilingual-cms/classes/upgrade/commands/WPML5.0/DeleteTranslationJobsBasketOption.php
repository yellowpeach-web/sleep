<?php

namespace WPML\Upgrade\Commands;

class DeleteTranslationJobsBasketOption implements \IWPML_Upgrade_Command {

	const OPTION = 'icl_translation_jobs_basket';

	private $results;

	public function run() {
		delete_option( self::OPTION );

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
