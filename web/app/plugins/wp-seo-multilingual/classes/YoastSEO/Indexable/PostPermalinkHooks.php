<?php

namespace WPML\WPSEO\YoastSEO\Indexable;

use Yoast\WP\SEO\Models\Indexable;

class PostPermalinkHooks implements \IWPML_Backend_Action, \IWPML_Frontend_Action, \IWPML_DIC_Action {

	private $sitepress;

	public function __construct( \SitePress $sitepress ) {
		$this->sitepress = $sitepress;
	}

	public function add_hooks() {
		add_filter( 'wpseo_should_save_indexable', [ $this, 'setPostPermalinkInPostLanguage' ], 10, 2 );
	}

	public function setPostPermalinkInPostLanguage( $shouldSave, $indexable ) {
		if ( ! $shouldSave || ! $this->hasPostPermalink( $indexable ) ) {
			return $shouldSave;
		}

		$postLanguage = $this->sitepress->get_language_for_element(
			$indexable->object_id,
			'post_' . $indexable->object_sub_type
		);

		if ( $postLanguage && $postLanguage !== $this->sitepress->get_current_language() ) {
			$this->sitepress->switch_lang( $postLanguage );
			try {
				$indexable->permalink = get_permalink( $indexable->object_id );
			} finally {
				$this->sitepress->switch_lang();
			}
		}

		return $shouldSave;
	}

	private function hasPostPermalink( $indexable ) {
		return 'post' === $indexable->object_type
			&& 'attachment' !== $indexable->object_sub_type
			&& $indexable->object_id;
	}
}
