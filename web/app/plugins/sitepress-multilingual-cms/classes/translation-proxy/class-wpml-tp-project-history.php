<?php

class WPML_TP_Project_History {

	const OPTION                = 'wpml_tp_project_history';
	const BLOCKED_OPTION        = 'wpml_tp_project_history_blocked';
	const CAS_ATTEMPTS          = 5;
	const BLOCKED_STATE_VERSION = 2;

	public static function remember( $service, $project_id ) {
		return self::store_project( $service, $project_id, false );
	}

	private static function seed( $service, $project_id ) {
		return self::store_project( $service, $project_id, false );
	}

	private static function store_project( $service, $project_id, $replace_existing ) {
		if ( WPML_Settings_Failsafe_Loader::isUnrecoverable() ) {
			return false;
		}

		$key        = self::service_key( $service );
		$project_id = self::normalize_project_id( $project_id );
		if ( ! $key || ! $project_id ) {
			return false;
		}

		return self::mutate_history(
			function ( array $history ) use ( $key, $project_id, $replace_existing, $service ) {
				if ( isset( $history[ $key ]['project_id'] ) ) {
					$known_project_id = self::normalize_project_id( $history[ $key ]['project_id'] );
					if ( $known_project_id === $project_id ) {
						return array(
							'accepted' => true,
							'history'  => $history,
						);
					}
					if ( ! $replace_existing ) {
						return array(
							'accepted' => false,
							'history'  => $history,
						);
					}
				}

				$history[ $key ] = array(
					'project_id' => $project_id,
					'service'    => isset( $service->name ) && is_scalar( $service->name ) ? (string) $service->name : '',
					'since'      => time(),
				);

				return array(
					'accepted' => true,
					'history'  => $history,
				);
			}
		);
	}

	public static function had_project( $service ) {
		$key     = self::service_key( $service );
		$history = self::get_history();

		return $key
			&& is_array( $history )
			&& isset( $history[ $key ]['project_id'] )
			&& (bool) self::normalize_project_id( $history[ $key ]['project_id'] );
	}

	public static function backfill_current_project( $sitepress ) {
		if (
			WPML_Settings_Failsafe_Loader::isUnrecoverable()
			||
			! is_object( $sitepress )
			|| ! method_exists( $sitepress, 'get_settings' )
			|| ! class_exists( 'TranslationProxy_Project' )
		) {
			return;
		}

		$settings = $sitepress->get_settings();
		if (
			! is_array( $settings )
			|| ! isset( $settings['translation_service'] )
			|| ! is_object( $settings['translation_service'] )
		) {
			return;
		}

		$service = $settings['translation_service'];
		$key     = self::service_key( $service );
		if ( ! $key ) {
			return;
		}

		$history = self::get_history();
		if ( null === $history ) {
			self::block( $service, '?', 'backfill' );
			return;
		}

		$has_history      = array_key_exists( $key, $history );
		$known_project_id = $has_history ? self::known_project_id( $history, $key ) : '';
		$projects         = isset( $settings['icl_translation_projects'] )
			? $settings['icl_translation_projects']
			: null;
		if ( ! is_array( $projects ) ) {
			if ( $has_history ) {
				self::block( $service, $known_project_id, 'backfill' );
			}
			return;
		}

		$service_for_index = clone $service;
		$project_index     = TranslationProxy_Project::generate_service_index( $service_for_index );

		if (
			! is_string( $project_index )
			|| '' === $project_index
			|| ! array_key_exists( $project_index, $projects )
		) {
			if ( $has_history ) {
				self::block( $service, $known_project_id, 'backfill' );
			}
			return;
		}

		$project    = $projects[ $project_index ];
		$project_id = is_array( $project ) && isset( $project['id'] )
			? self::normalize_project_id( $project['id'] )
			: '';
		if ( ! $project_id ) {
			if ( $has_history || self::has_partial_project_credentials( $project ) ) {
				self::block( $service, $has_history ? $known_project_id : '?', 'backfill' );
			}
			return;
		}

		$has_access_key = isset( $project['access_key'] ) && self::has_access_key( $project['access_key'] );
		if ( ! $has_history ) {
			if ( ! self::blocked_state_allows_candidate( $key, $project_id, $has_access_key ) ) {
				return;
			}
			if ( self::seed( $service, $project_id ) ) {
				$known_project_id = $project_id;
			} else {
				$history = self::get_history();
				if ( null === $history || ! isset( $history[ $key ]['project_id'] ) ) {
					self::block( $service, '?', 'backfill' );
					return;
				}

				$known_project_id = self::known_project_id( $history, $key );
			}
		}

		if ( $has_access_key && $known_project_id === $project_id ) {
			self::clear_blocked_after_backfill( $service, $known_project_id );
			return;
		}

		self::block( $service, $known_project_id, 'backfill' );
	}

