<?php

class WPML_Fix_Links_In_Display_As_Translated_Content implements IWPML_Action, IWPML_Frontend_Action, IWPML_DIC_Action {

	private $sitepress;

	private $translate_link_targets;

	public function __construct( SitePress $sitepress, WPML_Translate_Link_Targets $translate_link_targets ) {
		$this->sitepress              = $sitepress;
		$this->translate_link_targets = $translate_link_targets;
	}

	public function add_hooks() {
		add_filter(
			'the_content',
			array(
				$this,
				'fix_fallback_links',
			),
			WPML_LS_Render::THE_CONTENT_FILTER_PRIORITY - 1
		);
	}

	public function fix_fallback_links( $content ) {
		if ( stripos( $content, '<a' ) !== false ) {
			if ( $this->is_display_as_translated_content_type() ) {
				list( $content, $encoded_ls_links ) = $this->encode_language_switcher_links( $content );
				$content                            = $this->translate_link_targets->convert_text_for_display( $content );
				$content                            = $this->decode_language_switcher_links( $content, $encoded_ls_links );
			}
		}

		return $content;
	}

	private function is_display_as_translated_content_type() {
		$queried_object = get_queried_object();
		if ( isset( $queried_object->post_type ) ) {
			return $this->sitepress->is_display_as_translated_post_type( $queried_object->post_type );
		} else {
			return false;
		}
	}

	private function encode_language_switcher_links( $content ) {
		$encoded_ls_links = array();

		if ( preg_match_all( '/<a\s[^>]*>/', $content, $matches ) ) {
			foreach ( $matches[0] as $link ) {
				if ( ! $this->is_language_switcher_link( $link ) ) {
					continue;
				}

				$encoded_link                      = md5( $link );
				$encoded_ls_links[ $encoded_link ] = $link;
				$content                           = str_replace( $link, $encoded_link, $content );
			}
		}

		return array( $content, $encoded_ls_links );
	}

	private function is_language_switcher_link( $link ) {
		if (
			preg_match( '/class\s*=\s*"([^"]*)"/', $link, $class_attribute )
			&& strpos( $class_attribute[1], WPML_LS_Model_Build::LINK_CSS_CLASS ) !== false
		) {
			return true;
		}

		return (bool) preg_match( '/\sdata-wpml\s*=\s*"link"/', $link );
	}

	private function decode_language_switcher_links( $content, $encoded_ls_links ) {
		foreach ( $encoded_ls_links as $encoded => $link ) {
			$content = str_replace( $encoded, $link, $content );
		}

		return $content;
	}
}

