<?php
namespace WPML\CLI\Core\Commands;

use WPML\Troubleshooting\PageIdLinkRepair\Runner;

class RepairPageIdLinks implements ICommand {

	public const COMMAND = 'repair-page-id-links';

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

		$totals = self::loop( Runner::forSite( $this->sitepress ), $isDryRun );

		foreach ( self::lines( $totals ) as $line ) {
			\WP_CLI::log( $line );
		}
		\WP_CLI::success( $isDryRun ? 'Dry run: nothing was written.' : sprintf( 'Repaired %d item(s).', $totals['rows'] ) );
	}

	public static function loop( $runner, bool $isDryRun ): array {
		$totals = [
			'dryRun'         => $isDryRun,
			'counts'         => [ 'content' => 0, 'excerpt' => 0, 'block' => 0, 'string' => 0 ],
			'rows'           => 0,
			'no_match'       => 0,
			'target_missing' => 0,
			'foreign'        => 0,
			'unreadable'     => 0,
			'samples'        => [],
		];

		$cursorPosts   = 0;
		$cursorStrings = 0;
		do {
			$batch = $runner->run( $isDryRun, $cursorPosts, $cursorStrings );

			foreach ( $batch['counts'] as $field => $count ) {
				$totals['counts'][ $field ] += (int) $count;
			}
			foreach ( [ 'rows', 'no_match', 'target_missing', 'foreign', 'unreadable' ] as $key ) {
				$totals[ $key ] += (int) $batch[ $key ];
			}
			foreach ( $batch['samples'] as $sample ) {
				if ( count( $totals['samples'] ) < Runner::SAMPLES ) {
					$totals['samples'][] = $sample;
				}
			}

			$cursorPosts   = (int) $batch['cursorPosts'];
			$cursorStrings = (int) $batch['cursorStrings'];
		} while ( empty( $batch['done'] ) );

		return $totals;
	}

	public static function lines( array $totals ): array {
		$verb  = $totals['dryRun'] ? 'to repair' : 'repaired';
		$lines = [
			sprintf( 'Links %s in page content: %d', $verb, $totals['counts']['content'] ),
			sprintf( 'Links %s in excerpts: %d', $verb, $totals['counts']['excerpt'] ),
			sprintf( 'Links %s in block settings: %d', $verb, $totals['counts']['block'] ),
			sprintf( 'Links %s in string translations: %d', $verb, $totals['counts']['string'] ),
			sprintf( $totals['dryRun'] ? 'Items to update: %d' : 'Items updated: %d', $totals['rows'] ),
			sprintf( 'Links left as they are (not in the shape older WPML versions wrote): %d', $totals['no_match'] ),
			sprintf( 'Links left as they are (the page they point to no longer exists): %d', $totals['target_missing'] ),
			sprintf( 'Links left as they are (they point to another site address): %d', $totals['foreign'] ),
			sprintf( 'Not checked (too large to read): %d', $totals['unreadable'] ),
		];
		foreach ( $totals['samples'] as $sample ) {
			$lines[] = 'string' === $sample['type']
				? sprintf( '  string %d %s (%s): %d link(s)', $sample['id'], $sample['name'], $sample['language'], $sample['links'] )
				: sprintf( '  post %d %s (%s): %d link(s)', $sample['id'], $sample['title'], $sample['language'], $sample['links'] );
		}

		return $lines;
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
			\WP_CLI::error( sprintf( 'The repair failed on %d site(s).', $failed ) );
		}

		\WP_CLI::success( $isDryRun ? 'Dry run on every site: nothing was written.' : 'Every site of the network was repaired.' );
	}

	public function get_command() {
		return self::COMMAND;
	}
}