	public static function backfill_after_blog_switch() {
		global $sitepress;

		self::backfill_current_project( $sitepress );
	}

	public static function remember_created_project( $service, $project ) {
		if ( ! is_object( $project ) || ! isset( $project->id ) ) {
			return;
		}

		$access_key = isset( $project->accessKey ) ? $project->accessKey : null;
		if ( ! self::has_access_key( $access_key ) && isset( $project->access_key ) ) {
			$access_key = $project->access_key;
		}
		if ( ! self::has_access_key( $access_key ) ) {
			return;
		}

		$key                 = self::service_key( $service );
		$history             = self::get_history();
		$previous_project_id = is_array( $history ) && $key ? self::known_project_id( $history, $key ) : '';

		if ( ! self::store_project( $service, $project->id, true ) ) {
			return;
		}

		self::clear_blocked(
			$service,
			array_filter(
				array( $previous_project_id, self::normalize_project_id( $project->id ), '?' )
			),
			true,
			true
		);
	}

	public static function service_key( $service ) {
		if ( ! is_object( $service ) || ! isset( $service->id ) ) {
			return '';
		}

		if ( is_int( $service->id ) ) {
			$service_id = (string) $service->id;
		} elseif ( is_string( $service->id ) ) {
			$service_id = trim( $service->id );
		} else {
			return '';
		}

		if ( ! preg_match( '/^\d+$/', $service_id ) ) {
			return '';
		}

		$service_id = ltrim( $service_id, '0' );

		return '' !== $service_id ? $service_id : '';
	}

	public static function ensure_automatic_project_creation_allowed( $service ) {
		if ( WPML_Settings_Failsafe_Loader::isUnrecoverable() ) {
			return false;
		}

		$key     = self::service_key( $service );
		$history = self::get_history();
		if ( ! $key || null === $history ) {
			self::block( $service, '?', 'automatic_project_creation' );
			return false;
		}

		$stored_project = self::get_stored_project( $service );
		$has_history    = array_key_exists( $key, $history );
		if ( ! $has_history ) {
			$stored_project_id = is_array( $stored_project ) && isset( $stored_project['id'] )
				? self::normalize_project_id( $stored_project['id'] )
				: '';
			if ( ! $stored_project_id ) {
				if ( self::has_partial_project_credentials( $stored_project ) ) {
					self::block( $service, '?', 'automatic_project_creation' );
					return false;
				}
				if ( self::has_unresolved_blocked_state( $key ) ) {
					return false;
				}
				return true;
			}
			if (
				! self::blocked_state_allows_candidate(
					$key,
					$stored_project_id,
					self::stored_project_has_credentials( $stored_project, $stored_project_id )
				)
			) {
				return false;
			}

			if ( self::seed( $service, $stored_project_id ) ) {
				$known_project_id = $stored_project_id;
			} else {
				$history = self::get_history();
				if ( null === $history || ! isset( $history[ $key ]['project_id'] ) ) {
					self::block( $service, '?', 'automatic_project_creation' );
					return false;
				}

				$known_project_id = self::known_project_id( $history, $key );
			}
		} else {
			$known_project_id = self::known_project_id( $history, $key );
		}

		if ( self::stored_project_has_credentials( $stored_project, $known_project_id ) ) {
			self::clear_blocked( $service, $known_project_id );

			return ! self::has_unresolved_blocked_state( $key );
		}

		self::block( $service, $known_project_id, 'automatic_project_creation' );

		return false;
	}

