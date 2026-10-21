<?php

namespace WPML\WP;

use WPML\Utilities\AdvisoryLockFactory;

use function WPML\Container\make;
use function WPML\FP\curryN;

class OptionManager {

	private $group_keys_key = 'WPML_Group_Keys';

	const MISSING = '__WPML_OPTION_MANAGER_MISSING__';

	const LOCK_TIMEOUT_SECONDS = 10;

	public function get( $group, $key, $default = false ) {
		$row = get_option( $this->get_item_key( $group, $key ), self::MISSING );
		if ( is_array( $row ) && array_key_exists( 'v', $row ) ) {
			return null === $row['v'] ? $default : $row['v'];
		}

		$data  = get_option( $this->get_key( $group ), array() );
		$value = ( is_array( $data ) && isset( $data[ $key ] ) ) ? $data[ $key ] : null;

		if ( self::MISSING === $row ) {
			$this->materialize_item_row( $this->get_item_key( $group, $key ), $value );
		}

		return null === $value ? $default : $value;
	}

	private function materialize_item_row( $item_key, $value ) {
		if ( wp_installing() ) {
			return;
		}

		global $wpdb;

		$serialized = serialize( array( 'v' => $value ) );
		$autoload = strlen( $serialized ) <= 10240 ? 'yes' : 'no';

		$inserted = $wpdb->query(
			$wpdb->prepare(
				"INSERT IGNORE INTO {$wpdb->options} (option_name, option_value, autoload) VALUES (%s, %s, %s)",
				$item_key,
				$serialized,
				$autoload
			)
		);

		if ( $inserted && function_exists( 'wp_cache_delete' ) ) {
			wp_cache_delete( $item_key, 'options' );
			wp_cache_delete( 'notoptions', 'options' );
			if ( 'yes' === $autoload ) {
				wp_cache_delete( 'alloptions', 'options' );
			}
		}
	}

	public function set( $group, $key, $value, $autoload = true ) {
		$item_key = $this->get_item_key( $group, $key );

		update_option( $item_key, array( 'v' => $value ), $autoload );

		$this->store_group_key( $item_key );
		$this->store_group_key( $this->get_key( $group ) );

		$this->refresh_legacy_group_row( $group, $key, $value, $autoload );
	}

	private function refresh_legacy_group_row( $group, $key, $value, $autoload ) {
		$group_key = $this->get_key( $group );

		if ( ! function_exists( 'wp_using_ext_object_cache' ) || ! wp_using_ext_object_cache() ) {
			$this->invalidate_cache( $group_key );
		}

		$data         = get_option( $group_key, array() );
		$data         = is_array( $data ) ? $data : array();
		$data[ $key ] = $value;
		update_option( $group_key, $data, $autoload );
	}

	private function get_key( $group ) {
		return 'WPML(' . $group . ')';
	}

	private function get_item_key( $group, $key ) {
		return 'WPML(' . $group . '/' . $key . ')';
	}

	public function getFresh( $group, $key, $default = false ) {
		if ( ! function_exists( 'wp_using_ext_object_cache' ) || ! wp_using_ext_object_cache() ) {
			$this->invalidate_cache( $this->get_item_key( $group, $key ) );
			$this->invalidate_cache( $this->get_key( $group ) );
		}

		return $this->get( $group, $key, $default );
	}

	public function invalidateGroup( $group ) {
		if ( function_exists( 'wp_using_ext_object_cache' ) && wp_using_ext_object_cache() ) {
			return;
		}

		$names  = [ $this->get_key( $group ) ];
		$prefix = 'WPML(' . $group . '/';

		foreach ( (array) get_option( $this->group_keys_key, [] ) as $name ) {
			if ( 0 === strpos( (string) $name, $prefix ) ) {
				$names[] = $name;
			}
		}
		$alloptions = function_exists( 'wp_cache_get' ) ? wp_cache_get( 'alloptions', 'options' ) : false;
		if ( is_array( $alloptions ) ) {
			foreach ( array_keys( $alloptions ) as $name ) {
				if ( 0 === strpos( (string) $name, $prefix ) ) {
					$names[] = $name;
				}
			}
		}

		foreach ( array_unique( $names ) as $name ) {
			$this->invalidate_cache( $name );
		}
	}

