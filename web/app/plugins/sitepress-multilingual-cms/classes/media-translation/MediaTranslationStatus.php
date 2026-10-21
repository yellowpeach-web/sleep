<?php

namespace WPML\MediaTranslation;

use SitePress;
use WPML_Element_Translation_Package;
use WPML_Post_Element;
use WPML\MediaTranslation\MediaField;
use WPML\Media\AddAttachmentHook;

class MediaTranslationStatus implements \IWPML_Action {

	private $sitepress;
	
	private $media_field;

	public function __construct( SitePress $sitepress ) {
		$this->sitepress = $sitepress;
		$this->media_field = new MediaField();
	}

	public function add_hooks() {
		add_action( 'wpml_pro_translation_completed', array( $this, 'save_bundled_media_translation' ), 10, 3 );
	}

	public function save_bundled_media_translation( $new_post_id, $fields, $job ) {

		$media_translations  = $this->get_media_translations( $job );
		$translation_package = new WPML_Element_Translation_Package();

		foreach ( $media_translations as $attachment_id => $translation_data ) {
			$this->save_attachment_translation(
				$attachment_id,
				$translation_data,
				$translation_package,
				$job->language_code
			);
		}

	}

	private function get_media_translations( $job ) {
		$media = array();

		foreach ( $job->elements as $element ) {
			$result = $this->media_field->extractAttachmentIdAndMediaFields( $element->field_type );
			if ( $result ) {
				$attachment_id = $result['attachment_id'];
				$media_field = $result['media_field'];
				$media[ $attachment_id ][ $media_field ] = $element;
			}
		}

		return $media;
	}

	private function save_attachment_translation( $attachment_id, $translation_data, $translation_package, $language ) {
		$postarr             = [];
		$alt_text            = null;
		$media_custom_fields = [];

		$post_element              = new WPML_Post_Element( $attachment_id, $this->sitepress );
		$attachment_translation    = $post_element->get_translation( $language );
		$attachment_translation_id = null !== $attachment_translation ? $attachment_translation->get_id() : false;

		$baselines = [];

		foreach ( $translation_data as $field => $data ) {

			$translated_value = $translation_package->decode_field_data(
				$data->field_data_translated,
				$data->field_format
			);

			if (
				isset( $data->field_translate ) && ! (int) $data->field_translate
				&& $this->hasTranslatedValue( $attachment_translation_id, $field )
			) {
				continue;
			}

			if ( ! empty( $data->field_translate ) ) {
				$baselines[ $field ] = $translation_package->decode_field_data(
					$data->field_data,
					$data->field_format
				);
			}

			switch ( $field ) {
				case 'title':
					$wp_post_field             = 'post_title';
					$postarr[ $wp_post_field ] = $translated_value;
					break;
				case 'caption':
					$wp_post_field             = 'post_excerpt';
					$postarr[ $wp_post_field ] = $translated_value;
					break;
				case 'description':
					$wp_post_field             = 'post_content';
					$postarr[ $wp_post_field ] = $translated_value;
					break;
				case 'alt_text':
					$alt_text = $translated_value;
					break;
				default:
					$media_custom_fields[ $field ] = $translated_value;
					$field                         = $this->media_field->getFieldId( $field );
					if ( $attachment_translation_id ) {
						delete_post_meta( $attachment_translation_id, $field );
					}
			}
		}

		if ( $attachment_translation_id ) {
			if ( $postarr ) {
				$postarr['ID'] = $attachment_translation_id;
				wp_update_post( $postarr );
			}
		} else {
			$postarr['post_type']      = 'attachment';
			$postarr['post_status']    = 'inherit';
			$postarr['guid']           = get_post_field( 'guid', $attachment_id );
			$postarr['post_mime_type'] = get_post_field( 'post_mime_type', $attachment_id );

			$attachment_translation_id = AddAttachmentHook::suspendDuring(
				function () use ( $postarr ) {
					return wp_insert_post( $postarr );
				}
			);

			$this->sitepress->set_element_language_details( $attachment_translation_id, 'post_attachment', $post_element->get_trid(), $language );

			$this->copy_attached_file_info_from_original( $attachment_translation_id, $attachment_id );

		}

		if ( null !== $alt_text ) {
			update_post_meta( $attachment_translation_id, '_wp_attachment_image_alt', $alt_text );
		}

		foreach ( $media_custom_fields as $field => $value ) {
			$field = $this->media_field->getFieldId( $field );
			add_post_meta( $attachment_translation_id, $field, $value );
		}

		if ( $attachment_translation_id ) {
			foreach ( $baselines as $baseline_field => $source ) {
				MediaSourceBaseline::record( $attachment_id, $baseline_field, $language, $source );
			}
		}

		return $attachment_translation_id;
	}

	private function hasTranslatedValue( $attachment_translation_id, $field ) {
		if ( ! $attachment_translation_id ) {
			return false;
		}

		switch ( $field ) {
			case 'title':
				$value = get_post_field( 'post_title', $attachment_translation_id );
				break;
			case 'caption':
				$value = get_post_field( 'post_excerpt', $attachment_translation_id );
				break;
			case 'description':
				$value = get_post_field( 'post_content', $attachment_translation_id );
				break;
			case 'alt_text':
				$value = get_post_meta( $attachment_translation_id, '_wp_attachment_image_alt', true );
				break;
			default:
				$value = get_post_meta( $attachment_translation_id, $this->media_field->getFieldId( $field ), true );
				break;
		}

		return '' !== trim( (string) $value );
	}

	private function copy_attached_file_info_from_original( $attachment_id, $original_attachment_id ) {
		$meta_keys = array(
			'_wp_attachment_metadata',
			'_wp_attached_file',
			'_wp_attachment_backup_sizes',
		);
		foreach ( $meta_keys as $meta_key ) {
			update_post_meta(
				$attachment_id,
				$meta_key,
				get_post_meta( $original_attachment_id, $meta_key, true )
			);
		}
	}
}
