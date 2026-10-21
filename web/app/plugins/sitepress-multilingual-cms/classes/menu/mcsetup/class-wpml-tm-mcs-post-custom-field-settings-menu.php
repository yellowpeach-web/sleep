<?php

class WPML_TM_MCS_Post_Custom_Field_Settings_Menu extends WPML_TM_MCS_Custom_Field_Settings_Menu {

	protected function get_meta_type() {
		return WPML_Custom_Field_Setting_Query_Factory::TYPE_POSTMETA;
	}

	protected function get_setting( $key ) {
		return $this->settings_factory->post_meta_setting( $key );
	}

	protected function get_title() {
		return __( 'Custom Fields Translation', 'sitepress' );
	}

	protected function kind_shorthand() {
		return 'cf';
	}

	public function get_no_data_message() {
		/* translators: Shown in place of the list of extra fields of a post when there are none. "It is possible" is about those fields. */
		return __( 'No custom fields found. It is possible that they will only show up here after you add more posts after installing a new plugin.', 'sitepress' );
	}

	public function get_column_header( $id ) {
		$header = $id;
		if('name' === $id) {
			/* translators: Heading above the list of extra fields of a post, in the translation settings. */
			$header = __( 'Custom fields', 'sitepress' );
		}
		return $header;
	}
}