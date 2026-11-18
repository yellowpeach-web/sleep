<?php

namespace ACFML\FieldPreferences;

use ACFML\FieldGroup\Mode;
use ACFML\FieldGroup\ModeDefaults;
use ACFML\FieldGroup\ModeValidity;
use ACFML\Helper\Fields;
use WPML\FP\Fns;

class BeforeSetupPreferences implements \IWPML_Backend_Action, \IWPML_REST_Action {

	private $rules;

	public function add_hooks() {
		if ( $this->isWpmlSetupComplete() || ! function_exists( 'acf_get_field_groups' ) || ! function_exists( 'acf_get_fields' ) ) {
			return;
		}

		add_filter( 'wpml_resolve_custom_field_preferences', [ $this, 'resolve' ], 10, 3 );
	}

	public function resolve( $resolved, $metaKeys, $type = 'post' ) {
		if ( ! is_array( $resolved ) || ! is_array( $metaKeys ) ) {
			return $resolved;
		}
		$type = 'term' === $type ? 'term' : 'post';

		foreach ( $metaKeys as $metaKey ) {
			if ( ! is_string( $metaKey ) || '' === $metaKey || isset( $resolved[ $metaKey ] ) ) {
				continue;
			}

			foreach ( $this->getRules() as $pattern => $rule ) {
				if ( in_array( $type, $rule['types'], true ) && preg_match( '#^' . $pattern . '$#', $metaKey ) ) {
					$resolved[ $metaKey ] = $rule['preference'];
					break;
				}
			}
		}

		return $resolved;
	}

	private function getRules() {
		if ( null !== $this->rules ) {
			return $this->rules;
		}

		$rules = [];

		foreach ( (array) acf_get_field_groups() as $fieldGroup ) {
			$mode  = Mode::getMode( $fieldGroup );
			$mode  = $mode ?: Mode::TRANSLATION;
			$types = SubfieldRules::elementTypesForGroup( $fieldGroup );

			$collect = function ( $field, $fieldPattern ) use ( &$rules, $mode, $types ) {
				if ( ModeValidity::storesNoValue( $field ) ) {
					return $field;
				}

				if ( isset( $field['wpml_cf_preferences'] ) && is_numeric( $field['wpml_cf_preferences'] ) ) {
					$preference = (int) $field['wpml_cf_preferences'];
				} elseif ( Mode::ADVANCED === $mode ) {
					return $field;
				} else {
					$preference = (int) ModeDefaults::get( $mode, $field );
				}

				$rules[ $fieldPattern ] = [
					'preference' => $preference,
					'types'      => $types,
				];

				return $field;
			};

			Fields::iterate( (array) acf_get_fields( $fieldGroup ), $collect, Fns::identity() );
		}

		$this->rules = $rules;

		return $rules;
	}

	private function isWpmlSetupComplete() {
		return (bool) apply_filters( 'wpml_setting', false, 'setup_complete' );
	}
}
