<?php

namespace WPML\Media\TextScan;

class ScanQueue {

	const HOOK         = 'wpml_media_text_scan_tick';
	const BATCH        = 50;
	const ITEM_BUDGET  = 8;
	const CHUNK        = 500;
	const MAX_ATTEMPTS = 3;
	const RETRY_FILTER = 'wpml_media_text_scan_retry_delay';
	const BUSY_RETRY = 5;
	const FIRST_RETRY = 60;
	const DEFAULT_BUDGET = 20;

	private $wpdb;
	private $candidates;
	private $gate;
	private $keys;
	private $store;
	private $thumbnail;
	private $client;
	private $lock;
	private $progress;
	private $clock;

	public function __construct(
		\wpdb $wpdb,
		Candidates $candidates,
		MetaGate $gate,
		ContentKey $keys,
		VerdictStore $store,
		Thumbnail $thumbnail,
		ClassifierClient $client,
		ScanLock $lock,
		ScanProgress $progress,
		$clock = null
	) {
		$this->wpdb       = $wpdb;
		$this->candidates = $candidates;
		$this->gate       = $gate;
		$this->keys       = $keys;
		$this->store      = $store;
		$this->thumbnail  = $thumbnail;
		$this->client     = $client;
		$this->lock       = $lock;
		$this->progress   = $progress;
		$this->clock      = $clock ?: function () {
			return microtime( true );
		};
	}

	public function start( $scope ) {
		if ( Mode::OFF === Mode::get() ) {
			return new \WP_Error( 'wpml_text_scan_mode_off', __( 'Image scanning is off.', 'wpml-media' ), [ 'status' => 409 ] );
		}

		if ( ! $this->lock->acquire() ) {
			return self::running();
		}

		try {
			$progress = $this->progress->fresh();
			if ( ScanProgress::RUNNING === $progress['status'] ) {
				return self::running();
			}

			$this->queue( $this->candidates->forScan( $scope ) );

			$progress = $this->progress->startNew( $this->queuedCount(), $this->now() );
			if ( ScanProgress::RUNNING === $progress['status'] ) {
				$this->schedule( 0 );
			}
		} finally {
			$this->lock->release();
		}

		return $this->status();
	}

	private static function running() {
		return new \WP_Error( 'wpml_text_scan_running', __( 'A scan is already running.', 'wpml-media' ), [ 'status' => 409 ] );
	}

	public function tick( $budgetSeconds = self::DEFAULT_BUDGET, $force = false ) {
		$busy            = false;
		$held            = false;
		$retryIn         = null;
		$unlockedFailure = false;

		try {
			$startedAt = (float) call_user_func( $this->clock );
			$held      = $this->lock->acquire();
			if ( $held ) {
				$this->work( (int) $budgetSeconds, $startedAt, (bool) $force );
			} else {
				$busy = true;
				$this->schedule( self::BUSY_RETRY );
			}
		} catch ( \Throwable $e ) {
			if ( $held ) {
				$retryIn = $this->recordFailure();
			} else {
				$unlockedFailure = true;
			}
		} finally {
			if ( $held ) {
				try {
					$this->lock->release();
				} catch ( \Throwable $e ) {
				}
			}
			if ( null !== $retryIn || $unlockedFailure ) {
				try {
					$this->schedule( $unlockedFailure ? $this->retryDelay( 1 ) : $retryIn );
				} catch ( \Throwable $e ) {
				}
			}
		}

		return $this->safeStatus() + [ 'busy' => $busy ];
	}

	public function onCron() {
		$this->tick();
	}

