<?php

namespace WPML\OperationRecord;

use WPML\WP\OptionManager;

class Repository {

	const OPTION = 'wpml_operation_records';

	const VERSION = 1;

	const KIND_LANGUAGE_REMOVAL = 'language-removal';
	const KIND_ITEM_DELETE      = 'item-delete';
	const KIND_BULK_DELETE      = 'bulk-delete';
	const KIND_MOVE             = 'move';
	const KIND_DUPLICATION      = 'duplication';

	const KIND_RESET_DEFAULTS   = 'reset-defaults';

	const ROUTE_TRASH     = 'trash';
	const ROUTE_PERMANENT = 'permanent';

	const STATUS_RUNNING   = 'running';
	const STATUS_FINALIZED = 'finalized';

	const SKIPPED_CAP = 100;

	const KEEP_FINALIZED = 50;

	const RUNNING_EXPIRY = 604800;

	const COUNT_BUCKETS = array( 'removed', 'kept', 'skipped' );

	public function start( $kind, array $languages = array(), $actor = null, $route = null ) {
		$this->prune();

		$id = 0;

		$this->mutate(
			function ( array $store ) use ( $kind, $languages, $actor, $route, &$id ) {
				$id = (int) $store['next_id'];

				$store['records'][ $id ] = array(
					'id'               => $id,
					'v'                => self::VERSION,
					'kind'             => (string) $kind,
					'languages'        => array_values( array_map( 'strval', $languages ) ),
					'actor'            => null === $actor ? (int) get_current_user_id() : (int) $actor,
					'started'          => time(),
					'finished'         => null,
					'route'            => null === $route ? null : (string) $route,
					'counts'           => array(),
					'skipped'          => array(),
					'skipped_overflow' => 0,
					'jobs'             => null,
					'undo_until'       => null,
					'status'           => self::STATUS_RUNNING,
				);

				$store['next_id'] = $id + 1;

				return $store;
			}
		);

		return $id;
	}

	public function patch( $id, array $patch ) {
		$id = (int) $id;

		if ( null === $this->get( $id ) ) {
			return;
		}

		$this->mutate(
			function ( array $store ) use ( $id, $patch ) {
				if ( ! isset( $store['records'][ $id ] ) ) {
					return $store;
				}

				$store['records'][ $id ] = $this->applyPatch( $store['records'][ $id ], $patch );

				return $store;
			}
		);
	}

	public function finalize( $id, array $patch = array() ) {
		$this->patch(
			$id,
			array_merge(
				$patch,
				array(
					'finished' => time(),
					'status'   => self::STATUS_FINALIZED,
				)
			)
		);
	}

	public function get( $id ) {
		$store = $this->normalize( get_option( self::OPTION, null ) );
		$id    = (int) $id;

		return isset( $store['records'][ $id ] ) ? $store['records'][ $id ] : null;
	}

	public function latest( $kind = null ) {
		foreach ( $this->all() as $record ) {
			if ( null === $kind || ( isset( $record['kind'] ) && $kind === $record['kind'] ) ) {
				return $record;
			}
		}

		return null;
	}

	public function all() {
		$store   = $this->normalize( get_option( self::OPTION, null ) );
		$records = $store['records'];

		krsort( $records, SORT_NUMERIC );

		return array_values( $records );
	}

	public function forLanguage( $code ) {
		$code  = (string) $code;
		$found = array();

		foreach ( $this->all() as $record ) {
			if ( isset( $record['languages'] ) && in_array( $code, (array) $record['languages'], true ) ) {
				$found[] = $record;
			}
		}

		return $found;
	}

	public function prune() {
		$changed = false;
		$this->pruned( $this->normalize( get_option( self::OPTION, null ) ), $changed );

		if ( ! $changed ) {
			return;
		}

		$this->mutate(
			function ( array $store ) {
				$changed = false;

				return $this->pruned( $store, $changed );
			}
		);
	}

