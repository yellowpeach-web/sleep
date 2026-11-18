<?php
namespace WPML\CLI\Core\Commands;

use WPML\TranslationPolicy\CutoverDryRunReport;
use WPML\TranslationPolicy\CutoverSet;
use WPML\TranslationPolicy\FeatureSwitch;
use WPML\TranslationPolicy\MarkCutover;
use WPML\Upgrade\CommandsStatus;

class TranslationPolicyCutover implements ICommand {

	public const COMMAND = 'translation-policy cutover';

	private $sitepress;

	public function __construct( \SitePress $sitepress ) {
		$this->sitepress = $sitepress;
	}

	public function __invoke( $args, $assoc_args ) {
		$isDryRun = ! empty( $assoc_args['dry-run'] );

		if ( ! empty( $assoc_args['network'] ) ) {
			$this->runNetwork( $isDryRun );

			return;
		}

		if ( FeatureSwitch::isOn() ) {
			\WP_CLI::success( sprintf( 'This site switched to the translation policy in WPML %s.', FeatureSwitch::cutoverVersion() ) );

			return;
		}

		if ( ! FeatureSwitch::isArmed() ) {
			\WP_CLI::error( sprintf( 'The switch is not armed. Add define( \'%s\', true ); to wp-config.php first.', FeatureSwitch::CONSTANT ) );
		}

		if ( $isDryRun ) {
			$this->dryRun();

			return;
		}

		$waiting = ( new MarkCutover( [ $this->sitepress ] ) )->waitingSteps();

		\WP_CLI::error( sprintf( 'The cutover did not finish. Steps still waiting: %s', $waiting ? implode( ', ', $waiting ) : 'none (check the WPML upgrade log)' ) );
	}

	private function dryRun(): void {
		$status = new CommandsStatus();
		foreach ( CutoverSet::stepsForDryRun() as $step ) {
			\WP_CLI::log( sprintf( '%s: %s%s', $status->hasBeenExecuted( $step ) ? 'done' : 'would run', $step, $this->report( $step ) ) );
		}
		\WP_CLI::log( sprintf( 'would run: %s (syncs the policy again, then writes the latch)', MarkCutover::class ) );
		\WP_CLI::success( 'Dry run: nothing was written.' );
	}

	private function report( string $step ): string {
		if ( ! in_array( $step, CutoverSet::CORE_STEPS, true ) || ! is_subclass_of( $step, CutoverDryRunReport::class ) ) {
			return '';
		}

		return ' (' . ( new $step() )->dryRunReport() . ')';
	}

	private function runNetwork( bool $isDryRun ): void {
		if ( ! is_multisite() ) {
			\WP_CLI::error( 'This is not a network. Run the command without --network.' );
		}

		$failed = 0;
		foreach ( get_sites( [ 'number' => 0 ] ) as $site ) {
			$url    = $site->domain . $site->path;
			$result = \WP_CLI::runcommand(
				sprintf( 'wpml %s --url=%s%s', self::COMMAND, escapeshellarg( $url ), $isDryRun ? ' --dry-run' : '' ),
				[
					'launch'     => true,
					'exit_error' => false,
					'return'     => 'all',
				]
			);

			\WP_CLI::log( sprintf( '%s: %s', $url, trim( $result->stdout . ' ' . $result->stderr ) ) );
			if ( 0 !== $result->return_code ) {
				$failed++;
			}
		}

		if ( $failed ) {
			\WP_CLI::error( sprintf( '%d site(s) did not cut over.', $failed ) );
		}

		\WP_CLI::success( $isDryRun ? 'Dry run on every site: nothing was written.' : 'Every site of the network cut over.' );
	}

	public function get_command() {
		return self::COMMAND;
	}
}
