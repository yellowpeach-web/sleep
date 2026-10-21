<?php

namespace WPML\Utilities;

use function WPML\Container\make;

class Lock implements ILock {

	const ADVISORY_TIMEOUT = 1;

	private static $active_locks = [];

	private static $advisory_locks_supported = null;

	private $wpdb;

	protected $name;

	public function __construct( \wpdb $wpdb, $name ) {
		$this->wpdb = $wpdb;
		$this->name = 'wpml.' . $name . '.lock';
	}

	public static function whileLocked( $lockName, $releaseTimeout, callable $fn ) {
		$lock = make( Lock::class, [ ':name' => $lockName ] );
		if ( $lock->create( $releaseTimeout ) ) {
			$fn();
			$lock->release();
		}
	}

	public function create( $release_timeout = null, $retry_limit = 10 ) {
		if ( ! $release_timeout ) {
			$release_timeout = HOUR_IN_SECONDS;
		}

		if ( isset( self::$active_locks[ $this->name ] ) ) {
			return false;
		}

		if ( self::$advisory_locks_supported !== false && LockBackend::TABLE !== $this->lockBackend()->backend() ) {
			$advisory = $this->acquireAdvisoryLock();

			if ( $advisory !== null ) {
				self::$advisory_locks_supported = true;

				if ( ! $advisory ) {
					self::$active_locks[ $this->name ] = false;
					return false;
				}

				try {
					return $this->createSerialized( $release_timeout );
				} finally {
					$this->releaseAdvisoryLock();
				}
			}

			if ( '' !== (string) $this->wpdb->last_error ) {
				self::$advisory_locks_supported = false;
			}
		}

		return $this->createWithOptionsMutex( $release_timeout, $retry_limit );
	}

	public function isHeld( $release_timeout = null ) {
		if ( ! $release_timeout ) {
			$release_timeout = HOUR_IN_SECONDS;
		}

		if ( isset( self::$active_locks[ $this->name ] ) ) {
			return true;
		}

		$timestamp = $this->wpdb->get_var( $this->wpdb->prepare( "SELECT option_value FROM {$this->wpdb->options} WHERE option_name = %s", $this->name ) );

		if ( $this->isValidLockTimeout( $timestamp ) && $timestamp > ( time() - $release_timeout ) ) {
			return true;
		}

		return $this->isAdvisoryLockInUse();
	}

	private function isAdvisoryLockInUse() {
		if ( self::$advisory_locks_supported === false ) {
			return false;
		}

		return $this->lockBackend()->isNamedLockUsed( $this->advisoryLockName() );
	}

	private function lockBackend() {
		return new LockBackend( $this->wpdb );
	}

	private function createSerialized( $release_timeout ) {
		$timestamp = $this->wpdb->get_var( $this->wpdb->prepare( "SELECT option_value FROM {$this->wpdb->options} WHERE option_name = %s", $this->name ) );

		if ( $this->isValidLockTimeout( $timestamp ) && $timestamp > ( time() - $release_timeout ) ) {
			self::$active_locks[ $this->name ] = false;
			return false;
		}

		if ( ! $this->writeStamp() && ! ( $this->lastErrorIsTransient() && $this->writeStamp() ) ) {
			self::$active_locks[ $this->name ] = false;

			return false;
		}
		wp_cache_delete( $this->name, 'options' );

		self::$active_locks[ $this->name ] = true;

		return true;
	}

	private function writeStamp() {
		$result = $this->wpdb->query( $this->wpdb->prepare(
			"INSERT INTO {$this->wpdb->options} ( `option_name`, `option_value`, `autoload` ) VALUES (%s, %s, 'no') ON DUPLICATE KEY UPDATE `option_value` = VALUES( `option_value` ) /* LOCK */",
			$this->name,
			time()
		) );

		return false !== $result;
	}

	private function lastErrorIsTransient() {
		return (bool) preg_match( '/deadlock found|lock wait timeout/i', (string) $this->wpdb->last_error );
	}

	private function createWithOptionsMutex( $release_timeout, $retry_limit ) {
		do {
			$lock_result = $this->wpdb->query( $this->wpdb->prepare( "INSERT IGNORE INTO {$this->wpdb->options} ( `option_name`, `option_value`, `autoload` ) VALUES (%s, %s, 'no') /* LOCK */", $this->name, time() ) );

			if ( $lock_result ) {
				update_option( $this->name, time(), false );

				self::$active_locks[ $this->name ] = true;

				return true;
			}

			$lock_result = $this->wpdb->get_var( $this->wpdb->prepare( "SELECT option_value FROM {$this->wpdb->options} WHERE option_name = %s", $this->name ) );

			if ( ! $this->isValidLockTimeout( $lock_result ) ) {
				$lock_result = 1;
			}
			if ( ! $lock_result || $lock_result > ( time() - $release_timeout ) ) {
				break;
			}

			if ( $retry_limit <= 0 || ! $this->release() ) {
				break;
			}

			$retry_limit --;
		} while ( true );

		self::$active_locks[ $this->name ] = false;

		return false;
	}

	private function advisoryLockName() {
		return 'wpml.lock.' . md5( $this->wpdb->dbname . '|' . $this->wpdb->prefix . '|' . $this->name );
	}

	private function acquireAdvisoryLock() {
		return $this->lockBackend()->getNamedLock( $this->advisoryLockName(), self::ADVISORY_TIMEOUT );
	}

	private function releaseAdvisoryLock() {
		$this->lockBackend()->releaseNamedLock( $this->advisoryLockName() );
	}

	public static function resetAdvisoryLockProbe() {
		self::$advisory_locks_supported = null;
		LockBackend::reset();
	}

	public function release() {
		unset( self::$active_locks[ $this->name ] );
		return delete_option( $this->name );
	}

	private function isValidLockTimeout( $lock_result ) {

		return is_numeric( $lock_result ) && $lock_result > 0;

	}


}
