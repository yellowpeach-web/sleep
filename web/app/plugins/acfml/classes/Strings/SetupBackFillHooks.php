<?php

namespace ACFML\Strings;

use ACFML\FieldGroup\SetFieldPreferencesAsDefault;

class SetupBackFillHooks implements \IWPML_REST_Action {

	const PRIORITY_AFTER_PREFERENCES = SetFieldPreferencesAsDefault::PRIORITY_AFTER_GROUP_MODE + 1;

	private $backFill;

	public function __construct( BackFill $backFill ) {
		$this->backFill = $backFill;
	}

	public function add_hooks() {
		add_action( 'wpml_setup_completed', [ $this, 'run' ], self::PRIORITY_AFTER_PREFERENCES );
		add_action( 'wpml_set_translate_everything', [ $this, 'onTeaToggled' ], self::PRIORITY_AFTER_PREFERENCES );
	}

	public function onTeaToggled( $enabled ) {
		if ( $enabled ) {
			$this->run();
		}
	}

	public function run() {
		if ( ! function_exists( 'acf_get_field_groups' ) || ! HooksFactory::isStActivated() ) {
			return;
		}

		$this->backFill->registerMissing();
		BackFill::markDone();
		$this->backFill->copyOnce();
	}
}
