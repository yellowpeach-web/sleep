<?php
namespace WPML\CLI\Core\Commands;

use WPML\Troubleshooting\Actions\CacheClear;

class ClearCache implements ICommand {

	public function __invoke( $args, $assoc_args ) {
		if ( ( new CacheClear() )->run() ) {
			\WP_CLI::success( 'WPML cache cleared' );

			return;
		}

		\WP_CLI::error( 'WPML cache could not be fully cleared. See the site error log; some cached data may still be live.' );
	}

	public function get_command() {
		return 'clear-cache';
	}

}