	public function enqueueOne( $id ) {
		if ( Mode::OFF === Mode::get() ) {
			return;
		}

		$progress = $this->progress->get();
		$blocked  = self::isBlocked( $progress );
		if ( ! $blocked && ScanProgress::RUNNING !== $progress['status'] ) {
			$this->start( [ (int) $id ] );

			return;
		}

		$this->store->setState( (int) $id, VerdictStore::STATE_QUEUED );
		if ( $this->lock->acquire() ) {
			try {
				$progress          = $this->progress->fresh();
				$progress['total'] = (int) $progress['total'] + 1;
				$this->progress->put( $progress );
			} finally {
				$this->lock->release();
			}
		}
		if ( ! $blocked ) {
			$this->schedule( 0 );
		}
	}

	public function resumeIfPaused() {
		$progress = $this->progress->get();
		if ( ScanProgress::PAUSED !== $progress['status'] && ScanProgress::RUNNING !== $progress['status'] ) {
			return;
		}

		$mode = Mode::get();
		if ( Mode::LIKELY !== $mode && Mode::ALL !== $mode ) {
			return;
		}

		if ( ScanProgress::RUNNING === $progress['status'] ) {
			$this->schedule( 0 );

			return;
		}

		if ( $this->lock->acquire() ) {
			try {
				$progress = $this->progress->fresh();
				if ( ScanProgress::PAUSED === $progress['status'] ) {
					$progress['status']       = ScanProgress::RUNNING;
					$progress['blocked_host'] = null;
					$this->progress->put( $progress );
				}
			} finally {
				$this->lock->release();
			}
		}
		$this->schedule( 0 );
	}

	public function status() {
		$progress  = $this->progress->get();
		$byState   = $this->store->countByState();
		$translate = isset( $byState[ VerdictStore::STATE_TRANSLATE ] ) ? (int) $byState[ VerdictStore::STATE_TRANSLATE ] : 0;
		$skip      = isset( $byState[ VerdictStore::STATE_SKIP ] ) ? (int) $byState[ VerdictStore::STATE_SKIP ] : 0;
		$unsure    = isset( $byState[ VerdictStore::STATE_UNSURE ] ) ? (int) $byState[ VerdictStore::STATE_UNSURE ] : 0;
		$images    = (int) $this->candidates->countOriginalImages();

		return [
			'status'       => $progress['status'],
			'mode'         => Mode::get(),
			'total'        => (int) $progress['total'],
			'processed'    => (int) $progress['processed'],
			'held'         => (int) $progress['held'],
			'counts'       => [
				'translate'   => $translate,
				'skip'        => $skip,
				'unsure'      => $unsure,
				'not_scanned' => max( 0, $images - $translate - $skip - $unsure ),
			],
			'started_at'   => $progress['started_at'],
			'finished_at'  => $progress['finished_at'],
			'retry_at'     => $progress['retry_at'],
			'last_error'   => $progress['last_error'],
			'blocked_host' => $progress['blocked_host'],
		];
	}

	public function schedule( $delay = 0 ) {
		if ( false === wp_next_scheduled( self::HOOK ) ) {
			wp_schedule_single_event( $this->now() + max( 0, (int) $delay ), self::HOOK );
		}
	}

	public function unschedule() {
		wp_clear_scheduled_hook( self::HOOK );
	}

	public function retryDelay( $errorCount ) {
		$errorCount = max( 1, (int) $errorCount );
		$default    = (int) min( 900, 60 * pow( 2, min( $errorCount - 1, 10 ) ) );
		$filtered   = apply_filters( self::RETRY_FILTER, $default, $errorCount );

		return is_int( $filtered ) && $filtered > 0 ? $filtered : $default;
	}

