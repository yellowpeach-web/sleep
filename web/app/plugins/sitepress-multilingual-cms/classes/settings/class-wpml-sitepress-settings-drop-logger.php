<?php

class WPML_SitePress_Settings_Drop_Logger implements IWPML_Action {

	const SETTINGS_OPTION      = 'icl_sitepress_settings';
	const START_VERSION_OPTION = 'wpml_start_version';
	const LOG_PREFIX           = '[WPML][sitepress-settings-drop-candidate]';
	const TRACE_LIMIT          = 25;

	private $wp_api;

	private $wpdb;

	private $logged_for_blog = array();

	private $intentional_reset_for_blog = array();

	public function __construct( WPML_WP_API $wp_api, wpdb $wpdb ) {
		$this->wp_api = $wp_api;
		$this->wpdb   = $wpdb;
	}

	public function add_hooks() {
		add_filter(
			'pre_update_option_' . self::SETTINGS_OPTION,
			array( $this, 'inspect_update' ),
			PHP_INT_MAX,
			3
		);
		add_action(
			'add_option_' . self::SETTINGS_OPTION,
			array( $this, 'inspect_add' ),
			PHP_INT_MAX,
			2
		);
		add_action(
			'delete_option_' . self::SETTINGS_OPTION,
			array( $this, 'inspect_delete' ),
			PHP_INT_MAX,
			1
		);
		add_action(
			\WPML\Troubleshooting\ResetService::SETTINGS_RESET_STARTED,
			array( $this, 'start_intentional_reset' ),
			10,
			1
		);
		add_action(
			\WPML\Troubleshooting\ResetService::SETTINGS_RESET_FINISHED,
			array( $this, 'finish_intentional_reset' ),
			10,
			1
		);
	}

	public function start_intentional_reset( $blog_id ) {
		$this->intentional_reset_for_blog[ (int) $blog_id ] = true;
	}

	public function finish_intentional_reset( $blog_id ) {
		unset( $this->intentional_reset_for_blog[ (int) $blog_id ] );
	}

	public function inspect_update( $new_value, $old_value, $option_name ) {
		if ( $this->is_setup_complete( $new_value ) ) {
			return $new_value;
		}

		$blog_id = (int) $this->wp_api->get_current_blog_id();

		if ( isset( $this->logged_for_blog[ $blog_id ] ) || ! $this->was_previously_configured( $old_value ) ) {
			return $new_value;
		}

		$this->logged_for_blog[ $blog_id ] = true;
		$this->log_candidate( $option_name, $blog_id, $old_value, $new_value );

		return $new_value;
	}

	public function inspect_add( $option_name, $value ) {
		if ( $this->is_setup_complete( $value ) ) {
			return;
		}

		$blog_id = (int) $this->wp_api->get_current_blog_id();

		if ( isset( $this->logged_for_blog[ $blog_id ] ) || ! $this->has_configured_state_in_database() ) {
			return;
		}

		$this->logged_for_blog[ $blog_id ] = true;
		$this->log_candidate( $option_name, $blog_id, null, $value );
	}

	public function inspect_delete( $option_name ) {
		$blog_id = (int) $this->wp_api->get_current_blog_id();

		if (
			isset( $this->logged_for_blog[ $blog_id ] )
			|| isset( $this->intentional_reset_for_blog[ $blog_id ] )
		) {
			return;
		}

		$this->logged_for_blog[ $blog_id ] = true;
		$this->log_candidate( $option_name, $blog_id, null, null, 'settings option was deleted' );
	}

	private function was_previously_configured( $old_value ) {
		return $this->is_setup_complete( $old_value )
			|| $this->has_configured_state_in_database();
	}

	private function has_configured_state_in_database() {
		try {
			$raw_settings = $this->get_raw_option_value_from_database( self::SETTINGS_OPTION );
			if ( is_string( $raw_settings ) && is_serialized( $raw_settings ) ) {
				$settings = @unserialize( trim( $raw_settings ), array( 'allowed_classes' => false ) );
			} else {
				$settings = $raw_settings;
			}
			if ( $this->is_setup_complete( $settings ) ) {
				return true;
			}

			$start_version = $this->get_raw_option_value_from_database( self::START_VERSION_OPTION );

			return null !== $start_version && '' !== $start_version;
		} catch ( Throwable $e ) {
			return false;
		}
	}

