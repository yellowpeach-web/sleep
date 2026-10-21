<?php

if ( ! defined( 'ICL_DISABLE_CACHE' ) ) {
	define( 'ICL_DISABLE_CACHE', false );
}

require_once __DIR__ . '/constants-since-5-0.php';

if ( ! function_exists( 'wpml_language_cache_current_blog_id' ) ) {
	function wpml_language_cache_current_blog_id() {
		return function_exists( 'get_current_blog_id' ) ? (int) get_current_blog_id() : null;
	}
}


class icl_cache {
	const SPLIT_GENERATION_KEY = '__wpml_split_cache_generation';

	const SPLIT_EPOCH_KEY = '__wpml_split_cache_epoch';

	const SHARD_DISPLAY_LANGUAGE_KEY = '__wpml_split_cache_display_language';

	protected static $global_clear_sequence = 0;

	protected static $global_clear_events = array();

	protected static $language_name_preserved_during_clear = false;

	protected $data;

	protected $name;

	protected $cache_to_option;

	protected $cache_needs_saving;

	protected $cold_option;

	protected $is_cold_callback;

	protected $cold_data;

	protected $cold_loaded;

	protected $cold_needs_saving;

	protected $shard_option_prefix;

	protected $get_shard_language_callback;

	protected $shard_data;

	protected $shard_loaded;

	protected $shards_needing_saving;

	protected $split_generation;

	protected $option_cache_blog_id;

	protected $option_cache_initialized_while_switched;

	protected $observed_global_clear_sequence;

	protected $language_cache_epoch;

	public function __construct(
		$name = '',
		$cache_to_option = false,
		$cold_option = null,
		$is_cold_callback = null,
		$shard_option_prefix = null,
		$get_shard_language_callback = null
	) {
		global $switched;

		$this->data                        = [];
		$this->name                        = $name;
		$this->cache_to_option             = $cache_to_option;
		$this->cache_needs_saving          = false;
		$this->cold_option                 = $cold_option;
		$this->is_cold_callback            = $is_cold_callback;
		$this->cold_data                   = [];
		$this->cold_loaded                 = false;
		$this->cold_needs_saving           = false;
		$this->shard_option_prefix         = $shard_option_prefix;
		$this->get_shard_language_callback = $get_shard_language_callback;
		$this->shard_data                  = array();
		$this->shard_loaded                = array();
		$this->shards_needing_saving       = array();
		$this->split_generation            = null;

		$this->option_cache_blog_id = $cache_to_option
			? wpml_language_cache_current_blog_id()
			: null;

		$this->option_cache_initialized_while_switched = $cache_to_option && ! empty( $switched );
		$this->observed_global_clear_sequence          = self::$global_clear_sequence;
		$this->language_cache_epoch                    = null;

		$this->init();
	}

	public static function record_global_clear( $key = false, $key_as_prefix = false, $excluded_key = false ) {
		$blog_id = wpml_language_cache_current_blog_id();

		++self::$global_clear_sequence;

		foreach ( self::$global_clear_events as $event_id => $event ) {
			if (
				$event['blog_id'] === $blog_id
				&& (
					( false === $key && false === $excluded_key )
					|| (
						$event['key'] === $key
						&& $event['key_as_prefix'] === (bool) $key_as_prefix
						&& ( isset( $event['excluded_key'] ) ? $event['excluded_key'] : false ) === $excluded_key
					)
				)
			) {
				unset( self::$global_clear_events[ $event_id ] );
			}
		}

		$event_id = md5( serialize( array( $blog_id, $key, (bool) $key_as_prefix, $excluded_key ) ) );

		self::$global_clear_events[ $event_id ] = array(
			'sequence'      => self::$global_clear_sequence,
			'blog_id'       => $blog_id,
			'key'           => $key,
			'key_as_prefix' => (bool) $key_as_prefix,
			'excluded_key'  => $excluded_key,
		);
	}

	public static function fire_global_clear_action( $language_name_preserved = false ) {
		$previous                                   = self::$language_name_preserved_during_clear;
		self::$language_name_preserved_during_clear = (bool) $language_name_preserved;

		try {
			do_action( 'wpml_cache_clear' );
		} finally {
			self::$language_name_preserved_during_clear = $previous;
		}
	}

	public static function is_language_name_preserved_during_clear() {
		return self::$language_name_preserved_during_clear;
	}

	protected function is_split_enabled() {
		return null !== $this->cold_option && is_callable( $this->is_cold_callback );
	}

