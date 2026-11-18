<?php

use WPML\PB\AutoUpdate\PostSignatureLayoutField;
use WPML\PB\Cornerstone\BodyRenderer;
use WPML\PB\Cornerstone\Utils;
use WPML\PB\TranslationBody\RendersTranslationBody;

class WPML_Cornerstone_Data_Settings implements IWPML_Page_Builders_Data_Settings, RendersTranslationBody {

	use PostSignatureLayoutField;

	public function get_meta_field() {
		return '_cornerstone_data';
	}

	public function get_node_id_field() {
		return '_type';
	}

	public function get_fields_to_copy() {
		return array( '_cornerstone_settings', '_cornerstone_version', 'post_content' );
	}

	public function convert_data_to_array( $data ) {
		$converted_data = $data;
		if ( is_array( $data ) ) {
			$converted_data = $data[0];
		}

		if ( is_array( $converted_data ) ) {
			return $converted_data;
		}

		return json_decode( $converted_data, true );
	}

	public function prepare_data_for_saving( array $data ) {
		return wp_slash( wp_json_encode( $data ) );
	}

	public function get_pb_name() {
		return 'Cornerstone';
	}

	public function get_fields_to_save() {
		return array( '_cornerstone_data' );
	}

	public function add_hooks() {
		add_filter( 'wpml_custom_field_values_for_post_signature', [ $this, 'add_data_custom_field_to_md5' ], 10, 2 );
	}

	public function render_translation_body( $translated_post_id, WP_Post $original_post ) {
		return ( new BodyRenderer( $this ) )->render( $translated_post_id, $original_post );
	}

	public function is_handling_post( $postId ) {
		if ( get_post_meta( $postId, '_cornerstone_override', true ) ) {
			return false;
		}

		if ( Utils::isLayoutPostType( get_post_type( $postId ) ) ) {
			$post = get_post( $postId );
			return ! empty( $post->post_content );
		}

		return (bool) get_post_meta( $postId, $this->get_meta_field(), true );
	}
}
