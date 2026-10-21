<?php

namespace WPML\LanguageEditor\Endpoint;

use WPML\Ajax\IHandler;
use WPML\Collect\Support\Collection;
use WPML\FP\Either;
use WPML\Posts\TranslatedContentOfLanguages;

class ListLanguageContent implements IHandler {

	const DEFAULT_LIMIT = 25;

	const MAX_LIMIT = 200;

	public function run( Collection $data ) {
		if ( ! current_user_can( 'manage_options' ) ) {
			return Either::left( array( 'error' => 'forbidden' ) );
		}

		$code = sanitize_text_field( (string) $data->get( 'code', '' ) );

		if ( '' === $code ) {
			return Either::left( array( 'error' => 'missing_code' ) );
		}

		$include = $data->get( 'include', null );
		$route   = (string) $data->get( 'route', TranslatedContentOfLanguages::ROUTE_PERMANENT );

		return Either::right(
			TranslatedContentOfLanguages::itemsFiltered(
				array( $code ),
				is_array( $include ) ? $include : TranslatedContentOfLanguages::allTypes(),
				TranslatedContentOfLanguages::ROUTE_TRASH === $route
					? TranslatedContentOfLanguages::ROUTE_TRASH
					: TranslatedContentOfLanguages::ROUTE_PERMANENT,
				max( 0, (int) $data->get( 'offset', 0 ) ),
				$this->limit( $data->get( 'limit', self::DEFAULT_LIMIT ) )
			)
		);
	}

	private function limit( $wanted ) {
		$limit = (int) $wanted;

		if ( $limit < 1 ) {
			return self::DEFAULT_LIMIT;
		}

		return min( $limit, self::MAX_LIMIT );
	}
}