	private function get_raw_option_value_from_database( $option_name ) {
		$wpdb = $this->wpdb;

		return $wpdb->get_var(
			$wpdb->prepare(
				"SELECT option_value FROM {$wpdb->options} WHERE option_name = %s LIMIT 1",
				$option_name
			)
		);
	}

	private function is_setup_complete( $settings ) {
		return is_array( $settings ) && ! empty( $settings['setup_complete'] );
	}

	private function log_candidate( $option_name, $blog_id, $old_value, $new_value, $reason = null ) {
		$event = array(
			'reason'             => $reason ? $reason : $this->get_reason( $new_value ),
			'option'             => $option_name,
			'context'            => $this->get_request_context(),
			'blog_id'            => $blog_id,
			'user_id'            => (int) $this->wp_api->get_current_user_id(),
			'old_setup_complete' => $this->get_setup_state( $old_value ),
			'new_setup_complete' => $this->get_setup_state( $new_value ),
			'old_key_count'      => is_array( $old_value ) ? count( $old_value ) : null,
			'new_key_count'      => is_array( $new_value ) ? count( $new_value ) : null,
			'trace'              => $this->get_trace(),
		);

		$encoded_event = json_encode( $event, JSON_UNESCAPED_SLASHES | JSON_PARTIAL_OUTPUT_ON_ERROR );
		if ( ! is_string( $encoded_event ) ) {
			$encoded_event = '{"reason":"log encoding failed"}';
		}

		$this->wp_api->error_log( self::LOG_PREFIX . ' ' . $encoded_event );
	}

	private function get_reason( $settings ) {
		if ( ! is_array( $settings ) ) {
			return 'settings value is not an array';
		}

		if ( ! array_key_exists( 'setup_complete', $settings ) ) {
			return 'setup_complete is missing';
		}

		return 'setup_complete is false';
	}

	private function get_setup_state( $settings ) {
		if ( ! is_array( $settings ) ) {
			return 'not-array';
		}

		if ( ! array_key_exists( 'setup_complete', $settings ) ) {
			return 'missing';
		}

		return empty( $settings['setup_complete'] ) ? 'false' : 'true';
	}

	private function get_request_context() {
		if ( defined( 'WP_CLI' ) && WP_CLI ) {
			return 'cli';
		}

		if ( defined( 'DOING_CRON' ) && DOING_CRON ) {
			return 'cron';
		}

		if ( defined( 'REST_REQUEST' ) && REST_REQUEST ) {
			return 'rest';
		}

		if ( defined( 'DOING_AJAX' ) && DOING_AJAX ) {
			return 'ajax';
		}

		return $this->wp_api->is_admin() ? 'admin' : 'front-end';
	}

	private function get_trace() {
		$trace = array();

		foreach ( $this->wp_api->get_backtrace( self::TRACE_LIMIT, false, true ) as $frame ) {
			if ( isset( $frame['class'] ) && __CLASS__ === $frame['class'] ) {
				continue;
			}

			$file = isset( $frame['file'] ) ? $this->normalize_file( $frame['file'] ) : '?';
			$line = isset( $frame['line'] ) ? (int) $frame['line'] : 0;
			$call = ( isset( $frame['class'] ) ? $frame['class'] : '' )
				. ( isset( $frame['type'] ) ? $frame['type'] : '' )
				. ( isset( $frame['function'] ) ? $frame['function'] : '' );

			$trace[] = sprintf( '%s:%d %s()', $file, $line, $call );
		}

		return $trace;
	}

	private function normalize_file( $file ) {
		if ( ! is_string( $file ) ) {
			return '?';
		}

		$file = str_replace( '\\', '/', $file );

		if ( defined( 'ABSPATH' ) ) {
			$root = rtrim( str_replace( '\\', '/', ABSPATH ), '/' ) . '/';
			if ( 0 === strpos( $file, $root ) ) {
				return substr( $file, strlen( $root ) );
			}
		}

		if ( '/' === substr( $file, 0, 1 ) || preg_match( '/^[A-Za-z]:\//', $file ) ) {
			$file = '.../' . basename( $file );
		}

		return $file;
	}
}
