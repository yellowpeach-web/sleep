<?php

abstract class WPML_Media_Batch_Url_Translation {

	const BATCH_SIZE = 10;

	const BATCH_SIZE_FACTOR_ALL_MEDIA      = 1;
	const BATCH_SIZE_FACTOR_SPECIFIC_MEDIA = 10;

	protected $wpdb;

	public function __construct( wpdb $wpdb ) {
		$this->wpdb = $wpdb;
	}

	public function add_hooks() {
		\WPML\Request\Adapter\Ajax::register( $this->get_ajax_action(), \WPML\Request\Policy\Policy::capability( [ 'wpml_manage_media_translation', 'manage_translations' ], \WPML\Request\Policy\Authenticity::actionNonce( 'wpml_media_translate_media_url', 'nonce' ) ), array( $this, 'run_batch' ) );
	}

	public function run_batch() {
		if ( ! current_user_can( 'wpml_manage_media_translation' ) && ! current_user_can( 'manage_translations' ) ) {
			wp_send_json_error( __( 'You are not allowed to perform this action.', 'wpml-media' ) );
			return;
		}

		$offset        = isset( $_POST['offset'] ) ? (int) $_POST['offset'] : 0;
		$attachment_id = isset( $_POST['attachment_id'] ) ? (int) $_POST['attachment_id'] : 0;
		$all_media     = ! empty( $_POST['global'] );

		if ( $all_media ) {
			$number_of_elements_left = $this->process_batch( $offset );
		} else {
			$number_of_elements_left = $this->process_batch_for_selected_media( $offset, $attachment_id );
		}
		$batch_size_factor = $all_media ? self::BATCH_SIZE_FACTOR_ALL_MEDIA : self::BATCH_SIZE_FACTOR_SPECIFIC_MEDIA;
		$response          = array(
			'offset'   => $offset + $this->get_batch_size( $batch_size_factor ),
			'continue' => (int) ( $number_of_elements_left > 0 ),
			'message'  => $this->get_response_message( $number_of_elements_left ),
		);

		wp_send_json_success( $response );
	}

	abstract protected function get_response_message( $number_of_elements_left );

	abstract protected function process_batch( $offset );

	abstract protected function process_batch_for_selected_media( $offset, $attachment_id );

	protected function get_media_url_like_patterns( $attachment_id ) {
		$media_url = wp_get_attachment_url( $attachment_id );
		if ( ! $media_url ) {
			return array();
		}

		$media_url = (string) preg_replace( '#^(?:[a-z][a-z0-9+.-]*:)?//[^/]*#i', '', $media_url );

		if ( ! preg_match( '/(.+)\.([A-Za-z0-9]+)$/', $media_url, $match ) ) {
			return array();
		}

		$as_json = function ( $value ) {
			return wpml_like_escape( str_replace( '/', '\\/', $value ) );
		};

		$extension = wpml_like_escape( $match[2] );

		return array(
			'%' . wpml_like_escape( $media_url ) . '%',
			'%' . wpml_like_escape( $match[1] ) . '-%x%.' . $extension . '%',
			'%' . $as_json( $media_url ) . '%',
			'%' . $as_json( $match[1] ) . '-%x%.' . $extension . '%',
		);
	}

	abstract protected function get_ajax_error_message();

	protected function get_batch_size( $batch_size_factor = self::BATCH_SIZE_FACTOR_ALL_MEDIA ) {
		return $batch_size_factor * self::BATCH_SIZE;
	}

	abstract protected function get_ajax_action();

}
