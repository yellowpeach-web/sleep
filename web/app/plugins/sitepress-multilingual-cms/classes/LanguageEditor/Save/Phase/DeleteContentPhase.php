<?php

namespace WPML\LanguageEditor\Save\Phase;

use WPML\OperationRecord\Repository;
use WPML\OperationRecord\TrashMarkers;
use WPML\OperationRecord\UndoWindow;
use WPML\Posts\TranslatedContentOfLanguages;

class DeleteContentPhase implements PhaseProcessor {

	const ID         = 'delete_content';
	const CHUNK_SIZE = 20;

	const REFUSED_DEFAULT = 'delete_default_language';

	const TRASH_UNDO_WINDOW = UndoWindow::DEFAULT_DAYS * UndoWindow::SECONDS_PER_DAY;

	private $undoWindow;

	public function __construct( ?UndoWindow $undoWindow = null ) {
		$this->undoWindow = $undoWindow ? $undoWindow : new UndoWindow();
	}

	public function getId() {
		return self::ID;
	}

	public function applies( array $change ) {
		return ! empty( $change['delete']['content'] ) && (bool) self::codes( $change )
			&& '' === self::defaultLanguageIn( $change );
	}

	public static function defaultLanguageIn( array $change ) {
		if ( empty( $change['delete']['content'] ) ) {
			return '';
		}
		$default = (string) apply_filters( 'wpml_default_language', null );

		return ( '' !== $default && in_array( $default, self::codes( $change ), true ) ) ? $default : '';
	}

	public function getTotal( array $change ) {
		if ( ! $this->applies( $change ) ) {
			return 0;
		}

		$scope = RemovalScope::fromChange( $change );

		if ( $scope->isLegacy() ) {
			$counts = TranslatedContentOfLanguages::counts( $scope->codesList() );

			return (int) $counts['total'];
		}

		return $this->remainingIn( $scope );
	}

	private function remainingIn( RemovalScope $scope ) {
		$counts = TranslatedContentOfLanguages::countsFiltered(
			$scope->codesList(),
			$scope->includeMap(),
			$scope->route()
		);

		$total = (int) $counts['total'];

		if ( $scope->deletesStrings() ) {
			$total += LanguageStringTranslations::remaining( $scope->codesList() );
		}

		if ( $scope->cancelsJobs() ) {
			$total += LanguageJobs::remaining( $scope->codesList() );
		}

		return $total;
	}

	public function getChunkSize() {
		return self::CHUNK_SIZE;
	}

	public function isSkippable() {
		return true;
	}

	public function processChunk( array $change, $offset ) {
		if ( '' !== self::defaultLanguageIn( $change ) ) {
			return PhaseResult::hardFail( self::REFUSED_DEFAULT );
		}

		$scope = RemovalScope::fromChange( $change );

		if ( $scope->isLegacy() ) {
			$deleted = TranslatedContentOfLanguages::deleteChunk(
				$scope->codesList(),
				self::CHUNK_SIZE
			);

			return PhaseResult::ok( $deleted > 0 ? $deleted : 1 );
		}

		return $this->processScopedChunk( $scope );
	}

