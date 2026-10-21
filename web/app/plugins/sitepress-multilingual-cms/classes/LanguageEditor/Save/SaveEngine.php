<?php

namespace WPML\LanguageEditor\Save;

use WPML\LanguageEditor\Save\Phase\DisplayCodePhase;
use WPML\LanguageEditor\Save\Phase\CountryPhase;
use WPML\LanguageEditor\Save\Phase\LocalePhase;
use WPML\LanguageEditor\Save\Phase\DeleteContentPhase;
use WPML\LanguageEditor\Save\Phase\PhaseProcessor;
use WPML\LanguageEditor\Save\Phase\PhaseResult;
use WPML\Utilities\Lock;
use function WPML\Container\make;

class SaveEngine {

	const LOCK_NAME             = 'structural_language_save';
	const LOCK_TIME             = 90;
	const MAX_RETRIES           = 3;
	const MAX_CONSECUTIVE_FAILS = 6;

	const UNIT_FIELD_WRITES = 'field_writes';

	private $repository;

	private $wpdb;

	private $processors;

	public function __construct( SaveTaskRepository $repository, \wpdb $wpdb ) {
		$this->repository = $repository;
		$this->wpdb       = $wpdb;
	}

	private function processors() {
		if ( null === $this->processors ) {
			$languages = new \WPML\LanguageEditor\Adapter\LanguageRepository();
			$ordered   = [
				new DisplayCodePhase( $this->wpdb ),
				new CountryPhase( $this->wpdb, $languages ),
				new LocalePhase( $this->wpdb ),
				new DeleteContentPhase(),
			];
			$this->processors = [];
			foreach ( $ordered as $p ) {
				$this->processors[ $p->getId() ] = $p;
			}
		}
		return $this->processors;
	}

	public function start( array $changes ) {
		$active = $this->repository->findActive();
		if ( $active ) {
			return $active;
		}

		$task = new SaveTask();

		if ( ! $this->validChanges( $changes ) ) {
			return $this->failBeforeStart( $task, 'invalid_changes' );
		}

		foreach ( $changes as $change ) {
			if ( '' !== DeleteContentPhase::defaultLanguageIn( $change ) ) {
				return $this->failBeforeStart( $task, DeleteContentPhase::REFUSED_DEFAULT );
			}
		}

		$changes = array_values( $changes );

		$plan = ChangeOrdering::plan( $changes );
		if ( ! $plan['ok'] ) {
			return $this->failBeforeStart( $task, $plan['error'] );
		}
		$writeSteps = $plan['steps'];

		$units  = [];
		$totals = [];
		$total  = 0;

		if ( ! empty( $writeSteps ) ) {
			$units[]                            = [ 'phase' => self::UNIT_FIELD_WRITES ];
			$totals[ self::UNIT_FIELD_WRITES ]  = count( $writeSteps );
			$total                             += count( $writeSteps );
		}

		foreach ( $this->processors() as $id => $processor ) {
			foreach ( $this->phaseChangeOrder( $id, $changes, $writeSteps ) as $changeIdx ) {
				$change = $changes[ $changeIdx ];
				if ( $processor->applies( $change ) ) {
					$count    = $processor->getTotal( $change );
					$units[]  = [
						'phase'     => $id,
						'changeIdx' => $changeIdx,
					];
					$totals[ $id . ':' . $changeIdx ] = $count;
					$total                           += $count;
				}
			}
		}

		$task->setStatus( SaveTask::STATUS_IN_PROGRESS );
		$task->setTotalCount( $total );
		$task->setCompletedCount( 0 );
		$task->setPayload(
			[
				'changes'             => $changes,
				'units'               => $units,
				'unitIdx'             => 0,
				'offset'              => 0,
				'totals'              => $totals,
				'writeSteps'          => $writeSteps,
				'skipped'             => [],
				'unitStatus'          => [],
				'consecutiveFailures' => 0,
			]
		);

		if ( empty( $units ) || 0 === $total ) {
			$task->setStatus( SaveTask::STATUS_COMPLETED );
			$task->setCompletedCount( $total );
		}

		return $this->repository->insert( $task );
	}

