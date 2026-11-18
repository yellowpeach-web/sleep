<?php

namespace WPML\TranslationRoles;

use WPML\API\Sanitize;
use WPML\Collect\Support\Collection;
use WPML\Ajax\IHandler;
use WPML\FP\Either;
use WPML\FP\Fns;
use WPML\FP\Lst;
use WPML\FP\Obj;

class FindAvailableByRole implements IHandler {

	const USER_SEARCH_LIMIT = 10;

	public function run( Collection $data ) {
		$search  = Sanitize::string( $data->get( 'search' ) );
		$records = [
			'translator' => \WPML_Translator_Records::class,
			'manager'    => \WPML_Translation_Manager_Records::class,
		];

		$searchUsers = function ( \WPML_Translation_Roles_Records $roleRecords ) use ( $search ) {
			return $roleRecords->search_for_users_without_capability( $search, self::USER_SEARCH_LIMIT, HighUserCount::isHigh() );
		};

		return Either::of( $data->get( 'role' ) )
		             ->filter( Lst::includes( Fns::__, Obj::keys( $records ) ) )
		             ->map( Obj::prop( Fns::__, $records ) )
		             ->map( Fns::make() )
		             ->map( $searchUsers );
	}
}
