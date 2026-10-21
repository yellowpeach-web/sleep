<?php

class WPML_TM_AMS_Synchronize_Users_On_Access_Denied {

	const ERROR_MESSAGE = 'Authentication error, please contact your translation manager to check your subscription';

	private $ams_synchronize_actions;

	private $ate_jobs;

	public function is_access_denied_message( $message ) {
		return is_string( $message ) && false !== strpos( $message, self::ERROR_MESSAGE );
	}

	public function handle( $ate_job_id, $destination ) {
		$this->get_ams_synchronize_actions()->synchronize_translators();

		$ate_job_id = (int) $ate_job_id;
		if ( $ate_job_id <= 0 ) {
			return $destination;
		}

		$job = ( new \WPML\TM\Jobs\Authorization\AuthorizedJobResolver( $this->get_ate_jobs() ) )->byAteId(
			\WPML\Core\Security\ExecutionContext\ExecutionContextHolder::current(),
			$ate_job_id
		);
		if ( ! $job ) {
			return $destination;
		}

		return admin_url( \WPML\TM\Menu\TranslationQueue\TranslationQueuePage::base() . '&job_id=' . $job->localId() );
	}

	private function get_ams_synchronize_actions() {
		if ( ! $this->ams_synchronize_actions ) {
			$factory                       = new WPML_TM_AMS_Synchronize_Actions_Factory();
			$this->ams_synchronize_actions = $factory->create();
		}

		return $this->ams_synchronize_actions;
	}

	private function get_ate_jobs() {
		if ( ! $this->ate_jobs ) {
			$ate_jobs_records = wpml_tm_get_ate_job_records();
			$this->ate_jobs   = new WPML_TM_ATE_Jobs( $ate_jobs_records );
		}

		return $this->ate_jobs;
	}

	public function set_ams_synchronize_actions( WPML_TM_AMS_Synchronize_Actions $ams_synchronize_actions ) {
		$this->ams_synchronize_actions = $ams_synchronize_actions;
	}

	public function set_ate_jobs( WPML_TM_ATE_Jobs $ate_jobs ) {
		$this->ate_jobs = $ate_jobs;
	}
}
