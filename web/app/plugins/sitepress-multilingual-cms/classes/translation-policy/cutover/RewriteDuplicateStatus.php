<?php

namespace WPML\TranslationPolicy;

use WPML\TranslationPolicy\Duplicate\CopyProvenance;
use WPML\TranslationPolicy\Duplicate\DuplicateRows;
use WPML\TranslationPolicy\Duplicate\RetiredNotice;

class RewriteDuplicateStatus extends LateCutoverStep implements CutoverDryRunReport {

	const BATCH = 100;

	private $rows;

	private $provenance;

	private $shouldRewriteAll;

	private $notice;

	public function __construct( array $args = [] ) {
		global $wpdb;

		parent::__construct( $args );

		$this->rows             = isset( $args[3] ) && $args[3] instanceof DuplicateRows ? $args[3] : new DuplicateRows( $wpdb );
		$this->provenance       = isset( $args[4] ) && $args[4] instanceof CopyProvenance ? $args[4] : null;
		$this->shouldRewriteAll = isset( $args[5] ) ? (bool) $args[5] : defined( 'WP_CLI' ) && WP_CLI;
		$this->notice           = isset( $args[6] ) && $args[6] instanceof RetiredNotice ? $args[6] : new RetiredNotice();
	}

	protected function work(): bool {
		$duplicates = 0;
		try {
			do {
				$batch       = $this->rows->next( self::BATCH );
				$isFullBatch = self::BATCH === count( $batch );
				foreach ( $batch as $row ) {
					if ( ! $this->rewrite( $row ) ) {
						$this->logStop( $row['rid'], 'the copy entry or the status was not written' );

						return false;
					}
					$duplicates += $row['isDuplicate'] ? 1 : 0;
				}
			} while ( $this->shouldRewriteAll && $isFullBatch );
		} catch ( \Throwable $e ) {
			$this->logStop( isset( $row['rid'] ) ? (int) $row['rid'] : 0, $e->getMessage() );

			return false;
		} finally {
			$this->notice->count( $duplicates );
		}

		if ( 0 !== array_sum( $this->rows->count() ) ) {
			return false;
		}

		$this->notice->arm();

		return true;
	}

	public function dryRunReport(): string {
		$count = $this->rows->count();

		return sprintf(
			'%d duplicate(s) to rewrite as complete, each with a copy entry; %d translated independently to rewrite as complete',
			$count['duplicates'],
			$count['independent']
		);
	}

	private function rewrite( array $row ): bool {
		if ( $row['isDuplicate'] && ! $this->provenance()->write( $row['postId'], $row['language'], $row['originalId'] ) ) {
			return false;
		}

		return $this->rows->markComplete( $row['rid'] );
	}

	private function provenance(): CopyProvenance {
		if ( null === $this->provenance ) {
			$this->provenance = CopyProvenance::create();
		}

		return $this->provenance;
	}

	private function logStop( int $rid, string $why ) {
		error_log( sprintf( 'WPML cutover: the rewrite of status 9 stopped at icl_translation_status row %d (%s). The next admin or WP-CLI request tries again.', $rid, $why ) );
	}
}