	public function advance( $taskId ) {
		$task = $this->repository->findById( $taskId );
		if ( ! $task ) {
			return null;
		}
		if ( $task->isTerminal() ) {
			return $task;
		}
		if ( $this->failLegacyPayload( $task ) ) {
			return $task;
		}

		$lock = $this->lock();
		if ( ! $lock->create( self::LOCK_TIME ) ) {
			return $task;
		}

		try {
			$task->setStatus( SaveTask::STATUS_IN_PROGRESS );
			$this->processOneChunk( $task );
			$this->repository->update( $task );
		} finally {
			$lock->release();
		}

		return $task;
	}

	public function resume( $taskId ) {
		$task = $this->repository->findById( $taskId );
		if ( ! $task ) {
			return null;
		}
		if ( $task->isTerminal() ) {
			return $task;
		}
		if ( $this->failLegacyPayload( $task ) ) {
			return $task;
		}

		if ( $this->guardAlreadyDone( $task ) ) {
			$task->setStatus(
				empty( $task->getCursor( 'skipped', [] ) ) ? SaveTask::STATUS_COMPLETED : SaveTask::STATUS_COMPLETED_WITH_SKIPS
			);
			$this->repository->update( $task );
			return $task;
		}

		return $this->advance( $taskId );
	}

	public function status( $taskId = null ) {
		$task = $taskId ? $this->repository->findById( $taskId ) : $this->repository->findActive();

		return $task ? $this->withOperationRecord( $task ) : $task;
	}

	private function withOperationRecord( SaveTask $task ) {
		foreach ( (array) $task->getCursor( 'changes', [] ) as $change ) {
			if ( ! is_array( $change ) || empty( $change['delete']['content'] ) ) {
				continue;
			}

			$scope = \WPML\LanguageEditor\Save\Phase\RemovalScope::fromChange( $change );

			if ( $scope->isLegacy() || ! $scope->codesList() ) {
				continue;
			}

			$record = \WPML\LanguageEditor\Save\Phase\RemovalRecord::latest( $scope->codesList() );

			if ( $record ) {
				$task->setOperationRecord( \WPML\LanguageEditor\Save\Phase\RemovalRecord::summary( $record ) );

				return $task;
			}
		}

		return $task;
	}

	public function pause( $taskId ) {
		$task = $this->repository->findById( $taskId );
		if ( $task && ! $task->isTerminal() ) {
			$task->setStatus( SaveTask::STATUS_PAUSED );
			$this->repository->update( $task );
		}
	}