	private function pruned( array $store, &$changed ) {
		$now = time();

		foreach ( $store['records'] as $id => $record ) {
			$status = isset( $record['status'] ) ? $record['status'] : self::STATUS_RUNNING;

			if ( self::STATUS_RUNNING !== $status ) {
				continue;
			}

			if ( ( $now - (int) $record['started'] ) <= self::RUNNING_EXPIRY ) {
				continue;
			}

			$store['records'][ $id ]['status'] = self::STATUS_FINALIZED;
			$store['records'][ $id ]['note']   = 'expired';
			$changed                           = true;
		}

		$finalized = array();
		foreach ( $store['records'] as $id => $record ) {
			if ( self::STATUS_FINALIZED === ( isset( $record['status'] ) ? $record['status'] : self::STATUS_RUNNING ) ) {
				$finalized[] = (int) $id;
			}
		}

		if ( count( $finalized ) > self::KEEP_FINALIZED ) {
			rsort( $finalized, SORT_NUMERIC );

			foreach ( array_slice( $finalized, self::KEEP_FINALIZED ) as $id ) {
				unset( $store['records'][ $id ] );
				$changed = true;
			}
		}

		return $store;
	}

	private function applyPatch( array $record, array $patch ) {
		foreach ( $patch as $key => $value ) {
			switch ( $key ) {
				case 'counts_add':
					$record = $this->addCounts( $record, (array) $value );
					break;

				case 'skipped_add':
					$record = $this->addSkipped( $record, (array) $value );
					break;

				case 'jobs_add':
					$record = $this->addJobs( $record, (array) $value );
					break;

				default:
					$record[ $key ] = $value;
					break;
			}
		}

		return $record;
	}

	private function addCounts( array $record, array $delta ) {
		foreach ( $delta as $type => $buckets ) {
			if ( ! isset( $record['counts'][ $type ] ) ) {
				$record['counts'][ $type ] = array_fill_keys( self::COUNT_BUCKETS, 0 );
			}

			foreach ( (array) $buckets as $bucket => $amount ) {
				$current                             = isset( $record['counts'][ $type ][ $bucket ] ) ? (int) $record['counts'][ $type ][ $bucket ] : 0;
				$record['counts'][ $type ][ $bucket ] = $current + (int) $amount;
			}
		}

		return $record;
	}

	private function addSkipped( array $record, array $delta ) {
		foreach ( $delta as $type => $entries ) {
			if ( ! isset( $record['skipped'][ $type ] ) ) {
				$record['skipped'][ $type ] = array();
			}

			foreach ( (array) $entries as $entry ) {
				if ( count( $record['skipped'][ $type ] ) >= self::SKIPPED_CAP ) {
					$record['skipped_overflow'] = (int) $record['skipped_overflow'] + 1;
					continue;
				}

				$record['skipped'][ $type ][] = array(
					'id'     => isset( $entry['id'] ) ? (int) $entry['id'] : 0,
					'reason' => isset( $entry['reason'] ) ? (string) $entry['reason'] : '',
				);
			}
		}

		return $record;
	}

	private function addJobs( array $record, array $delta ) {
		if ( ! isset( $record['jobs'] ) || ! is_array( $record['jobs'] ) ) {
			$record['jobs'] = array(
				'cancelled'           => 0,
				'stopped_in_progress' => 0,
			);
		}

		foreach ( $delta as $key => $amount ) {
			$current                = isset( $record['jobs'][ $key ] ) ? (int) $record['jobs'][ $key ] : 0;
			$record['jobs'][ $key ] = $current + (int) $amount;
		}

		return $record;
	}

	private function normalize( $raw ) {
		if ( ! is_array( $raw ) ) {
			return array(
				'next_id' => 1,
				'records' => array(),
			);
		}

		$next    = isset( $raw['next_id'] ) ? (int) $raw['next_id'] : 1;
		$records = isset( $raw['records'] ) && is_array( $raw['records'] ) ? $raw['records'] : array();

		return array(
			'next_id' => $next < 1 ? 1 : $next,
			'records' => $records,
		);
	}

	private function mutate( callable $updater ) {
		( new OptionManager() )->mutateRaw(
			self::OPTION,
			function ( $current ) use ( $updater ) {
				return $updater( $this->normalize( $current ) );
			},
			false
		);
	}
}
