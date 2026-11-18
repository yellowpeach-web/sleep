<?php

use ACFML\Helper\MediaFields;
use ACFML\Helper\MediaTranslation;

class WPML_ACF_Attachments implements \IWPML_Backend_Action, \IWPML_Frontend_Action, \IWPML_DIC_Action {

	private static $attachment_fields_copied = array();

	private $media_values = array();

	public function add_hooks() {
		add_filter( 'acf/load_value/type=gallery', array( $this, 'load_translated_attachment' ) );
		add_filter( 'acf/load_value/type=image', array( $this, 'load_translated_attachment' ) );
		add_filter( 'acf/load_value/type=file', array( $this, 'load_translated_attachment' ) );
		add_action( 'wpml_after_update_attachment_texts', array( $this, 'copy_attachment_fields_to_translation' ), 10, 2 );
		add_filter( 'wpml_ids_of_media_used_in_post', array( $this, 'add_media_field_ids' ), 10, 2 );

		if ( MediaTranslation::isEnabled() ) {
			add_filter( 'wpml_custom_field_values_for_post_signature', array( $this, 'addMediaFieldValuesToSignature' ), 10, 2 );
			add_action( 'added_post_meta', array( $this, 'forget_media_values_of_meta' ), 10, 2 );
			add_action( 'updated_post_meta', array( $this, 'forget_media_values_of_meta' ), 10, 2 );
			add_action( 'deleted_post_meta', array( $this, 'forget_media_values_of_meta' ), 10, 2 );
			add_action( 'acf/save_post', array( $this, 'forget_media_values' ), 0 );
		}
	}

	public function load_translated_attachment( $attachments ) {

		$safeConvert = function ( $maybeAttachmentId ) {
			return is_int( $maybeAttachmentId ) || ( is_string( $maybeAttachmentId ) && $maybeAttachmentId === ( (string) (int) $maybeAttachmentId ) )
				? apply_filters( 'wpml_object_id', $maybeAttachmentId, 'attachment', true )
				: $maybeAttachmentId;
		};

		$attachments = \WPML\Utils\Unserialize::maybe( $attachments );

		$translatedAttachments = [];
		if ( is_array( $attachments ) ) {
			$isArrayList = array_keys( $attachments ) === range( 0, count( $attachments ) - 1 );

			foreach ( $attachments as $key => $value ) {
				if ( $isArrayList || in_array( $key, [ 'id', 'ID' ] ) ) {
					$value = $safeConvert( $value );
				}
				$translatedAttachments[ $key ] = $value;
			}
		} else {
			$translatedAttachments = $safeConvert( $attachments );
		}

		return $translatedAttachments;
	}

	public function copy_attachment_fields_to_translation( $original_id, $translation ) {
		if ( function_exists( 'get_fields' )
			&& function_exists( 'acf_get_field' )

			&& ! isset( self::$attachment_fields_copied[ $original_id ][ $translation->element_id ] ) ) {
			$acf_fields = get_fields( $original_id, false );
			if ( is_array( $acf_fields ) ) {
				foreach ( $acf_fields as $acf_field_name => $acf_field_value ) {
					$acf_field = acf_get_field( $acf_field_name );
					if ( isset( $acf_field['wpml_cf_preferences'] ) ) {
						$fieldKey = $acf_field['key'] ?? $acf_field_name;
						switch ( $acf_field['wpml_cf_preferences'] ) {
							case ( WPML_COPY_CUSTOM_FIELD ):
								update_field( $fieldKey, $acf_field_value, $translation->element_id );
								break;
							case ( WPML_COPY_ONCE_CUSTOM_FIELD ):
								$translated_post_meta = get_post_meta( $translation->element_id, $acf_field_name, true );
								if ( ! $translated_post_meta ) {
									update_field( $fieldKey, $acf_field_value, $translation->element_id );
								}
								break;
						}
					}
				}
			}
			self::$attachment_fields_copied[ $original_id ][ $translation->element_id ] = 1;
		}
	}

	public function add_media_field_ids( $media_ids, $post_id ) {
		return array_values( array_unique( array_merge( $media_ids, MediaFields::getAttachmentIds( $post_id ) ) ) );
	}

	public function addMediaFieldValuesToSignature( $custom_fields, $post_id ) {
		$id = (int) $post_id;
		if ( ! isset( $this->media_values[ $id ] ) ) {
			$this->media_values[ $id ] = MediaFields::getValues( $post_id );
		}

		foreach ( $this->media_values[ $id ] as $meta_key => $raw_value ) {
			if ( ! array_key_exists( $meta_key, $custom_fields ) ) {
				$custom_fields[ $meta_key ] = $raw_value;
			}
		}

		return $custom_fields;
	}

	public function forget_media_values_of_meta( $meta_id, $object_id = 0 ) {
		if ( $object_id ) {
			$this->forget_media_values( $object_id );
		} else {
			$this->media_values = array();
		}
	}

	public function forget_media_values( $post_id ) {
		unset( $this->media_values[ (int) $post_id ] );
	}
}
