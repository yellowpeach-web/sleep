<?php

namespace WPML\WPSEO\Shared\RestHead;

use WPML\WPSEO\Shared\Sitemap\HomeUrl;

abstract class BaseLanguageHooks implements \IWPML_REST_Action, \IWPML_DIC_Action {

	const METHODS_OF_HEAD_ROUTE = [ 'GET', 'HEAD' ];

	protected $sitepress;

	private $isLanguageSwitched = false;

	private $refererOfRequest;

	private $preparedRequest;

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
		add_filter( 'rest_pre_dispatch', [ $this, 'restoreLanguageWhenAnsweredBeforeCallbacks' ], PHP_INT_MAX, 3 );
		add_filter( 'rest_request_after_callbacks', [ $this, 'restoreLanguage' ], 10, 3 );
		add_filter( 'rest_post_dispatch', [ $this, 'restoreLanguage' ], 10, 3 );
	}

	public function prepareHeadOfRequestedUrl( $result, \WP_REST_Server $server, \WP_REST_Request $request ) {
		if ( null === $result && ! $this->isLanguageSwitched && $this->isHeadRequest( $request ) ) {
			$language = $this->getLanguageOfRequestedUrl( $request );

			$this->sitepress->switch_lang( $language );
			$this->isLanguageSwitched = true;
			$this->preparedRequest    = $request;
			$this->onLanguageSwitched();
			$this->restoreAfterAnswersOfLaterFilters();
			$this->forgetRewriteMatchOfRestRoute();
			$this->hideRefererOfRequest();

			if ( $this->isHomeUrlOfLanguage( $request->get_param( 'url' ), $language ) ) {
				add_action( 'pre_get_posts', [ $this, 'markMainQueryAsHomePage' ] );
			}
		}

		return $result;
	}

	public function restoreLanguageWhenAnsweredBeforeCallbacks( $result, $server = null, $request = null ) {
		if ( ! empty( $result ) ) {
			$this->restoreLanguage( $result, $server, $request );
		}

		return $result;
	}

	public function restoreLanguage( $response, $handlerOrServer = null, $request = null ) {
		if ( $this->isLanguageSwitched && $this->isPreparedRequest( $request ) ) {
			$this->sitepress->switch_lang();
			$this->isLanguageSwitched = false;
			$this->preparedRequest    = null;
			$this->onLanguageSwitched();
			$this->restoreRefererOfRequest();
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
		return 0 === strcasecmp( $this->getHeadRoute(), $request->get_route() )
			&& in_array( $request->get_method(), self::METHODS_OF_HEAD_ROUTE, true )
			&& is_string( $request->get_param( 'url' ) );
	}

	private function isPreparedRequest( $request ) {
		return null === $request || $request === $this->preparedRequest;
	}

	protected function getLanguageOfRequestedUrl( \WP_REST_Request $request ) {
		global $wpml_url_converter;

		return (string) $wpml_url_converter->get_language_of_url( $request->get_param( 'url' ) );
	}

	private function restoreAfterAnswersOfLaterFilters() {
		remove_filter( 'rest_pre_dispatch', [ $this, 'restoreLanguageWhenAnsweredBeforeCallbacks' ], PHP_INT_MAX );
		add_filter( 'rest_pre_dispatch', [ $this, 'restoreLanguageWhenAnsweredBeforeCallbacks' ], PHP_INT_MAX, 3 );
	}

	private function forgetRewriteMatchOfRestRoute() {
		global $wp;

		if ( $wp ) {
			$wp->matched_rule  = '';
			$wp->matched_query = '';
		}
	}

	private function hideRefererOfRequest() {
		$serverVariables = $_SERVER;
		if ( isset( $serverVariables['HTTP_REFERER'] ) ) {
			$this->refererOfRequest = $serverVariables['HTTP_REFERER'];
			unset( $_SERVER['HTTP_REFERER'] );
		}
	}

	private function restoreRefererOfRequest() {
		if ( null !== $this->refererOfRequest ) {
			$_SERVER['HTTP_REFERER'] = $this->refererOfRequest;
			$this->refererOfRequest  = null;
		}
	}

	private function isHomeUrlOfLanguage( string $url, string $language ): bool {
		return untrailingslashit( $url ) === untrailingslashit( HomeUrl::get( $language ) );
	}
}
