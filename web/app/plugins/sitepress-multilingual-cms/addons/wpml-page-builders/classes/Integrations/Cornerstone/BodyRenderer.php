<?php

namespace WPML\PB\Cornerstone;

use Themeco\Cornerstone\Documents\Document;
use Themeco\Cornerstone\Elements\ShortcodeContentBuilder;

class BodyRenderer {

	const STORAGE_HTML      = 'html';
	const STORAGE_SHORTCODE = 'shortcode';

	const HTML_START = '<!-- cs-content -->';
	const HTML_END   = '<!-- cs-content-end -->';

	private $data_settings;

	public function __construct( \IWPML_Page_Builders_Data_Settings $data_settings ) {
		$this->data_settings = $data_settings;
	}

	public function render( $translated_post_id, \WP_Post $original_post ) {
		try {
			$storage = $this->get_body_storage( $original_post );
			if ( ! $storage ) {
				return null;
			}

			$settings = cs_get_serialized_post_meta( $translated_post_id, '_cornerstone_settings', true );
			$settings = is_array( $settings ) ? $settings : [];

			return self::STORAGE_HTML === $storage
				? $this->render_html( $translated_post_id, $settings )
				: $this->render_shortcodes( $translated_post_id, $settings );
		} catch ( \Throwable $e ) {
			return null;
		}
	}

	private function render_html( $post_id, array $settings ) {
		global $post;

		$current_post   = $post;
		$add_line_break = function ( $buffer ) {
			return $buffer . "\n";
		};

		$post = get_post( $post_id );
		add_filter( 'cs_render_handle_extraneous', $add_line_break );

		try {
			$document = Document::locate( $post_id );
			$html     = $document ? cornerstone( 'Resolver' )->renderContentFromDocument( $document ) : '';
		} finally {
			remove_filter( 'cs_render_handle_extraneous', $add_line_break );
			$post = $current_post;
		}

		if ( ! is_string( $html ) || '' === trim( $html ) ) {
			return null;
		}

		return apply_filters( 'cornerstone_save_post_content', self::HTML_START . $html . self::HTML_END, $settings );
	}

	private function render_shortcodes( $post_id, array $settings ) {
		$elements = $this->get_elements( $post_id );
		if ( ! $elements ) {
			return null;
		}

		$output = cornerstone()->resolve( ShortcodeContentBuilder::class )->build_output( $elements, $settings );
		if ( is_wp_error( $output ) || ! isset( $output['content'] ) ) {
			return null;
		}

		return "[cs_content _p='" . (int) $post_id . "']"
			. apply_filters( 'cornerstone_save_post_content', $output['content'], $settings )
			. '[/cs_content]';
	}

	private function get_elements( $post_id ) {
		$elements = cs_get_serialized_post_meta(
			$post_id,
			$this->data_settings->get_meta_field(),
			true,
			'cs_content_load_serialized_content'
		);

		if ( ! is_array( $elements ) ) {
			return [];
		}

		return isset( $elements['data'] ) && is_array( $elements['data'] ) ? $elements['data'] : $elements;
	}

	private function get_body_storage( \WP_Post $original_post ) {
		if ( Utils::isLayoutPostType( $original_post->post_type ) ) {
			return null;
		}

		if ( false !== strpos( $original_post->post_content, self::HTML_START ) ) {
			return self::STORAGE_HTML;
		}

		if ( false !== strpos( $original_post->post_content, '[cs_content' ) ) {
			return self::STORAGE_SHORTCODE;
		}

		return null;
	}
}
