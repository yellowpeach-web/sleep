<?php

class WPML_Action_Filter_Loader {

	private $defered_actions = array();

	private $ajax_action_validation;

	private $guarded_factories = array();

	private $declared_types;

	private static $declared_types_file;

	public function __construct( $declared_types = null ) {
		$this->declared_types = is_array( $declared_types ) ? $declared_types : null;
	}

	public function load( $loaders ) {

		foreach ( $loaders as $loader ) {
			$declared = $this->declared_types_of( $loader );
			if ( null === $declared ) {
				$loader_type = new WPML\Action\Type( $loader );
				$is          = array( $loader_type, 'is' );
			} else {
				$is = function ( $type ) use ( $declared ) {
					return in_array( $type, $declared, true );
				};
			}

			$backend  = $is( 'backend' );
			$frontend = $is( 'frontend' );
			$ajax     = $is( 'ajax' );
			$rest     = $is( 'rest' );
			$cli      = $is( 'cli' );
			$dic      = $is( 'dic' );


			if ( $backend && $frontend ) {
				$this->load_factory_or_action( $loader, $dic );
			} elseif ( $backend && is_admin() ) {
				$this->load_factory_or_action( $loader, $dic );
			} elseif ( $frontend && ! is_admin() ) {
				$this->load_factory_or_action( $loader, $dic );
			} elseif ( $ajax && wpml_is_ajax() ) {
				$this->load_factory_or_action( $loader, $dic );
			} elseif ( $rest ) {
				$this->load_guarded( $loader, $dic );
			} elseif ( $cli && wpml_is_cli() ) {
				$this->load_factory_or_action( $loader, $dic );
			}
		}
	}

	private function load_factory_or_action( $loader, $use_dic ) {
		$action_or_factory = $this->instantiate( $loader, $use_dic );

		if ( $action_or_factory instanceof IWPML_Action ) {
			$action_or_factory->add_hooks();
		} elseif ( $action_or_factory instanceof IWPML_Action_Loader_Factory ) {
			$this->load_factory( $action_or_factory );
		}
	}

	private function load_guarded( $loader, $use_dic ) {
		if ( WPML_Lifecycle_Hook_Guard::is_allowed_loader( $loader ) ) {
			$this->load_factory_or_action( $loader, $use_dic );

			return;
		}

		$snapshot          = WPML_Lifecycle_Hook_Guard::snapshot();
		$action_or_factory = $this->instantiate( $loader, $use_dic );

		if ( $action_or_factory instanceof IWPML_Action ) {
			$action_or_factory->add_hooks();
		} elseif ( $action_or_factory instanceof IWPML_Action_Loader_Factory ) {
			if ( $action_or_factory instanceof IWPML_Deferred_Action_Loader ) {
				$this->guarded_factories[ spl_object_hash( $action_or_factory ) ] = $loader;
			}
			$this->load_factory( $action_or_factory );
		}

		WPML_Lifecycle_Hook_Guard::detach_added_since( $snapshot, $loader );
	}

	private function declared_types_of( $loader ) {
		if ( null === $this->declared_types ) {
			if ( null === self::$declared_types_file ) {
				$map                       = include __DIR__ . '/action-types.php';
				self::$declared_types_file = is_array( $map ) ? $map : array();
			}
			$this->declared_types = self::$declared_types_file;
		}

		$key = ltrim( $loader, '\\' );

		return isset( $this->declared_types[ $key ] ) ? $this->declared_types[ $key ] : null;
	}

	private function instantiate( $loader, $use_dic ) {
		if ( ! class_exists( $loader ) ) {
			error_log( sprintf( 'WPML: action class "%s" could not be loaded and was skipped.', $loader ) );

			return null;
		}

		if ( $use_dic ) {
			return WPML\Container\make( $loader );
		}

		return new $loader();
	}

	private function load_factory( IWPML_Action_Loader_Factory $factory ) {
		if ( $factory instanceof WPML_AJAX_Base_Factory ) {
			$factory->set_ajax_action_validation( $this->get_ajax_action_validation() );
		}

		if ( $factory instanceof IWPML_Deferred_Action_Loader ) {
			$this->add_deferred_action( $factory );
		} else {
			$this->run_factory( $factory );
		}
	}

	private function add_deferred_action( IWPML_Deferred_Action_Loader $factory ) {
		$action = $factory->get_load_action();
		if ( ! isset( $this->defered_actions[ $action ] ) ) {
			$this->defered_actions[ $action ] = array();
			add_action( $action, array( $this, 'deferred_loader' ) );
		}
		$this->defered_actions[ $action ][] = $factory;
	}

	public function deferred_loader() {
		$action = current_action();
		foreach ( $this->defered_actions[ $action ] as $factory ) {
			$guarded_by = isset( $this->guarded_factories[ spl_object_hash( $factory ) ] ) ? $this->guarded_factories[ spl_object_hash( $factory ) ] : null;
			if ( null === $guarded_by ) {
				$this->run_factory( $factory );
				continue;
			}

			$snapshot = WPML_Lifecycle_Hook_Guard::snapshot();
			$this->run_factory( $factory );
			WPML_Lifecycle_Hook_Guard::detach_added_since( $snapshot, $guarded_by );
		}
	}

	private function get_ajax_action_validation() {
		if ( ! $this->ajax_action_validation ) {
			$this->ajax_action_validation = new WPML_AJAX_Action_Validation();
		}

		return $this->ajax_action_validation;
	}

	private function run_factory( IWPML_Action_Loader_Factory $factory ) {
		$load_handlers = $factory->create();

		if ( $load_handlers ) {
			if ( ! is_array( $load_handlers ) || is_callable( $load_handlers ) ) {
				$load_handlers = array( $load_handlers );
			}
			foreach ( $load_handlers as $load_handler ) {
				if ( is_callable( $load_handler ) ) {
					$load_handler();
				} else {
					$load_handler->add_hooks();
				}
			}
		}
	}
}