	private function processOneChunk( SaveTask $task ) {
		$units   = $task->getCursor( 'units', [] );
		$unitIdx = (int) $task->getCursor( 'unitIdx', 0 );

		if ( $unitIdx >= count( $units ) ) {
			$this->finish( $task );
			return;
		}

		$unit      = $units[ $unitIdx ];
		$unitKey   = $this->unitKey( $unit );
		$offset    = (int) $task->getCursor( 'offset', 0 );
		$totals    = $task->getCursor( 'totals', [] );
		$unitTotal = isset( $totals[ $unitKey ] ) ? (int) $totals[ $unitKey ] : 0;

		if ( self::UNIT_FIELD_WRITES === $unit['phase'] ) {
			if ( $offset >= $unitTotal ) {
				$this->advanceUnit( $task, $unitKey, 'done' );
				return;
			}
			$writeSteps = $task->getCursor( 'writeSteps', [] );
			if ( ! isset( $writeSteps[ $offset ] ) ) {
				$this->advanceUnit( $task, $unitKey, 'done' );
				return;
			}
			$result    = ( new FieldWriteExecutor( $this->wpdb ) )->writeStep( $writeSteps[ $offset ] );
			$skippable = false;
		} else {
			$processor = $this->processor( $unit['phase'] );
			if ( ! $processor || $offset >= $unitTotal ) {
				$this->advanceUnit( $task, $unitKey, 'done' );
				return;
			}
			$result    = $processor->processChunk( $this->unitChange( $task, $unit ), $offset );
			$skippable = $processor->isSkippable();
		}

		if ( $result->hasHardError() && ! $skippable ) {
			$this->recordUnitStatus( $task, $unitKey, 'failed' );
			$task->setStatus( SaveTask::STATUS_FAILED );
			return;
		}

		if ( $result->hasHardError() ) {
			$this->recordSkip( $task, $unitKey, $offset, (string) $result->getHardError() );
			$this->advanceUnit( $task, $unitKey, 'skipped' );
			return;
		}

		if ( $result->isRetryable() ) {
			$retries = $task->getRetryCount() + 1;
			$task->setRetryCount( $retries );
			$consec = (int) $task->getCursor( 'consecutiveFailures', 0 ) + 1;
			$task->setCursor( 'consecutiveFailures', $consec );

			if ( $retries >= self::MAX_RETRIES || $consec >= self::MAX_CONSECUTIVE_FAILS ) {
				$task->setRetryCount( 0 );
				if ( $skippable ) {
					$this->recordSkip( $task, $unitKey, $offset, 'phase_skipped_after_retries' );
					if ( 0 === strpos( $unitKey, CountryPhase::ID . ':' ) ) {
						$this->recordNote(
							$task,
							$unitKey,
							[ 'remap' => 'failed', 'reason' => 'phase_skipped_after_retries' ]
						);
					}
					$this->recordUnitStatus( $task, $unitKey, 'skipped' );
					$this->advanceUnit( $task, $unitKey, 'skipped' );
				} else {
					$this->recordUnitStatus( $task, $unitKey, 'failed' );
					$task->setStatus( SaveTask::STATUS_FAILED );
				}
			}
			return;
		}

		$task->setRetryCount( 0 );
		$task->setCursor( 'consecutiveFailures', 0 );

		$notes = $result->getNotes();
		if ( ! empty( $notes ) ) {
			$this->recordNote( $task, $unitKey, $notes );
		}

		foreach ( $result->getSkipped() as $s ) {
			$reason = isset( $s['reason'] ) ? $s['reason'] : 'skipped';
			$detail = isset( $s['detail'] ) && '' !== (string) $s['detail'] ? (string) $s['detail'] : null;
			$this->recordSkip( $task, $unitKey, isset( $s['item'] ) ? $s['item'] : null, $reason, $detail );

			if ( 0 === strpos( $unitKey, CountryPhase::ID . ':' ) ) {
				$prior   = $task->getCursor( 'phaseOutcomes', [] );
				$prior   = isset( $prior[ $unitKey ] ) && is_array( $prior[ $unitKey ] ) ? $prior[ $unitKey ] : [];
				$verdict = [ 'remap' => 'skipped', 'reason' => $reason ];
				if ( null !== $detail ) {
					$verdict['detail'] = $detail;
				}
				$this->recordNote( $task, $unitKey, array_merge( $prior, $verdict ) );
			}
		}

		$processed = max( 1, $result->getProcessed() );
		$newOffset = $offset + $processed;
		$task->addCompletedCount( $processed );
		$task->setCursor( 'offset', $newOffset );

		if ( $newOffset >= $unitTotal ) {
			$this->advanceUnit( $task, $unitKey, 'done' );
		}
	}

	private function advanceUnit( SaveTask $task, $unitKey, $outcome ) {
		$this->recordUnitStatus( $task, $unitKey, $outcome );
		$unitIdx = (int) $task->getCursor( 'unitIdx', 0 ) + 1;
		$task->setCursor( 'unitIdx', $unitIdx );
		$task->setCursor( 'offset', 0 );

		$units = $task->getCursor( 'units', [] );
		if ( $unitIdx >= count( $units ) ) {
			$this->finish( $task );
		}
	}

	private function finish( SaveTask $task ) {
		$skipped    = $task->getCursor( 'skipped', [] );
		$unitStatus = $task->getCursor( 'unitStatus', [] );
		$hasSkips   = ! empty( $skipped ) || in_array( 'skipped', (array) $unitStatus, true );
		$task->setCompletedCount( $task->getTotalCount() );
		$task->setStatus( $hasSkips ? SaveTask::STATUS_COMPLETED_WITH_SKIPS : SaveTask::STATUS_COMPLETED );
	}

	private function recordSkip( SaveTask $task, $unitKey, $item, $reason, $detail = null ) {
		$skipped = $task->getCursor( 'skipped', [] );
		$entry   = [ 'phase' => $unitKey, 'item' => $item, 'reason' => $reason ];
		if ( null !== $detail && '' !== (string) $detail ) {
			$entry['detail'] = (string) $detail;
		}
		$skipped[] = $entry;
		$task->setCursor( 'skipped', $skipped );
	}

