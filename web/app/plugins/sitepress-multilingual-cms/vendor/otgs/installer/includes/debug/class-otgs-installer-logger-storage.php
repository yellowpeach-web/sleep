<?php

class OTGS_Installer_Logger_Storage {

	const MAX_SIZE = 50;

	const OPTION_KEY                    = 'otgs-installer-log';

	const COMPONENT_SUBSCRIPTION        = 'subscription-fetching';
	const COMPONENT_PRODUCTS_URL        = 'products-url-fetching';
	const COMPONENT_DOWNLOAD            = 'download';
	const COMPONENT_REPOSITORIES        = 'repositories-fetching';
	const COMPONENT_PRODUCTS_PARSING    = 'products-parsing';
	const COMPONENT_RENDERING           = 'activate-update-rendering';
	const API_CONNECTION_TEST           = 'api-connection-test';
	const PRODUCTS_FILE_CONNECTION_TEST = 'products-connection-test';

	const LOCK_WAIT_SECONDS = 5;

	const NAMED_LOCKS_REFUSED_OPTION = 'otgs_installer_named_locks_refused_until';

	const NAMED_LOCKS_REFUSED_SECONDS = 3600;

	const LOCK_ROW_PREFIX = '_otgs_installer_lock_';

	const LOCK_ROW_TTL = 30;

	const LOCK_ROW_POLL_MICROSECONDS = 100000;

	const LOCK_NAMED = 'named';
	const LOCK_ROW   = 'row';

	private static $named_locks_refused = null;

	private static $refused_option_present = false;

	private $log_entries;
	private $log_factory;
	private $max_size;

	private $lock_row_owner;

	public function __construct( OTGS_Installer_Log_Factory $log_factory, $max_size = self::MAX_SIZE ) {
		$this->max_size    = $max_size;
		$this->log_factory = $log_factory;
	}

	public function get() {
		if ( ! $this->log_entries ) {
			$this->log_entries = get_option( self::OPTION_KEY );
		}

		return $this->convert_to_object( $this->log_entries ? $this->log_entries : array() );
	}

	public function add( OTGS_Installer_Log $log ) {
		$log->set_time( current_time( 'mysql' ) );
		$locked = $this->lock();
		try {
			if ( $locked ) {
				$this->log_entries = $this->read_stored();
			}
			$log_entries = $this->get();
			array_unshift( $log_entries, $log );
			$log_entries = array_slice( $log_entries, 0, $this->max_size );
			$log_entries_arr = $this->convert_to_array( $log_entries );
			update_option( self::OPTION_KEY, $log_entries_arr, false );
			$this->log_entries = $log_entries_arr;
		} finally {
			if ( $locked ) {
				$this->unlock( $locked );
			}
		}
	}

	private function lock() {
		global $wpdb;
		if ( ! $wpdb instanceof wpdb ) {
			return false;
		}

		if ( ! $this->named_locks_refused() ) {
			$result = $this->quietly(
				$wpdb,
				function () use ( $wpdb ) {
					return $wpdb->get_var( $wpdb->prepare( 'SELECT GET_LOCK(%s, %d)', $this->lock_name(), self::LOCK_WAIT_SECONDS ) );
				}
			);
			if ( '1' === (string) $result ) {
				$this->named_locks_work();

				return self::LOCK_NAMED;
			}
			if ( '0' === (string) $result || '' === (string) $wpdb->last_error ) {
				return false;
			}
			$this->remember_refusal();
		}

		return $this->lock_row( $wpdb ) ? self::LOCK_ROW : false;
	}

