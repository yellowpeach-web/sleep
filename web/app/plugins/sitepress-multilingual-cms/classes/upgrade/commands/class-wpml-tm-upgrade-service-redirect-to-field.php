<?php

use WPML\TM\Menu\TranslationServices\Troubleshooting\RefreshServices;
use WPML\TM\Menu\TranslationServices\Troubleshooting\RefreshServicesFactory;
use WPML\TM\Upgrade\Commands\ServicesRefreshBackoff;

class WPML_TM_Upgrade_Service_Redirect_To_Field implements IWPML_Upgrade_Command {
	const RETRY_AFTER_FAILURE_TRANSIENT = ServicesRefreshBackoff::TRANSIENT;

	private $result = true;

	private $service_refresh;

	public function __construct( $args ) {
		if ( isset( $args[0] ) && $args[0] instanceof RefreshServices ) {
			$this->service_refresh = $args[0];
		}
	}

	private function run() {
		global $sitepress;
		$service = $sitepress->get_setting( 'translation_service' );
		if ( ! $service && ! is_array( $service ) ) {
			$this->result = true;

			return $this->result;
		}

		$this->result = ServicesRefreshBackoff::attempt(
			function () {
				return $this->get_service_refresh()->refresh_services();
			}
		);

		return $this->result;
	}


	public function run_admin() {
		return $this->run();
	}

	public function run_ajax() {

	}

	public function run_frontend() {

	}

	public function get_results() {
		return $this->result;
	}

	private function get_service_refresh() {
		if ( ! $this->service_refresh ) {
			$factory               = new RefreshServicesFactory();
			$this->service_refresh = $factory->create_an_instance();
		}

		return $this->service_refresh;
	}
}
