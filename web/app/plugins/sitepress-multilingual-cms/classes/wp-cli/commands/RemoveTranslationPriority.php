<?php
namespace WPML\CLI\Core\Commands;

use WPML\Upgrade\Commands\RemoveTranslationPriorityTaxonomy;
use WPML\Upgrade\CommandsStatus;

class RemoveTranslationPriority implements ICommand {

	public const COMMAND = 'upgrade remove-translation-priority';

	private $schema;

	private $sitepress;

	private $status;

	public function __construct( \WPML_Upgrade_Schema $schema, \SitePress $sitepress, CommandsStatus $status ) {
		$this->schema    = $schema;
		$this->sitepress = $sitepress;
		$this->status    = $status;
	}

	public function __invoke( $args, $assoc_args ) {
		$total   = 0;
		$command = new RemoveTranslationPriorityTaxonomy(
			[
				$this->schema,
				$this->sitepress,
				null,
				function ( $deleted ) use ( &$total ) {
					$total += $deleted;
					\WP_CLI::log( sprintf( 'Deleted %d relationship rows (%d so far).', $deleted, $total ) );
				},
			]
		);

		if ( ! $command->run_admin() ) {
			\WP_CLI::error( 'The clean-up did not finish: another process is running it, or a step failed. Run the command again in a minute.' );

			return;
		}

		$this->status->markAsExecuted( RemoveTranslationPriorityTaxonomy::class );
		\WP_CLI::success( sprintf( 'The Translation Priority data is removed (%d relationship rows deleted). The upgrade step is marked done.', $total ) );
	}

	public function get_command() {
		return self::COMMAND;
	}

	public function get_help() {
		return [
			'shortdesc' => 'Remove the data of the retired Translation Priority feature now, in one run.',
			'longdesc'  => "WPML 5.2.0 removes this data on its own, a few seconds per request. Use this command on a site with very many relationship rows on the Translation Priority terms, so the removal finishes at once. Back up the database first.\n\nSupport reference: wpmldev-12243.\n\n## EXAMPLES\n\n    wp wpml upgrade remove-translation-priority",
		];
	}
}
