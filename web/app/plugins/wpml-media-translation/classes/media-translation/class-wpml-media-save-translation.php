<?php

use WPML\MediaTranslation\MediaField;

class WPML_Media_Save_Translation implements IWPML_Action {

	private $sitepress;
	private $wpdb;
	private $media_file_factory;
	private $post_data;
	private $translation_element_factory;

	private $media_field;

	public function __construct(
		SitePress $sitepress,
		wpdb $wpdb,
		WPML_Media_File_Factory $media_file_factory,
		WPML_Translation_Element_Factory $translation_element_factory,
		?MediaField $media_field = null
	) {
		$this->sitepress                   = $sitepress;
		$this->wpdb                        = $wpdb;
		$this->media_file_factory          = $media_file_factory;
		$this->translation_element_factory = $translation_element_factory;
		$this->media_field                 = null !== $media_field ? $media_field : new MediaField();
	}

	public function add_hooks() {
		\WPML\Request\Adapter\Ajax::register( 'wpml_media_save_translation', \WPML\Request\Policy\Policy::capability( [ 'upload_files', 'edit_posts' ], \WPML\Request\Policy\Authenticity::actionNonce( 'media-translation', 'wpnonce' ) ), array( $this, 'save_media_translation' ) );
	}

	public function save_media_translation() {

		if ( wp_verify_nonce( $_POST['wpnonce'], 'media-translation' ) ) {

			$post_array['ID']       = (int) $_POST['translated-attachment-id'];
			$original_attachment_id = (int) $_POST['original-attachment-id'];
			$translated_language    = sanitize_text_field( $_POST['translated-language'] );

			if ( ! current_user_can( 'edit_post', (int) $original_attachment_id ) ) {
				wp_send_json_error( array( 'error' => __( 'You are not allowed to edit this item.', 'wpml-media' ) ) );
				return;
			}

			if ( (int) $post_array['ID'] ) {
				$target_id = (int) $post_array['ID'];

				if ( 'attachment' !== get_post_type( $target_id ) || ! current_user_can( 'edit_post', $target_id ) ) {
					wp_send_json_error( array( 'error' => __( 'You are not allowed to edit this item.', 'wpml-media' ) ) );
					return;
				}

				$original_trid = $this->sitepress->get_element_trid( (int) $original_attachment_id, 'post_attachment' );
				$target_trid   = $this->sitepress->get_element_trid( $target_id, 'post_attachment' );
				if ( ! $original_trid || (int) $original_trid !== (int) $target_trid ) {
					wp_send_json_error( array( 'error' => __( 'You are not allowed to edit this item.', 'wpml-media' ) ) );
					return;
				}
			}

			if ( isset( $_POST['translation']['title'] ) ) {
				$post_array['post_title'] = $_POST['translation']['title'];
			}
			if ( isset( $_POST['translation']['caption'] ) ) {
				$post_array['post_excerpt'] = $_POST['translation']['caption'];
			}
			if ( isset( $_POST['translation']['description'] ) ) {
				$post_array['post_content'] = $_POST['translation']['description'];
			}

			if ( $this->should_restore_media() ) {
				$post_array['post_mime_type'] = get_post_field( 'post_mime_type', $original_attachment_id );
			}

			if ( $post_array['ID'] ) {
				$attachment_id = wp_update_post( $post_array );

				if ( $this->should_restore_media() ) {
					$this->restore_media_file( $attachment_id, $original_attachment_id );
				}
			} else {

				$post_array['post_type']      = 'attachment';
				$post_array['post_status']    = 'inherit';
				$post_array['guid']           = get_post_field( 'guid', $original_attachment_id );
				$post_array['post_mime_type'] = get_post_field( 'post_mime_type', $original_attachment_id );

				$attachment_id = $this->create_attachment_translation( $original_attachment_id, $post_array );

				$this->sitepress->set_element_language_details(
					$attachment_id,
					'post_attachment',
					$this->get_post_trid_value(),
					$this->get_post_lang_value()
				);

				( new WPML_WP_Cache( WPML_ELEMENT_TRANSLATIONS_CACHE_GROUP ) )->flush_group_cache();

				if ( ! $this->has_media_upload() ) {
					$this->copy_attached_file_info_from_original( $attachment_id, $original_attachment_id );
				}

			}

			if ( $this->has_media_upload() ) {
				$this->update_media_file( $attachment_id, $original_attachment_id, $translated_language );
			}

			if ( isset( $_POST['translation']['alt-text'] ) ) {
				update_post_meta(
					$attachment_id,
					'_wp_attachment_image_alt',
					sanitize_text_field( $_POST['translation']['alt-text'] )
				);
			}

			if ( ! empty( $_POST['translation']['custom-field'] )
				&& is_array( $_POST['translation']['custom-field'] )
			) {
				$translation_fields = $_POST['translation']['custom-field'];
				foreach ( $translation_fields as $meta_key => $meta_value ) {
					$meta_key = $this->media_field->getFieldId( $meta_key );
					delete_post_meta( $attachment_id, $meta_key );
				}

				foreach ( $translation_fields as $meta_key => $meta_value ) {
					$meta_key = $this->media_field->getFieldId( $meta_key );
					if ( strpos( $meta_value, "\n" ) !== false || strpos( $meta_value, "\r\n" ) !== false ) {
						$sanitized = sanitize_textarea_field( $meta_value );
					} else {
						$sanitized = sanitize_text_field( $meta_value );
					}

					add_post_meta(
						$attachment_id,
						$meta_key,
						$sanitized
					);
				}
			}

			if ( 0 === strpos( get_post_field( 'post_mime_type', $original_attachment_id ), 'image/' ) ) {
				$translated_thumb         = wp_get_attachment_thumb_url( $attachment_id );
				$original_thumb           = wp_get_attachment_thumb_url( $original_attachment_id );
				$media_file_is_translated = $translated_thumb !== $original_thumb;
			} else {
				$media_file_is_translated = get_attached_file( $attachment_id ) !== get_attached_file( $original_attachment_id );
				$translated_thumb         = wp_mime_type_icon( $original_attachment_id );
			}

			$media_usage = get_post_meta( $original_attachment_id, WPML_Media_Usage::FIELD_NAME, true );
			$posts_list  = array();
			if ( isset( $media_usage['posts'] ) ) {
				foreach ( $media_usage['posts'] as $post_id ) {
					$post_element     = $this->translation_element_factory->create( $post_id, 'post' );
					$post_translation = $post_element->get_translation( $translated_language );
					if ( $post_translation ) {
						$posts_list[] = array(
							'url'   => get_edit_post_link( $post_translation->get_id() ),
							'title' => get_post_field( 'post_title', $post_translation->get_id() ),
						);
					}
				}
			}

			if ( isset( $media_usage['posts'] ) && $this->should_restore_media() ) {
				do_action( 'wpml_restored_media_file_translation', $attachment_id, $media_usage['posts'] );
			}

			$response = array(
				'attachment_id' => $attachment_id,
				'thumb'         => $media_file_is_translated ? $translated_thumb : false,
				'status'        => $media_file_is_translated
					? WPML_Media_Translation_Status::TRANSLATED
					: WPML_Media_Translation_Status::NEEDS_MEDIA_TRANSLATION,
				'usage'         => $posts_list,
			);

			wp_send_json_success( $response );
		} else {
			/* translators: Error message on the Media Translation page when the security check of a request fails. */
			wp_send_json_error( array( 'error' => __( 'Invalid nonce', 'wpml-media' ) ) );
		}
	}