	protected function is_sharding_enabled() {
		return $this->cache_to_option
			&& $this->is_split_enabled()
			&& is_string( $this->shard_option_prefix )
			&& '' !== $this->shard_option_prefix
			&& is_callable( $this->get_shard_language_callback );
	}

	protected function is_cold( $key ) {
		return (bool) call_user_func( $this->is_cold_callback, $key );
	}

	protected function get_shard_language( $key ) {
		if ( ! $this->is_sharding_enabled() ) {
			return null;
		}

		$display_language = call_user_func( $this->get_shard_language_callback, $key );

		return is_scalar( $display_language ) && '' !== (string) $display_language
			? (string) $display_language
			: null;
	}

	protected function is_reserved_key( $key ) {
		return $this->is_split_enabled()
			&& (
				$this->is_shared_reserved_key( $key )
				|| ( $this->is_sharding_enabled() && self::SHARD_DISPLAY_LANGUAGE_KEY === $key )
			);
	}

	protected function is_shared_reserved_key( $key ) {
		return self::SPLIT_GENERATION_KEY === $key || self::SPLIT_EPOCH_KEY === $key;
	}

	protected function uses_language_cache_epoch() {
		return $this->cache_to_option
			&& $this->is_split_enabled()
			&& 'language_name' === $this->name
			&& WPML_LANGUAGE_DETAILS_CACHE_OPTION === $this->cold_option;
	}

	protected function initialize_language_cache_epoch() {
		if ( ! $this->uses_language_cache_epoch() ) {
			return;
		}

		$epoch = wpml_language_cache_get_or_create_epoch();
		if ( ! is_string( $epoch ) || '' === $epoch ) {
			$this->data                 = array();
			$this->cache_needs_saving   = false;
			$this->split_generation     = null;
			$this->language_cache_epoch = null;

			return;
		}

		$this->language_cache_epoch = $epoch;

		$stored_epoch      = is_array( $this->data ) && isset( $this->data[ self::SPLIT_EPOCH_KEY ] )
			? $this->data[ self::SPLIT_EPOCH_KEY ]
			: null;
		$stored_generation = is_array( $this->data ) && isset( $this->data[ self::SPLIT_GENERATION_KEY ] )
			? $this->data[ self::SPLIT_GENERATION_KEY ]
			: null;
		$invalid_slice     = $epoch !== $stored_epoch
			|| ! is_string( $stored_generation )
			|| '' === $stored_generation;

		if ( ! $invalid_slice ) {
			foreach ( array_keys( $this->data ) as $key ) {
				if (
					self::SHARD_DISPLAY_LANGUAGE_KEY === $key
					|| (
						! $this->is_shared_reserved_key( $key )
						&& ( $this->is_cold( $key ) || null !== $this->get_shard_language( $key ) )
					)
				) {
					$invalid_slice = true;
					break;
				}
			}
		}

		if ( $invalid_slice ) {
			$this->data               = array();
			$this->cache_needs_saving = true;
			$this->split_generation   = null;
		}

		if ( ! isset( $this->data[ self::SPLIT_EPOCH_KEY ] ) || $epoch !== $this->data[ self::SPLIT_EPOCH_KEY ] ) {
			$this->data[ self::SPLIT_EPOCH_KEY ] = $epoch;
			$this->cache_needs_saving            = true;
		}
	}

	protected function refresh_language_cache_epoch() {
		if ( ! $this->uses_language_cache_epoch() ) {
			return;
		}

		$epoch = get_option( WPML_LANGUAGE_DETAILS_CACHE_EPOCH_OPTION );

		$this->language_cache_epoch = is_string( $epoch ) && '' !== $epoch ? $epoch : null;
	}

	protected function is_option_cache_switched() {
		global $switched;

		if ( ! $this->cache_to_option ) {
			return false;
		}

		if ( $this->option_cache_initialized_while_switched || ! empty( $switched ) ) {
			return true;
		}

		$current_blog_id = wpml_language_cache_current_blog_id();

		return null !== $this->option_cache_blog_id
			&& null !== $current_blog_id
			&& $current_blog_id !== $this->option_cache_blog_id;
	}