	public function mutate( $group, $key, callable $updater, $autoload = true ) {
		return $this->mutate_locked(
			$this->get_item_key( $group, $key ),
			function () use ( $group, $key, $updater, $autoload ) {
				$new = $updater( $this->getFresh( $group, $key ) );
				$this->set( $group, $key, $new, $autoload );

				return $new;
			}
		);
	}

	public function mutateRaw( $option_name, callable $updater, $autoload = true ) {
		return $this->mutate_locked(
			$option_name,
			function () use ( $option_name, $updater, $autoload ) {
				$new = $updater( $this->getFreshRaw( $option_name ) );
				update_option( $option_name, $new, $autoload );

				return $new;
			}
		);
	}

	public function getFreshRaw( $option_name, $default = false ) {
		if ( ! function_exists( 'wp_using_ext_object_cache' ) || ! wp_using_ext_object_cache() ) {
			$this->invalidate_cache( $option_name );
		}

		return get_option( $option_name, $default );
	}

	private function mutate_locked( $option_name, callable $operation ) {
		$lock   = make( AdvisoryLockFactory::class )->create( 'option_mutate_' . $option_name );
		$locked = $lock->acquire( self::LOCK_TIMEOUT_SECONDS );
		if ( ! $locked ) {
			do_action( 'wpml_option_mutate_lock_not_acquired', $option_name );
		}

		try {
			return $operation();
		} finally {
			if ( $locked ) {
				$lock->release();
			}
		}
	}

	private function invalidate_cache( $option_name ) {
		if ( ! function_exists( 'wp_cache_delete' ) || ! function_exists( 'wp_cache_get' ) || ! function_exists( 'wp_cache_set' ) ) {
			return;
		}

		wp_cache_delete( $option_name, 'options' );

		$notoptions = wp_cache_get( 'notoptions', 'options' );
		if ( is_array( $notoptions ) && array_key_exists( $option_name, $notoptions ) ) {
			unset( $notoptions[ $option_name ] );
			wp_cache_set( 'notoptions', $notoptions, 'options' );
		}

		$alloptions = wp_cache_get( 'alloptions', 'options' );
		if ( is_array( $alloptions ) && array_key_exists( $option_name, $alloptions ) ) {
			wp_cache_delete( 'alloptions', 'options' );
		}
	}

	private function store_group_key( $group_key ) {
		$group_keys = get_option( $this->group_keys_key, array() );
		$group_keys = is_array( $group_keys ) ? $group_keys : array();
		if ( in_array( $group_key, $group_keys, true ) ) {
			return;
		}
		$group_keys[] = $group_key;
		update_option( $this->group_keys_key, array_unique( $group_keys ) );
	}

	public function reset_options( $options ) {
		$options[] = $this->group_keys_key;

		return array_merge( $options, get_option( $this->group_keys_key, array() ) );
	}

	public static function updateWithoutAutoLoad( $group = null, $key = null, $value = null ) {
		$update = function ( $group, $key, $value ) {
			( new OptionManager() )->set( $group, $key, $value, false );
		};

		return call_user_func_array( curryN( 3, $update ), func_get_args() );
	}

	public static function update( $group = null, $key = null, $value = null ) {
		return call_user_func_array( curryN( 3, [ new OptionManager(), 'set' ] ), func_get_args() );
	}

	public static function getOr( $default = null, $group = null, $key = null ) {
		$get = function ( $default, $group, $key ) {
			return ( new OptionManager() )->get( $group, $key, $default );
		};

		return call_user_func_array( curryN( 3, $get ), func_get_args() );
	}
}
