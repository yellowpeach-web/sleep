<?php

namespace WPML\TranslationRoles;

use WPML\Collect\Support\Collection;
use WPML\Ajax\IHandler;
use WPML\FP\Either;
use WPML\FP\Fns;
use WPML\LIB\WP\User;
use WPML\TranslationRoles\Service\TranslationManagerCapability;
use WPML\TranslationRoles\Service\TranslatorCapability;
use function WPML\Container\make;
use function WPML\FP\invoke;

abstract class Remove implements IHandler {

	const ROLE_SERVICES = [
		User::CAP_TRANSLATE           => TranslatorCapability::class,
		User::CAP_MANAGE_TRANSLATIONS => TranslationManagerCapability::class,
	];

	public function run( Collection $data ) {
		return Either::fromNullable( $data->get( 'ID' ) )
		             ->map( User::get() )
		             ->filter( invoke( 'exists' ) )
		             ->map( function ( $user ) {
			             make( self::ROLE_SERVICES[ static::getCap() ] )->revoke( $user->ID );

			             return $user;
		             } )
		             ->bimap( Fns::always( $this->msgUserNotFound() ), Fns::always( true ) );
	}

	abstract protected static function getCap();

	protected function msgUserNotFound() {
		return __( 'User not found', 'sitepress' );
	}
}
