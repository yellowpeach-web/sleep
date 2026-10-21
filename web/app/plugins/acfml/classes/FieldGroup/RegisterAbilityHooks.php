<?php

namespace ACFML\FieldGroup;

use ACFML\Tools\Local;
use WPML\FP\Obj;

class RegisterAbilityHooks implements \IWPML_Backend_Action, \IWPML_Frontend_Action {

	public const ABILITY = 'acf/register-field-group';

	private const MODES = [ Mode::TRANSLATION, Mode::LOCALIZATION ];

	public const PRIORITY_BEFORE_DEFAULT_MODE = Local::ENSURE_TRANSLATION_MODE_PRIORITY - 1;

	private $requestedMode;

	public function add_hooks() {
		add_filter( 'wp_register_ability_args', [ $this, 'addFieldGroupModeInput' ], 10, 2 );
	}

	public function addFieldGroupModeInput( array $args, string $abilityName ): array {
		if ( self::ABILITY !== $abilityName || ! is_callable( Obj::prop( 'execute_callback', $args ) ) ) {
			return $args;
		}

		$args['input_schema']['properties'][ Mode::KEY ] = [
			'type'        => 'string',
			'description' => 'How WPML translates the fields of this group: "translation" for the same content in every language, translated; "localization" for different content in each language',
			'enum'        => self::MODES,
		];

		$args['execute_callback'] = $this->withRequestedMode( $args['execute_callback'] );

		return $args;
	}

	public function setRequestedMode( array $fieldGroup ): array {
		return Obj::assoc( Mode::KEY, $this->requestedMode, $fieldGroup );
	}

	private function withRequestedMode( callable $createFieldGroup ): callable {
		return function ( $input = [] ) use ( $createFieldGroup ) {
			$mode = Obj::prop( Mode::KEY, (array) $input );

			if ( ! in_array( $mode, self::MODES, true ) ) {
				return $createFieldGroup( $input );
			}

			$this->requestedMode = $mode;
			add_filter( 'acf/prepare_field_group_for_import', [ $this, 'setRequestedMode' ], self::PRIORITY_BEFORE_DEFAULT_MODE );

			try {
				return $createFieldGroup( $input );
			} finally {
				remove_filter( 'acf/prepare_field_group_for_import', [ $this, 'setRequestedMode' ], self::PRIORITY_BEFORE_DEFAULT_MODE );
				$this->requestedMode = null;
			}
		};
	}
}
