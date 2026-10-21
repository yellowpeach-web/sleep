<?php

namespace WPML\WPSEO\Shared\RestHead;

use WPML\WPSEO\Shared\Sitemap\HomeUrl;

abstract class BaseLanguageHooks implements \IWPML_REST_Action, \IWPML_DIC_Action {

	protected $sitepress;

	private $isLanguageSwitched = false;

	public function __construct( \SitePress $sitepress ) {
		$this->sitepress = $sitepress;
	}

	abstract protected function getHeadRoute();

	protected function onLanguageSwitched() {
	}

	protected function isLanguageSwitched(): bool {
		return $this->isLanguageSwitched;
	}

	public function add_hooks() {
		add_filter( 'rest_pre_dispatch', [ $this, 'prepareHeadOfRequestedUrl' ], 10, 3 );
		add_filter( 'rest_request_after_callbacks', [ $this, 'restoreLanguage' ] );
		add_filter( 'rest_post_dispatch', [ $this, 'restoreLanguage' ] );
	}

	public function prepareHeadOfRequestedUrl( $result, \WP_REST_Server $server, \WP_REST_Request $request ) {
		if ( null === $result && $this->isHeadRequest( $request ) ) {
			$language = $this->getLanguageOfRequestedUrl( $request );

			$this->sitepress->switch_lang( $language );
			$this->isLanguageSwitched = true;
			$this->onLanguageSwitched();
			$this->forgetRewriteMatchOfRestRoute();

			if ( $this->isHomeUrlOfLanguage( $request->get_param( 'url' ), $language ) ) {
				add_action( 'pre_get_posts', [ $this, 'markMainQueryAsHomePage' ] );
			}
		}

		return $result;
	}

	public function restoreLanguage( $response ) {
		if ( $this->isLanguageSwitched ) {
			$this->sitepress->switch_lang();
			$this->isLanguageSwitched = false;
			$this->onLanguageSwitched();
			remove_action( 'pre_get_posts', [ $this, 'markMainQueryAsHomePage' ] );
		}

		return $response;
	}

	public function markMainQueryAsHomePage( $query ) {
		if ( $query->is_main_query() && 'posts' === get_option( 'show_on_front' ) ) {
			$query->is_home = true;
		}
	}

	protected function isHeadRequest( \WP_REST_Request $request ) {
		return 0 === strcasecmp( $this->getHeadRoute(), rtrim( $request->get_route(), '/' ) )
			&& is_string( $request->get_param( 'url' ) );
	}

	protected function getLanguageOfRequestedUrl( \WP_REST_Request $request ) {
		return (string) $this->sitepress->get_language_from_url( $request->get_param( 'url' ) );
	}

	private function forgetRewriteMatchOfRestRoute() {
		global $wp;

		if ( $wp ) {
			$wp->matched_rule  = '';
			$wp->matched_query = '';
		}
	}

	private function isHomeUrlOfLanguage( string $url, string $language ): bool {
		return untrailingslashit( $url ) === untrailingslashit( HomeUrl::get( $language ) );
	}
}
