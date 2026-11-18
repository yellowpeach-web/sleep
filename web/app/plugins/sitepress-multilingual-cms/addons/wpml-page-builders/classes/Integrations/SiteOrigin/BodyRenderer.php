<?php

namespace WPML\PB\SiteOrigin;

use SiteOrigin_Panels;
use SiteOrigin_Panels_Admin;
use SiteOrigin_Panels_Post_Content_Filters;

class BodyRenderer {

	private $data_settings;

	public function __construct( \IWPML_Page_Builders_Data_Settings $data_settings ) {
		$this->data_settings = $data_settings;
	}

	public function render( $translated_post_id ) {
		try {
			if ( ! siteorigin_panels_setting( 'copy-content' ) ) {
				return null;
			}

			$panels_data = get_post_meta( $translated_post_id, $this->data_settings->get_meta_field(), true );
			if ( empty( $panels_data ) || ! is_array( $panels_data ) ) {
				return null;
			}

			SiteOrigin_Panels_Post_Content_Filters::add_filters();
			$GLOBALS['SITEORIGIN_PANELS_POST_CONTENT_RENDER'] = true;

			try {
				$body = $this->render_layout( $translated_post_id, $panels_data );
			} finally {
				SiteOrigin_Panels_Post_Content_Filters::remove_filters();
				unset( $GLOBALS['SITEORIGIN_PANELS_POST_CONTENT_RENDER'] );
			}

			if ( ! is_string( $body ) ) {
				return null;
			}

			if ( siteorigin_panels_setting( 'copy-styles' ) && false === has_filter( 'content_save_pre', 'wp_filter_post_kses' ) ) {
				$body .= "\n\n";
				$body .= '<style type="text/css" class="panels-style" data-panels-style-for-post="' . (int) $translated_post_id . '">';
				$body .= '@import url(' . esc_url( SiteOrigin_Panels::front_css_url() ) . '); ';
				$body .= SiteOrigin_Panels::renderer()->generate_css( $translated_post_id, $panels_data );
				$body .= '</style>';
			}

			return $body;
		} catch ( \Throwable $e ) {
			return null;
		}
	}

	private function render_layout( $post_id, array $panels_data ) {
		if ( method_exists( SiteOrigin_Panels_Admin::class, 'render_and_restore_post_globals' ) ) {
			return SiteOrigin_Panels_Admin::render_and_restore_post_globals( $post_id, false, $panels_data );
		}

		global $post;
		$current_post = $post;

		try {
			return SiteOrigin_Panels::renderer()->render( $post_id, false, $panels_data );
		} finally {
			$post = $current_post;
		}
	}
}
