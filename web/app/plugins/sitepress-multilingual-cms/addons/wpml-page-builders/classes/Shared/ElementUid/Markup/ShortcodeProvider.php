<?php

namespace WPML\PB\ElementUid\Markup;

use WPML\PB\ElementUid\ShortcodeCapture;

class ShortcodeProvider implements Provider {

	private $strategy;

	private $encoding;

	public function __construct( \WPML_PB_Shortcode_Strategy $strategy, \WPML_PB_Shortcode_Encoding $encoding ) {
		$this->strategy = $strategy;
		$this->encoding = $encoding;
	}

	public function appliesTo( $postId ) {
		$content = $this->getContent( $postId );

		return false === strpos( $content, '<!-- wp:' ) && ShortcodeCapture::hasUidAttribute( $content );
	}

	public function getElements( $postId ) {
		$content = $this->getContent( $postId );

		if ( '' === $content ) {
			return [];
		}

		$content  = apply_filters( 'wpml_pb_shortcode_content_for_translation', $content, $postId );
		$elements = [];

		foreach ( $this->strategy->get_shortcode_parser()->get_shortcodes( $content ) as $shortcode ) {
			$uid = ShortcodeCapture::getUidFromShortcode( $shortcode );

			if ( null === $uid ) {
				continue;
			}

			$strings = $this->getStrings( $shortcode );

			if ( $strings ) {
				$elements[] = [
					'uid'     => $uid,
					'hash'    => ShortcodeCapture::hash( $shortcode ),
					'strings' => $strings,
				];
			}
		}

		return $elements;
	}

	public function getSource() {
		return ShortcodeCapture::SOURCE;
	}

	private function getContent( $postId ) {
		$post = get_post( $postId );

		return $post instanceof \WP_Post ? (string) $post->post_content : '';
	}

	private function getStrings( array $shortcode ) {
		$tag     = $shortcode['tag'];
		$strings = [];

		if ( $this->shouldHandleContent( $shortcode ) ) {
			$this->addStrings(
				$strings,
				$this->encoding->decode(
					$shortcode['content'],
					$this->strategy->get_shortcode_tag_encoding( $tag ),
					$this->strategy->get_shortcode_tag_encoding_condition( $tag )
				),
				'content',
				$this->strategy->get_shortcode_tag_type( $tag )
			);
		}

		$translatable = $this->strategy->get_shortcode_attributes( $tag );

		foreach ( (array) shortcode_parse_atts( $shortcode['attributes'] ) as $attribute => $value ) {
			if ( in_array( $attribute, $translatable, true ) ) {
				$this->addStrings(
					$strings,
					$this->encoding->decode( $value, $this->strategy->get_shortcode_attribute_encoding( $tag, $attribute ) ),
					$attribute,
					$this->strategy->get_shortcode_attribute_type( $tag, $attribute )
				);
			}
		}

		return $strings;
	}

	private function addStrings( array &$strings, $value, $name, $editorType ) {
		if ( is_array( $value ) ) {
			foreach ( $value as $key => $item ) {
				if ( is_array( $item ) && ! empty( $item['translate'] ) && isset( $item['value'] ) ) {
					$this->addStrings( $strings, $item['value'], $name . ' ' . $key, $editorType );
				}
			}
		} elseif ( '' !== trim( $value ) ) {
			$strings[] = new \WPML_PB_String( $value, md5( $value ), $name, $editorType );
		}
	}

	private function shouldHandleContent( array $shortcode ) {
		if ( ! isset( $shortcode['content'] ) || '' === $shortcode['content'] ) {
			return false;
		}

		$tag    = $shortcode['tag'];
		$handle = ! (
			$this->strategy->get_shortcode_ignore_content( $tag )
			|| in_array( $this->strategy->get_shortcode_tag_type( $tag ), [ 'media-url', 'media-ids' ], true )
		);

		return (bool) apply_filters( 'wpml_pb_should_handle_content', $handle, $shortcode );
	}
}
