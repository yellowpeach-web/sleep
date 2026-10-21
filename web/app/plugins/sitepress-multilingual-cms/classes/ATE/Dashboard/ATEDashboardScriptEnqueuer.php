<?php

namespace WPML\TM\ATE\Dashboard;

use WPML\UIPage;
use function WPML\Container\make;

class ATEDashboardScriptEnqueuer implements \IWPML_Backend_Action {

	private $dashboardLoader;

	private $ateConsoleSectionFactory;

	public function __construct(
		?ATEDashboardLoader $dashboardLoader = null,
		?\WPML_TM_AMS_ATE_Console_Section_Factory $ateConsoleSectionFactory = null
	) {
		$this->dashboardLoader           = $dashboardLoader;
		$this->ateConsoleSectionFactory = $ateConsoleSectionFactory;
	}

	public function add_hooks() {
		add_action( 'admin_enqueue_scripts', [ $this, 'enqueue' ] );
	}

	public function enqueue() {
		if ( ! UIPage::isWpmlAdminScreen( $_GET ) ) {
			return;
		}

		if ( ! $this->ateConsoleSectionFactory && ! class_exists( '\WPML_TM_AMS_ATE_Console_Section_Factory' ) ) {
			return;
		}

		$ateConsoleSection = $this->get_ate_console_section_factory()->create();
		if ( ! $ateConsoleSection ) {
			return;
		}

		$dashboardLoader = $this->get_dashboard_loader();
		$dashboardLoader->registerScript();
		$dashboardLoader->initializeScript( $ateConsoleSection->get_ams_constructor() );
	}

	private function get_dashboard_loader() {
		if ( ! $this->dashboardLoader ) {
			$this->dashboardLoader = make( ATEDashboardLoader::class );
		}

		return $this->dashboardLoader;
	}

	private function get_ate_console_section_factory() {
		if ( ! $this->ateConsoleSectionFactory ) {
			$this->ateConsoleSectionFactory = new \WPML_TM_AMS_ATE_Console_Section_Factory();
		}

		return $this->ateConsoleSectionFactory;
	}
}
