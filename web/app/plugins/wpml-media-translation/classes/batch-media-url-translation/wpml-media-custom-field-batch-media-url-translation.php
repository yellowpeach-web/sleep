<?php

class WPML_Media_Custom_Field_Batch_Url_Translation extends WPML_Media_Batch_Url_Translation implements IWPML_Action {

	const AJAX_ACTION = 'wpml_media_translate_media_url_in_custom_fields';

	private $custom_field_translation;
	private $translatable_custom_fields;

	public function __construct(
		WPML_Media_Custom_Field_Images_Translation $custom_field_translation,
		wpdb $wpdb,
		array $translatable_custom_fields
	) {
		parent::__construct( $wpdb );
		$this->custom_field_translation   = $custom_field_translation;
		$this->translatable_custom_fields = $translatable_custom_fields;
	}

	protected function get_ajax_action() {
		return self::AJAX_ACTION;
	}

	public static function is_ajax_request() {
		return isset( $_POST['action'] ) && self::AJAX_ACTION === $_POST['action'];
	}

	protected function get_response_message( $number_of_custom_fields_left ) {
		return sprintf(
			/* translators: Progress message on the Media Translation page while media addresses inside custom fields are being updated. %s: either the number of items still left or the word for "done!". */
			__( 'Translating media urls in custom field translations: %s', 'wpml-media' ),
			$number_of_custom_fields_left > 0 ?
				/* translators: Second half of the progress message on the Media Translation page, for example "Translating media urls in post translations: 12 left". %d: the number of items still to process. */
				sprintf( __( '%d left', 'wpml-media' ), $number_of_custom_fields_left ) :
				/* translators: Second half of the progress message on the Media Translation page, shown in place of the count once everything has been processed, for example "Translating media urls in post translations: done!". */
				__( 'done!', 'wpml-media' )
		);
	}

	protected function get_ajax_error_message() {
		return array(
			'key'   => 'wpml_media_batch_urls_update_error_custom_fields',
			/* translators: Error message on the Media Translation page when updating media addresses inside custom fields fails. %s: the reason the server gave. */
			'value' => esc_js( __( 'Translating media urls in custom fields translations failed: Please try again (%s)', 'wpml-media' ) ),
		);
	}

	protected function process_batch( $offset ) {
		$wpdb = $this->wpdb;
		$offset = (int) $offset;
		$post_element_type_like = 'post\_%';

		if ( $this->translatable_custom_fields ) {
			$custom_fields = $this->wpdb->get_results(
				$wpdb->prepare(
					"
				SELECT SQL_CALC_FOUND_ROWS t.element_id AS post_id, p.meta_id, p.meta_key, p.meta_value
				FROM {$wpdb->prefix}icl_translations t
					JOIN {$wpdb->prefix}postmeta p ON t.element_id = p.post_id
				WHERE t.element_type LIKE %s
					AND t.element_type <> 'post_attachment'
					AND t.source_language_code IS NULL
					AND p.meta_key IN (" . implode( ', ', array_fill( 0, count( $this->translatable_custom_fields ), '%s' ) ) . ")
				ORDER BY t.element_id ASC
				LIMIT %d, %d",
					array_merge( [ $post_element_type_like ], $this->translatable_custom_fields, [ $offset, self::BATCH_SIZE ] )
				)
			);

			$number_of_all_custom_fields = (int) $this->wpdb->get_var( 'SELECT FOUND_ROWS()' );

			foreach ( $custom_fields as $custom_field ) {
				$this->custom_field_translation->translate_images(
					$custom_field->meta_id,
					$custom_field->post_id,
					$custom_field->meta_key,
					$custom_field->meta_value
				);
			}
		} else {
			$number_of_all_custom_fields = 0;
		}

		return $number_of_all_custom_fields - $offset - self::BATCH_SIZE;
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

		if ( $this->translatable_custom_fields ) {
			$custom_fields = $this->wpdb->get_results(
				$wpdb->prepare(
					"
				SELECT SQL_CALC_FOUND_ROWS t.element_id AS post_id, p.meta_id, p.meta_key, p.meta_value  
				FROM {$wpdb->prefix}icl_translations t
					JOIN {$wpdb->postmeta} p ON t.element_id = p.post_id
				WHERE t.element_type LIKE %s
					AND t.element_type <> 'post_attachment' 
					AND t.source_language_code IS NULL
					AND p.meta_key IN (" . implode( ', ', array_fill( 0, count( $this->translatable_custom_fields ), '%s' ) ) . ")
					AND (p.meta_value LIKE %s OR p.meta_value LIKE %s OR p.meta_value LIKE %s OR p.meta_value LIKE %s)
				ORDER BY t.element_id ASC
				LIMIT %d, %d",
					array_merge(
						[ $post_element_type_like ],
						$this->translatable_custom_fields,
						$url_patterns,
						[
							$offset,
							$batch_size,
						]
					)
				)
			);

			$number_of_all_custom_fields = (int) $this->wpdb->get_var( 'SELECT FOUND_ROWS()' );

			foreach ( $custom_fields as $custom_field ) {
				$this->custom_field_translation->translate_images(
					$custom_field->meta_id,
					$custom_field->post_id,
					$custom_field->meta_key,
					$custom_field->meta_value
				);
			}
		} else {
			$number_of_all_custom_fields = 0;
		}

		return $number_of_all_custom_fields - $offset - $batch_size;

	}

}
