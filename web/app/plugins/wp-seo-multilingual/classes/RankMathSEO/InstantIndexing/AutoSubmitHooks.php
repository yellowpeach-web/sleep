<?php

namespace WPML\WPSEO\RankMathSEO\InstantIndexing;

use WPML\WPSEO\Shared\IndexNow\SiteHomeUrlSwitch;

class AutoSubmitHooks extends SiteHomeUrlSwitch implements \IWPML_Frontend_Action, \IWPML_Backend_Action, \IWPML_DIC_Action {

	const PUBLISH_URL_FILTER      = 'rank_math/instant_indexing/publish_url';
	const SAVE_POST_ACTION_PREFIX = 'save_post_';
	const DESIRED_SLUG_META_KEY   = '_wp_desired_post_slug';

	const PRIORITY_AFTER_RANK_MATH = 11;

	private $sitepress;

	public function __construct( \SitePress $sitepress ) {
		$this->sitepress = $sitepress;
	}

	public function add_hooks() {
		add_filter( self::PUBLISH_URL_FILTER, [ $this, 'submitPermalinkWithSiteKeyLocation' ], 10, 2 );
	}

	public function submitPermalinkWithSiteKeyLocation( $url, $post ) {
		if ( ! $post instanceof \WP_Post ) {
			return $url;
		}

		if ( $this->isHomeUrlGluedToSlug( $url, $post ) ) {
			$url = $this->getPermalinkInPostLanguage( $post );
		}

		$this->useSiteHomeUrl();
		add_action( self::SAVE_POST_ACTION_PREFIX . $post->post_type, [ $this, 'useLanguageHomeUrl' ], self::PRIORITY_AFTER_RANK_MATH );

		return $url;
	}

	private function isHomeUrlGluedToSlug( $url, \WP_Post $post ): bool {
		return '' !== $post->post_name && get_home_url() . $post->post_name === $url;
	}

	private function getPermalinkInPostLanguage( \WP_Post $post ): string {
		$postLanguage = $this->sitepress->get_language_for_element( $post->ID, 'post_' . $post->post_type );
		$isSwitched   = $postLanguage && $postLanguage !== $this->sitepress->get_current_language();

		if ( $isSwitched ) {
			$this->sitepress->switch_lang( $postLanguage );
		}

		try {
			return (string) get_permalink( $this->getPublishedPost( $post ) );
		} finally {
			if ( $isSwitched ) {
				$this->sitepress->switch_lang();
			}
		}
	}

	private function getPublishedPost( \WP_Post $post ): \WP_Post {
		if ( 'trash' !== $post->post_status ) {
			return $post;
		}

		$publishedPost              = clone $post;
		$publishedPost->post_status = 'publish';
		$publishedPost->post_name   = (string) get_post_meta( $post->ID, self::DESIRED_SLUG_META_KEY, true ) ?: $post->post_name;

		return $publishedPost;
	}
}
