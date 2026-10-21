<?php

namespace WPML\WPSEO\YoastSEO\Editor;

use SitePress;

class ContentLocaleHooks implements \IWPML_Backend_Action, \IWPML_DIC_Action {

	const PRIORITY_AFTER_YOAST_ENQUEUES_EDITOR_SCRIPTS = 20;

	const YOAST_EDITOR_SCRIPT_HANDLES = [
		'yoast-seo-post-edit',
		'yoast-seo-post-edit-classic',
		'yoast-seo-term-edit',
		'yoast-seo-elementor',
	];

	private $sitepress;

	public function __construct( SitePress $sitepress ) {
		$this->sitepress = $sitepress;
	}

	public function add_hooks() {
		add_action( 'admin_enqueue_scripts', [ $this, 'setContentLocaleOfEditedElement' ], self::PRIORITY_AFTER_YOAST_ENQUEUES_EDITOR_SCRIPTS );
		add_action( 'elementor/editor/after_enqueue_scripts', [ $this, 'setContentLocaleOfEditedElement' ] );
	}

	public function setContentLocaleOfEditedElement() {
		$languageCode = $this->getLanguageOfEditedElement();
		$locale       = $languageCode ? $this->sitepress->get_locale( $languageCode ) : null;

		if ( ! $locale ) {
			return;
		}

		$script = sprintf(
			'if ( window.wpseoScriptData && window.wpseoScriptData.metabox ) { window.wpseoScriptData.metabox.contentLocale = %s; }',
			wp_json_encode( $locale )
		);

		foreach ( self::YOAST_EDITOR_SCRIPT_HANDLES as $handle ) {
			wp_add_inline_script( $handle, $script, 'before' );
		}
	}

	private function getLanguageOfEditedElement() {
		global $pagenow;

		$postId   = absint( $_GET['post'] ?? 0 );
		$termId   = absint( $_GET['tag_ID'] ?? 0 );
		$taxonomy = sanitize_key( wp_unslash( $_GET['taxonomy'] ?? '' ) );

		if ( 'post.php' === $pagenow && $postId ) {
			return $this->getElementLanguage( $postId, (string) get_post_type( $postId ) );
		}

		if ( 'post-new.php' === $pagenow ) {
			return $this->sitepress->get_current_language();
		}

		if ( 'term.php' === $pagenow && $termId && $taxonomy ) {
			return $this->getTermLanguage( $termId, $taxonomy );
		}

		return null;
	}

	private function getTermLanguage( $termId, $taxonomy ) {
		$term = get_term( $termId, $taxonomy );

		return $term instanceof \WP_Term ? $this->getElementLanguage( $term->term_taxonomy_id, $taxonomy ) : null;
	}

	private function getElementLanguage( $elementId, $elementType ) {
		return apply_filters(
			'wpml_element_language_code',
			null,
			[
				'element_id'   => $elementId,
				'element_type' => $elementType,
			]
		) ?: null;
	}
}
