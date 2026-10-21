<?php

use WPML\WPSEO\YoastSEO\Utils;

class WPML_WPSEO_Filters implements IWPML_Action {

	private $canonicals;

	private $user_meta_fields = [
		Utils::KEY_META_TITLE,
		Utils::KEY_USER_META_DESC,
		Utils::KEY_USER_PRONOUNS,
	];

	public function __construct( WPML_Canonicals $canonicals ) {
		$this->canonicals = $canonicals;
	}

	public function add_hooks() {
		add_filter( 'wpml_translatable_user_meta_fields', array( $this, 'translatable_user_meta_fields_filter' ) );
		add_action( 'wpml_before_make_duplicate', array( $this, 'before_make_duplicate_action' ) );
		add_filter( 'wpseo_canonical', array( $this, 'canonical_filter' ) );
		add_filter( 'wpml_must_translate_canonical_url', array( $this, 'must_translate_canonical_url_filter' ), 10, 2 );
		add_filter( 'wpseo_prev_rel_link', array( $this, 'rel_link_filter' ) );
		add_filter( 'wpseo_next_rel_link', array( $this, 'rel_link_filter' ) );
		add_filter( 'wpseo_opengraph_url', array( $this, 'opengraph_url_filter' ) );
	}

	public function translatable_user_meta_fields_filter( $fields ) {
		return array_merge( $this->user_meta_fields, $fields );
	}

	public function get_user_meta_fields() {
		return $this->user_meta_fields;
	}

	public function before_make_duplicate_action() {
		Utils::add_filter( 'wpseo_premium_post_redirect_slug_change', '__return_true' );
	}

	public function canonical_filter( $url ) {
		if ( ! $url ) {
			return $url;
		}

		$queriedObject = get_queried_object();

		if ( $queriedObject instanceof WP_Post ) {
			$url = $this->getUrlInPostLanguage( $url, $queriedObject );
		}

		if ( null === $queriedObject || $queriedObject instanceof WP_User ) {
			$url = $this->canonicals->get_general_canonical_url( $url );
		}

		return urlencode( $url );
	}

	public function must_translate_canonical_url_filter( $should_translate, $post_element ) {
		$post_id = $post_element->get_element_id();
		if ( $post_id && get_post_meta( $post_id, '_yoast_wpseo_canonical', true ) ) {
			return false;
		}

		return $should_translate;
	}

	public function rel_link_filter( $link ) {
		if ( preg_match( '/href="([^"]+)"/', $link, $matches ) ) {
			$canonical_url = $this->canonicals->get_general_canonical_url( $matches[1] );
			$link          = str_replace( $matches[1], $canonical_url, $link );
		}

		return $link;
	}

	public function opengraph_url_filter( $url ) {
		if ( ! $url ) {
			return $url;
		}

		$queriedObject = get_queried_object();

		if ( $queriedObject instanceof WP_Post ) {
			$url = $this->getUrlInPostLanguage( $url, $queriedObject );
		}

		return urlencode( $url );
	}

	private function getUrlInPostLanguage( $url, WP_Post $post ) {
		return (string) $this->canonicals->get_canonical_url( $url, $post, '' );
	}
}
