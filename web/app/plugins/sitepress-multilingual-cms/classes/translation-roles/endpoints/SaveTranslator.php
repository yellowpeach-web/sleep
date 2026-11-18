<?php

namespace WPML\TranslationRoles;

use WPML\Collect\Support\Collection;
use WPML\FP\Lst;
use WPML\FP\Obj;
use WPML\TranslationRoles\Service\TranslatorCapability;
use function WPML\Container\make;
use function WPML\FP\pipe;

class SaveTranslator extends SaveUser {

	public function run( Collection $data ) {

		$pairs = wpml_collect( $data->get( 'pairs' ) )
			->filter( pipe( Obj::prop( 'to' ), Lst::length() ) )
			->mapWithKeys( function ( $pair ) { return [ $pair['from'] => $pair['to'] ]; } )
			->toArray();

		return self::getUser( $data )
		           ->map( function ( $user ) use ( $pairs ) {
			           make( TranslatorCapability::class )->grant( $user->ID, $pairs, TranslatorCapability::PAIRS_SET );

			           return true;
		           } );
	}
}
