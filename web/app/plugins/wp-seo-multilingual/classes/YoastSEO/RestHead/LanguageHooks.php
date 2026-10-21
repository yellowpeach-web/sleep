<?php

namespace WPML\WPSEO\YoastSEO\RestHead;

use WPML\WPSEO\Shared\RestHead\BaseLanguageHooks;

class LanguageHooks extends BaseLanguageHooks {

	const HEAD_ROUTE = '/yoast/v1/get_head';

	protected function getHeadRoute() {
		return self::HEAD_ROUTE;
	}

	public function add_hooks() {
		parent::add_hooks();
		add_filter( 'rest_dispatch_request', [ $this, 'getHeadOfSecondaryLanguageUrl' ], 10, 2 );
	}

	public function getHeadOfSecondaryLanguageUrl( $result, \WP_REST_Request $request ) {
		if ( null !== $result
			|| ! $this->isHeadRequest( $request )
			|| $this->sitepress->get_default_language() === $this->getLanguageOfRequestedUrl( $request )
		) {
			return $result;
		}

		return $this->getHeadOfFrontEndPage( $request->get_param( 'url' ) );
	}

	private function getHeadOfFrontEndPage( string $url ): \WP_REST_Response {
		$serverVariables = $_SERVER;
		$path            = (string) wp_parse_url( $url, PHP_URL_PATH );
		$query           = (string) wp_parse_url( $url, PHP_URL_QUERY );

		try {
			$_SERVER['REQUEST_URI'] = ( $path ?: '/' ) . ( $query ? '?' . $query : '' );
			unset( $_SERVER['PATH_INFO'] );

			$this->queryFrontEndPage( $this->getQueryVariables( $query ) );

			$head   = YoastSEO()->meta->for_current_page()->get_head();
			$status = is_404() ? 404 : 200;
		} finally {
			$_SERVER = $serverVariables;
		}

		return new \WP_REST_Response(
			(object) [
				'html'   => $head->html,
				'json'   => $head->json,
				'status' => $status,
			],
			$status
		);
	}

	private function queryFrontEndPage( array $queryVariables ) {
		global $wp;

		$wp->parse_request( $queryVariables );
		$wp->query_posts();
		$wp->handle_404();
		$wp->register_globals();
	}

	private function getQueryVariables( string $query ): array {
		$queryVariables = [];
		parse_str( $query, $queryVariables );

		return $queryVariables;
	}
}