	protected function synchronize_global_clears() {
		if ( ! $this->cache_to_option ) {
			return;
		}

		if ( null === $this->observed_global_clear_sequence ) {
			$this->observed_global_clear_sequence = self::$global_clear_sequence;

			return;
		}

		if ( $this->observed_global_clear_sequence === self::$global_clear_sequence ) {
			return;
		}

		$cache_key       = $this->name . '_cache_class';
		$was_invalidated = false;

		foreach ( self::$global_clear_events as $event ) {
			if ( $event['sequence'] <= $this->observed_global_clear_sequence ) {
				continue;
			}

			if (
				null !== $event['blog_id']
				&& null !== $this->option_cache_blog_id
				&& $event['blog_id'] !== $this->option_cache_blog_id
			) {
				continue;
			}

			$key = $event['key'];
			if ( isset( $event['excluded_key'] ) && $cache_key === $event['excluded_key'] ) {
				continue;
			}
			if (
				false === $key
				|| $cache_key === $key
				|| ( $event['key_as_prefix'] && 0 === strpos( $cache_key, $key ) )
				|| ( false !== strpos( $key, '_per_language' ) && false !== strpos( $cache_key, $key . '#' ) )
			) {
				$was_invalidated = true;
				break;
			}
		}

		$this->observed_global_clear_sequence = self::$global_clear_sequence;

		if ( $was_invalidated ) {
			$this->data                  = array();
			$this->cold_data             = array();
			$this->cold_loaded           = false;
			$this->cache_needs_saving    = false;
			$this->cold_needs_saving     = false;
			$this->shard_data            = array();
			$this->shard_loaded          = array();
			$this->shards_needing_saving = array();
			$this->split_generation      = null;
			$this->refresh_language_cache_epoch();
		}
	}

	protected function ensure_split_generation() {
		if ( ! $this->is_split_enabled() ) {
			return;
		}

		if ( ! is_array( $this->data ) ) {
			$this->data               = [];
			$this->cache_needs_saving = true;
		}

		if ( $this->uses_language_cache_epoch() ) {
			if ( null === $this->language_cache_epoch ) {
				return;
			}
			if (
				! isset( $this->data[ self::SPLIT_EPOCH_KEY ] )
				|| $this->language_cache_epoch !== $this->data[ self::SPLIT_EPOCH_KEY ]
			) {
				$this->data[ self::SPLIT_EPOCH_KEY ] = $this->language_cache_epoch;
				$this->cache_needs_saving            = true;
			}
		}

		if ( null === $this->split_generation ) {
			$stored_generation = isset( $this->data[ self::SPLIT_GENERATION_KEY ] )
				? $this->data[ self::SPLIT_GENERATION_KEY ]
				: null;

			$this->split_generation = is_string( $stored_generation ) && '' !== $stored_generation
				? $stored_generation
				: uniqid( 'wpml-', true );
		}

		if (
			! isset( $this->data[ self::SPLIT_GENERATION_KEY ] )
			|| $this->split_generation !== $this->data[ self::SPLIT_GENERATION_KEY ]
		) {
			$this->data[ self::SPLIT_GENERATION_KEY ] = $this->split_generation;
			$this->cache_needs_saving                 = true;
		}
	}

	protected function ensure_cold_loaded() {
		if ( ! $this->cold_loaded ) {
			if ( $this->uses_language_cache_epoch() && null === $this->language_cache_epoch ) {
				return;
			}

			$this->ensure_split_generation();
			$stored = get_option( $this->cold_option );

			if (
				is_array( $stored )
				&& isset( $stored[ self::SPLIT_GENERATION_KEY ] )
				&& $this->split_generation === $stored[ self::SPLIT_GENERATION_KEY ]
				&& (
					! $this->uses_language_cache_epoch()
					|| (
						isset( $stored[ self::SPLIT_EPOCH_KEY ] )
						&& $this->language_cache_epoch === $stored[ self::SPLIT_EPOCH_KEY ]
					)
				)
			) {
				$this->cold_data = $stored;

				foreach ( array_keys( $this->cold_data ) as $key ) {
					if ( ! $this->is_shared_reserved_key( $key ) && ! $this->is_cold( $key ) ) {
						unset( $this->cold_data[ $key ] );
						$this->cold_needs_saving = true;
					}
				}
			} else {
				$this->cold_data = [
					self::SPLIT_GENERATION_KEY => $this->split_generation,
				];
				if ( $this->uses_language_cache_epoch() && null !== $this->language_cache_epoch ) {
					$this->cold_data[ self::SPLIT_EPOCH_KEY ] = $this->language_cache_epoch;
				}
				$this->cold_needs_saving = true;
			}

			$this->cold_loaded = true;
		}
	}

	protected function get_shard_option_name( $display_language ) {
		return $this->shard_option_prefix . rawurlencode( $display_language );
	}

