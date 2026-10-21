<?php

class WPML_Media_Post_Batch_Url_Translation extends WPML_Media_Batch_Url_Translation implements IWPML_Action {

	const AJAX_ACTION = 'wpml_media_translate_media_url_in_posts';

	private $post_image_translation;

	public function __construct( WPML_Media_Post_Images_Translation $post_image_translation, wpdb $wpdb ) {
		parent::__construct( $wpdb );
		$this->post_image_translation = $post_image_translation;
		$this->wpdb                   = $wpdb;
	}

	protected function get_ajax_action() {
		return self::AJAX_ACTION;
	}

	public static function is_ajax_request() {
		return isset( $_POST['action'] ) && self::AJAX_ACTION === $_POST['action'];
	}

	protected function get_response_message( $number_of_posts_left ) {
		return sprintf(
			/* translators: Progress message on the Media Translation page while media addresses inside translated posts are being updated. %s: either the number of items still left or the word for "done!". */
			__( 'Translating media urls in post translations: %s', 'wpml-media' ),
			$number_of_posts_left > 0 ?
				/* translators: Second half of the progress message on the Media Translation page, for example "Translating media urls in post translations: 12 left". %d: the number of items still to process. */
				sprintf( __( '%d left', 'wpml-media' ), $number_of_posts_left ) :
				/* translators: Second half of the progress message on the Media Translation page, shown in place of the count once everything has been processed, for example "Translating media urls in post translations: done!". */
				__( 'done!', 'wpml-media' )
		);
	}

	protected function get_ajax_error_message() {
		return array(
			'key'   => 'wpml_media_batch_urls_update_errors_posts',
			/* translators: Error message on the Media Translation page when updating media addresses inside posts fails. %s: the reason the server gave. */
			'value' => esc_js( __( 'Translating media urls in posts translations failed: Please try again (%s)', 'wpml-media' ) )
		);
	}

	protected function process_batch( $offset ) {
		$wpdb = $this->wpdb;
		$offset = (int) $offset;
		$post_element_type_like = 'post\_%';

		$posts = $this->wpdb->get_col(
			$wpdb->prepare(
				"SELECT SQL_CALC_FOUND_ROWS element_id AS id
				FROM {$wpdb->prefix}icl_translations
				WHERE element_type LIKE %s
					AND element_type <> 'post_attachment'
					AND source_language_code IS NULL
				ORDER BY element_id ASC
				LIMIT %d, %d",
				$post_element_type_like,
				$offset,
				self::BATCH_SIZE
			)
		);

		$number_of_all_posts = (int) $this->wpdb->get_var( "SELECT FOUND_ROWS()" );

		foreach ( $posts as $post_id ) {
			$this->post_image_translation->translate_images( $post_id );
		}

		return $number_of_all_posts - $offset - self::BATCH_SIZE;
	}

	protected function process_batch_for_selected_media( $offset, $attachment_id ) {
		$wpdb                   = $this->wpdb;
		$offset                 = (int) $offset;
		$post_element_type_like = 'post\_%';

		$url_patterns = $this->get_media_url_like_patterns( $attachment_id );
		if ( ! $url_patterns ) {
			return 0;
		}

		$batch_size = $this->get_batch_size( parent::BATCH_SIZE_FACTOR_SPECIFIC_MEDIA );

		$posts = $this->wpdb->get_col(
			$wpdb->prepare(
				"SELECT SQL_CALC_FOUND_ROWS element_id AS id
				FROM {$wpdb->prefix}icl_translations t
				JOIN {$wpdb->posts} p ON t.element_id = p.ID
				WHERE element_type LIKE %s
					AND element_type <> 'post_attachment'
					AND source_language_code IS NULL
					AND (post_content LIKE %s OR post_content LIKE %s OR post_content LIKE %s OR post_content LIKE %s)
				ORDER BY element_id ASC
				LIMIT %d, %d",
				$post_element_type_like,
				$url_patterns[0],
				$url_patterns[1],
				$url_patterns[2],
				$url_patterns[3],
				$offset,
				$batch_size
			)
		);

		$number_of_all_posts = (int) $this->wpdb->get_var( "SELECT FOUND_ROWS()" );

		foreach ( $posts as $post_id ) {
			$this->post_image_translation->translate_images( $post_id );
		}

		return $number_of_all_posts - $offset - $batch_size;
	}

}
