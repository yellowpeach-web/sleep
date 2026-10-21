<?php

namespace WPML\Troubleshooting\Actions;

use IWPML_Backend_Action;
use WPML\API\Sanitize;

class Dispatcher implements IWPML_Backend_Action {

	const ACTIONS = array(
		'cache_clear' => CacheClear::class,
	);

	const CAPABILITIES = array(
		'cache_clear' => 'wpml_manage_support',
	);

	const CAPABILITY_FILTER = 'wpml_support_debug_action_capabilities';

	const DEFAULT_CAPABILITY = 'manage_options';

	public static function capability_for( $action ) {
		$declared = apply_filters( self::CAPABILITY_FILTER, self::CAPABILITIES );
		$declared = array_merge( is_array( $declared ) ? $declared : array(), self::CAPABILITIES );

		$capability = isset( $declared[ $action ] ) ? $declared[ $action ] : null;

		return is_string( $capability ) && '' !== $capability ? $capability : self::DEFAULT_CAPABILITY;
	}

	const FILTER = 'wpml_support_debug_actions';

	public static function actions() {
		$actions = apply_filters( self::FILTER, self::ACTIONS );

		return array_merge( is_array( $actions ) ? $actions : array(), self::ACTIONS );
	}

	public function add_hooks() {
		add_action( 'admin_init', array( $this, 'maybe_dispatch' ), 11 );
	}

	public static function requested( $key ) {
		$value = Sanitize::stringProp( $key, $_GET );
		if ( ! $value ) {
			$value = Sanitize::stringProp( $key, $_POST );
		}
		return (string) $value;
	}

	public function maybe_dispatch() {
		$action = self::requested( 'debug_action' );
		$nonce  = self::requested( 'nonce' );

		if ( ! $action ) {
			return;
		}

		if ( ! $nonce ) {
			wp_send_json_error( array( 'reason' => 'missing_nonce' ), 400 );

			return;
		}

		if ( ! current_user_can( self::DEFAULT_CAPABILITY ) && ! current_user_can( self::capability_for( $action ) ) ) {
			wp_send_json_error( array( 'reason' => 'forbidden' ), 403 );

			return;
		}

		$actions = self::actions();
		if ( ! isset( $actions[ $action ] ) ) {
			wp_send_json_error( array( 'reason' => 'unknown_action' ), 400 );

			return;
		}

		if ( \WPML\Setup\Initializer::rejectSettingsMutationAjax() ) {
			return;
		}

		if ( ! wp_verify_nonce( $nonce, $action ) ) {
			wp_send_json_error( array( 'reason' => 'bad_nonce' ), 403 );

			return;
		}

		if ( ob_get_level() ) {
			ob_end_clean();
		}

		$class    = $actions[ $action ];
		$instance = new $class();

		ob_start();
		$result = $instance->run();
		$output = (string) ob_get_clean();

		if ( false === $result ) {
			wp_send_json_error( array( 'reason' => 'action_failed', 'action' => $action ), 500 );

			return;
		}

		self::answer( $action, $output );
	}

	public static function answer( $action, $output ) {
		$output = trim( $output );

		if ( '' !== $output ) {
			$decoded = json_decode( $output, true );
			if ( JSON_ERROR_NONE === json_last_error() && null !== $decoded ) {
				wp_send_json( $decoded );
				return;
			}

			wp_send_json_error( array( 'reason' => 'action_output', 'message' => wp_strip_all_tags( $output ) ), 500 );
			return;
		}

		wp_send_json_success( array( 'action' => $action ) );
	}
}
