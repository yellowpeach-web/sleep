<?php

class WPML_PB_Package_Strings_Resave {

	public function from_element( WPML_Post_Element $post_element ) {
		if ( ! $post_element->get_source_element() ) {
			return array();
		}

		$original_post_id = $post_element->get_source_element()->get_id();

		return apply_filters( 'wpml_st_get_post_string_packages', array(), $original_post_id );
	}
}
