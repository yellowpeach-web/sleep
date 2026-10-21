<?php

namespace WPML\WPSEO\IndexNowPlugin;

use WPML\WPSEO\Shared\IndexNow\SiteHomeUrlSwitch;

class SubmitHooks extends SiteHomeUrlSwitch implements \IWPML_Frontend_Action, \IWPML_Backend_Action {

	const REST_SUBMIT_ROUTE_PATTERN = '#^/indexnow/v_[^/]+/(submitUrl|allSubmissions)/?$#';
	const PUBLISH_SUBMIT_ACTION     = 'transition_post_status';
	const RETRY_SUBMIT_ACTION       = 'indexnow_process_retry_queue';
	const PUBLISHED_STATUS          = 'publish';
	const TRASHED_STATUS            = 'trash';

	const PRIORITY_BEFORE_INDEXNOW = 9;
	const PRIORITY_AFTER_INDEXNOW  = 11;

	public function add_hooks() {
		add_filter( 'rest_request_before_callbacks', [ $this, 'useSiteHomeUrlForRestSubmit' ], 10, 3 );
		add_filter( 'rest_request_after_callbacks', [ $this, 'useLanguageHomeUrlAfterRestSubmit' ], 10, 3 );
		add_action( self::PUBLISH_SUBMIT_ACTION, [ $this, 'useSiteHomeUrlForPublishSubmit' ], self::PRIORITY_BEFORE_INDEXNOW, 2 );
		add_action( self::PUBLISH_SUBMIT_ACTION, [ $this, 'useLanguageHomeUrlAfterPublishSubmit' ], self::PRIORITY_AFTER_INDEXNOW, 2 );
		add_action( self::RETRY_SUBMIT_ACTION, [ $this, 'useSiteHomeUrl' ], self::PRIORITY_BEFORE_INDEXNOW, 0 );
		add_action( self::RETRY_SUBMIT_ACTION, [ $this, 'useLanguageHomeUrl' ], self::PRIORITY_AFTER_INDEXNOW, 0 );
	}

	public function useSiteHomeUrlForRestSubmit( $response, $handler, \WP_REST_Request $request ) {
		if ( $this->isRestSubmit( $request ) ) {
			$this->useSiteHomeUrl();
		}

		return $response;
	}

	public function useLanguageHomeUrlAfterRestSubmit( $response, $handler, \WP_REST_Request $request ) {
		if ( $this->isRestSubmit( $request ) ) {
			$this->useLanguageHomeUrl();
		}

		return $response;
	}

	public function useSiteHomeUrlForPublishSubmit( $newStatus, $oldStatus ) {
		if ( $this->isPublishSubmit( $newStatus, $oldStatus ) ) {
			$this->useSiteHomeUrl();
		}
	}

	public function useLanguageHomeUrlAfterPublishSubmit( $newStatus, $oldStatus ) {
		if ( $this->isPublishSubmit( $newStatus, $oldStatus ) ) {
			$this->useLanguageHomeUrl();
		}
	}

	private function isRestSubmit( \WP_REST_Request $request ): bool {
		return (bool) preg_match( self::REST_SUBMIT_ROUTE_PATTERN, $request->get_route() );
	}

	private function isPublishSubmit( $newStatus, $oldStatus ): bool {
		return self::PUBLISHED_STATUS === $newStatus
			|| ( self::PUBLISHED_STATUS === $oldStatus && self::TRASHED_STATUS === $newStatus );
	}
}