	protected function ensure_shard_loaded( $display_language ) {
		if ( isset( $this->shard_loaded[ $display_language ] ) ) {
			return;
		}

		if ( $this->uses_language_cache_epoch() && null === $this->language_cache_epoch ) {
			return;
		}

		$this->ensure_split_generation();
		$stored = get_option( $this->get_shard_option_name( $display_language ) );

		if (
			is_array( $stored )
			&& isset( $stored[ self::SPLIT_GENERATION_KEY ] )
			&& $this->split_generation === $stored[ self::SPLIT_GENERATION_KEY ]
			&& isset( $stored[ self::SHARD_DISPLAY_LANGUAGE_KEY ] )
			&& $display_language === $stored[ self::SHARD_DISPLAY_LANGUAGE_KEY ]
			&& (
				! $this->uses_language_cache_epoch()
				|| (
					isset( $stored[ self::SPLIT_EPOCH_KEY ] )
					&& $this->language_cache_epoch === $stored[ self::SPLIT_EPOCH_KEY ]
				)
			)
		) {
			$this->shard_data[ $display_language ] = $stored;

			foreach ( array_keys( $stored ) as $key ) {
				if ( $this->is_reserved_key( $key ) ) {
					continue;
				}

				$key_display_language = $this->get_shard_language( $key );
				if ( $this->is_cold( $key ) || ( null !== $key_display_language && $display_language !== $key_display_language ) ) {
					unset( $this->shard_data[ $display_language ][ $key ] );
					$this->shards_needing_saving[ $display_language ] = true;
				}
			}
		} else {
			$this->shard_data[ $display_language ] = array(
				self::SPLIT_GENERATION_KEY       => $this->split_generation,
				self::SHARD_DISPLAY_LANGUAGE_KEY => $display_language,
			);
			if ( $this->uses_language_cache_epoch() && null !== $this->language_cache_epoch ) {
				$this->shard_data[ $display_language ][ self::SPLIT_EPOCH_KEY ] = $this->language_cache_epoch;
			}
			$this->shards_needing_saving[ $display_language ] = true;
		}

		$this->shard_loaded[ $display_language ] = true;
	}

	protected function discard_legacy_shard_duplicate( $display_language, $key ) {
		if ( ! array_key_exists( $key, (array) $this->data ) ) {
			return false;
		}

		unset( $this->data[ $key ] );
		$this->cache_needs_saving = true;

		if ( isset( $this->shard_data[ $display_language ] ) && array_key_exists( $key, $this->shard_data[ $display_language ] ) ) {
			unset( $this->shard_data[ $display_language ][ $key ] );
			$this->shards_needing_saving[ $display_language ] = true;
		}

		return true;
	}

	protected function discard_legacy_cold_duplicate( $key ) {
		if ( ! array_key_exists( $key, $this->data ) ) {
			return false;
		}

		unset( $this->data[ $key ] );
		$this->cache_needs_saving = true;

		if ( array_key_exists( $key, $this->cold_data ) ) {
			unset( $this->cold_data[ $key ] );
			$this->cold_needs_saving = true;
		}

		return true;
	}

	public function init() {
		if ( $this->cache_to_option ) {
			if ( icl_disable_cache() ) {
				return;
			}

			if ( null === $this->observed_global_clear_sequence ) {
				$this->observed_global_clear_sequence = self::$global_clear_sequence;
			}

			add_action( 'shutdown', [ $this, 'save_cache_if_required' ] );

			if ( $this->is_option_cache_switched() ) {
				return;
			}

			$this->data = icl_cache_get( $this->name . '_cache_class' );
			if ( false === $this->data ) {
				$this->data = [];
			}

			$this->initialize_language_cache_epoch();
			$this->ensure_split_generation();
		}
	}

