<?php

class WPML_Translation_Editor_Header {

	private $job_instance;

	public function __construct( $job_instance ) {
		$this->job_instance = $job_instance;
	}

	public function get_model() {
		$type_title        = esc_html( $this->job_instance->get_type_title() );
		$title             = esc_html( $this->job_instance->get_title() );
		$data              = array();
		/* translators: Heading of the translation editor. %1$s: the name of the content type, for example Page, %2$s: the title of the content, in bold. */
		$data['title']     = sprintf( __( '%1$s translation: %2$s', 'sitepress' ), $type_title, '<strong>' . $title . '</strong>' );
		$data['link_url']  = $this->job_instance->get_url( true );
		/* translators: Link in the translation editor that opens the content itself. %s: the name of the content type, for example Page. Verb, imperative. */
		$data['link_text'] = $this->job_instance instanceof WPML_External_Translation_Job ? '' : sprintf( __( 'View %s', 'sitepress' ), $type_title );

		return $data;
	}
}

