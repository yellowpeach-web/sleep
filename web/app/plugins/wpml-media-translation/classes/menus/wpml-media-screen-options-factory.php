<?php

class WPML_Media_Screen_Options_Factory implements IWPML_Backend_Action_Loader {

	public function create() {

		$options = array();

		if ( $this->is_translation_dashboard() ) {

			$option_name = 'wpml_media_translation_dashboard_items_per_page';
			$options[]   = array(
				'key'  => 'per_page',
				'args' => array(
					'label'   => 'Number of items per page:',
					'default' => get_option( $option_name, 20 ),
					'option'  => $option_name,
				),
			);

		}

		if ( $options ) {
			return new WPML_Media_Screen_Options( $options );
		}

		return null;

	}

	private function is_translation_dashboard() {
		return ! isset( $_GET['sm'] ) || 'media-translation' === $_GET['sm'];
	}

}