	public function save_cache_if_required() {
		if ( $this->is_option_cache_switched() ) {
			return;
		}
		$this->synchronize_global_clears();
		if ( $this->uses_language_cache_epoch() && null === $this->language_cache_epoch ) {
			$this->cache_needs_saving    = false;
			$this->cold_needs_saving     = false;
			$this->shards_needing_saving = array();

			return;
		}

		if (
			$this->uses_language_cache_epoch()
			&& ( $this->cache_needs_saving || $this->cold_needs_saving || $this->shards_needing_saving )
			&& wpml_language_cache_get_fresh_epoch() !== $this->language_cache_epoch
		) {
			$this->data                  = array();
			$this->cold_data             = array();
			$this->cold_loaded           = false;
			$this->cache_needs_saving    = false;
			$this->cold_needs_saving     = false;
			$this->shard_data            = array();
			$this->shard_loaded          = array();
			$this->shards_needing_saving = array();
			$this->split_generation      = null;

			return;
		}

		if ( $this->shards_needing_saving ) {
			$display_languages = array_keys( $this->shards_needing_saving );
			sort( $display_languages, SORT_STRING );

			foreach ( $display_languages as $display_language ) {
				if ( ! isset( $this->shard_data[ $display_language ] ) ) {
					unset( $this->shards_needing_saving[ $display_language ] );
					continue;
				}

				$option_name = $this->get_shard_option_name( $display_language );
				$shard_saved = update_option( $option_name, $this->shard_data[ $display_language ], false );
				if ( false === $shard_saved && get_option( $option_name ) !== $this->shard_data[ $display_language ] ) {
					return;
				}
				unset( $this->shards_needing_saving[ $display_language ] );
			}
		}

		if ( $this->cold_needs_saving ) {
			$cold_saved = update_option( $this->cold_option, $this->cold_data, false );
			if ( false === $cold_saved && get_option( $this->cold_option ) !== $this->cold_data ) {
				return;
			}
			$this->cold_needs_saving = false;
		}
		if ( $this->cache_needs_saving ) {
			$hot_saved = icl_cache_set( $this->name . '_cache_class', $this->data );
			if ( false !== $hot_saved ) {
				$this->cache_needs_saving = false;
			}
		}
	}

	public function get_from_shard( $display_language, $key ) {
		if ( ICL_DISABLE_CACHE || ! $this->is_sharding_enabled() ) {
			return false;
		}
		if ( $this->is_option_cache_switched() ) {
			return false;
		}

		$display_language = is_scalar( $display_language ) ? (string) $display_language : '';
		if ( '' === $display_language || $this->is_reserved_key( $key ) ) {
			return false;
		}

		$this->synchronize_global_clears();
		if ( $this->uses_language_cache_epoch() && null === $this->language_cache_epoch ) {
			return false;
		}

		$this->ensure_shard_loaded( $display_language );
		if ( ! isset( $this->shard_loaded[ $display_language ] ) ) {
			return false;
		}
		if ( $this->discard_legacy_shard_duplicate( $display_language, $key ) ) {
			return false;
		}

		return isset( $this->shard_data[ $display_language ][ $key ] )
			? $this->shard_data[ $display_language ][ $key ]
			: false;
	}

	public function has_in_shard( $display_language, $key ) {
		if ( ICL_DISABLE_CACHE || ! $this->is_sharding_enabled() ) {
			return false;
		}
		if ( $this->is_option_cache_switched() ) {
			return false;
		}

		$display_language = is_scalar( $display_language ) ? (string) $display_language : '';
		if ( '' === $display_language || $this->is_reserved_key( $key ) ) {
			return false;
		}

		$this->synchronize_global_clears();
		if ( $this->uses_language_cache_epoch() && null === $this->language_cache_epoch ) {
			return false;
		}

		$this->ensure_shard_loaded( $display_language );
		if ( ! isset( $this->shard_loaded[ $display_language ] ) ) {
			return false;
		}
		if ( $this->discard_legacy_shard_duplicate( $display_language, $key ) ) {
			return false;
		}

		return array_key_exists( $key, $this->shard_data[ $display_language ] );
	}

	public function set_in_shard( $display_language, $key, $value ) {
		if ( ICL_DISABLE_CACHE || ! $this->is_sharding_enabled() ) {
			return;
		}
		if ( $this->is_option_cache_switched() ) {
			return;
		}

		$display_language = is_scalar( $display_language ) ? (string) $display_language : '';
		if ( '' === $display_language || $this->is_reserved_key( $key ) ) {
			return;
		}

		$this->synchronize_global_clears();
		if ( $this->uses_language_cache_epoch() && null === $this->language_cache_epoch ) {
			return;
		}

		$this->ensure_split_generation();
		$this->ensure_shard_loaded( $display_language );
		if ( ! isset( $this->shard_loaded[ $display_language ] ) ) {
			return;
		}

		$this->discard_legacy_shard_duplicate( $display_language, $key );
		$old_value = isset( $this->shard_data[ $display_language ][ $key ] )
			? $this->shard_data[ $display_language ][ $key ]
			: null;
		if ( $old_value !== $value ) {
			$this->shard_data[ $display_language ][ $key ]    = $value;
			$this->shards_needing_saving[ $display_language ] = true;
		}
	}