	private function work( $budget, $startedAt, $force ) {
		$progress = $this->progress->fresh();
		$mode     = Mode::get();

		if ( Mode::OFF === $mode ) {
			if ( ScanProgress::RUNNING === $progress['status'] ) {
				$progress['status'] = ScanProgress::PAUSED;
				$this->progress->put( $progress );
			}

			return;
		}

		if ( ScanProgress::PAUSED === $progress['status'] ) {
			if ( self::isBlocked( $progress ) && ! $force ) {
				return;
			}
			$progress['status']       = ScanProgress::RUNNING;
			$progress['blocked_host'] = null;
			$this->progress->put( $progress );
		}

		if ( ScanProgress::RUNNING !== $progress['status'] ) {
			return;
		}

		if ( $force && null !== $progress['retry_at'] ) {
			$progress['retry_at'] = null;
			$this->progress->put( $progress );
		}

		if ( null !== $progress['retry_at'] && (int) $progress['retry_at'] > $this->now() ) {
			$this->schedule( (int) $progress['retry_at'] - $this->now() );

			return;
		}

		$batch    = [];
		$batchIds = [];
		foreach ( $this->queuedIds() as $id ) {
			if ( $this->elapsed( $startedAt ) >= self::ITEM_BUDGET ) {
				break;
			}

			try {
				$item = $this->prepareItem( $id, $mode, $progress );
			} catch ( \Throwable $e ) {
				$this->store->finalLocal( $id, 'error' );
				$progress['processed']++;
				$item = null;
			}

			if ( null !== $item ) {
				$item['id'] = $id;
				$batch[]    = $item;
				$batchIds[] = $id;
			}
		}

		if ( $batch ) {
			$this->classify( $batch, $batchIds, (int) max( 5, $budget - $this->elapsed( $startedAt ) ), $progress );
		}

		if ( 0 === $this->queuedCount() ) {
			$progress['status']      = ScanProgress::DONE;
			$progress['finished_at'] = $this->now();
			$this->unschedule();
		} elseif ( self::isBlocked( $progress ) ) {
			$this->unschedule();
		} else {
			$delay = null !== $progress['retry_at'] ? (int) $progress['retry_at'] - $this->now() : 0;
			$this->schedule( max( 0, $delay ) );
		}

		$this->progress->put( $progress );
	}

	private function prepareItem( $id, $mode, array &$progress ) {
		if ( 'attachment' !== get_post_type( $id ) || ! $this->candidates->isOriginal( $id ) ) {
			$this->store->deleteState( $id );
			$progress['processed']++;

			return null;
		}

		$key = $this->keys->compute( $id );
		if ( null === $key ) {
			$this->store->finalLocal( $id, 'unreadable' );
			$progress['processed']++;

			return null;
		}

		$gateVerdict = null;
		$record      = $this->store->get( $id );
		$oldKey      = $record && isset( $record['key'] ) && is_array( $record['key'] ) ? $record['key'] : null;
		if (
			null !== $oldKey
			&& ( ! isset( $oldKey['sha1'], $key['sha1'] ) || $oldKey['sha1'] !== $key['sha1'] )
			&& ContentKey::changed( $oldKey, $key )
		) {
			$gateVerdict = $this->gate->verdict( $id );
			$this->store->resetForChange( $id, $key, $gateVerdict );
			$this->store->setState( $id, VerdictStore::STATE_QUEUED );
		}
		$this->store->writeKey( $id, $key );

		if ( $this->store->reuseIfUnchanged( $id, $key ) ) {
			$progress['processed']++;

			return null;
		}

		if ( null === $gateVerdict ) {
			$gateVerdict = $this->gate->verdict( $id );
		}
		if ( MetaGate::PASS !== $gateVerdict ) {
			$this->store->finalFromGate( $id, $gateVerdict );
			$progress['processed']++;

			return null;
		}
		$this->store->writeGate( $id, $gateVerdict );

		if ( Mode::ALL !== $mode ) {
			$this->store->deleteState( $id );
			$progress['held']++;
			$progress['processed']++;

			return null;
		}

		$item = $this->thumbnail->classifierItem( $id );
		if ( null === $item ) {
			$this->store->finalLocal( $id, 'unreadable' );
			$progress['processed']++;

			return null;
		}

		return $item;
	}

