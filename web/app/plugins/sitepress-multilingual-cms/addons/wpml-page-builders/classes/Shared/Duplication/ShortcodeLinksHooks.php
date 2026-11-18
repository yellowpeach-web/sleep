<?php

namespace WPML\PB\Duplication;

use WPML_PB_Config_Import_Shortcode;
use WPML_PB_Shortcode_Encoding;
use WPML_PB_Shortcode_Strategy;
use WPML_TM_Page_Builders;

class ShortcodeLinksHooks implements \IWPML_Action {

	private $shortcodeStrategy;

	private $wpdb;

	private $targetLanguageLinks;

	public function __construct( WPML_PB_Shortcode_Strategy $shortcodeStrategy, \wpdb $wpdb, TargetLanguageLinks $targetLanguageLinks ) {
		$this->shortcodeStrategy   = $shortcodeStrategy;
		$this->wpdb                = $wpdb;
		$this->targetLanguageLinks = $targetLanguageLinks;
	}

	public function add_hooks() {
		add_action( 'icl_make_duplicate', [ $this, 'convertLinks' ], 10, 4 );
	}

	public function convertLinks( $masterPostId, $language, $postArray, $duplicatePostId ) {
		$linkAttributesByTag = $this->getLinkAttributesByTag();
		if ( ! $linkAttributesByTag ) {
			return;
		}

		$query   = $this->wpdb->prepare( "SELECT post_content FROM {$this->wpdb->posts} WHERE ID = %d", $duplicatePostId );
		$content = (string) $this->wpdb->get_var( $query );
		if ( false === strpos( $content, '[' ) ) {
			return;
		}

		$converted = $this->targetLanguageLinks->convert(
			$language,
			function ( \WPML_Translate_Link_Targets $translateLinkTargets ) use ( $content, $linkAttributesByTag ) {
				return $this->convertContent( $content, $linkAttributesByTag, $translateLinkTargets );
			}
		);

		if ( $converted !== $content ) {
			$this->wpdb->update( $this->wpdb->posts, [ 'post_content' => $converted ], [ 'ID' => $duplicatePostId ] );
		}
	}

	private function getLinkAttributesByTag() {
		$linkAttributesByTag = [];

		foreach ( $this->shortcodeStrategy->get_shortcodes() as $tag ) {
			foreach ( $this->shortcodeStrategy->get_shortcode_attributes( $tag ) as $attribute ) {
				if ( $this->isPlainLinkAttribute( $tag, $attribute ) ) {
					$linkAttributesByTag[ $tag ][] = $attribute;
				}
			}
		}

		return $linkAttributesByTag;
	}

	private function isPlainLinkAttribute( $tag, $attribute ) {
		if ( WPML_TM_Page_Builders::FIELD_STYLE_LINK !== $this->shortcodeStrategy->get_shortcode_attribute_type( $tag, $attribute ) ) {
			return false;
		}

		$encoding = $this->shortcodeStrategy->get_shortcode_attribute_encoding( $tag, $attribute );

		return ! $encoding || WPML_PB_Shortcode_Encoding::ENCODE_TYPES_VISUAL_COMPOSER_LINK === $encoding;
	}

	private function convertContent( $content, array $linkAttributesByTag, \WPML_Translate_Link_Targets $translateLinkTargets ) {
		foreach ( $linkAttributesByTag as $tag => $attributes ) {
			$content = (string) preg_replace_callback(
				'/\[' . preg_quote( $tag, '/' ) . '(?![\w-])[^\]]*\]/',
				function ( $openingTag ) use ( $attributes, $translateLinkTargets ) {
					return $this->convertAttributes( $openingTag[0], $attributes, $translateLinkTargets );
				},
				$content
			);
		}

		return $content;
	}

	private function convertAttributes( $openingTag, array $attributes, \WPML_Translate_Link_Targets $translateLinkTargets ) {
		foreach ( $attributes as $attribute ) {
			$openingTag = (string) preg_replace_callback(
				'/' . WPML_PB_Config_Import_Shortcode::NAME_BOUNDARY . '(' . preg_quote( $attribute, '/' ) . '=)(["\'])(https?:\/\/[^"\']+)\2/',
				function ( $attributeMatch ) use ( $translateLinkTargets ) {
					return $attributeMatch[1] . $attributeMatch[2] . $translateLinkTargets->convert_url( $attributeMatch[3] ) . $attributeMatch[2];
				},
				$openingTag
			);
		}

		return $openingTag;
	}
}