	public function get( $key ) {
		if ( ICL_DISABLE_CACHE ) {
			return null;
		}
		if ( $this->is_option_cache_switched() ) {
			return false;
		}
		$this->synchronize_global_clears();
		if ( $this->uses_language_cache_epoch() && null === $this->language_cache_epoch ) {
			return false;
		}
		if ( $this->is_reserved_key( $key ) ) {
			return false;
		}
		$shard_language = $this->get_shard_language( $key );
		if ( null !== $shard_language ) {
			return $this->get_from_shard( $shard_language, $key );
		}
		if ( $this->is_split_enabled() && $this->is_cold( $key ) ) {
			$this->ensure_cold_loaded();
			if ( $this->discard_legacy_cold_duplicate( $key ) ) {
				return false;
			}
			return isset( $this->cold_data[ $key ] ) ? $this->cold_data[ $key ] : false;
		}

		return isset( $this->data[ $key ] ) ? $this->data[ $key ] : false;
	}

	public function has_key( $key ) {
		if ( ICL_DISABLE_CACHE ) {
			return false;
		}
		if ( $this->is_option_cache_switched() ) {
			return false;
		}
		$this->synchronize_global_clears();
		if ( $this->uses_language_cache_epoch() && null === $this->language_cache_epoch ) {
			return false;
		}
		if ( $this->is_reserved_key( $key ) ) {
			return false;
		}
		$shard_language = $this->get_shard_language( $key );
		if ( null !== $shard_language ) {
			return $this->has_in_shard( $shard_language, $key );
		}
		if ( $this->is_split_enabled() && $this->is_cold( $key ) ) {
			$this->ensure_cold_loaded();
			if ( $this->discard_legacy_cold_duplicate( $key ) ) {
				return false;
			}

			return array_key_exists( $key, $this->cold_data );
		}

		return array_key_exists( $key, (array) $this->data );
	}

	public function set( $key, $value ) {
		if ( ICL_DISABLE_CACHE ) {
			return;
		}
		if ( $this->is_option_cache_switched() ) {
			return;
		}
		$this->synchronize_global_clears();
		if ( $this->uses_language_cache_epoch() && null === $this->language_cache_epoch ) {
			return;
		}
		if ( $this->is_reserved_key( $key ) ) {
			return;
		}
		$shard_language = $this->get_shard_language( $key );
		if ( null !== $shard_language ) {
			$this->set_in_shard( $shard_language, $key, $value );

			return;
		}
		if ( $this->cache_to_option ) {
			$this->ensure_split_generation();
			if ( $this->is_split_enabled() && $this->is_cold( $key ) ) {
				$this->ensure_cold_loaded();
				$this->discard_legacy_cold_duplicate( $key );
				$old_value = isset( $this->cold_data[ $key ] ) ? $this->cold_data[ $key ] : null;
				if ( $old_value !== $value ) {
					$this->cold_data[ $key ] = $value;
					$this->cold_needs_saving = true;
				}
				return;
			}
			if ( $this->cold_loaded && array_key_exists( $key, $this->cold_data ) ) {
				unset( $this->cold_data[ $key ] );
				$this->cold_needs_saving = true;
			}
			$old_value = null;
			if ( isset( $this->data[ $key ] ) ) {
				$old_value = $this->data[ $key ];
			}
			if ( $old_value !== $value ) {
				$this->data[ $key ]       = $value;
				$this->cache_needs_saving = true;
			}
		} else {
			$this->data[ $key ] = $value;
		}
	}

	public function clear() {
		if ( $this->is_option_cache_switched() ) {
			return false;
		}
		$this->synchronize_global_clears();

		$this->data                  = array();
		$this->cold_data             = array();
		$this->cold_loaded           = false;
		$this->cache_needs_saving    = false;
		$this->cold_needs_saving     = false;
		$this->shard_data            = array();
		$this->shard_loaded          = array();
		$this->shards_needing_saving = array();
		$this->split_generation      = null;
		$this->language_cache_epoch  = null;
		$cold_cleared_by_helper      = false;
		if ( $this->cache_to_option ) {
			$cache_key = $this->name . '_cache_class';
			$cleared   = icl_cache_clear( $cache_key );
			if ( false === $cleared ) {
				return false;
			}

			$cold_cleared_by_helper = ! icl_disable_cache()
				&& 'language_name_cache_class' === $cache_key
				&& WPML_LANGUAGE_DETAILS_CACHE_OPTION === $this->cold_option;
		}
		if ( $this->is_split_enabled() && ! $cold_cleared_by_helper ) {
			if ( $this->uses_language_cache_epoch() ) {
				return false;
			}

			delete_option( $this->cold_option );
		}

		return true;
	}
}

if ( ! function_exists( 'icl_disable_cache' ) ) {
	function icl_disable_cache() {
		return defined( 'ICL_DISABLE_CACHE' ) && ICL_DISABLE_CACHE;
	}
}

