<?php

use WPML\PB\Cornerstone\Utils;

class WPML_Cornerstone_Update_Translation extends WPML_Page_Builders_Update_Translation {

	public function get_converted_data( $post_id ) {
		if ( Utils::isLayoutPostType( get_post_type( $post_id ) ) ) {
			$post = get_post( $post_id );

			return $this->data_settings->convert_data_to_array( $post ? $post->post_content : '' );
		}

		return parent::get_converted_data( $post_id );
	}

	public function save( $post_id, $original_post_id, $converted_data ) {
		if ( ! Utils::isLayoutPostType( get_post_type( $original_post_id ) ) ) {
			parent::save( $post_id, $original_post_id, $converted_data );
			return;
		}

		$postarr = [
			'ID'           => $post_id,
			'post_content' => wp_json_encode( $converted_data ),
		];
		if ( \WPML\PB\Helper\TranslationContent::update( $postarr ) ) {
			\WPML\PB\Helper\TranslationContent::followRecord( $post_id );
		}

		foreach ( array_diff( $this->data_settings->get_fields_to_copy(), [ 'post_content' ] ) as $meta_key ) {
			$value = get_post_meta( $original_post_id, $meta_key, true );
			update_post_meta(
				$post_id,
				$meta_key,
				apply_filters( 'wpml_pb_copy_meta_field', $value, $post_id, $original_post_id, $meta_key )
			);
		}
	}

	public function update_strings_in_modules( array &$data_array ) {
		foreach ( $data_array as $key => &$data ) {
			if ( isset( $data['_type'] ) && ! Utils::typeIsLayout( $data['_type'] ) ) {
				$data = $this->update_strings_in_node( Utils::getNodeId( $data ), $data );
				if ( Utils::shouldCheckForSubmodules( $data['_type'] ) ) {
					$this->update_strings_in_modules( $data );
				}
			} elseif ( is_array( $data ) ) {
				$this->update_strings_in_modules( $data );
			}
		}
	}

	protected function update_strings_in_node( $node_id, $settings ) {
		$strings = $this->translatable_nodes->get( $node_id, $settings );
		foreach ( $strings as $string ) {
			$translation = $this->get_translation( $string );
			$settings    = $this->translatable_nodes->update( $node_id, $settings, $translation );
		}

		return $settings;
	}
}