	private function lock_row( $wpdb ) {
		$owner    = bin2hex( random_bytes( 8 ) );
		$row      = self::LOCK_ROW_PREFIX . self::OPTION_KEY;
		$deadline = microtime( true ) + self::LOCK_WAIT_SECONDS;

		while ( true ) {
			$inserted = $wpdb->query(
				$wpdb->prepare(
					"INSERT IGNORE INTO {$wpdb->options} (option_name, option_value, autoload) VALUES (%s, CONCAT(UNIX_TIMESTAMP() + %d, ':', %s), 'no')",
					$row,
					self::LOCK_ROW_TTL,
					$owner
				)
			);
			$taken    = 1 === (int) $inserted || 1 === (int) $wpdb->query(
				$wpdb->prepare(
					"UPDATE {$wpdb->options} SET option_value = CONCAT(UNIX_TIMESTAMP() + %d, ':', %s) WHERE option_name = %s AND CAST(SUBSTRING_INDEX(option_value, ':', 1) AS UNSIGNED) < UNIX_TIMESTAMP()",
					self::LOCK_ROW_TTL,
					$owner,
					$row
				)
			);
			if ( $taken ) {
				$this->lock_row_owner = $owner;

				return true;
			}

			$left = $deadline - microtime( true );
			if ( $left <= 0 ) {
				return false;
			}
			usleep( (int) min( self::LOCK_ROW_POLL_MICROSECONDS, $left * 1000000 ) );
		}
	}

	private function named_locks_refused() {
		if ( null === self::$named_locks_refused ) {
			self::$named_locks_refused = false;
			$options                   = function_exists( 'wp_load_alloptions' ) ? wp_load_alloptions() : array();
			if ( is_array( $options ) && isset( $options[ self::NAMED_LOCKS_REFUSED_OPTION ] ) ) {
				self::$refused_option_present = true;
				self::$named_locks_refused    = (int) $options[ self::NAMED_LOCKS_REFUSED_OPTION ] > time();
			}
		}

		return self::$named_locks_refused;
	}

	private function remember_refusal() {
		self::$named_locks_refused = true;
		update_option( self::NAMED_LOCKS_REFUSED_OPTION, time() + self::NAMED_LOCKS_REFUSED_SECONDS, true );
		self::$refused_option_present = true;
	}

	private function named_locks_work() {
		if ( self::$refused_option_present ) {
			delete_option( self::NAMED_LOCKS_REFUSED_OPTION );
			self::$refused_option_present = false;
		}
	}

	private function quietly( $wpdb, $statement ) {
		$previous              = $wpdb->suppress_errors;
		$wpdb->suppress_errors = true;
		try {
			return $statement();
		} finally {
			$wpdb->suppress_errors = $previous;
		}
	}

	private function read_stored() {
		global $wpdb;
		$raw = $wpdb->get_var( $wpdb->prepare( "SELECT option_value FROM {$wpdb->options} WHERE option_name = %s LIMIT 1", self::OPTION_KEY ) );
		$rows = maybe_unserialize( $raw );

		return is_array( $rows ) ? $rows : array();
	}

	private function unlock( $locked ) {
		global $wpdb;
		if ( self::LOCK_ROW === $locked ) {
			$wpdb->query(
				$wpdb->prepare(
					"DELETE FROM {$wpdb->options} WHERE option_name = %s AND SUBSTRING_INDEX(option_value, ':', -1) = %s",
					self::LOCK_ROW_PREFIX . self::OPTION_KEY,
					$this->lock_row_owner
				)
			);
			$this->lock_row_owner = null;

			return;
		}

		$this->quietly(
			$wpdb,
			function () use ( $wpdb ) {
				return $wpdb->query( $wpdb->prepare( 'SELECT RELEASE_LOCK(%s)', $this->lock_name() ) );
			}
		);
	}

	private function lock_name() {
		global $wpdb;

		return $wpdb->prefix . self::OPTION_KEY;
	}

	private function convert_to_object( $log_entries ) {
		$log_converted = array();

		foreach ( $log_entries as $log_data ) {
			$log = $this->log_factory->create();
			$log->set_request_args( $log_data['request_args'] )
			    ->set_request_url( $log_data['request_url'] )
			    ->set_response( $log_data['response'] )
			    ->set_time( $log_data['time'] )
			    ->set_component( $log_data['component'] );
			$log_converted[] = $log;
		}

		return $log_converted;
	}

	private function convert_to_array( $log_entries ) {
		$log_converted = array();

		foreach ( $log_entries as $log_data ) {
			$log_converted[] = array(
				'request_args' => $log_data->get_request_args(),
				'request_url'  => $log_data->get_request_url(),
				'response'     => $log_data->get_response(),
				'component'    => $log_data->get_component(),
				'time'         => $log_data->get_time(),
			);
		}

		return $log_converted;
	}
}