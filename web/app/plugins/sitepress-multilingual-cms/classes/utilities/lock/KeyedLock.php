<?php

namespace WPML\Utilities;

class KeyedLock extends Lock {

	private $keyName;

	private $forgetsRefusals = false;

	private $heldKey = null;

	public function __construct( \wpdb $wpdb, $name ) {
		$this->keyName = 'wpml.' . $name . '.lock.key';
		parent::__construct( $wpdb, $name );
	}

	public function forgetRefusals() {
		$this->forgetsRefusals = true;

		return $this;
	}

	public function heldSince() {
		global $wpdb;

		$timestamp = $wpdb->get_var( $wpdb->prepare( "SELECT option_value FROM {$wpdb->options} WHERE option_name = %s", $this->name ) );

		return is_numeric( $timestamp ) && $timestamp > 0 ? (int) $timestamp : null;
	}

	public function create( $key = null, $release_timeout = null ) {
		$acquired = parent::create( $release_timeout );

		if ( $acquired ) {

			if ( ! $key ) {
				$key = wp_generate_uuid4();
			}

			$this->maybePurgeInvalidCache();
			update_option( $this->keyName, $key, false );
			$this->heldKey = $key;

			return $key;
		} elseif ( $key === get_option( $this->keyName ) ) {
			$this->extendTimeout();
			$this->heldKey = $key;

			return $key;
		}

		if ( $this->forgetsRefusals ) {
			$this->forgetRefusal();
		}

		return false;
	}

	private function forgetRefusal() {
		try {
			$memo = new \ReflectionProperty( Lock::class, 'active_locks' );
			if ( PHP_VERSION_ID < 80100 ) {
				$memo->setAccessible( true );
			}
			$locks = $memo->getValue();

			if ( is_array( $locks ) && array_key_exists( $this->name, $locks ) && false === $locks[ $this->name ] ) {
				unset( $locks[ $this->name ] );
				$memo->setValue( null, $locks );
			}
		} catch ( \ReflectionException $e ) {
			return;
		}
	}

	public function release() {
		if ( null !== $this->heldKey ) {
			$storedKey = $this->storedKey();

			if ( null !== $storedKey && $storedKey !== $this->heldKey ) {
				return false;
			}
		}

		$this->heldKey = null;
		delete_option( $this->keyName );
		wp_cache_delete( $this->keyName, 'options' );

		return parent::release();
	}

	private function storedKey() {
		global $wpdb;

		$key = $wpdb->get_var( $wpdb->prepare( "SELECT option_value FROM {$wpdb->options} WHERE option_name = %s", $this->keyName ) );

		return is_string( $key ) && '' !== $key ? $key : null;
	}

	private function extendTimeout() {
		update_option( $this->name, time(), false );
	}

	private function maybePurgeInvalidCache() {
		$alloptions = wp_cache_get( 'alloptions', 'options', true );
		if ( is_array( $alloptions ) && isset( $alloptions[ $this->keyName ] ) ) {
			wp_cache_delete( 'alloptions', 'options' );
		}
	}
}