	private function has_media_upload() {
		return ! empty( $_POST['update-media-file'] );
	}

	private function should_restore_media() {
		return ! empty( $_POST['restore-media'] );
	}

	private function create_attachment_translation( $original_attachment_id, $post_array ) {

		$post_element = $this->translation_element_factory->create( $original_attachment_id, 'post' );
		$this->set_post_trid_value( $post_element->get_trid() );
		$this->set_post_lang_value( $_POST['translated-language'] );

		add_filter( 'wpml_tm_save_post_trid_value', array( $this, 'get_post_trid_value' ) );
		add_filter( 'wpml_tm_save_post_lang_value', array( $this, 'get_post_lang_value' ) );

		$attachment_id = wp_insert_post( $post_array );

		remove_filter( 'wpml_tm_save_post_trid_value', array( $this, 'get_post_trid_value' ) );
		remove_filter( 'wpml_tm_save_post_lang_value', array( $this, 'get_post_lang_value' ) );

		return $attachment_id;
	}

	private function set_post_trid_value( $value ) {
		$this->post_data['post_icl_trid'] = $value;
	}

	public function get_post_trid_value() {
		return $this->post_data['post_icl_trid'];
	}

	private function set_post_lang_value( $value ) {
		$this->post_data['post_icl_language'] = $value;
	}

