<?php

namespace WPML\MediaTranslation;

use WPML\Media\Option;
use WPML\MediaTranslation\MediaField;

class MediaTranslationEditorLayout implements \IWPML_Action {
	
	private $media_field;
	
	public function __construct() {
		$this->media_field = new MediaField();
	}
	
	public function add_hooks() {
		if ( Option::getTranslateMediaLibraryTexts() || Option::shouldHandleMediaAuto() ) {
			add_filter( 'wpml_tm_job_layout', [ $this, 'group_media_fields' ] );
			add_filter( 'wpml_tm_adjust_translation_fields', [ $this, 'set_custom_labels' ] );
		}
	}

	public function group_media_fields( $fields ) {

		$media_fields = [];

		foreach ( $fields as $k => $field ) {
			$media_field = $this->is_media_field( $field );
			if ( $media_field ) {
				unset( $fields[ $k ] );
				$media_fields[ $media_field['attachment_id'] ][] = $field;
			}
		}

		if ( $media_fields ) {

			$media_section_field = [
				'field_type'    => 'tm-section',
				/* translators: Heading of the section that groups the media fields (title, caption, description, alt text) in the translation editor. */
				'title'         => __( 'Media', 'sitepress' ),
				'fields'        => [],
				'empty'         => false,
				'empty_message' => '',
				'sub_title'     => '',
			];

			foreach ( $media_fields as $attachment_id => $media_field ) {

				$media_group_field = [
					'title'      => '',
					'divider'    => false,
					'field_type' => 'tm-group',
					'fields'     => $media_field,
				];

				$image       = wp_get_attachment_image_src( (int) $attachment_id, [ 100, 100 ] );
				$image_field = [
					'field_type' => 'wcml-image',
					'divider'    => $media_field !== end( $media_fields ),
					'image_src'  => $image[0],
					'fields'     => [ $media_group_field ],
				];

				$media_section_field['fields'][] = $image_field;

			}

			$fields[] = $media_section_field;
		}

		return array_values( $fields );
	}

	private function is_media_field( $field ) {
		$result = $this->media_field->extractAttachmentIdAndMediaFields( $field );
		
		if ( $result ) {
			if ( $result['media_field'] ) {
				$result['media_field'] = $this->media_field->getFieldId( $result['media_field'] );
			}

			return [
				'attachment_id' => $result['attachment_id'],
				'label'         => $result['media_field'],
			];
		}

		return [];
	}

	public function set_custom_labels( $fields ) {

		foreach ( $fields as $k => $field ) {
			$media_field = $this->is_media_field( $field['field_type'] );
			if ( $media_field ) {
				$fields[ $k ]['title'] = $this->get_field_label( $media_field );
			}
		}

		return $fields;
	}

	private function get_field_label( $media_field ) {

		switch ( $media_field['label'] ) {
			case 'title':
				/* translators: Column heading in the table of translation jobs, and the label of the title field in the translation editor: the title of the piece of content. */
				$label = __( 'Title', 'sitepress' );
				break;
			case 'caption':
				/* translators: Label of the field holding the words shown under an image, in the translation editor. */
				$label = __( 'Caption', 'sitepress' );
				break;
			case 'description':
				/* translators: Column heading and field label for the longer text that describes something. */
				$label = __( 'Description', 'sitepress' );
				break;
			case 'alt_text':
				/* translators: Label of the field holding the text that stands in for an image when it cannot be seen, in the translation editor. */
				$label = __( 'Alt Text', 'sitepress' );
				break;
			default:
				$label = $media_field['label'];
		}

		return $label;
	}
}
