<?php

class WPML_Editor_UI_Field_Image extends WPML_Editor_UI_Fields {

	private $image_id;
	private $divider;
	private $group;

	function __construct( $id, $image_id, $data, $divider = true ) {

		$this->image_id = $image_id;
		$this->divider  = $divider;
		$this->group    = new WPML_Editor_UI_Field_Group( '', false );

		/* translators: Column heading in the table of translation jobs, and the label of the title field in the translation editor: the title of the piece of content. */
		$this->group->add_field( new WPML_Editor_UI_Single_Line_Field( $id . '-title', __( 'Title', 'sitepress' ), $data, false ) );
		/* translators: Label of the field holding the words shown under an image, in the translation editor. */
		$this->group->add_field( new WPML_Editor_UI_Single_Line_Field( $id . '-caption', __('Caption', 'sitepress' ), $data, false ) );
		/* translators: Label of the field holding the text that stands in for an image when it cannot be seen, in the translation editor. */
		$this->group->add_field( new WPML_Editor_UI_Single_Line_Field( $id . '-alt-text', __('Alt Text', 'sitepress' ), $data, false ) );
		/* translators: Column heading and field label for the longer text that describes something. */
		$this->group->add_field( new WPML_Editor_UI_Single_Line_Field( $id . '-description', __('Description', 'sitepress' ), $data, false ) );

		$this->add_field( $this->group );
	}

	public function get_layout() {
		$image = wp_get_attachment_image_src( $this->image_id, array( 100, 100 ) );
		$data  = array(
			'field_type' => 'wcml-image',
			'divider'    => $this->divider,
			'image_src'  => isset( $image[0] ) ? $image[0] : '',
		);

		$data['fields'] = parent::get_layout();

		return $data;
	}


}