	public static function ensure_send_allowed( $service, $project_id, $access_key ) {
		if ( WPML_Settings_Failsafe_Loader::isUnrecoverable() ) {
			return false;
		}

		$key     = self::service_key( $service );
		$history = self::get_history();
		if ( ! $key || null === $history ) {
			self::block( $service, '?', 'send' );
			return false;
		}
		if (
			( null !== $project_id && ! is_int( $project_id ) && ! is_string( $project_id ) )
			|| self::is_malformed_access_key( $access_key )
		) {
			self::block( $service, '?', 'send' );
			return false;
		}

		$project_id  = self::normalize_project_id( $project_id );
		$has_history = array_key_exists( $key, $history );
		if ( ! $has_history ) {
			if ( $project_id ) {
				if ( ! self::blocked_state_allows_candidate( $key, $project_id, self::has_access_key( $access_key ) ) ) {
					return false;
				}
				if ( self::seed( $service, $project_id ) ) {
					$known_project_id = $project_id;
				} else {
					$history = self::get_history();
					if ( null === $history || ! isset( $history[ $key ]['project_id'] ) ) {
						self::block( $service, '?', 'send' );
						return false;
					}

					$known_project_id = self::known_project_id( $history, $key );
				}
			} else {
				$stored_project    = self::get_stored_project( $service );
				$stored_project_id = is_array( $stored_project ) && isset( $stored_project['id'] )
					? self::normalize_project_id( $stored_project['id'] )
					: '';
				if (
					self::has_access_key( $access_key )
					|| self::is_malformed_access_key( $access_key )
					|| $stored_project_id
					|| self::has_partial_project_credentials( $stored_project )
				) {
					self::block( $service, '?', 'send' );
					return false;
				}
				if ( self::has_unresolved_blocked_state( $key ) ) {
					return false;
				}
				return true;
			}
		} else {
			$known_project_id = self::known_project_id( $history, $key );
		}

		if ( $project_id === $known_project_id && self::has_access_key( $access_key ) ) {
			self::clear_blocked( $service, $known_project_id );

			return ! self::has_unresolved_blocked_state( $key );
		}

		self::block( $service, $known_project_id, 'send' );

		return false;
	}

