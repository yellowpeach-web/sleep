<?php

namespace WPML\Utilities;

class LockBackend {

	const MYSQL = 'mysql';
	const TABLE = 'table';

	const FILTER = 'wpml_locks_backend';

	const DISABLE_CONSTANT = 'WPML_DISABLE_MYSQL_LOCKS';

	const REFUSED_OPTION = 'wpml_named_locks_refused_until';

	const REFUSED_MEMO_SECONDS = 3600;

	const ROW_PREFIX = '_wpml_lock_';

	const DEFAULT_TTL = 300;

	const POLL_MICROSECONDS = 100000;

	private static $refused = null;

	private static $refusedOptionPresent = false;

	private static $held = [];

	private static $shutdownWpdb = null;

	private $wpdb;

	public function __construct( \wpdb $wpdb ) {
		$this->wpdb = $wpdb;
	}

	public function backend() {
		$forced = $this->forcedBackend();
		if ( $forced ) {
			return $forced;
		}

		return $this->namedLocksRefused() ? self::TABLE : self::MYSQL;
	}

	public function getNamedLock( $name, $timeout ) {
		if ( self::TABLE === $this->backend() ) {
			return null;
		}

		$result = $this->quietly(
			function () use ( $name, $timeout ) {
				return $this->wpdb->get_var( $this->wpdb->prepare( 'SELECT GET_LOCK(%s, %d)', $name, $timeout ) );
			}
		);

		if ( '1' === (string) $result ) {
			$this->namedLocksWork();

			return true;
		}
		if ( '0' === (string) $result ) {
			return false;
		}

		$this->noteRefusal();

		return null;
	}

	public function releaseNamedLock( $name ) {
		if ( self::TABLE === $this->backend() ) {
			return;
		}

		$this->quietly(
			function () use ( $name ) {
				return $this->wpdb->query( $this->wpdb->prepare( 'SELECT RELEASE_LOCK(%s)', $name ) );
			}
		);
		$this->noteRefusal();
	}

	public function isNamedLockUsed( $name ) {
		if ( self::TABLE === $this->backend() ) {
			return false;
		}

		$holder = $this->quietly(
			function () use ( $name ) {
				return $this->wpdb->get_var( $this->wpdb->prepare( 'SELECT IS_USED_LOCK(%s)', $name ) );
			}
		);
		$this->noteRefusal();

		return null !== $holder && '' !== (string) $holder;
	}

	public function acquireRow( $name, $timeout, $ttl = null ) {
		if ( isset( self::$held[ $name ] ) ) {
			self::$held[ $name ]['count']++;

			return true;
		}

		$ttl      = max( 1, (int) ( $ttl ?: self::DEFAULT_TTL ) );
		$owner    = bin2hex( random_bytes( 8 ) );
		$deadline = microtime( true ) + max( 0, (int) $timeout );

		while ( true ) {
			if ( $this->claimRow( self::ROW_PREFIX . $name, $owner, $ttl ) ) {
				self::$held[ $name ] = [
					'owner' => $owner,
					'count' => 1,
				];
				$this->deleteHeldRowsOnShutdown();

				return true;
			}

			$left = $deadline - microtime( true );
			if ( $left <= 0 ) {
				return false;
			}
			usleep( (int) min( self::POLL_MICROSECONDS, $left * 1000000 ) );
		}
	}

	public function releaseRow( $name ) {
		if ( ! isset( self::$held[ $name ] ) ) {
			return;
		}

		self::$held[ $name ]['count']--;
		if ( self::$held[ $name ]['count'] > 0 ) {
			return;
		}

		$owner = self::$held[ $name ]['owner'];
		unset( self::$held[ $name ] );
		self::deleteRow( $this->wpdb, $name, $owner );
	}

	public static function deleteHeldRows() {
		if ( ! self::$shutdownWpdb ) {
			return;
		}

		foreach ( self::$held as $name => $row ) {
			self::deleteRow( self::$shutdownWpdb, $name, $row['owner'] );
		}
		self::$held = [];
	}

