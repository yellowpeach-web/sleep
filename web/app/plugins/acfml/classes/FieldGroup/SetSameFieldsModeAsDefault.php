<?php

namespace ACFML\FieldGroup;

use WPML\FP\Obj;
use WPML\FP\Relation;
use function WPML\FP\pipe;

class SetSameFieldsModeAsDefault implements \IWPML_REST_Action {

	const PRIORITY_HOOKS = 10;

	private $listenersLoaded = false;

	public function add_hooks() {
		add_action( 'wpml_setup_completed', [ $this, 'onSetupCompleted' ], self::PRIORITY_HOOKS );
		add_action( 'wpml_set_translate_everything', [ $this, 'onTeaToggled' ], self::PRIORITY_HOOKS );
	}

	public function onSetupCompleted() {
		$this->run();
	}

	public function onTeaToggled( $enabled ) {
		if ( $enabled ) {
			$this->run();
		}
	}

	public function run() {
		$this->ensureFieldGroupSaveListenersLoaded();

		$isWritable                = function ( $group ) {
			return ! SetupInventory::isJsonFileNewer( (array) $group );
		};
		$hasNoMode                 = function ( $group ) {
			return ! Mode::isConfigured( $group );
		};
		$setTranslationModeOnGroup = pipe( Obj::assoc( Mode::KEY, Mode::TRANSLATION ), 'acf_update_field_group' );

		$targets = wpml_collect( acf_get_field_groups() )
			->reject( Relation::propEq( 'ID', 0 ) )
			->filter( $hasNoMode )
			->filter( $isWritable );

		$targets->each( $setTranslationModeOnGroup );

		return $targets->count();
	}

	protected function ensureFieldGroupSaveListenersLoaded() {
		if ( $this->listenersLoaded ) {
			return;
		}
		$this->listenersLoaded = true;

		foreach ( ( new HooksFactory() )->create() as $action ) {
			$action->add_hooks();
		}
		\WPML\Container\make( \WPML_ACF_Field_Settings::class )->add_hooks();
		$this->loadSaveHooksBeforeSetupIsComplete();
	}

	private function loadSaveHooksBeforeSetupIsComplete() {
		if ( ! \WPML_ACF::isWpmlSetupComplete() ) {
			\WPML\Container\make( SaveHooks::class )->add_hooks();
		}
	}
}
