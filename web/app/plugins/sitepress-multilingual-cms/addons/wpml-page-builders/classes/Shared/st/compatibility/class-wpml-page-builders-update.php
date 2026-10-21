<?php

class WPML_Page_Builders_Update {

	protected $data_settings;

	public function __construct( IWPML_Page_Builders_Data_Settings $data_settings ) {
		$this->data_settings = $data_settings;
	}

	public function get_converted_data( $post_id ) {
		$data = get_post_meta( $post_id, $this->data_settings->get_meta_field(), true );
		return $this->data_settings->convert_data_to_array( $data );
	}

	public function save( $post_id, $original_post_id, $converted_data ) {
		$this->copy_meta_fields( $post_id, $original_post_id, $this->data_settings->get_fields_to_copy() );

		$mayKeepRawHtml = apply_filters( 'wpml_pb_grant_unfiltered_html', false );

		if ( $mayKeepRawHtml ) {
			add_filter( 'user_has_cap', [ __CLASS__, 'grant_unfiltered_html' ] );
			add_filter( 'map_meta_cap', [ __CLASS__, 'allow_unfiltered_html_on_multisite' ], 10, 3 );
		}

		try {
			$this->save_data( $post_id, $this->data_settings->get_fields_to_save(), $this->data_settings->prepare_data_for_saving( $converted_data ) );
		} finally {
			if ( $mayKeepRawHtml ) {
				remove_filter( 'user_has_cap', [ __CLASS__, 'grant_unfiltered_html' ] );
				remove_filter( 'map_meta_cap', [ __CLASS__, 'allow_unfiltered_html_on_multisite' ] );
			}
		}
	}

	public static function grant_unfiltered_html( $allcaps ) {
		$allcaps['unfiltered_html'] = true;

		return $allcaps;
	}

	public static function allow_unfiltered_html_on_multisite( $caps, $cap, $user_id ) {
		if ( 'unfiltered_html' !== $cap ) {
			return $caps;
		}

		if ( defined( 'DISALLOW_UNFILTERED_HTML' ) && DISALLOW_UNFILTERED_HTML ) {
			return $caps;
		}

		if ( ! is_multisite() ) {
			return $caps;
		}

		if ( 0 !== (int) $user_id ) {
			return $caps;
		}

		return [ 'unfiltered_html' ];
	}

	private function save_data( $post_id, $fields, $data ) {
		foreach ( $fields as $field ) {
			update_post_meta( $post_id, $field, $data );
		}
	}

	private function copy_meta_fields( $translated_post_id, $original_post_id, $meta_fields ) {
		foreach ( $meta_fields as $meta_key ) {
			if ( 'post_content' === $meta_key ) {
				$original_post = get_post( $original_post_id );
				$postarr = [
					'ID'           => $translated_post_id,
					'post_content' => $original_post->post_content,
				];
				if ( \WPML\PB\Helper\TranslationContent::update( $postarr ) ) {
					\WPML\PB\Helper\TranslationContent::followRecord( $translated_post_id );
				}
			} else {
				$value = get_post_meta( $original_post_id, $meta_key, true );
				update_post_meta(
					$translated_post_id,
					$meta_key,
					apply_filters( 'wpml_pb_copy_meta_field', $value, $translated_post_id, $original_post_id, $meta_key )
				);
			}
		}
	}
}
