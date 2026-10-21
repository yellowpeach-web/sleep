<?php

use ACFML\FieldState;

class WPML_ACF_Custom_Fields_Sync implements \IWPML_Backend_Action {

	private $field_state;

	public function __construct( FieldState $field_state ) {
		$this->field_state = $field_state;
	}

	public function add_hooks() {
		add_filter( 'acf/update_value', [ $this, 'clean_empty_values_for_copy_once_field' ], 10, 3 );
	}

	public function clean_empty_values_for_copy_once_field( $value, $post_id, $field ) {
		if ( '' === $value
			&& ! $this->wasStoredBefore( $field )
			&& isset( $field['wpml_cf_preferences'] )
			&& WPML_COPY_ONCE_CUSTOM_FIELD === $field['wpml_cf_preferences']
			&& ! $this->isFieldType( $field, 'group' )
			&& ! $this->isFieldType( $field, 'clone' )
		) {
			$value = null;
		}
		return $value;
	}

	private function wasStoredBefore( array $field ): bool {
		$stateBefore = (array) $this->field_state->getStateBefore();
		return isset( $field['name'] ) && array_key_exists( $field['name'], $stateBefore );
	}

	private function isFieldType( $field, $type ) {
		return isset( $field['type'] ) && $type === $field['type'];
	}
}
