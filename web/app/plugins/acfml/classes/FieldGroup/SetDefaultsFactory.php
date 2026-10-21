<?php

namespace ACFML\FieldGroup;

use ACFML\Options\ValueRegistration;
use ACFML\Strings\BackFill;
use ACFML\Strings\Factory;
use ACFML\Strings\SetupBackFillHooks;
use ACFML\Strings\Translator;
use function WPML\Container\make;

class SetDefaultsFactory implements \IWPML_REST_Action_Loader {

	public function create() {
		return [
			new SetSameFieldsModeAsDefault(),
			new SetFieldPreferencesAsDefault( new FieldNamePatterns(), wpml_load_core_tm() ),
			self::createSetupBackFillHooks(),
		];
	}

	private static function createSetupBackFillHooks() {
		$factory = new Factory();

		return new SetupBackFillHooks(
			new BackFill( new Translator( $factory ), make( ValueRegistration::class ) )
		);
	}
}
