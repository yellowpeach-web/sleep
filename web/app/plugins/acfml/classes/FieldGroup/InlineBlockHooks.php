<?php

namespace ACFML\FieldGroup;

use ACFML\Helper\Fields;
use ACFML\Tools\Local;
use WPML\FP\Fns;
use WPML\FP\Obj;

class InlineBlockHooks implements \IWPML_Backend_Action, \IWPML_Frontend_Action, \IWPML_DIC_Action {

	const AFTER_ACF_BLOCK_NAMESPACE_PRIORITY = 20;
	const BEFORE_EXPERT_MODE_STAMP_PRIORITY  = Local::ENSURE_TRANSLATION_MODE_PRIORITY - 1;

	private $declaredModes = [];

	private $storedModes;

	public function __construct( InlineBlockGroupModes $storedModes ) {
		$this->storedModes = $storedModes;
	}

	public function add_hooks() {
		add_filter( 'block_type_metadata', [ $this, 'recordDeclaredMode' ], self::AFTER_ACF_BLOCK_NAMESPACE_PRIORITY );
		add_filter( 'acf/prepare_field_group_for_import', [ $this, 'applyMode' ], self::BEFORE_EXPERT_MODE_STAMP_PRIORITY );
		add_action( 'wp_loaded', [ $this->storedModes, 'startStoringAfterUpgrade' ] );
	}

	public function recordDeclaredMode( $metadata ) {
		$name = Obj::prop( 'name', $metadata );
		$mode = Obj::path( [ 'acf', Mode::KEY ], $metadata );

		if ( is_string( $name ) && in_array( $mode, Mode::ALL, true ) ) {
			$this->declaredModes[ $name ] = $mode;
		}

		return $metadata;
	}

	public function applyMode( array $fieldGroup ): array {
		if ( ! self::isInlineBlockGroup( $fieldGroup ) ) {
			return $fieldGroup;
		}

		$mode                    = $this->getDeclaredMode( self::getLocationBlockName( $fieldGroup ) ) ?? $this->storedModes->getOrStore( $fieldGroup['key'] );
		$fieldGroup[ Mode::KEY ] = $mode;

		if ( Mode::ADVANCED !== $mode ) {
			$fieldGroup['fields'] = Fields::iterate( (array) Obj::prop( 'fields', $fieldGroup ), $this->stampModePreference( $mode ), Fns::identity() );
		}

		return $fieldGroup;
	}

	public static function isInlineBlockGroup( array $fieldGroup ): bool {
		$blockName = self::getLocationBlockName( $fieldGroup );

		return null !== $blockName
			&& Obj::prop( 'key', $fieldGroup ) === 'group_' . sanitize_key( str_replace( [ '/', '-' ], '_', $blockName ) );
	}

	public static function isPostMetaInlineBlockGroup( array $fieldGroup ): bool {
		return self::isInlineBlockGroup( $fieldGroup )
			&& function_exists( 'acf_block_uses_post_meta' )
			&& acf_block_uses_post_meta( [ 'name' => self::getLocationBlockName( $fieldGroup ) ] );
	}

	private static function getLocationBlockName( array $fieldGroup ): ?string {
		$blockName = Obj::path( [ 'location', 0, 0, 'value' ], $fieldGroup );
		$isBlock   = 'block' === Obj::path( [ 'location', 0, 0, 'param' ], $fieldGroup );

		return $isBlock && is_string( $blockName ) ? $blockName : null;
	}

	private function getDeclaredMode( string $blockName ): ?string {
		$mode = $this->declaredModes[ $blockName ] ?? Obj::prop( Mode::KEY, (array) acf_get_block_type( $blockName ) );

		return in_array( $mode, Mode::ALL, true ) ? $mode : null;
	}

	private function stampModePreference( string $mode ): callable {
		$getModePreference = ModeDefaults::get( $mode );

		return function ( $field ) use ( $getModePreference ) {
			if ( isset( $field['wpml_cf_preferences'] ) || ModeValidity::storesNoValue( $field ) ) {
				return $field;
			}

			$field['wpml_cf_preferences'] = $getModePreference( $field );

			return $field;
		};
	}
}
