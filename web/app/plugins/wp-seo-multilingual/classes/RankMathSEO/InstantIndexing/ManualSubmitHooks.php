<?php

namespace WPML\WPSEO\RankMathSEO\InstantIndexing;

use WPML\WPSEO\Shared\IndexNow\SiteHomeUrlSwitch;

class ManualSubmitHooks extends SiteHomeUrlSwitch implements \IWPML_Frontend_Action, \IWPML_Backend_Action {

	const REST_SUBMIT_ROUTE  = '/rankmath/v1/in/submitUrls';
	const ROW_SUBMIT_ACTION  = 'rank_math_instant_index_post';
	const BULK_SUBMIT_ACTION = 'rank_math_indexnow';
	const NO_BULK_ACTION     = '-1';

	const PRIORITY_BEFORE_RANK_MATH = 9;
	const PRIORITY_AFTER_RANK_MATH  = 11;

	public function add_hooks() {
		add_filter( 'rest_request_before_callbacks', [ $this, 'useSiteHomeUrlForRestSubmit' ], 10, 3 );
		add_filter( 'rest_request_after_callbacks', [ $this, 'useLanguageHomeUrlAfterRestSubmit' ], 10, 3 );
		add_action( 'admin_init', [ $this, 'useSiteHomeUrlForRowSubmit' ], self::PRIORITY_BEFORE_RANK_MATH );
		add_action( 'load-edit.php', [ $this, 'useSiteHomeUrlForBulkSubmit' ] );
	}

	public function useSiteHomeUrlForRestSubmit( $response, $handler, \WP_REST_Request $request ) {
		if ( self::REST_SUBMIT_ROUTE === $request->get_route() ) {
			$this->useSiteHomeUrl();
		}

		return $response;
	}

	public function useLanguageHomeUrlAfterRestSubmit( $response, $handler, \WP_REST_Request $request ) {
		if ( self::REST_SUBMIT_ROUTE === $request->get_route() ) {
			$this->useLanguageHomeUrl();
		}

		return $response;
	}

	public function useSiteHomeUrlForRowSubmit() {
		if ( self::ROW_SUBMIT_ACTION === $this->getRequestParameter( 'action' ) ) {
			$this->useSiteHomeUrl();
			add_action( 'admin_init', [ $this, 'useLanguageHomeUrl' ], self::PRIORITY_AFTER_RANK_MATH );
		}
	}

	public function useSiteHomeUrlForBulkSubmit() {
		$screen = get_current_screen();

		if ( $screen && self::BULK_SUBMIT_ACTION === $this->getBulkAction() ) {
			$bulkActionHook = 'handle_bulk_actions-' . $screen->id;

			add_filter( $bulkActionHook, [ $this, 'useSiteHomeUrlForBulkHandler' ], self::PRIORITY_BEFORE_RANK_MATH );
			add_filter( $bulkActionHook, [ $this, 'useLanguageHomeUrlAfterBulkHandler' ], self::PRIORITY_AFTER_RANK_MATH );
		}
	}

	public function useSiteHomeUrlForBulkHandler( $redirectUrl ) {
		$this->useSiteHomeUrl();

		return $redirectUrl;
	}

	public function useLanguageHomeUrlAfterBulkHandler( $redirectUrl ) {
		$this->useLanguageHomeUrl();

		return $redirectUrl;
	}

	private function getBulkAction(): string {
		$topAction = $this->getRequestParameter( 'action' );

		return self::NO_BULK_ACTION === $topAction || '' === $topAction
			? $this->getRequestParameter( 'action2' )
			: $topAction;
	}

	private function getRequestParameter( string $name ): string {
		if ( ! isset( $_REQUEST[ $name ] ) || ! is_string( $_REQUEST[ $name ] ) ) {
			return '';
		}

		return sanitize_key( wp_unslash( $_REQUEST[ $name ] ) );
	}
}
