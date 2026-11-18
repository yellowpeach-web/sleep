<?php

namespace WPML\PB\AutoUpdate;

trait PostSignatureLayoutField {

	abstract public function get_meta_field();

	public function add_data_custom_field_to_md5( array $custom_fields_values, $post_id ) {
		if ( Settings::isEnabled() ) {
			unset( $custom_fields_values[ $this->get_meta_field() ] );
		} else {
			$value = get_post_meta( $post_id, $this->get_meta_field(), true );

			$custom_fields_values[ $this->get_meta_field() ] = is_scalar( $value ) ? $value : wp_json_encode( $value );
		}

		return $custom_fields_values;
	}
}
