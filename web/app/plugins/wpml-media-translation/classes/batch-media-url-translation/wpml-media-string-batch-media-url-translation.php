<?php

class WPML_Media_String_Batch_Url_Translation extends WPML_Media_Batch_Url_Translation implements IWPML_Action {

	const BATCH_SIZE  = 500;
	const AJAX_ACTION = 'wpml_media_translate_media_url_in_strings';

	private $string_factory;

	public function __construct(
		wpdb $wpdb,
		WPML_ST_String_Factory $string_factory
	) {
		parent::__construct( $wpdb );
		$this->string_factory = $string_factory;
	}

	protected function get_batch_size( $batch_size_factor = self::BATCH_SIZE_FACTOR_ALL_MEDIA ) {
		return $batch_size_factor * self::BATCH_SIZE;
	}

	protected function get_ajax_action() {
		return self::AJAX_ACTION;
	}

	public static function is_ajax_request() {
		return isset( $_POST['action'] ) && self::AJAX_ACTION === $_POST['action'];
	}

	protected function get_response_message( $number_of_strings_left ) {
		return sprintf(
			/* translators: Progress message on the Media Translation page while media addresses inside translated texts are being updated. %s: either the number of items still left or the word for "done!". */
			__( 'Translating media urls in string translations: %s', 'wpml-media' ),
			$number_of_strings_left > 0 ?
				/* translators: Second half of the progress message on the Media Translation page, for example "Translating media urls in post translations: 12 left". %d: the number of items still to process. */
				sprintf( __( '%d left', 'wpml-media' ), $number_of_strings_left ) :
				/* translators: Second half of the progress message on the Media Translation page, shown in place of the count once everything has been processed, for example "Translating media urls in post translations: done!". */
				__( 'done!', 'wpml-media' )
		);
	}

	protected function get_ajax_error_message() {
		return array(
			'key'   => 'wpml_media_batch_urls_update_error_strings',
			/* translators: Error message on the Media Translation page when updating media addresses inside texts fails. %s: the reason the server gave. */
			'value' => esc_js( __( 'Translating media urls in string translations failed: Please try again (%s)', 'wpml-media' ) ),
		);
	}

	protected function process_batch( $offset ) {
		$wpdb = $this->wpdb;
		$offset = (int) $offset;

		$original_strings = $this->wpdb->get_col(
			$wpdb->prepare(
				"SELECT SQL_CALC_FOUND_ROWS id, language
				FROM {$wpdb->prefix}icl_strings
				ORDER BY id ASC
				LIMIT %d, %d",
				$offset,
				self::BATCH_SIZE
			)
		);

		$number_of_all_strings = (int) $this->wpdb->get_var( 'SELECT FOUND_ROWS()' );

		foreach ( $original_strings as $string_id ) {
			$string              = $this->string_factory->find_by_id( $string_id );
			$string_translations = $string->get_translations();
			foreach ( $string_translations as $string_translation ) {
				if ( $string_translation->value ) {
					$string->set_translation( $string_translation->language, $string_translation->value );
				}
			}
		}

		return $number_of_all_strings - $offset - self::BATCH_SIZE;
	}

	protected function process_batch_for_selected_media( $offset, $attachment_id ) {
		$wpdb   = $this->wpdb;
		$offset = (int) $offset;

		$url_patterns = $this->get_media_url_like_patterns( $attachment_id );
		if ( ! $url_patterns ) {
			return 0;
		}

		$batch_size = $this->get_batch_size( parent::BATCH_SIZE_FACTOR_SPECIFIC_MEDIA );

		$original_strings = $this->wpdb->get_col(
			$wpdb->prepare(
				"SELECT SQL_CALC_FOUND_ROWS id, language
				FROM {$wpdb->prefix}icl_strings
				WHERE (value LIKE %s OR value LIKE %s OR value LIKE %s OR value LIKE %s)
				ORDER BY id ASC
				LIMIT %d, %d",
				$url_patterns[0],
				$url_patterns[1],
				$url_patterns[2],
				$url_patterns[3],
				$offset,
				$batch_size
			)
		);

		$number_of_all_strings = (int) $this->wpdb->get_var( 'SELECT FOUND_ROWS()' );

		foreach ( $original_strings as $string_id ) {
			$string              = $this->string_factory->find_by_id( $string_id );
			$string_translations = $string->get_translations();
			foreach ( $string_translations as $string_translation ) {
				if ( $string_translation->value ) {
					$string->set_translation( $string_translation->language, $string_translation->value );
				}
			}
		}

		return $number_of_all_strings - $offset - $batch_size;
	}

}
