<?php

namespace WPML\Setup\Endpoint;

use WPML\Ajax\IHandler;
use WPML\Collect\Support\Collection;
use WPML\Core\LanguageNegotiation;
use WPML\FP\Either;
use WPML\FP\Fns;
use WPML\FP\Logic;
use WPML\FP\Obj;
use WPML\FP\Relation;
use WPML\FP\Right;
use WPML\FP\Str;
use function WPML\Container\make;
use function WPML\FP\chain;
use function WPML\FP\pipe;
use function WPML\FP\System\sanitizeString;

class AddressStep implements IHandler {

	const DOMAIN_MODE_UNAVAILABLE = 'This option is not yet available for Multisite installs';

	public function run( Collection $data ) {

		$isDomainMode    = Relation::propEq( 'mode', LanguageNegotiation::DOMAIN_STRING );
		$isDirectoryMode = Relation::propEq( 'mode', LanguageNegotiation::DIRECTORY_STRING );
		$otherWise       = Fns::always( true );

		if ( $isDomainMode( $data ) && ! LanguageNegotiation::isDomainModeAvailable() ) {
			return Either::left( __( 'This option is not yet available for Multisite installs', 'sitepress' ) );
		}

		$handleModes = Logic::cond( [
			[ $isDomainMode, $this->handleDomains() ],
			[ $isDirectoryMode, $this->validateSubdirectoryUrls() ],
			[ $otherWise, Fns::identity() ]
		] );

		return Right::of( $data )
		            ->chain( $handleModes )
		            ->map( Obj::prop( 'mode' ) )
		            ->map( sanitizeString() )
		            ->map( LanguageNegotiation::saveMode() )
		            ->map( Fns::always( 'ok' ) );
	}

	private function validateDomains() {
		return function ( $data ) {
			if ( Obj::propOr( false, 'ignoreInvalidUrls', $data ) ) {
				return Either::of( $data );
			}

			$validator = make( \WPML_Language_Domain_Validation::class );
			$scheme    = wpml_parse_url( get_home_url(), PHP_URL_SCHEME ) ?: 'http';
			$failed    = wpml_collect( $data->get( 'domains' ) )
				->map(
					function ( $domain ) use ( $validator, $scheme ) {
						return $validator->validate( \WPML_Language_Domains::baseUrlOf( $domain, $scheme ) );
					}
				)
				->reject( Relation::equals( \WPML_Language_Domain_Validation::VALID ) )
				->toArray();

			return $failed
				? Either::left( $failed )
				: Either::of( $data );
		};
	}

	private function normalizeDomains() {
		return function ( Collection $data ) {
			$stored  = LanguageNegotiation::getDomains();
			$stored  = is_array( $stored ) ? $stored : [];
			$domains = [];
			$failed  = [];

			foreach ( (array) $data->get( 'domains', [] ) as $code => $domain ) {
				$domain = \WPML_Language_Domains::to_store( is_scalar( $domain ) ? (string) $domain : '', isset( $stored[ $code ] ) ? $stored[ $code ] : null );

				if ( \WPML_Language_Domains::is_normalize_error( $domain ) ) {
					$failed[ $code ] = $domain;
				} else {
					$domains[ $code ] = $domain;
				}
			}

			return $failed
				? Either::left( $failed )
				: Either::of( $data->put( 'domains', $domains ) );
		};
	}

	private function handleDomains() {
		$saveDomains = Fns::tap( pipe( Obj::prop( 'domains' ), LanguageNegotiation::saveDomains() ) );

		return pipe( $this->normalizeDomains(), chain( $this->validateDomains() ), chain( $saveDomains ) );
	}

	private function validateSubdirectoryUrls() {
		return function ( Collection $data ) {
			return $this->validate( \wpml_collect( $data->get( 'domains' ) )->map(  Fns::nthArg( 1 ) ), $data );
		};
	}

	private function validate( Collection $domains, Collection $data ) {
		$unavailableUrls = $domains->reject( $this->getValidator( $data ) )->keys()->toArray();

		return $unavailableUrls
			? Either::left( $unavailableUrls )
			: Either::of( $data );
	}

	private function getValidator( Collection $data ) {
		return Obj::propOr( false, 'ignoreInvalidUrls', $data )
			? Fns::always( true )
			: Fns::unary( [ make( \WPML_Lang_URL_Validator::class ), 'validate_langs_in_dirs' ] );
	}
}
