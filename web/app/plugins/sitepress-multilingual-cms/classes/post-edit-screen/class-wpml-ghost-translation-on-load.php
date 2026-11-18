<?php

namespace WPML\TM\PostEditScreen;

use IWPML_Backend_Action;
use SitePress;
use WP_Post;
use WPML_Block_Editor_Helper;
use WPML_Post_Status_Display_Factory;

class GhostTranslationOnLoad implements IWPML_Backend_Action {

	private $sitepress;

	private $status_display_factory;

	public function __construct(
		?SitePress $sitepress = null,
		?WPML_Post_Status_Display_Factory $status_display_factory = null
	) {
		$this->sitepress              = $sitepress ?: $GLOBALS['sitepress'];
		$this->status_display_factory = $status_display_factory ?: new WPML_Post_Status_Display_Factory( $this->sitepress );
	}

	public function add_hooks() {
		add_action( 'load-post.php', [ $this, 'arm' ] );
	}

	public function arm() {
		add_action( 'add_meta_boxes', [ $this, 'detect' ], 1, 2 );
	}

	public function detect( $post_type, $post = null ) {
		if ( ! $post instanceof WP_Post || empty( $post->ID ) ) {
			return;
		}

		if ( ! WPML_Block_Editor_Helper::is_block_editor_screen() ) {
			return;
		}

		if ( ! $this->sitepress->is_translated_post_type( $post->post_type ) ) {
			return;
		}

		$post_id        = (int) $post->ID;
		$post_language  = $this->sitepress->get_language_for_element( $post_id, 'post_' . $post->post_type );
		$status_display = $this->status_display_factory->create();

		foreach ( $this->sitepress->get_active_languages() as $lang ) {
			if ( $lang['code'] === $post_language ) {
				continue;
			}

			$status_display->get_status_html( $post_id, $lang['code'] );
		}
	}
}
