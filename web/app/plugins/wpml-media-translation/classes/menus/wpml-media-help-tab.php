<?php

class WPML_Media_Help_Tab implements IWPML_Action {

	public function add_hooks() {

		add_action( 'admin_head', array( $this, 'add' ) );
	}

	public function add() {
		$current_screen = get_current_screen();

		if ( $this->is_media_related_screen( $current_screen ) ) {
			$media_translation_dashboard = esc_html__( 'WPML &raquo; Media Translation', 'wpml-media' );
			$wpml_translation_dashboard  = esc_html__( 'WPML &raquo; Translation Management', 'wpml-media' );
			$current_screen->add_help_tab(
				array(
					'id'      => 'wpml-media-translation',
					/* translators: Title of the help tab on the Media Translation page. */
					'title'   => esc_html__( 'Translating Media', 'wpml-media' ),
					'content' =>
						 '<p>' . esc_html__( 'There are two ways for you to translate Media:', 'wpml-media' ) . '</p>' .
						 '<ul>' .
						'<li>' . sprintf(
							/* translators: Line in the help tab on the Media Translation page. %s: the name of the WPML page that holds the media dashboard. */
							esc_html__( 'Use the dashboard on the %s page to translate your images and other media files.', 'wpml-media' ),
							$media_translation_dashboard
						) . '</li>' .
						'<li>' . sprintf(
							/* translators: Line in the help tab on the Media Translation page. %s: the name of the WPML Translation Management page. */
							esc_html__( 'Use the dashboard on the %s page to send pages that contain media, for translation.', 'wpml-media' ),
							$wpml_translation_dashboard
						) . '</li>' .
						 '</ul>' .
						 '<a href="' . esc_url( $this->get_documentation_url() ) . '">' . wpml_bold_names( __( 'Learn more about WPML Media Translation', 'wpml-media' ) ) . '</a>',
				)
			);
		}

	}

	private function get_documentation_url() {
		$url = 'https://wpml.org/documentation/translating-your-contents/media/';

		if ( class_exists( '\WPML\OutboundLinks\OutboundLinks' ) ) {
			return \WPML\OutboundLinks\OutboundLinks::to(
				$url,
				array( 'medium' => 'settings', 'campaign' => 'media-translation' )
			);
		}

		return $url;
	}

	private function is_media_related_screen( $current_screen ) {
		$accepted_bases = array(
			'wpml_page_wpml-media',
			'upload',
			'media',
			'wpml_page_wpml-translation-management/menu/main',
		);

		return $current_screen && in_array( $current_screen->base, $accepted_bases );
	}

}
