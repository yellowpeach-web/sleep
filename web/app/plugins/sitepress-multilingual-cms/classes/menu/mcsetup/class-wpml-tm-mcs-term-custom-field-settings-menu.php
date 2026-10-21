<?php

class WPML_TM_MCS_Term_Custom_Field_Settings_Menu extends WPML_TM_MCS_Custom_Field_Settings_Menu {

	protected function get_meta_type() {
		return WPML_Custom_Field_Setting_Query_Factory::TYPE_TERMMETA;
	}

	protected function get_setting( $key ) {
		return $this->settings_factory->term_meta_setting( $key );
	}

	protected function get_title() {
		return __( 'Custom Term Meta Translation', 'sitepress' );
	}

	protected function kind_shorthand() {
		return 'tcf';
	}

	public function get_no_data_message() {
		/* translators: Shown in place of the list of extra fields of a term when there are none. "It is possible" is about those fields. */
		return __( 'No term meta found. It is possible that they will only show up here after you add/create them.', 'sitepress' );
	}

	public function get_column_header( $id ) {
		$header = $id;
		if ( 'name' === $id ) {
			/* translators: Heading above the list of the extra fields a term can carry. */
			$header = __( 'Term Meta', 'sitepress' );
		}
		return $header;
	}

}