	private function recordNote( SaveTask $task, $unitKey, array $notes ) {
		$outcomes             = $task->getCursor( 'phaseOutcomes', [] );
		$outcomes[ $unitKey ] = $notes;
		$task->setCursor( 'phaseOutcomes', $outcomes );
	}

	private function recordUnitStatus( SaveTask $task, $unitKey, $status ) {
		$us             = $task->getCursor( 'unitStatus', [] );
		$us[ $unitKey ] = $status;
		$task->setCursor( 'unitStatus', $us );
	}

	private function guardAlreadyDone( SaveTask $task ) {
		$units     = $task->getCursor( 'units', [] );
		$unitIdx   = (int) $task->getCursor( 'unitIdx', 0 );
		$offset    = (int) $task->getCursor( 'offset', 0 );
		$unitCount = count( $units );

		for ( $i = $unitIdx; $i < $unitCount; $i++ ) {
			$unit = $units[ $i ];
			if ( self::UNIT_FIELD_WRITES === $unit['phase'] ) {
				$writeSteps = $task->getCursor( 'writeSteps', [] );
				$unitTotal  = count( $writeSteps );
			} else {
				$processor = $this->processor( $unit['phase'] );
				if ( ! $processor ) {
					continue;
				}
				$unitTotal = $processor->getTotal( $this->unitChange( $task, $unit ) );
			}
			$remaining = $unitTotal - ( $i === $unitIdx ? $offset : 0 );
			if ( $remaining > 0 ) {
				return false;
			}
		}
		return true;
	}

	private function phaseChangeOrder( $phaseId, array $changes, array $writeSteps ) {
		$order = array_keys( $changes );
		if ( DisplayCodePhase::ID !== $phaseId ) {
			return $order;
		}

		$byCode = [];
		foreach ( $changes as $idx => $change ) {
			if ( isset( $change['code'] ) ) {
				$byCode[ (string) $change['code'] ] = $idx;
			}
		}

		$planned = [];
		foreach ( $writeSteps as $step ) {
			if ( DisplayCodePhase::ID === $step['field'] && empty( $step['temp'] ) && isset( $byCode[ $step['code'] ] ) ) {
				$planned[] = $byCode[ $step['code'] ];
			}
		}

		$rest = array_values( array_diff( $order, $planned ) );
		return array_merge( $planned, $rest );
	}

	private function unitChange( SaveTask $task, array $unit ) {
		$changes   = $task->getCursor( 'changes', [] );
		$changeIdx = isset( $unit['changeIdx'] ) ? (int) $unit['changeIdx'] : -1;
		return isset( $changes[ $changeIdx ] ) ? (array) $changes[ $changeIdx ] : [];
	}

	private function unitKey( array $unit ) {
		if ( self::UNIT_FIELD_WRITES === $unit['phase'] ) {
			return self::UNIT_FIELD_WRITES;
		}
		return $unit['phase'] . ':' . ( isset( $unit['changeIdx'] ) ? (int) $unit['changeIdx'] : 0 );
	}

	private function failBeforeStart( SaveTask $task, $error ) {
		$task->setStatus( SaveTask::STATUS_FAILED );
		$task->setTotalCount( 0 );
		$task->setCompletedCount( 0 );
		$task->setPayload(
			[
				'changes'    => [],
				'units'      => [],
				'unitIdx'    => 0,
				'offset'     => 0,
				'totals'     => [],
				'writeSteps' => [],
				'skipped'    => [],
				'unitStatus' => [],
				'error'      => $error,
			]
		);
		return $this->repository->insert( $task );
	}

	private function validChanges( array $changes ) {
		if ( empty( $changes ) ) {
			return false;
		}
		foreach ( $changes as $change ) {
			if ( ! is_array( $change ) || empty( $change['code'] ) ) {
				return false;
			}
		}
		return true;
	}

	private function failLegacyPayload( SaveTask $task ) {
		if ( null === $task->getCursor( 'units', null ) && null !== $task->getCursor( 'phases', null ) ) {
			$task->setCursor( 'error', 'stale_task_format' );
			$task->setStatus( SaveTask::STATUS_FAILED );
			$this->repository->update( $task );
			return true;
		}
		return false;
	}

	private function processor( $id ) {
		$processors = $this->processors();
		return isset( $processors[ $id ] ) ? $processors[ $id ] : null;
	}

	private function lock() {
		return make( Lock::class, [ ':name' => self::LOCK_NAME ] );
	}
}
