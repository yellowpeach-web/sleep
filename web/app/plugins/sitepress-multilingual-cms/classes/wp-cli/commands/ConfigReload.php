<?php
namespace WPML\CLI\Core\Commands;

class ConfigReload implements ICommand {

	public const COMMAND = 'config reload';

	private $config_update;

	public function __construct( \WPML_Config_Update $config_update ) {
		$this->config_update = $config_update;
	}

	public function __invoke( $args, $assoc_args ) {
		if ( ! \WPML_Config::reload_now() ) {
			\WP_CLI::error( 'WPML is not set up yet on this site: there is nothing to apply the configuration to. Complete the WPML setup, then run the command again.' );

			return;
		}

		$this->config_update->mark_local_config_imported();

		\WP_CLI::success( 'WPML read the wpml-config.xml files again and applied them.' );
	}

	public function get_command() {
		return self::COMMAND;
	}

	public function get_help() {
		return [
			'shortdesc' => 'Read the wpml-config.xml files of the active plugins and the theme again, and apply them.',
			'longdesc'  => "WPML reads a changed wpml-config.xml on the next WP-CLI command or cron run by itself. Use this command in a deployment script to apply the files at a known step.\n\n## EXAMPLES\n\n    wp wpml config reload",
		];
	}
}
