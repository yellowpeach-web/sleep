<?php

class WPML_Media_Attachment_Image_Update implements IWPML_Action {

	const TRANSIENT_FILE_UPLOAD_PREFIX = 'wpml_media_file_update_';

	private $wpdb;

	public function __construct( wpdb $wpdb ) {
		$this->wpdb = $wpdb;
	}

	public function add_hooks() {
		\WPML\Request\Adapter\Ajax::register( 'wpml_media_upload_file', \WPML\Request\Policy\Policy::capability( 'upload_files', \WPML\Request\Policy\Authenticity::actionNonce( 'media-translation', 'wpnonce' ) ), array( $this, 'handle_upload' ) );
	}

	public function handle_upload() {
		if ( ! $this->is_valid_action() ) {
			wp_send_json_error( 'invalid action' );
			return;
		}
		$this->process_uploaded_file();
	}

	private function process_uploaded_file() {
		check_ajax_referer( 'media-translation', 'wpnonce' );

		$original_attachment_id = (int) $_POST['original-attachment-id'];
		$attachment_id          = (int) $_POST['attachment-id'];

		if ( ! self::can_upload_translated_file( $original_attachment_id, $attachment_id ) ) {
			wp_send_json_error( __( 'You are not allowed to edit this item.', 'wpml-media' ) );
			return;
		}

		$target_language = \WPML\Language\RequestedLanguage::validate(
			isset( $_POST['language'] ) ? sanitize_text_field( wp_unslash( $_POST['language'] ) ) : '',
			\WPML\Language\RequestedLanguage::SCOPE_CONFIGURED
		);
		if ( null === $target_language ) {
			wp_send_json_error( __( 'Invalid target language.', 'wpml-media' ) );
			return;
		}

		$transient_key = self::TRANSIENT_FILE_UPLOAD_PREFIX . $original_attachment_id . '_' . $target_language;

		if ( ! empty( $_POST['discard'] ) ) {
			$this->delete_staged_files( get_transient( $transient_key ) );
			delete_transient( $transient_key );
			wp_send_json_success();
			return;
		}

		if ( ! isset( $_FILES['file'] ) ) {
			wp_send_json_error( __( 'No file uploaded.', 'wpml-media' ) );
			return;
		}
		$file_array = array(
			'name'     => isset( $_FILES['file']['name'] ) ? sanitize_file_name( wp_unslash( $_FILES['file']['name'] ) ) : '',
			'type'     => isset( $_FILES['file']['type'] ) ? sanitize_mime_type( wp_unslash( $_FILES['file']['type'] ) ) : '',
			'tmp_name' => isset( $_FILES['file']['tmp_name'] ) && is_string( $_FILES['file']['tmp_name'] ) ? $_FILES['file']['tmp_name'] : '',
			'error'    => isset( $_FILES['file']['error'] ) ? (int) $_FILES['file']['error'] : UPLOAD_ERR_NO_FILE,
			'size'     => isset( $_FILES['file']['size'] ) ? (int) $_FILES['file']['size'] : 0,
		);

		$upload_overrides = apply_filters( 'wpml_media_wp_upload_overrides', array( 'test_form' => false ) );
		$file             = wp_handle_upload( $file_array, $upload_overrides );

		if ( isset( $file['error'] ) ) {
			wp_send_json_error( $file['error'] );
			return;
		}

		$thumb_path = '';

		if ( wp_image_editor_supports( array( 'mime_type' => $file['type'] ) ) ) {
			$editor = wp_get_image_editor( $file['file'] );

			if ( is_wp_error( $editor ) || ! $this->is_thumbnail_creation_enabled() ) {
				$thumb_url = wp_mime_type_icon( $file['type'] );

				if ( ! $thumb_url ) {
					wp_send_json_error( __( 'Failed to load the image editor', 'wpml-media' ) );
				}
			} else {
				if ( $this->is_pdf_or_video( $file['type'] ) ) {
					$thumb = $this->generate_thumb_for_pdf_or_video_file( $file, $editor );
				} else {
					$thumb = $this->resize_thumbnail( $editor );
				}

				if ( is_string( $thumb ) ) {
					$thumb_url = $thumb;
				} else {
					$uploads_dir = wp_get_upload_dir();
					$thumb_url   = $uploads_dir['baseurl'] . $uploads_dir['subdir'] . '/' . $thumb['file'];
					$thumb_path  = $thumb['path'];
				}
			}
		} else {
			$thumb_url = wp_mime_type_icon( $file['type'] );
		}

		$this->delete_staged_files( get_transient( $transient_key ) );

		set_transient(
			$transient_key,
			array(
				'upload' => $file,
				'thumb'  => $thumb_path,
			),
			HOUR_IN_SECONDS
		);

		wp_send_json_success(
			array(
				'attachment_id' => $attachment_id,
				'thumb'         => $thumb_url,
				'name'          => basename( $file['file'] ),
			)
		);
	}

