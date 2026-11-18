<?php

namespace WPML\PB\BeaverBuilder;

use FLBuilder;
use FLBuilderModel;

class BodyRenderer {

	private $data_settings;

	public function __construct( \IWPML_Page_Builders_Data_Settings $data_settings ) {
		$this->data_settings = $data_settings;
	}

	public function render( $translated_post_id ) {
		if ( ! $this->data_settings->is_handling_post( $translated_post_id ) ) {
			return null;
		}

		$buffer_level = ob_get_level();

		try {
			self::forget_layout( $translated_post_id );

			FLBuilderModel::set_post_id( $translated_post_id );

			try {
				return FLBuilder::render_editor_content();
			} finally {
				FLBuilderModel::reset_post_id();
			}
		} catch ( \Throwable $e ) {
			while ( ob_get_level() > $buffer_level ) {
				ob_end_clean();
			}

			return null;
		}
	}

	private static function forget_layout( $post_id ) {
		$forget = \Closure::bind(
			function ( $post_id ) {
				unset( self::$published_layout_data[ $post_id ], self::$draft_layout_data[ $post_id ] );
			},
			null,
			FLBuilderModel::class
		);

		$forget( $post_id );
	}
}
