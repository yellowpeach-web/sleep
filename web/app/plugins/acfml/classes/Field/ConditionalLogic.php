<?php

namespace ACFML\Field;

use ACFML\Helper\FieldGroup;
use WPML\FP\Fns;

class ConditionalLogic implements \IWPML_Backend_Action, \IWPML_Frontend_Action {

	private const RELATIONAL_POST_TYPES = [ 'post_object', 'page_link', 'relationship' ];
	private const ID_OPERATORS          = [ '==', '!=', '==contains', '!=contains' ];

	public function add_hooks() {
		add_filter( 'acf/load_field', Fns::withoutRecursion( Fns::identity(), [ $this, 'translateConditionalLogic' ] ) );
	}

	public function translateConditionalLogic( $field ) {
		if ( FieldGroup::isScreen() ) {
			return $field;
		}

		if ( empty( $field['conditional_logic'] ) || ! is_array( $field['conditional_logic'] ) ) {
			return $field;
		}

		$field['conditional_logic'] = array_map( [ $this, 'translateRuleGroup' ], $field['conditional_logic'] );

		return $field;
	}

	private function translateRuleGroup( $group ) {
		return array_map( [ $this, 'translateRule' ], $group );
	}

	private function translateRule( $rule ) {
		if ( ! isset( $rule['field'], $rule['value'] ) || ! is_numeric( $rule['value'] ) ) {
			return $rule;
		}

		if ( ! in_array( $rule['operator'] ?? '', self::ID_OPERATORS, true ) ) {
			return $rule;
		}

		$referencedField = acf_get_field( $rule['field'] );
		if ( ! isset( $referencedField['type'] ) ) {
			return $rule;
		}

		$translatedId = $this->translateRelationalId( (int) $rule['value'], $referencedField );
		if ( $translatedId ) {
			$rule['value'] = (string) $translatedId;
		}

		return $rule;
	}

	private function translateRelationalId( $id, array $referencedField ) {
		if ( 'taxonomy' === $referencedField['type'] && ! empty( $referencedField['taxonomy'] ) ) {
			return apply_filters( 'wpml_object_id', $id, $referencedField['taxonomy'], true );
		}

		if ( in_array( $referencedField['type'], self::RELATIONAL_POST_TYPES, true ) ) {
			$postType = get_post_type( $id );

			return $postType ? apply_filters( 'wpml_object_id', $id, $postType, true ) : null;
		}

		return null;
	}
}