	private function classify( array $batch, array $batchIds, $timeout, array &$progress ) {
		try {
			$raw = $this->client->classify( $batch, VerdictStore::PURPOSE_SCAN, $timeout );
		} catch ( ClassifierUnavailable $e ) {
			$progress['error_count'] = (int) $progress['error_count'] + 1;
			$progress['last_error']  = $e->reason();

			if ( ClassifierUnavailable::BLOCKED === $e->reason() ) {
				$progress['status']       = ScanProgress::PAUSED;
				$progress['blocked_host'] = $e->host();
				$progress['retry_at']     = null;

				return;
			}

			$progress['retry_at'] = $this->now() + $this->retryDelay( $progress['error_count'] );

			return;
		}

		$valid = HttpClassifierClient::validResults( $raw, $batchIds );
		foreach ( $batchIds as $id ) {
			try {
				if ( isset( $valid[ $id ] ) ) {
					$this->store->finalFromRemote( $id, $valid[ $id ], VerdictStore::PURPOSE_SCAN );
					$progress['processed']++;
				} elseif ( $this->store->incrementAttempts( $id ) >= self::MAX_ATTEMPTS ) {
					$this->store->finalLocal( $id, 'no_answer' );
					$progress['processed']++;
				}
			} catch ( \Throwable $e ) {
				$this->store->finalLocal( $id, 'error' );
				$progress['processed']++;
			}
		}

		$progress['error_count'] = 0;
		$progress['retry_at']    = null;
	}

	private function recordFailure() {
		try {
			$progress                = $this->progress->fresh();
			$progress['error_count'] = (int) $progress['error_count'] + 1;
			$delay                   = $this->retryDelay( $progress['error_count'] );
			$progress['retry_at']    = $this->now() + $delay;
			$progress['last_error']  = 'error';
			$this->progress->put( $progress );

			return $delay;
		} catch ( \Throwable $e ) {
			return self::FIRST_RETRY;
		}
	}

	private static function isBlocked( array $progress ) {
		return ScanProgress::PAUSED === $progress['status'] && null !== $progress['blocked_host'];
	}

	private function safeStatus() {
		try {
			return $this->status();
		} catch ( \Throwable $e ) {
			$progress = ScanProgress::defaults();
			unset( $progress['error_count'] );
			$progress['mode']       = Mode::OFF;
			$progress['counts']     = [
				'translate'   => 0,
				'skip'        => 0,
				'unsure'      => 0,
				'not_scanned' => 0,
			];
			$progress['last_error'] = 'error';

			return $progress;
		}
	}

	private function queue( array $ids ) {
		$wpdb = $this->wpdb;
		foreach ( array_chunk( $ids, self::CHUNK ) as $chunk ) {
			$wpdb->query(
				$wpdb->prepare(
					"INSERT INTO {$wpdb->postmeta} (post_id, meta_key, meta_value) VALUES "
					. implode( ', ', array_fill( 0, count( $chunk ), "(%d, '_wpml_media_scan_state', 'queued')" ) ),
					array_map( 'intval', $chunk )
				)
			);
			foreach ( $chunk as $id ) {
				wp_cache_delete( (int) $id, 'post_meta' );
			}
		}
	}

	private function queuedIds() {
		$wpdb = $this->wpdb;

		return array_map(
			'intval',
			(array) $wpdb->get_col(
				$wpdb->prepare(
					"SELECT post_id FROM {$wpdb->postmeta} WHERE meta_key = %s AND meta_value = %s ORDER BY post_id ASC LIMIT %d",
					VerdictStore::STATE_META,
					VerdictStore::STATE_QUEUED,
					self::BATCH
				)
			)
		);
	}

	private function queuedCount() {
		$wpdb = $this->wpdb;

		return (int) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(*) FROM {$wpdb->postmeta} WHERE meta_key = %s AND meta_value = %s",
				VerdictStore::STATE_META,
				VerdictStore::STATE_QUEUED
			)
		);
	}

	private function elapsed( $startedAt ) {
		return (float) call_user_func( $this->clock ) - $startedAt;
	}

	private function now() {
		return (int) floor( call_user_func( $this->clock ) );
	}
}