if ( ! function_exists( 'wpml_language_cache_get_fresh_epoch' ) ) {
	function wpml_language_cache_get_fresh_epoch() {
		global $wpdb;

		if ( ! isset( $wpdb, $wpdb->options ) ) {
			$epoch = get_option( WPML_LANGUAGE_DETAILS_CACHE_EPOCH_OPTION );

			return is_string( $epoch ) && '' !== $epoch ? $epoch : null;
		}

		$epoch = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT option_value FROM {$wpdb->options} WHERE option_name = %s",
				WPML_LANGUAGE_DETAILS_CACHE_EPOCH_OPTION
			)
		);

		return is_string( $epoch ) && '' !== $epoch ? $epoch : null;
	}
}

if ( ! function_exists( 'wpml_language_cache_new_epoch' ) ) {
	function wpml_language_cache_new_epoch() {
		return function_exists( 'wp_generate_uuid4' )
			? wp_generate_uuid4()
			: uniqid( 'wpml-', true );
	}
}

if ( ! function_exists( 'wpml_language_cache_get_or_create_epoch' ) ) {
	function wpml_language_cache_get_or_create_epoch() {
		$epoch = get_option( WPML_LANGUAGE_DETAILS_CACHE_EPOCH_OPTION );
		if ( is_string( $epoch ) && '' !== $epoch ) {
			return $epoch;
		}

		$candidate = wpml_language_cache_new_epoch();
		if ( add_option( WPML_LANGUAGE_DETAILS_CACHE_EPOCH_OPTION, $candidate, '', true ) ) {
			return $candidate;
		}

		return wpml_language_cache_get_fresh_epoch();
	}
}

if ( ! function_exists( 'wpml_language_cache_rotate_epoch' ) ) {
	function wpml_language_cache_rotate_epoch() {
		$epoch   = wpml_language_cache_new_epoch();
		$updated = update_option( WPML_LANGUAGE_DETAILS_CACHE_EPOCH_OPTION, $epoch, true );

		if ( false === $updated && wpml_language_cache_get_fresh_epoch() !== $epoch ) {
			return false;
		}

		return $epoch;
	}
}

if ( ! function_exists( 'wpml_language_cache_get_shard_option_names' ) ) {
	function wpml_language_cache_get_shard_option_names() {
		global $wpdb;

		if ( ! isset( $wpdb, $wpdb->options ) || ! method_exists( $wpdb, 'prepare' ) || ! method_exists( $wpdb, 'get_col' ) ) {
			return array();
		}

		$escaped_prefix = method_exists( $wpdb, 'esc_like' )
			? $wpdb->esc_like( WPML_LANGUAGE_NAMES_CACHE_OPTION_PREFIX )
			: addcslashes( WPML_LANGUAGE_NAMES_CACHE_OPTION_PREFIX, '_%\\' );

		$option_names = $wpdb->get_col(
			$wpdb->prepare(
				"SELECT option_name FROM {$wpdb->options} WHERE option_name LIKE %s ORDER BY option_name ASC",
				$escaped_prefix . '%'
			)
		);

		if ( ! empty( $wpdb->last_error ) ) {
			return false;
		}

		if ( null === $option_names && empty( $wpdb->last_error ) ) {
			return array();
		}
		if ( ! is_array( $option_names ) ) {
			return false;
		}

		return array_values(
			array_filter(
				array_unique( array_map( 'strval', $option_names ) ),
				function ( $option_name ) {
					return 0 === strpos( $option_name, WPML_LANGUAGE_NAMES_CACHE_OPTION_PREFIX );
				}
			)
		);
	}
}

if ( ! function_exists( 'wpml_language_cache_delete_shards' ) ) {
	function wpml_language_cache_delete_shards() {
		$option_names = wpml_language_cache_get_shard_option_names();
		if ( false === $option_names ) {
			return false;
		}

		$all_deleted = true;
		foreach ( $option_names as $option_name ) {
			$deleted = delete_option( $option_name );
			if ( false === $deleted ) {
				$missing = new \stdClass();
				if ( get_option( $option_name, $missing ) !== $missing ) {
					$all_deleted = false;
				}
			}
		}

		return $all_deleted;
	}
}

if ( ! function_exists( 'wpml_language_cache_shards_exist' ) ) {
	function wpml_language_cache_shards_exist() {
		$option_names = wpml_language_cache_get_shard_option_names();

		return false === $option_names || ! empty( $option_names );
	}
}