	private static function can_upload_translated_file( $original_attachment_id, $attachment_id ) {
		if ( ! current_user_can( 'upload_files' ) ) {
			return false;
		}

		if ( ! current_user_can( 'edit_post', $original_attachment_id ) ) {
			return false;
		}

		return ! $attachment_id || current_user_can( 'edit_post', $attachment_id );
	}

	private function is_pdf_or_video( $file_type ) {
		return 'application/pdf' === $file_type || stripos( $file_type, 'video' ) !== false;
	}

	private function generate_thumb_for_pdf_or_video_file( $file, WP_Image_Editor $editor ) {
		$this->resize_to_thumbnail_size( $editor );

		$dirname = dirname( $file['file'] ) . '/';
		$ext     = pathinfo( $file['file'], PATHINFO_EXTENSION );
		$size    = $editor->get_size();

		$filename = wp_unique_filename(
			$dirname,
			sprintf(
				'%s-%s-%dx%d.jpg',
				wp_basename( $file['file'], '.' . $ext ),
				$ext,
				$size['width'],
				$size['height']
			)
		);

		return $editor->save( $dirname . $filename, 'image/jpeg' );
	}

	private function delete_staged_files( $staged ) {
		if ( ! is_array( $staged ) ) {
			return;
		}

		if ( ! empty( $staged['upload']['file'] ) ) {
			self::delete_staged_file( $staged['upload']['file'] );
		}

		if ( ! empty( $staged['thumb'] ) ) {
			self::delete_staged_file( $staged['thumb'] );
		}
	}

	public static function delete_staged_file( $path ) {
		if ( ! self::is_inside_uploads_dir( $path ) ) {
			return false;
		}

		wp_delete_file( $path );

		return true;
	}

	public static function is_inside_uploads_dir( $path ) {
		if ( ! is_string( $path ) || '' === $path ) {
			return false;
		}

		$upload_dir = wp_get_upload_dir();
		$basedir    = isset( $upload_dir['basedir'] ) ? (string) $upload_dir['basedir'] : '';
		if ( '' === $basedir ) {
			return false;
		}

		$normalized_base = rtrim( wp_normalize_path( $basedir ), '/' ) . '/';
		$normalized_path = wp_normalize_path( $path );
		if ( 0 !== strpos( $normalized_path, $normalized_base ) ) {
			return false;
		}

		$relative = substr( $normalized_path, strlen( $normalized_base ) );

		return '' !== $relative && ! in_array( '..', explode( '/', $relative ), true );
	}

	private function is_thumbnail_creation_enabled() {
		$thumbnail_size_w = get_option( 'thumbnail_size_w' );
		$thumbnail_size_h = get_option( 'thumbnail_size_h' );

		if ( 0 >= $thumbnail_size_w || 0 >= $thumbnail_size_h ) {
			return false;
		}

		return true;
	}

	private function resize_thumbnail( $editor ) {

		$this->resize_to_thumbnail_size( $editor );

		return $editor->save();
	}

	private function resize_to_thumbnail_size( WP_Image_Editor $editor ) {

		$size             = $editor->get_size();
		$thumbnail_size_w = get_option( 'thumbnail_size_w' );
		$thumbnail_size_h = get_option( 'thumbnail_size_h' );

		if ( $thumbnail_size_w < $size['width'] || $thumbnail_size_h < $size['height'] ) {
			$resizing = $editor->resize( $thumbnail_size_w, $thumbnail_size_h, true );
			if ( is_wp_error( $resizing ) ) {
				wp_send_json_error( $resizing->get_error_message() );
			}
		}
	}

	private function is_valid_action() {
		$is_attachment_id = isset( $_POST['attachment-id'] );
		$is_post_action   = isset( $_POST['action'] ) && 'wpml_media_upload_file' === $_POST['action'];

		return $is_attachment_id && $is_post_action
			&& isset( $_POST['wpnonce'] )
			&& wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['wpnonce'] ) ), 'media-translation' );
	}

}
