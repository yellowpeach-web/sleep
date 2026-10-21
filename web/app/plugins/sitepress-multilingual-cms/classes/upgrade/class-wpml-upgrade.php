<?php

use WPML\Upgrade\CommandsStatus;

class WPML_Upgrade {
	const SCOPE_ADMIN     = 'admin';
	const SCOPE_AJAX      = 'ajax';
	const SCOPE_FRONT_END = 'front-end';
	const SCOPE_CLI       = 'cli';

	private $commands;
	const UPDATE_STATUSES_KEY = 'wpml_update_statuses';

	private $sitepress;

	private $command_factory;

	private $command_status;

	private $upgrade_in_progress;

	private $front_end_scope_id;

	private $current_scope;

	public function __construct(
		array $commands,
		SitePress $sitepress,
		WPML_Upgrade_Command_Factory $command_factory,
		?CommandsStatus $command_status = null,
		$front_end_scope_id = null
	) {
		$this->add_commands( $commands );
		$this->sitepress          = $sitepress;
		$this->command_factory    = $command_factory;
		$this->command_status     = $command_status ?: new CommandsStatus();
		$this->front_end_scope_id = $front_end_scope_id;
	}

	public function add_commands( array $commands ) {
		foreach ( $commands as $command ) {
			if ( $command instanceof WPML_Upgrade_Command_Definition ) {
				$this->commands[] = $command;
			}
		}
	}

	public function run() {
		$result = false;

		$new_commands = apply_filters( 'wpml_upgrade_commands', array() );
		if ( $new_commands && is_array( $new_commands ) ) {
			$this->add_commands( $new_commands );
		}

		$this->command_status->beginBatch();
		try {
			if ( defined( 'WP_CLI' ) && WP_CLI ) {
				$result = $this->run_cli();
			} elseif ( $this->sitepress->get_wp_api()->is_admin() ) {
				if ( $this->sitepress->get_wp_api()->is_ajax() ) {
					$result = $this->run_ajax();
				} else {
					$result = $this->run_admin();
				}
			} elseif ( $this->sitepress->get_wp_api()->is_front_end() ) {
				$result = $this->run_front_end();
			}
		} finally {
			$this->command_status->endBatch();
		}

		$this->set_upgrade_completed();

		return $result;
	}

	private function get_commands_by_scope( $scope ) {
		$results = array();
		foreach ( $this->commands as $command ) {
			if ( in_array( $scope, $command->get_scopes(), true ) ) {
				$results[] = $command;
			}
		}

		return $results;
	}

	private function get_admin_commands() {
		return $this->get_commands_by_scope( self::SCOPE_ADMIN );
	}

	private function get_ajax_commands() {
		return $this->get_commands_by_scope( self::SCOPE_AJAX );
	}

	private function get_front_end_commands() {
		return $this->get_commands_by_scope( self::SCOPE_FRONT_END );
	}

	private function get_cli_commands() {
		return $this->get_commands_by_scope( self::SCOPE_CLI );
	}

	private function run_admin() {
		return $this->run_commands( $this->get_admin_commands(), 'maybe_run_admin', self::SCOPE_ADMIN );
	}

	private function run_ajax() {
		return $this->run_commands( $this->get_ajax_commands(), 'maybe_run_ajax', self::SCOPE_AJAX );
	}

	private function run_front_end() {
		$commands = $this->get_front_end_commands();
		$result   = $this->run_commands( $commands, 'maybe_run_front_end', self::SCOPE_FRONT_END );

		$this->maybe_settle_front_end_scope( $commands );

		return $result;
	}

	private function maybe_settle_front_end_scope( array $commands ) {
		if ( null === $this->front_end_scope_id ) {
			return;
		}

		foreach ( $commands as $command ) {
			if ( ! $this->is_settled_in_scope( $command, self::SCOPE_FRONT_END ) ) {
				return;
			}
		}

		$this->command_status->markFrontEndScopeSettled( ICL_SITEPRESS_VERSION, $this->front_end_scope_id );
	}