if ( ! function_exists( 'wpml_cache_clear_targets_language_name' ) ) {
	function wpml_cache_clear_targets_language_name( $key, $key_as_prefix ) {
		return false === $key
			|| 'language_name_cache_class' === $key
			|| ( $key_as_prefix && 0 === strpos( 'language_name_cache_class', $key ) );
	}
}

if ( ! function_exists( 'icl_cache_get' ) ) {
	function icl_cache_get( $key ) {
		$result = false;
		if ( ! icl_disable_cache() ) {
			$icl_cache = get_option( '_icl_cache' );

			$result = isset( $icl_cache[ $key ] ) ? $icl_cache[ $key ] : false;
		}

		return $result;
	}
}

if ( ! function_exists( 'icl_cache_set' ) ) {
	function icl_cache_set( $key, $value = null ) {

		global $switched;
		if ( empty( $switched ) && ! icl_disable_cache() ) {
			$icl_cache = get_option( '_icl_cache' );
			if ( false === $icl_cache ) {
				$icl_cache = [];
				delete_option( '_icl_cache' );
			}

			if ( ! isset( $icl_cache[ $key ] ) || $icl_cache[ $key ] !== $value ) {
				if ( ! is_null( $value ) ) {
					$icl_cache[ $key ] = $value;
				} elseif ( isset( $icl_cache[ $key ] ) ) {
					unset( $icl_cache[ $key ] );
				}

				$updated = update_option( '_icl_cache', $icl_cache );
				if ( false === $updated && get_option( '_icl_cache' ) !== $icl_cache ) {
					return false;
				}
			}

			return true;
		}

		return false;
	}
}

if ( ! function_exists( 'icl_cache_clear' ) ) {
	function icl_cache_clear( $key = false, $key_as_prefix = false, $preserve_language_name = false ) {
		$cleared = true;
		if ( ! icl_disable_cache() ) {
			$targets_language_name = ! $preserve_language_name && wpml_cache_clear_targets_language_name( $key, $key_as_prefix );
			$epoch_rotated         = ! $targets_language_name || false !== wpml_language_cache_rotate_epoch();

			global $wpml_term_translations, $wpml_post_translations;

			$wpml_term_translations->reload();
			$wpml_post_translations->reload();

			if ( ! $epoch_rotated ) {
				icl_cache::record_global_clear( $key, $key_as_prefix );
				icl_cache::fire_global_clear_action();

				return false;
			}

			if ( false === $key ) {
				if ( $preserve_language_name ) {
					$icl_cache = get_option( '_icl_cache' );
					$preserved = is_array( $icl_cache ) && array_key_exists( 'language_name_cache_class', $icl_cache )
						? array( 'language_name_cache_class' => $icl_cache['language_name_cache_class'] )
						: array();

					if ( $preserved ) {
						$updated = update_option( '_icl_cache', $preserved );
						if ( false === $updated && get_option( '_icl_cache' ) !== $preserved ) {
							$cleared = false;
						}
					} else {
						delete_option( '_icl_cache' );
					}
				} else {
					delete_option( '_icl_cache' );
				}
			} else {
				$icl_cache = get_option( '_icl_cache' );

				if ( is_array( $icl_cache ) ) {
					if ( isset( $icl_cache[ $key ] ) ) {
						unset( $icl_cache[ $key ] );
					}

					if ( $key_as_prefix ) {
						$cache_keys = array_keys( $icl_cache );
						foreach ( $cache_keys as $cache_key ) {
							if ( strpos( $cache_key, $key ) === 0 ) {
								unset( $icl_cache[ $cache_key ] );
							}
						}
					}

					if ( false !== strpos( $key, '_per_language' ) ) {
						foreach ( $icl_cache as $k => $v ) {
							if ( false !== strpos( $k, $key . '#' ) ) {
								unset( $icl_cache[ $k ] );
							}
						}
					}
					update_option( '_icl_cache', $icl_cache );
				}
			}

			if ( $targets_language_name ) {
				delete_option( WPML_LANGUAGE_DETAILS_CACHE_OPTION );
				$cleared = wpml_language_cache_delete_shards();
			}
		}
		$excluded_key = $preserve_language_name ? 'language_name_cache_class' : false;
		icl_cache::record_global_clear( $key, $key_as_prefix, $excluded_key );
		icl_cache::fire_global_clear_action( $preserve_language_name );

		return $cleared;
	}
}

if ( ! function_exists( 'icl_cache_clear_preserving_language_names' ) ) {
	function icl_cache_clear_preserving_language_names() {
		return icl_cache_clear( false, false, true );
	}
}

function w3tc_translate_cache_key_filter( $key ) {
	global $sitepress;

	return $sitepress->get_current_language() . $key;
}