	public function get_post_lang_value() {
		return $this->post_data['post_icl_language'];
	}

	private function restore_media_file( $attachment_id, $original_attachment_id ) {

		$media_file = $this->media_file_factory->create( $attachment_id );
		$media_file->delete();

		$this->copy_attached_file_info_from_original( $attachment_id, $original_attachment_id );
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

		do_action( 'wpml_after_copy_attached_file_postmeta', $original_attachment_id, $attachment_id );
	}

	private function get_attachment_post_data( $file ) {
		$postarr = array(
			'post_mime_type'    => $file['type'],
			'guid'              => $file['url'],
			'post_modified'     => current_time( 'mysql' ),
			'post_modified_gmt' => current_time( 'mysql', 1 ),
		);

		return $postarr;
	}

	private function update_media_file( $attachment_id, $original_attachment_id, $translated_language_code ) {
		$transient_key = WPML_Media_Attachment_Image_Update::TRANSIENT_FILE_UPLOAD_PREFIX . $original_attachment_id . '_' . $translated_language_code;

		$media_file_upload = get_transient( $transient_key );
		if ( $media_file_upload ) {

			$file = $media_file_upload['upload'];

			if ( ! empty( $media_file_upload['thumb'] ) ) {
				WPML_Media_Attachment_Image_Update::delete_staged_file( $media_file_upload['thumb'] );
			}

			$media_file = $this->media_file_factory->create( $attachment_id );
			$media_file->delete();

			delete_post_meta( $attachment_id, '_wp_attachment_backup_sizes' );

			$_POST['pre-update-translated-media-guid'] = get_post_field( 'guid', $attachment_id );

			$pre_update_metadata                        = wp_get_attachment_metadata( $attachment_id );
			$_POST['pre-update-translated-media-sizes'] = isset( $pre_update_metadata['sizes'] ) && is_array( $pre_update_metadata['sizes'] )
				? array_map(
					function ( $size ) {
						return isset( $size['file'] ) ? $size['file'] : '';
					},
					$pre_update_metadata['sizes']
				)
				: [];

			$post_data = $this->get_attachment_post_data( $file );
			$this->wpdb->update( $this->wpdb->posts, $post_data, array( 'ID' => $attachment_id ) );
			update_attached_file( $attachment_id, $file['file'] );

			clean_post_cache( $attachment_id );

			do_action( 'wpml_updated_attached_file', $attachment_id, $file, $translated_language_code );

			$attachment_metadata = wp_generate_attachment_metadata( $attachment_id, $file['file'] );

			wp_update_attachment_metadata( $attachment_id, $attachment_metadata );

			do_action( 'wpml_added_media_file_translation', $original_attachment_id, $file, $translated_language_code );

			delete_transient( $transient_key );
		}
	}
}