	private function processScopedChunk( RemovalScope $scope ) {
		$codes = $scope->codesList();

		$before = $this->remainingIn( $scope );

		if ( 0 === $before && null === RemovalRecord::running( $codes ) && null !== RemovalRecord::latest( $codes ) ) {
			return PhaseResult::ok( 1 );
		}

		$repository = new Repository();
		$recordId   = RemovalRecord::ensure( $codes, $scope->recordRoute() );
		$record     = $repository->get( $recordId );
		$record     = is_array( $record ) ? $record : array();

		$patch   = array();
		$skips   = array();
		$done    = 0;
		$acted   = false;

		if ( $scope->cancelsJobs() && ! isset( $record['jobs'] ) ) {
			$acted = $this->cancelTheLanguagesJobs( $codes, $patch, $skips ) || $acted;
		}

		$excluded = $this->recordedSkippedIds( $record );

		$outcome = SyncSuspension::around(
			function () use ( $scope, $excluded ) {
				return TranslatedContentOfLanguages::deleteChunkFiltered(
					$scope->codesList(),
					self::CHUNK_SIZE,
					$scope->includeMap(),
					$scope->route(),
					$excluded
				);
			}
		);

		$done += (int) $outcome['deleted'];

		foreach ( $outcome['counts'] as $type => $count ) {
			$patch['counts_add'][ $type ]['removed'] = ( isset( $patch['counts_add'][ $type ]['removed'] ) ? $patch['counts_add'][ $type ]['removed'] : 0 ) + (int) $count;
		}

		foreach ( $outcome['skipped'] as $skipped ) {
			$type = (string) $skipped['type'];

			$patch['skipped_add'][ $type ][]          = array(
				'id'     => (int) $skipped['id'],
				'reason' => (string) $skipped['reason'],
			);
			$patch['counts_add'][ $type ]['skipped'] = ( isset( $patch['counts_add'][ $type ]['skipped'] ) ? $patch['counts_add'][ $type ]['skipped'] : 0 ) + 1;

			$skips[] = array(
				'item'   => (int) $skipped['id'],
				'reason' => (string) $skipped['reason'],
			);
		}

		if ( TranslatedContentOfLanguages::ROUTE_TRASH === $scope->route() && $outcome['trashed'] ) {
			$markers = new TrashMarkers();

			foreach ( $outcome['trashed'] as $trashedId ) {
				if ( 'trash' !== get_post_status( $trashedId ) ) {
					continue;
				}

				$markers->mark( $trashedId, $recordId );
			}
		}

		$acted = $acted || $outcome['deleted'] > 0 || (bool) $outcome['skipped'];

		$budget = self::CHUNK_SIZE - $done;

		if ( $budget > 0 && $scope->deletesStrings() ) {
			$strings = LanguageStringTranslations::deleteChunk( $codes, $budget );

			if ( $strings > 0 ) {
				$done   += $strings;
				$budget -= $strings;
				$acted   = true;

				$patch['counts_add'][ LanguageStringTranslations::RECORD_TYPE ]['removed'] = $strings;
			}
		}

		if ( $budget > 0 && $scope->cancelsJobs() ) {
			$jobs = LanguageJobs::removeChunk( $codes, $budget );

			if ( $jobs > 0 ) {
				$done += $jobs;
				$acted = true;

				$patch['counts_add'][ LanguageJobs::RECORD_TYPE ]['removed'] = $jobs;
			}
		}

		$after = $before;

		if ( $acted ) {
			if ( $patch ) {
				$repository->patch( $recordId, $patch );
			}

			$after = $this->remainingIn( $scope );

			if ( 0 === $after ) {
				$fresh = $repository->get( $recordId );

				if ( $fresh && Repository::STATUS_RUNNING === ( isset( $fresh['status'] ) ? $fresh['status'] : '' ) ) {
					$repository->finalize( $recordId, $this->withUndoWindow( $fresh, $scope, array() ) );
				}
			}
		} elseif ( $record && Repository::STATUS_RUNNING === ( isset( $record['status'] ) ? $record['status'] : '' ) ) {
			$repository->finalize( $recordId, $this->withUndoWindow( $record, $scope, $patch ) );
		}

		return PhaseResult::ok( max( $done, $before - $after, 1 ), $skips );
	}

	private function withUndoWindow( array $record, RemovalScope $scope, array $patch ) {
		if ( TranslatedContentOfLanguages::ROUTE_TRASH !== $scope->route() ) {
			return $patch;
		}

		if ( isset( $record['undo_until'] ) && null !== $record['undo_until'] ) {
			return $patch;
		}

		$started = isset( $record['started'] ) ? (int) $record['started'] : 0;

		if ( $started <= 0 ) {
			return $patch;
		}

		$until = $this->undoWindow->until( $started );

		if ( null === $until ) {
			return $patch;
		}

		$patch['undo_until'] = $until;

		return $patch;
	}

	private function cancelTheLanguagesJobs( array $codes, array &$patch, array &$skips ) {
		$split = LanguageJobs::collect( $codes );

		$failure = LanguageJobs::cancel( array_merge( $split['queued'], $split['in_progress'] ) );

		$patch['jobs_add'] = array(
			'cancelled'           => count( $split['queued'] ),
			'stopped_in_progress' => count( $split['in_progress'] ),
		);

		if ( '' !== $failure ) {
			$patch['skipped_add'][ LanguageJobs::RECORD_TYPE ][] = array(
				'id'     => 0,
				'reason' => 'cancel-failed',
			);

			$skips[] = array(
				'item'   => null,
				'reason' => 'jobs_cancel_failed',
			);
		}

		return true;
	}

	private function recordedSkippedIds( array $record ) {
		$ids = array();

		if ( ! isset( $record['skipped'] ) || ! is_array( $record['skipped'] ) ) {
			return $ids;
		}

		foreach ( $record['skipped'] as $entries ) {
			foreach ( (array) $entries as $entry ) {
				if ( isset( $entry['id'] ) && (int) $entry['id'] > 0 ) {
					$ids[] = (int) $entry['id'];
				}
			}
		}

		return array_values( array_unique( $ids ) );
	}

	private static function codes( array $change ) {
		if ( ! empty( $change['delete']['codes'] ) && is_array( $change['delete']['codes'] ) ) {
			return array_values( array_filter( array_map( 'strval', $change['delete']['codes'] ) ) );
		}

		return ! empty( $change['code'] ) ? array( (string) $change['code'] ) : array();
	}
}
