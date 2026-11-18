<?php

use WPML\PB\BeaverBuilder\BeaverThemer\HooksFactory as BeaverThemer;
use WPML\PB\BeaverBuilder\BodyRenderer;
use WPML\PB\TranslationBody\RendersTranslationBody;

class WPML_Beaver_Builder_Data_Settings implements IWPML_Page_Builders_Data_Settings, RendersTranslationBody {

	const META_FIELD_KEY       = '_fl_builder_data';
	const META_FIELD_DRAFT_KEY = '_fl_builder_draft';

	public function get_meta_field() {
		return self::META_FIELD_KEY;
	}

	public function get_node_id_field() {
		return 'node';
	}

	public function get_fields_to_copy() {
		$fields = [
			'_fl_builder_draft_settings',
			'_fl_builder_data_settings',
			'_fl_builder_enabled',
		];

		if ( BeaverThemer::isActive() ) {
			return array_merge(
				$fields,
				[
					'_fl_theme_builder_locations',
					'_fl_theme_builder_exclusions',
					'_fl_theme_builder_edit_mode',
					'_fl_theme_builder_user_rules',
				]
			);
		}

		return $fields;
	}

	public function convert_data_to_array( $data ) {
		return $data;
	}

	public function prepare_data_for_saving( array $data ) {
		return $this->slash( $data );
	}

	public function get_pb_name() {
		return 'Beaver builder';
	}

	public function get_fields_to_save() {
		return [ self::META_FIELD_KEY, self::META_FIELD_DRAFT_KEY ];
	}

	public function add_hooks() {}

	public function render_translation_body( $translated_post_id, WP_Post $original_post ) {
		return ( new BodyRenderer( $this ) )->render( $translated_post_id );
	}

	private function slash( $data ) {
		if ( is_array( $data ) ) {
			foreach ( $data as $key => $val ) {
				$data[ $key ] = $this->slash( $val );
			}
		} elseif ( is_object( $data ) ) {
			foreach ( $data as $key => $val ) {
				$data->$key = $this->slash( $val );
			}
		} elseif ( is_string( $data ) ) {
			$data = wp_slash( $data );
		}

		return $data;
	}

	public function is_handling_post( $postId ) {
		return (bool) get_post_meta( $postId, '_fl_builder_enabled', true );
	}
}
