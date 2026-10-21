<?php

class WPML_Single_Url_Cache_Generation {

	const OPTION_KEY       = 'wpml_single_url_resolution_generation';
	const MAX_CAS_ATTEMPTS = 5;

	private $wpdb;

	private $algorithm_version;

	private $request_cache = [];

	public function __construct( ?wpdb $wpdb = null, $algorithm_version = null ) {
		if ( ! $wpdb ) {
			global $wpdb;
		}

		$this->wpdb              = $wpdb;
		$this->algorithm_version = null === $algorithm_version
			? WPML_Single_Url_Cache_Key::ALGORITHM_VERSION
			: (string) $algorithm_version;
	}

	public function get() {
		$blog_id = get_current_blog_id();

		if ( ! isset( $this->request_cache[ $blog_id ] ) ) {
			$this->request_cache[ $blog_id ] = $this->synchronize_algorithm_version();
		}

		return $this->request_cache[ $blog_id ];
	}

	public function bump() {
		for ( $attempt = 0; $attempt < self::MAX_CAS_ATTEMPTS; ++$attempt ) {
			$raw = $this->read_raw_option();
			if ( null === $raw ) {
				$this->add_initial_state();
				continue;
			}

			$state = $this->decode_state( $raw );
			$new   = [
				'generation'        => $state['generation'] + 1,
				'algorithm_version' => $this->algorithm_version,
			];

			if ( $this->compare_and_swap( $raw, $this->encode_state( $new ) ) ) {
				$this->request_cache[ get_current_blog_id() ] = $new['generation'];
				return $new['generation'];
			}
		}

		$this->reset_request_cache();
		return $this->synchronize_algorithm_version();
	}

	public function synchronize_algorithm_version() {
		for ( $attempt = 0; $attempt < self::MAX_CAS_ATTEMPTS; ++$attempt ) {
			$raw = 0 === $attempt ? $this->read_cached_option() : $this->read_raw_option();

			if ( null === $raw ) {
				if ( $this->add_initial_state() ) {
					$this->request_cache[ get_current_blog_id() ] = 1;
					return 1;
				}
				continue;
			}

			$state = $this->decode_state( $raw );
			if ( $state['tracked'] && $this->algorithm_version === $state['algorithm_version'] ) {
				$this->request_cache[ get_current_blog_id() ] = $state['generation'];
				return $state['generation'];
			}

			$is_legacy      = ! $state['tracked'];
			$new_generation = $is_legacy ? $state['generation'] : $state['generation'] + 1;
			$new            = [
				'generation'        => $new_generation,
				'algorithm_version' => $this->algorithm_version,
			];

			if ( ! $this->compare_and_swap( $raw, $this->encode_state( $new ) ) ) {
				continue;
			}

			$this->request_cache[ get_current_blog_id() ] = $new_generation;

			if ( ! $is_legacy ) {
				do_action(
					'wpml_single_url_resolution_cache_generation_changed',
					$state['generation'],
					$new_generation,
					'resolver_algorithm_' . $this->algorithm_version
				);
			}

			return $new_generation;
		}

		$state                                        = $this->decode_state( $this->read_raw_option() );
		$this->request_cache[ get_current_blog_id() ] = $state['generation'];

		return $state['generation'];
	}

	public function reset_request_cache() {
		unset( $this->request_cache[ get_current_blog_id() ] );
	}

	protected function read_cached_option() {
		$value = get_option( self::OPTION_KEY, null );
		return null === $value ? null : $this->normalize_raw_value( $value );
	}

	protected function read_raw_option() {
		if ( ! $this->wpdb ) {
			return $this->read_cached_option();
		}

		$wpdb = $this->wpdb;
		$raw  = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT option_value FROM {$wpdb->options} WHERE option_name = %s LIMIT 1",
				self::OPTION_KEY
			)
		);

		return null === $raw ? null : (string) $raw;
	}

	protected function compare_and_swap( $expected, $replacement ) {
		if ( ! $this->wpdb ) {
			return false;
		}

		$wpdb   = $this->wpdb;
		$result = $wpdb->query(
			$wpdb->prepare(
				"UPDATE {$wpdb->options}
				SET option_value = %s
				WHERE option_name = %s AND option_value = %s",
				$replacement,
				self::OPTION_KEY,
				$expected
			)
		);

		if ( 1 === (int) $result ) {
			$this->clear_option_cache();
			return true;
		}

		return false;
	}

	protected function add_initial_state() {
		return add_option(
			self::OPTION_KEY,
			$this->encode_state(
				[
					'generation'        => 1,
					'algorithm_version' => $this->algorithm_version,
				]
			),
			'',
			'no'
		);
	}

	protected function clear_option_cache() {
		wp_cache_delete( self::OPTION_KEY, 'options' );
		wp_cache_delete( 'alloptions', 'options' );
		wp_cache_delete( 'notoptions', 'options' );
	}

	private function normalize_raw_value( $value ) {
		return is_string( $value ) ? $value : (string) maybe_serialize( $value );
	}

	private function decode_state( $raw ) {
		$decoded = is_string( $raw ) ? json_decode( $raw, true ) : null;

		if (
			is_array( $decoded )
			&& isset( $decoded['generation'], $decoded['algorithm_version'] )
		) {
			return [
				'generation'        => max( 1, (int) $decoded['generation'] ),
				'algorithm_version' => (string) $decoded['algorithm_version'],
				'tracked'           => true,
			];
		}

		return [
			'generation'        => max( 1, (int) $raw ),
			'algorithm_version' => '',
			'tracked'           => false,
		];
	}

	private function encode_state( array $state ) {
		return (string) wp_json_encode(
			[
				'generation'        => max( 1, (int) $state['generation'] ),
				'algorithm_version' => (string) $state['algorithm_version'],
			]
		);
	}
}