	private function run_cli() {
		return $this->run_commands( $this->get_cli_commands(), 'maybe_run_front_end', self::SCOPE_CLI );
	}

	private function run_commands( $commands, $default, $scope ) {
		$results = array();
		foreach ( $commands as $command ) {
			$results[] = $this->run_command( $command, $default, $scope );
		}

		return $results;
	}

	private function run_command( WPML_Upgrade_Command_Definition $command_definition, $default, $scope ) {
		$method = $default;
		if ( $command_definition->get_method() ) {
			$method = $command_definition->get_method();
		}

		if ( $this->is_settled_in_scope( $command_definition, $scope ) ) {
			return null;
		}

		if ( 'maybe_run_ajax' === $method && ! $this->nonce_ok( $command_definition->get_class_name() ) ) {
			return null;
		}

		$this->set_upgrade_in_progress();
		$upgrade = $command_definition->create();

		$this->current_scope = $scope;

		return $this->$method( $upgrade );
	}

	private function is_settled_in_scope( WPML_Upgrade_Command_Definition $command_definition, $scope ) {
		$class_name = $command_definition->get_class_name();

		return $this->command_status->hasBeenExecuted( $class_name )
			|| $this->command_status->hasNoWorkInScope( $class_name, $scope, ICL_SITEPRESS_VERSION );
	}

	private function record_outcome( IWPML_Upgrade_Command $upgrade, $outcome ) {
		if ( $outcome ) {
			$this->mark_command_as_executed( $upgrade );
		} elseif ( null === $outcome && null !== $this->current_scope ) {
			$this->command_status->markNoWorkInScope( get_class( $upgrade ), $this->current_scope, ICL_SITEPRESS_VERSION );
		}
	}

	private function maybe_run_admin( IWPML_Upgrade_Command $upgrade ) {
		if ( $upgrade->run_admin() ) {
			$this->mark_command_as_executed( $upgrade );
		}

		return $upgrade->get_results();
	}

	private function maybe_run_front_end( IWPML_Upgrade_Command $upgrade ) {
		$this->record_outcome( $upgrade, $upgrade->run_frontend() );

		return $upgrade->get_results();
	}

	private function maybe_run_ajax( IWPML_Upgrade_Command $upgrade ) {
		$outcome = $upgrade->run_ajax();
		$this->record_outcome( $upgrade, $outcome );
		if ( $outcome ) {
			$this->command_status->endBatch();
			$this->set_upgrade_completed();
			$this->sitepress->get_wp_api()->wp_send_json_success( '' );
		}

		return $upgrade->get_results();
	}

	private function nonce_ok( $class_name ) {
		$class_name = ltrim( (string) $class_name, '\\' );
		if ( '' === $class_name ) {
			return false;
		}

		$class_name = $this->get_command_id( $class_name );
		if ( isset( $_POST['action'] ) && $_POST['action'] === $class_name ) {
			$nonce = filter_input( INPUT_POST, 'nonce', FILTER_SANITIZE_FULL_SPECIAL_CHARS );
			if ( $this->sitepress->get_wp_api()->wp_verify_nonce( $nonce, $class_name . '-nonce' ) ) {
				return true;
			}
		}

		return false;
	}

	public function mark_command_as_executed( IWPML_Upgrade_Command $class ) {
		$this->command_status->markAsExecuted( get_class( $class ) );
	}

	private function get_command_id( $class_name ) {
		return str_replace( '_', '-', strtolower( $class_name ) );
	}

	private function set_upgrade_in_progress() {
		if ( ! $this->upgrade_in_progress ) {
			$this->upgrade_in_progress = true;
			set_transient( WPML_Upgrade_Loader::TRANSIENT_UPGRADE_IN_PROGRESS, true, MINUTE_IN_SECONDS );
		}
	}

	private function set_upgrade_completed() {
		if ( $this->upgrade_in_progress ) {
			$this->upgrade_in_progress = false;
			delete_transient( WPML_Upgrade_Loader::TRANSIENT_UPGRADE_IN_PROGRESS );
		}
	}
}

