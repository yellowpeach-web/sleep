<?php

namespace WPML\TM\Settings;

use WPML\Core\Component\CustomFieldPreferences\Domain\ElementType;
use WPML\Infrastructure\WordPress\Component\CustomFieldPreferences\ContainerFreeServices;

class LegacyChecksumOrders {

	private static $storageOrder = [];

	private $orders = [];

	private $ordersStorage = null;

	public function forNames( array $names ) {
		$names = array_values( array_map( 'strval', $names ) );
		if ( count( $names ) < 2 ) {
			return [];
		}

		$storage = $this->storageOrder();
		if ( $storage !== $this->ordersStorage ) {
			$this->ordersStorage = $storage;
			$this->orders        = [];
		}
		$key = implode( "\0", $names );
		if ( ! isset( $this->orders[ $key ] ) ) {
			$this->orders[ $key ] = $this->ordersFor( $names );
		}

		return $this->orders[ $key ];
	}

	protected function ordersFor( array $names ) {
		$hashes = array_map( 'sha1', $names );
		asort( $hashes, SORT_STRING );
		$byHash = [];
		foreach ( array_keys( $hashes ) as $i ) {
			$byHash[] = $names[ $i ];
		}

		$byName = $names;
		sort( $byName, SORT_STRING );

		$distinct = [];
		foreach ( [ $names, $this->inStorageOrder( $names ), $byHash, $byName ] as $order ) {
			$distinct[ implode( "\0", $order ) ] = $order;
		}

		return array_values( $distinct );
	}

	private function inStorageOrder( array $names ) {
		$remaining = array_flip( $names );
		$ordered   = [];
		foreach ( $this->storageOrder() as $name ) {
			if ( isset( $remaining[ $name ] ) ) {
				$ordered[] = $name;
				unset( $remaining[ $name ] );
			}
		}

		$tail = [];
		foreach ( $names as $name ) {
			if ( isset( $remaining[ $name ] ) ) {
				$tail[] = $name;
			}
		}

		return array_merge( $ordered, $tail );
	}

	protected function storageOrder() {
		$blog = get_current_blog_id();
		if ( ! isset( self::$storageOrder[ $blog ] ) ) {
			self::$storageOrder[ $blog ] = $this->readStorageOrder();
		}

		return self::$storageOrder[ $blog ];
	}

	private function readStorageOrder() {
		if ( ! ContainerFreeServices::state()->isMigrated() ) {
			return [];
		}

		global $wpdb;

		$names = $wpdb->get_col(
			$wpdb->prepare(
				"SELECT name FROM {$wpdb->prefix}icl_meta_settings WHERE element_type = %s ORDER BY id",
				ElementType::POST
			)
		);

		return is_array( $names ) ? array_map( 'strval', $names ) : [];
	}

	public static function reset() {
		self::$storageOrder = [];
	}
}