	public static function reset() {
		self::$refused              = null;
		self::$refusedOptionPresent = false;
		self::$held                 = [];
	}

	private function claimRow( $row, $owner, $ttl ) {
		$options = $this->wpdb->options;

		$inserted = $this->wpdb->query(
			$this->wpdb->prepare(
				"INSERT IGNORE INTO {$options} (option_name, option_value, autoload) VALUES (%s, CONCAT(UNIX_TIMESTAMP() + %d, ':', %s), 'no') /* WPML LOCK */",
				$row,
				$ttl,
				$owner
			)
		);
		if ( 1 === (int) $inserted ) {
			return true;
		}

		$taken = $this->wpdb->query(
			$this->wpdb->prepare(
				"UPDATE {$options} SET option_value = CONCAT(UNIX_TIMESTAMP() + %d, ':', %s) WHERE option_name = %s AND CAST(SUBSTRING_INDEX(option_value, ':', 1) AS UNSIGNED) < UNIX_TIMESTAMP() /* WPML LOCK */",
				$ttl,
				$owner,
				$row
			)
		);

		return 1 === (int) $taken;
	}

	private static function deleteRow( $wpdb, $name, $owner ) {
		$wpdb->query(
			$wpdb->prepare(
				"DELETE FROM {$wpdb->options} WHERE option_name = %s AND SUBSTRING_INDEX(option_value, ':', -1) = %s /* WPML LOCK */",
				self::ROW_PREFIX . $name,
				$owner
			)
		);
	}

	private function deleteHeldRowsOnShutdown() {
		if ( ! self::$shutdownWpdb ) {
			register_shutdown_function( [ self::class, 'deleteHeldRows' ] );
		}
		self::$shutdownWpdb = $this->wpdb;
	}

	private function forcedBackend() {
		$filtered = function_exists( 'apply_filters' ) ? apply_filters( self::FILTER, 'auto' ) : 'auto';
		if ( self::MYSQL === $filtered || self::TABLE === $filtered ) {
			return $filtered;
		}

		if ( defined( self::DISABLE_CONSTANT ) && constant( self::DISABLE_CONSTANT ) ) {
			return self::TABLE;
		}

		return null;
	}

	private function namedLocksRefused() {
		if ( null === self::$refused ) {
			self::$refused = false;

			if ( function_exists( 'wp_load_alloptions' ) ) {
				$options = wp_load_alloptions();
				if ( is_array( $options ) && isset( $options[ self::REFUSED_OPTION ] ) ) {
					self::$refusedOptionPresent = true;
					self::$refused              = (int) $options[ self::REFUSED_OPTION ] > time();
				}
			}
		}

		return self::$refused;
	}

	private function noteRefusal() {
		$error = isset( $this->wpdb->last_error ) ? (string) $this->wpdb->last_error : '';
		if ( '' === $error || $this->forcedBackend() ) {
			return;
		}

		self::$refused = true;

		if ( function_exists( 'update_option' ) ) {
			update_option( self::REFUSED_OPTION, time() + self::REFUSED_MEMO_SECONDS, true );
			self::$refusedOptionPresent = true;
		}

		if ( function_exists( 'do_action' ) ) {
			do_action( 'wpml_locks_backend_switched', self::TABLE, $error );
		}

		if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
			error_log( '[WPML] The database refused a MySQL named lock (' . $error . '); WPML locks use the options table for the next hour.' );
		}
	}

	private function namedLocksWork() {
		if ( self::$refusedOptionPresent && ! self::$refused && function_exists( 'delete_option' ) ) {
			delete_option( self::REFUSED_OPTION );
			self::$refusedOptionPresent = false;
		}
	}

	private function quietly( callable $statement ) {
		$previous                    = isset( $this->wpdb->suppress_errors ) ? $this->wpdb->suppress_errors : false;
		$this->wpdb->suppress_errors = true;

		try {
			return $statement();
		} finally {
			$this->wpdb->suppress_errors = $previous;
		}
	}
}
