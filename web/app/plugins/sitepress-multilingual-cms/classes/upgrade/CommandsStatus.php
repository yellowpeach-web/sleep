<?php

namespace WPML\Upgrade;

class CommandsStatus {
	const OPTION_KEY = 'wpml_update_statuses';

	const FRONT_END_SCOPE_KEY = 'front-end.scope-settled';

	const NO_WORK_KEY = 'no-work';

	const FAILED_KEY = 'failed';

	const FAILED_MESSAGE_MAX = 1000;

	private $batching = false;

	private $pending = array();

	private $kept = null;

	private $keeping_suspended = false;

	public function beginBatch() {
		$this->batching          = true;
		$this->kept              = null;
		$this->keeping_suspended = false;
	}

	public function suspendKeeping() {
		$this->keeping_suspended = true;
		$this->kept              = null;
	}

	public function resumeKeeping() {
		$this->keeping_suspended = false;
		$this->kept              = null;
	}

	public function endBatch() {
		$this->batching          = false;
		$this->kept              = null;
		$this->keeping_suspended = false;

		if ( empty( $this->pending ) ) {
			return;
		}

		$update_options = get_option( self::OPTION_KEY, array() );
		if ( ! is_array( $update_options ) ) {
			$update_options = array();
		}
		foreach ( $this->pending as $id => $value ) {
			if ( null === $value ) {
				unset( $update_options[ $id ] );
			} else {
				$update_options[ $id ] = $value;
			}
		}
		$this->pending = array();

		if ( update_option( self::OPTION_KEY, $update_options, true ) ) {
			SchemaFacts::forget();
			wp_cache_flush();
		}
	}

	public function hasBeenExecuted( $className ) {
		return (bool) $this->get_update_option_value( $this->get_command_id( $className ) );
	}

	public function markAsExecuted( $className, $flag = true ) {
		$this->set_update_status( $this->get_command_id( $className ), $flag );
		if ( null !== $this->getFailure( $className ) ) {
			$this->forget_update_status( $this->get_failed_key( $className ) );
		}

		if ( ! $this->batching ) {
			wp_cache_flush();
		}
	}

	public function clearExecuted( $className ) {
		$update_options = get_option( self::OPTION_KEY, array() );
		if ( ! is_array( $update_options ) ) {
			return;
		}
		unset( $update_options[ $this->get_command_id( $className ) ] );
		update_option( self::OPTION_KEY, $update_options, true );
		$this->kept = null;
		SchemaFacts::forget();
	}

	public function isFrontEndScopeSettled( $version, $loaderId ) {
		return $this->get_update_option_value( $this->get_front_end_scope_key( $loaderId ) ) === $version;
	}

	public function markFrontEndScopeSettled( $version, $loaderId ) {
		if ( $this->isFrontEndScopeSettled( $version, $loaderId ) ) {
			return;
		}

		$this->set_update_status( $this->get_front_end_scope_key( $loaderId ), $version );
	}

	public function hasNoWorkInScope( $className, $scope, $version ) {
		return $this->get_update_option_value( $this->get_no_work_key( $className, $scope ) ) === $version;
	}

	public function markNoWorkInScope( $className, $scope, $version ) {
		if ( $this->hasNoWorkInScope( $className, $scope, $version ) ) {
			return;
		}

		$this->set_update_status( $this->get_no_work_key( $className, $scope ), $version );
	}

	public function markFailed( $className, $message, $version ) {
		$message = substr( (string) $message, 0, self::FAILED_MESSAGE_MAX );
		$known   = $this->getFailure( $className );
		if ( $known && $known['version'] === $version && $known['message'] === $message ) {
			return;
		}

		$this->set_update_status(
			$this->get_failed_key( $className ),
			[
				'version' => $version,
				'message' => $message,
				'time'    => $known ? $known['time'] : time(),
			]
		);
	}

	public function getFailure( $className ) {
		$record = $this->get_update_option_value( $this->get_failed_key( $className ) );
		if ( ! is_array( $record ) ) {
			return null;
		}

		return [
			'version' => isset( $record['version'] ) ? (string) $record['version'] : '',
			'message' => isset( $record['message'] ) ? (string) $record['message'] : '',
			'time'    => isset( $record['time'] ) ? (int) $record['time'] : 0,
		];
	}

	private function get_failed_key( $className ) {
		return self::FAILED_KEY . '.' . $this->get_command_id( $className );
	}

	private function forget_update_status( $id ) {
		if ( $this->batching ) {
			$this->pending[ $id ] = null;

			return;
		}

		$update_options = get_option( self::OPTION_KEY, array() );
		if ( ! is_array( $update_options ) || ! array_key_exists( $id, $update_options ) ) {
			return;
		}
		unset( $update_options[ $id ] );
		update_option( self::OPTION_KEY, $update_options, true );
		SchemaFacts::forget();
	}

	private function get_no_work_key( $className, $scope ) {
		return self::NO_WORK_KEY . '.' . $scope . '.' . $this->get_command_id( $className );
	}

	private function get_front_end_scope_key( $loaderId ) {
		return self::FRONT_END_SCOPE_KEY . '.' . $loaderId;
	}

	private function get_command_id( $className ) {
		return str_replace( '_', '-', strtolower( $className ) );
	}

	private function set_update_status( $id, $value ) {
		if ( $this->batching ) {
			$this->pending[ $id ] = $value;
			return;
		}

		$update_options        = get_option( self::OPTION_KEY, array() );
		$update_options[ $id ] = $value;
		update_option( self::OPTION_KEY, $update_options, true );
		SchemaFacts::forget();
	}

	private function get_update_option_value( $id ) {
		if ( array_key_exists( $id, $this->pending ) ) {
			return $this->pending[ $id ];
		}

		$update_options = $this->read_ledger();

		if ( $update_options && array_key_exists( $id, $update_options ) ) {
			return $update_options[ $id ];
		}

		return null;
	}

	private function read_ledger() {
		if ( null !== $this->kept ) {
			return $this->kept;
		}

		$update_options = get_option( self::OPTION_KEY, array() );

		if ( $this->batching && ! $this->keeping_suspended ) {
			$this->kept = $update_options;
		}

		return $update_options;
	}
}
