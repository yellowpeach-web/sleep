<?php

class WPML_Post_Types extends WPML_SP_User {

	public function get_translatable() {
		return $this->sitepress->get_translatable_documents( true );
	}

	public function get_readonly() {
		$wp_post_types = $this->sitepress->get_wp_api()->get_wp_post_types_global();

		$types = array();
		$readonly_config = wpml_get_tm_sub_setting( 'custom-types_readonly_config', array() );
		if ( is_array( $readonly_config ) ) {
			foreach ( array_keys( $readonly_config ) as $cp ) {
				if ( isset( $wp_post_types[ $cp ] ) ) {
					$types[ $cp ] = $wp_post_types[ $cp ];
				}
			}
		}
		return $types;
	}

	public function get_translatable_and_readonly() {
		return $this->get_translatable() + $this->get_readonly();
	}

}
