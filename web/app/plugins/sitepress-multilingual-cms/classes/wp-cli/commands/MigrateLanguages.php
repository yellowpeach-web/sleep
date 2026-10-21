<?php
namespace WPML\CLI\Core\Commands;

use WPML\Upgrade\Commands\MigrateLanguagesToCountryModel;

class MigrateLanguages implements ICommand {

	private $schema;

	public function __construct( \WPML_Upgrade_Schema $schema ) {
		$this->schema = $schema;
	}

	public function __invoke( $args, $assoc_args ) {
		if ( \WPML_Settings_Failsafe_Loader::isUnrecoverable() ) {
			\WP_CLI::error(
				'WPML settings are quarantined because the stored configuration could not be recovered. The language migration was not run. Restore a valid settings backup or complete an explicit WPML reset, then retry.'
			);

			return;
		}

		$command = new MigrateLanguagesToCountryModel( [ $this->schema ] );

		if ( $command->run() ) {
			\WP_CLI::success( 'WPML languages migrated to the (language[-script], country) model.' );
		} else {
			\WP_CLI::warning( 'Migration deferred: the Phase-1 language schema (country/type/display_code) is not in place yet.' );
		}
	}

	public function get_command() {
		return 'migrate-languages';
	}
}