	public static function render_blocked_notice() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}
		$record = self::read_blocked_state_record();
		if ( 'missing' === $record['status'] ) {
			return;
		}
		if ( 'valid' !== $record['status'] ) {
			self::render_unreadable_blocked_notice();
			return;
		}
		$state = $record['state'];
		printf(
			'<div class="notice notice-error"><p><strong>%s</strong> %s</p></div>',
			esc_html__( 'WPML blocked sending content for translation.', 'sitepress' ),
			esc_html(
				sprintf(
					/* translators: %s: the Translation Proxy project id this site used before */
					__( 'The connection details of your translation project are missing or do not match the previously recorded project "%s", most likely because the WPML settings were reset or corrupted. Sending now could create a new, empty project or target the wrong project. Restore a backup of your settings or reconnect to your translation service, then send again.', 'sitepress' ),
					(string) $state['project_id']
				)
			)
		);
	}

	public static function render_unreadable_blocked_notice() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		printf(
			'<div class="notice notice-error"><p><strong>%s</strong> %s</p></div>',
			esc_html__( 'WPML blocked Translation Proxy operations.', 'sitepress' ),
			/* translators: Notice on the translation services screen when the record WPML keeps of the work sent out cannot be read. */
			esc_html__( 'The independent Translation Proxy safety record is unreadable. Its original value was preserved. Reconnect the translation service or contact WPML support before sending content for translation.', 'sitepress' )
		);
	}

	public static function init_admin_notice() {
		if ( ! is_admin() || ! \WPML\UIPage::isWpmlAdminScreen() ) {
			return;
		}

		$record = self::read_blocked_state_record();
		if ( 'unreadable' === $record['status'] ) {
			add_action( 'admin_notices', [ self::class, 'render_unreadable_blocked_notice' ] );
			return;
		}

		if ( 'valid' === $record['status'] ) {
			add_action( 'admin_notices', [ self::class, 'render_blocked_notice' ] );
		}
	}

	public static function clear_blocked( $service, $project_id, $allow_unknown_service = false, $allow_any_project = false ) {
		global $wpdb;

		$service_id  = self::service_key( $service );
		$project_ids = is_array( $project_id ) ? $project_id : array( $project_id );
		$project_ids = array_values(
			array_map(
				'strval',
				array_filter( $project_ids, 'is_scalar' )
			)
		);
		if ( ! $service_id || ! $project_ids ) {
			return;
		}

		if ( ! self::can_compare_and_swap( $wpdb ) ) {
			$record = self::read_blocked_state_record();
			if ( 'valid' !== $record['status'] ) {
				return;
			}

			$remaining = self::remove_matching_blocked_states(
				$record['states'],
				$service_id,
				$project_ids,
				$allow_unknown_service,
				$allow_any_project
			);
			if ( count( $remaining ) === count( $record['states'] ) ) {
				return;
			}
			if ( ! $remaining ) {
				delete_option( self::BLOCKED_OPTION );
			} else {
				update_option( self::BLOCKED_OPTION, self::encode_blocked_states( $remaining ), false );
			}
			return;
		}

		for ( $attempt = 0; $attempt < self::CAS_ATTEMPTS; $attempt++ ) {
			$wpdb->last_error = '';
			$raw              = $wpdb->get_var(
				$wpdb->prepare(
					"SELECT option_value FROM {$wpdb->options} WHERE option_name = %s LIMIT 1",
					self::BLOCKED_OPTION
				)
			);
			if ( $wpdb->last_error || null === $raw ) {
				return;
			}

			try {
				$value = self::unserialize_safety_state( $raw );
			} catch ( Throwable $e ) {
				return;
			}
			$states = self::decode_blocked_states( $value );
			if ( null === $states ) {
				return;
			}

			$remaining = self::remove_matching_blocked_states(
				$states,
				$service_id,
				$project_ids,
				$allow_unknown_service,
				$allow_any_project
			);
			if ( count( $remaining ) === count( $states ) ) {
				return;
			}

			if ( $remaining ) {
				$written = $wpdb->query(
					$wpdb->prepare(
						"UPDATE {$wpdb->options} SET option_value = %s, autoload = 'no' WHERE option_name = %s AND BINARY option_value = BINARY %s",
						serialize( self::encode_blocked_states( $remaining ) ),
						self::BLOCKED_OPTION,
						$raw
					)
				);
			} else {
				$written = $wpdb->query(
					$wpdb->prepare(
						"DELETE FROM {$wpdb->options} WHERE option_name = %s AND BINARY option_value = BINARY %s",
						self::BLOCKED_OPTION,
						$raw
					)
				);
			}

			if ( false === $written ) {
				return;
			}
			self::invalidate_option_cache( self::BLOCKED_OPTION );
			if ( 1 === (int) $written ) {
				return;
			}
		}
	}

	private static function remove_matching_blocked_states( array $states, $service_id, array $project_ids, $allow_unknown_service, $allow_any_project ) {
		$remaining = array();
		foreach ( $states as $state ) {
			if ( ! self::blocked_state_matches( $state, $service_id, $project_ids, $allow_unknown_service, $allow_any_project ) ) {
				$remaining[] = $state;
			}
		}

		return $remaining;
	}

	private static function blocked_state_matches( $state, $service_id, array $project_ids, $allow_unknown_service, $allow_any_project ) {
		return self::is_valid_blocked_state( $state )
			&& ( $service_id === $state['service_id'] || ( $allow_unknown_service && '' === $state['service_id'] ) )
			&& ( $allow_any_project || in_array( (string) $state['project_id'], $project_ids, true ) );
	}

	private static function is_valid_blocked_state( $state ) {
		return is_array( $state )
			&& 4 === count( $state )
			&& isset( $state['project_id'], $state['service_id'], $state['reason'], $state['time'] )
			&& is_string( $state['project_id'] )
			&& '' !== $state['project_id']
			&& is_string( $state['service_id'] )
			&& ( '' === $state['service_id'] || preg_match( '/^[1-9]\d*$/', $state['service_id'] ) )
			&& in_array( $state['reason'], array( 'automatic_project_creation', 'backfill', 'send' ), true )
			&& is_int( $state['time'] );
	}

	private static function decode_blocked_states( $value ) {
		if ( self::is_valid_blocked_state( $value ) ) {
			return array( $value );
		}
		if (
			! is_array( $value )
			|| 2 !== count( $value )
			|| ! array_key_exists( 'version', $value )
			|| ! array_key_exists( 'incidents', $value )
			|| self::BLOCKED_STATE_VERSION !== $value['version']
			|| ! is_array( $value['incidents'] )
			|| ! $value['incidents']
		) {
			return null;
		}

		$states     = array();
		$identities = array();
		$index      = 0;
		foreach ( $value['incidents'] as $incident_index => $state ) {
			if ( $incident_index !== $index || ! self::is_valid_blocked_state( $state ) ) {
				return null;
			}
			$identity = self::blocked_state_identity( $state );
			if ( isset( $identities[ $identity ] ) ) {
				return null;
			}
			$identities[ $identity ] = true;
			$states[]                = $state;
			++$index;
		}

		return $states;
	}

	private static function encode_blocked_states( array $states ) {
		$states = array_values( $states );
		if ( 1 === count( $states ) ) {
			return $states[0];
		}

		return array(
			'version'   => self::BLOCKED_STATE_VERSION,
			'incidents' => $states,
		);
	}

	private static function read_blocked_state_record() {
		global $wpdb;

		if ( ! self::can_compare_and_swap( $wpdb ) ) {
			$missing = new stdClass();
			try {
				$state = get_option( self::BLOCKED_OPTION, $missing );
			} catch ( Throwable $e ) {
				return array(
					'status' => 'unreadable',
					'state'  => null,
					'states' => array(),
				);
			}

			if ( $missing === $state ) {
				return array(
					'status' => 'missing',
					'state'  => null,
					'states' => array(),
				);
			}

			$states = self::decode_blocked_states( $state );

			return null !== $states
				? array(
					'status' => 'valid',
					'state'  => $states[0],
					'states' => $states,
				)
				: array(
					'status' => 'unreadable',
					'state'  => null,
					'states' => array(),
				);
		}

		$wpdb->last_error = '';
		$raw              = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT option_value FROM {$wpdb->options} WHERE option_name = %s LIMIT 1",
				self::BLOCKED_OPTION
			)
		);
		if ( $wpdb->last_error ) {
			return array(
				'status' => 'unreadable',
				'state'  => null,
				'states' => array(),
			);
		}
		if ( null === $raw ) {
			return array(
				'status' => 'missing',
				'state'  => null,
				'states' => array(),
			);
		}

		try {
			$state = self::unserialize_safety_state( $raw );
		} catch ( Throwable $e ) {
			return array(
				'status' => 'unreadable',
				'state'  => null,
				'states' => array(),
			);
		}

		$states = self::decode_blocked_states( $state );

		return null !== $states
			? array(
				'status' => 'valid',
				'state'  => $states[0],
				'states' => $states,
			)
			: array(
				'status' => 'unreadable',
				'state'  => null,
				'states' => array(),
			);
	}

	private static function blocked_state_allows_candidate( $service_id, $project_id, $complete_credentials ) {
		$record = self::read_blocked_state_record();
		if ( 'missing' === $record['status'] ) {
			return true;
		}
		if ( 'valid' !== $record['status'] ) {
			self::surface_unreadable_blocked_state();
			return false;
		}

		$has_relevant_state = false;
		foreach ( $record['states'] as $state ) {
			if ( '' !== $state['service_id'] && $service_id !== $state['service_id'] ) {
				continue;
			}
			$has_relevant_state = true;
			if (
				$service_id !== $state['service_id']
				|| '?' === $state['project_id']
				|| $project_id !== $state['project_id']
				|| ! $complete_credentials
			) {
				add_action( 'admin_notices', [ self::class, 'render_blocked_notice' ] );
				return false;
			}
		}

		if ( $has_relevant_state ) {
			add_action( 'admin_notices', [ self::class, 'render_blocked_notice' ] );
		}

		return true;
	}

	private static function has_unresolved_blocked_state( $service_id ) {
		$record = self::read_blocked_state_record();
		if ( 'missing' === $record['status'] ) {
			return false;
		}
		if ( 'valid' !== $record['status'] ) {
			self::surface_unreadable_blocked_state();
			return true;
		}

		foreach ( $record['states'] as $state ) {
			if ( '' === $state['service_id'] || $service_id === $state['service_id'] ) {
				add_action( 'admin_notices', [ self::class, 'render_blocked_notice' ] );

				return true;
			}
		}

		return false;
	}

	private static function clear_blocked_after_backfill( $service, $project_id ) {
		if ( is_admin() ) {
			self::clear_blocked( $service, $project_id );
		}
	}

	private static function get_history() {
		$history = self::read_history();

		return self::is_valid_history( $history ) ? $history : null;
	}

	private static function read_history() {
		global $wpdb;

		if ( ! self::can_compare_and_swap( $wpdb ) ) {
			try {
				return get_option( self::OPTION, array() );
			} catch ( Throwable $e ) {
				return null;
			}
		}

		$wpdb->last_error = '';
		$raw              = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT option_value FROM {$wpdb->options} WHERE option_name = %s LIMIT 1",
				self::OPTION
			)
		);
		if ( $wpdb->last_error ) {
			return null;
		}

		if ( null === $raw ) {
			return array();
		}

		try {
			return self::unserialize_safety_state( $raw );
		} catch ( Throwable $e ) {
			return null;
		}
	}

	private static function mutate_history( $updater ) {
		global $wpdb;

		if ( ! self::can_compare_and_swap( $wpdb ) ) {
			$history = self::get_history();
			if ( null === $history ) {
				return false;
			}
			$result = call_user_func( $updater, $history );
			if ( empty( $result['accepted'] ) ) {
				return false;
			}
			if ( $result['history'] === $history ) {
				return true;
			}

			return update_option( self::OPTION, $result['history'], false );
		}

		for ( $attempt = 0; $attempt < self::CAS_ATTEMPTS; $attempt++ ) {
			$wpdb->last_error = '';
			$raw              = $wpdb->get_var(
				$wpdb->prepare(
					"SELECT option_value FROM {$wpdb->options} WHERE option_name = %s LIMIT 1",
					self::OPTION
				)
			);
			if ( $wpdb->last_error ) {
				return false;
			}

			$exists = null !== $raw;
			if ( $exists ) {
				try {
					$history = self::unserialize_safety_state( $raw );
				} catch ( Throwable $e ) {
					return false;
				}
			} else {
				$history = array();
			}
			if ( ! self::is_valid_history( $history ) ) {
				return false;
			}

			$result = call_user_func( $updater, $history );
			if ( empty( $result['accepted'] ) ) {
				return false;
			}
			if ( $result['history'] === $history ) {
				return true;
			}

			$serialized = maybe_serialize( $result['history'] );
			if ( $exists ) {
				$written = $wpdb->query(
					$wpdb->prepare(
						"UPDATE {$wpdb->options} SET option_value = %s, autoload = 'no' WHERE option_name = %s AND BINARY option_value = BINARY %s",
						$serialized,
						self::OPTION,
						$raw
					)
				);
			} else {
				$written = $wpdb->query(
					$wpdb->prepare(
						"INSERT IGNORE INTO {$wpdb->options} (option_name, option_value, autoload) VALUES (%s, %s, 'no')",
						self::OPTION,
						$serialized
					)
				);
			}

			if ( false === $written ) {
				return false;
			}
			if ( 1 === (int) $written ) {
				self::invalidate_option_cache( self::OPTION );
				return true;
			}
		}

		return false;
	}

	private static function can_compare_and_swap( $wpdb ) {
		return is_object( $wpdb )
			&& isset( $wpdb->options )
			&& method_exists( $wpdb, 'prepare' )
			&& method_exists( $wpdb, 'get_var' )
			&& method_exists( $wpdb, 'query' );
	}

	private static function unserialize_safety_state( $raw ) {
		if ( ! is_serialized( $raw ) ) {
			return $raw;
		}

		return @unserialize( trim( $raw ), array( 'allowed_classes' => false ) );
	}

	private static function is_valid_history( $history ) {
		if ( ! is_array( $history ) ) {
			return false;
		}

		foreach ( $history as $service_id => $entry ) {
			if (
				! preg_match( '/^[1-9]\d*$/', (string) $service_id )
				|| ! self::is_valid_history_entry( $entry )
			) {
				return false;
			}
		}

		return true;
	}

	private static function is_valid_history_entry( $entry ) {
		if (
			! is_array( $entry )
			|| ! isset( $entry['project_id'] )
			|| ! self::normalize_project_id( $entry['project_id'] )
		) {
			return false;
		}

		if ( 1 === count( $entry ) ) {
			return array_key_exists( 'project_id', $entry );
		}

		return 3 === count( $entry )
			&& array_key_exists( 'service', $entry )
			&& array_key_exists( 'since', $entry )
			&& is_string( $entry['service'] )
			&& is_int( $entry['since'] );
	}

	private static function invalidate_option_cache( $option_name ) {
		if ( ! function_exists( 'wp_cache_delete' ) ) {
			return;
		}

		wp_cache_delete( $option_name, 'options' );
		wp_cache_delete( 'alloptions', 'options' );
		wp_cache_delete( 'notoptions', 'options' );
	}

	private static function known_project_id( array $history, $key ) {
		if ( ! isset( $history[ $key ]['project_id'] ) ) {
			return '?';
		}

		$project_id = self::normalize_project_id( $history[ $key ]['project_id'] );

		return $project_id ? $project_id : '?';
	}

	private static function normalize_project_id( $project_id ) {
		if ( ! is_int( $project_id ) && ! is_string( $project_id ) ) {
			return '';
		}

		$project_id = trim( (string) $project_id );

		return '' !== $project_id && '0' !== $project_id ? $project_id : '';
	}

	private static function has_access_key( $access_key ) {
		return is_string( $access_key ) && '' !== trim( $access_key );
	}

	private static function is_malformed_access_key( $access_key ) {
		return null !== $access_key && ! is_string( $access_key );
	}

	private static function get_stored_project( $service ) {
		global $sitepress;
		if (
			! is_object( $service )
			|| ! is_object( $sitepress )
			|| ! method_exists( $sitepress, 'get_setting' )
			|| ! class_exists( 'TranslationProxy_Project' )
		) {
			return null;
		}

		$projects = $sitepress->get_setting( 'icl_translation_projects' );
		if ( ! is_array( $projects ) ) {
			return null;
		}

		$service_for_index = clone $service;
		$project_index     = TranslationProxy_Project::generate_service_index( $service_for_index );
		if ( ! is_string( $project_index ) || '' === $project_index || ! array_key_exists( $project_index, $projects ) ) {
			return null;
		}

		return $projects[ $project_index ];
	}

	private static function has_partial_project_credentials( $project ) {
		if ( null === $project ) {
			return false;
		}
		if ( ! is_array( $project ) ) {
			return true;
		}
		if ( array() === $project ) {
			return false;
		}

		if ( ! array_key_exists( 'id', $project ) ) {
			return true;
		}

		return array( 'id' ) !== array_keys( $project )
			|| ! is_string( $project['id'] )
			|| '' !== $project['id'];
	}

	private static function stored_project_has_credentials( $project, $expected_project_id ) {
		return is_array( $project )
			&& isset( $project['id'], $project['access_key'] )
			&& self::normalize_project_id( $project['id'] ) === self::normalize_project_id( $expected_project_id )
			&& self::has_access_key( $project['access_key'] );
	}

	private static function block( $service, $known_project_id, $reason ) {
		$service_id = self::service_key( $service );
		$project_id = self::normalize_project_id( $known_project_id );
		$state      = array(
			'project_id' => $project_id ? $project_id : '?',
			'service_id' => $service_id,
			'reason'     => $reason,
			'time'       => time(),
		);
		$status     = self::persist_blocked_state( $state );
		if ( 'unreadable' === $status ) {
			self::surface_unreadable_blocked_state();
			return;
		}

		if ( 'changed' === $status ) {
			error_log(
				'automatic_project_creation' === $reason
					? sprintf(
						'WPML: BLOCKED automatic Translation Proxy project creation. This site previously used TP project "%s" for service "%s"; reconnect the service explicitly before creating another project.',
						$known_project_id,
						$service_id ? $service_id : '?'
					)
					: ( 'backfill' === $reason
						? sprintf(
							'WPML: Current Translation Proxy credentials do not match the independently recorded project identity for service "%s" (previous project "%s"). No send or project creation was attempted; reconnect the service explicitly before continuing.',
							$service_id ? $service_id : '?',
							$known_project_id
						)
						: sprintf(
							'WPML: BLOCKED a Translation Proxy send with missing or mismatched project credentials. This site previously used TP project "%s"; sending now could silently create a new, empty project or target the wrong project. Restore the translation service configuration (or reconnect the service) before sending.',
							$known_project_id
						)
					)
			);

		}

		add_action( 'admin_notices', [ self::class, 'render_blocked_notice' ] );
	}

	private static function persist_blocked_state( array $state ) {
		global $wpdb;

		if ( ! self::can_compare_and_swap( $wpdb ) ) {
			$missing = new stdClass();
			try {
				$existing = get_option( self::BLOCKED_OPTION, $missing );
			} catch ( Throwable $e ) {
				return 'unreadable';
			}
			if ( $missing !== $existing ) {
				$states = self::decode_blocked_states( $existing );
				if ( null === $states ) {
					return 'unreadable';
				}
				if ( self::blocked_states_contain_identity( $states, $state ) ) {
					return 'same';
				}
				$states[] = $state;

				return update_option( self::BLOCKED_OPTION, self::encode_blocked_states( $states ), false )
					? 'changed'
					: 'unreadable';
			}

			return update_option( self::BLOCKED_OPTION, $state, false ) ? 'changed' : 'unreadable';
		}

		for ( $attempt = 0; $attempt < self::CAS_ATTEMPTS; $attempt++ ) {
			$wpdb->last_error = '';
			$raw              = $wpdb->get_var(
				$wpdb->prepare(
					"SELECT option_value FROM {$wpdb->options} WHERE option_name = %s LIMIT 1",
					self::BLOCKED_OPTION
				)
			);
			if ( $wpdb->last_error ) {
				return 'unreadable';
			}

			$exists = null !== $raw;
			if ( $exists ) {
				try {
					$value = self::unserialize_safety_state( $raw );
				} catch ( Throwable $e ) {
					return 'unreadable';
				}
				$states = self::decode_blocked_states( $value );
				if ( null === $states ) {
					return 'unreadable';
				}
				if ( self::blocked_states_contain_identity( $states, $state ) ) {
					return 'same';
				}
				$states[] = $state;
			} else {
				$states = array( $state );
			}

			$serialized = serialize( self::encode_blocked_states( $states ) );
			if ( $exists ) {
				$written = $wpdb->query(
					$wpdb->prepare(
						"UPDATE {$wpdb->options} SET option_value = %s, autoload = 'no' WHERE option_name = %s AND BINARY option_value = BINARY %s",
						$serialized,
						self::BLOCKED_OPTION,
						$raw
					)
				);
			} else {
				$written = $wpdb->query(
					$wpdb->prepare(
						"INSERT IGNORE INTO {$wpdb->options} (option_name, option_value, autoload) VALUES (%s, %s, 'no')",
						self::BLOCKED_OPTION,
						$serialized
					)
				);
			}

			if ( false === $written ) {
				return 'unreadable';
			}
			self::invalidate_option_cache( self::BLOCKED_OPTION );
			if ( 1 === (int) $written ) {
				return 'changed';
			}
		}

		$record = self::read_blocked_state_record();

		return 'valid' === $record['status'] && self::blocked_states_contain_identity( $record['states'], $state )
			? 'same'
			: 'unreadable';
	}

	private static function blocked_states_contain_identity( array $states, array $needle ) {
		$identity = self::blocked_state_identity( $needle );
		foreach ( $states as $state ) {
			if ( self::blocked_state_identity( $state ) === $identity ) {
				return true;
			}
		}

		return false;
	}

	private static function blocked_state_identity( array $state ) {
		return serialize( array( $state['service_id'], $state['project_id'] ) );
	}

	private static function surface_unreadable_blocked_state() {
		error_log( 'WPML: Translation Proxy operations remain blocked because the independent safety record is unreadable. The original value was preserved; reconnect the service or contact WPML support.' );
		add_action( 'admin_notices', [ self::class, 'render_unreadable_blocked_notice' ] );
	}
}
