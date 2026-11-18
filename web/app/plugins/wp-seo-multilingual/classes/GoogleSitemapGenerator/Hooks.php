<?php

namespace WPML\WPSEO\GoogleSitemapGenerator;

class Hooks implements \IWPML_Frontend_Action, \IWPML_Backend_Action, \IWPML_DIC_Action {

	const HANDED_OVER_FILTER = 'wpml_google_sitemap_generator_handed_over';

	private $wpdb;

	private $sitepress;

	private $isBuildingPermalinkInPostLanguage = false;

	public function __construct( \wpdb $wpdb, \SitePress $sitepress ) {
		$this->wpdb      = $wpdb;
		$this->sitepress = $sitepress;
	}

	public function add_hooks() {
		add_filter( self::HANDED_OVER_FILTER, '__return_true' );

		if ( ! is_admin() && $this->isPerDomain() ) {
			add_filter( 'option_sm_options', [ $this, 'excludeOtherLanguages' ] );
		}

		add_action( 'sm_build_content', [ $this, 'addPermalinkHooks' ], 1 );
	}

	public function addPermalinkHooks() {
		add_filter( 'page_link', [ $this, 'filterPermalink' ], 10, 2 );
		add_filter( 'post_link', [ $this, 'filterPermalink' ], 10, 2 );
		add_filter( 'post_type_link', [ $this, 'filterPermalink' ], 10, 2 );
	}

	public function filterPermalink( $permalink, $post ) {
		$postId       = $post instanceof \WP_Post ? $post->ID : $post;
		$languageCode = $this->sitepress->get_language_for_element( $postId, 'post_' . get_post_type( $postId ) );

		if ( $this->isBuildingPermalinkInPostLanguage || ! $languageCode || $languageCode === $this->sitepress->get_current_language() ) {
			return $this->sitepress->convert_url( $permalink, $languageCode );
		}

		return $this->getPermalinkInLanguage( $postId, $languageCode );
	}

	public function excludeOtherLanguages( $options ) {
		$currentLanguage = apply_filters( 'wpml_current_language', false );
		$wpdb            = $this->wpdb;
		$postIds         = $wpdb->get_col(
			$wpdb->prepare(
				"SELECT element_id FROM {$wpdb->prefix}icl_translations WHERE element_type LIKE %s AND language_code <> %s",
				$wpdb->esc_like( 'post_' ) . '%',
				$currentLanguage
			)
		);
		$postIds         = array_map( 'intval', $postIds );

		if ( ! is_array( $options ) ) {
			$options = [];
		}

		if ( ! array_key_exists( 'sm_b_exclude', $options ) || ! is_array( $options['sm_b_exclude'] ) ) {
			$options['sm_b_exclude'] = [];
		}

		$options['sm_b_exclude'] = array_merge( $options['sm_b_exclude'], $postIds );

		return $options;
	}

	private function getPermalinkInLanguage( int $postId, string $languageCode ) {
		$this->isBuildingPermalinkInPostLanguage = true;
		$this->sitepress->switch_lang( $languageCode );

		try {
			return get_permalink( $postId );
		} finally {
			$this->sitepress->switch_lang();
			$this->isBuildingPermalinkInPostLanguage = false;
		}
	}

	private function isPerDomain() {
		return WPML_LANGUAGE_NEGOTIATION_TYPE_DOMAIN === (int) $this->sitepress->get_setting( 'language_negotiation_type', false );
	}
}
