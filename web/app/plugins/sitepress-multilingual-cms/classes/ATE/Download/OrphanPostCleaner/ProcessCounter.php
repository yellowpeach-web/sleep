<?php

namespace WPML\TM\ATE\Download\OrphanPostCleaner;

use WPML\Utilities\AdvisoryLockFactory;

use function WPML\Container\make;

class ProcessCounter {

	const OPTION_NAME = 'wpml_ate_download_process_counter';
	const LOCK_NAME = 'wpml_ate_process_counter_lock';
	const LOCK_TIMEOUT_SECONDS = 10;
	const EXPIRATION_SECONDS = 20;

	private $wpdb;

	public function __construct( \wpdb $wpdb ) {
		$this->wpdb = $wpdb;
	}

	public function increment() {
		$this->withLock( function() {
			$data = $this->getData();

			if ( $this->isExpired( $data ) ) {
				$data = [ 'counter' => 0 ];
			}

			$data['counter']++;
			$data['timestamp'] = time();

			$this->saveData( $data );
		} );
	}

	public function decrement() {
		$this->withLock( function() {
			$data = $this->getData();

			if ( $this->isExpired( $data ) ) {
				$this->deleteData();
				return;
			}

			$data['counter'] = max( 0, $data['counter'] - 1 );

			if ( $data['counter'] > 0 ) {
				$data['timestamp'] = time();
				$this->saveData( $data );
			} else {
				$this->deleteData();
			}
		} );
	}

	public function get() {
		$data = $this->getData();
		return $this->isExpired( $data ) ? 0 : $data['counter'];
	}

	private function withLock( callable $callback ) {
		$lock         = make( AdvisoryLockFactory::class )->create( self::LOCK_NAME );
		$lockAcquired = $lock->acquire( self::LOCK_TIMEOUT_SECONDS );

		try {
			$callback();
		} finally {
			if ( $lockAcquired ) {
				$lock->release();
			}
		}
	}

	private function getData() {
		$wpdb = $this->wpdb;
		$row  = $wpdb->get_var( $wpdb->prepare(
			"SELECT option_value FROM {$wpdb->options} WHERE option_name = %s",
			self::OPTION_NAME
		) );

		$data = $row ? maybe_unserialize( $row ) : null;

		if ( ! is_array( $data ) || ! isset( $data['counter'], $data['timestamp'] ) ) {
			return [ 'counter' => 0, 'timestamp' => 0 ];
		}
		return $data;
	}

	private function saveData( array $data ) {
		$wpdb  = $this->wpdb;
		$value = maybe_serialize( $data );

		$wpdb->query( $wpdb->prepare(
			"INSERT INTO {$wpdb->options} (option_name, option_value, autoload)
			 VALUES (%s, %s, 'no')
			 ON DUPLICATE KEY UPDATE option_value = %s",
			self::OPTION_NAME,
			$value,
			$value
		) );
	}

	private function deleteData() {
		$this->wpdb->delete(
			$this->wpdb->options,
			[ 'option_name' => self::OPTION_NAME ]
		);
	}

	private function isExpired( array $data ) {
		return ( time() - $data['timestamp'] ) > self::EXPIRATION_SECONDS;
	}
}
