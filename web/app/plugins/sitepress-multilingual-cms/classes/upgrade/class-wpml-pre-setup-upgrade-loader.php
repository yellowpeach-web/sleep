<?php

class WPML_Pre_Setup_Upgrade_Loader implements IWPML_Action {

	private $upgrade_loader;

	private $sitepress;

	private $factory;

	public function __construct(
		WPML_Upgrade_Loader $upgrade_loader,
		SitePress $sitepress,
		WPML_Upgrade_Command_Factory $factory
	) {
		$this->upgrade_loader = $upgrade_loader;
		$this->sitepress      = $sitepress;
		$this->factory        = $factory;
	}

	public function add_hooks() {
		add_action( 'wpml_loaded', array( $this, 'run_pre_setup_upgrade' ) );
		register_activation_hook( WPML_PLUGIN_PATH . '/' . WPML_PLUGIN_FILE, array( $this, 'run_pre_setup_upgrade' ) );
	}

	public function run_pre_setup_upgrade() {
		if (
			WPML_Settings_Failsafe_Loader::isUnrecoverable()
			|| get_transient( WPML_Upgrade_Loader::TRANSIENT_UPGRADE_IN_PROGRESS )
		) {
			return;
		}

		$commands = self::filter_pre_setup( $this->upgrade_loader->get_command_definitions() );
		if ( ! $commands ) {
			return;
		}

		$upgrade = new WPML_Upgrade( $commands, $this->sitepress, $this->factory );
		$upgrade->run();
	}

	public static function filter_pre_setup( array $definitions ) {
		return array_values(
			array_filter(
				$definitions,
				function ( $definition ) {
					$class = $definition->get_class_name();

					return class_exists( $class )
						&& in_array( IWPML_Pre_Setup_Upgrade_Command::class, class_implements( $class ) ?: [], true );
				}
			)
		);
	}
}
