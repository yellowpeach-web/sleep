<?php

namespace WPML\WPSEO\Shared\EditScreen;

use SitePress;

class EditedElementLanguage {

	private $sitepress;

	public function __construct( SitePress $sitepress ) {
		$this->sitepress = $sitepress;
	}

	public function get() {
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
