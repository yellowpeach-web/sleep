<?php

namespace WPML\TM\ATE\AutoTranslate\Endpoint;

use WPML\Ajax\IHandler;
use WPML\API\Settings;
use WPML\Collect\Support\Collection;
use WPML\FP\Either;
use WPML\FP\Fns;
use WPML\FP\Lst;
use WPML\FP\Obj;
use WPML\LIB\WP\User;
use WPML\Setup\Option;
use WPML\TM\API\ATE\LanguageMappings;
use WPML\TM\ATE\Network\SubsiteAttach;
use WPML\TM\ATE\Network\SubsiteAttachState;
use WPML\TranslationPolicy\Migration\LateLegacyWrites;
use function WPML\Container\make;

class EnableATE implements IHandler {


	public function run( Collection $data ) {
		return $this->enable();
	}

	public function enable() {
		make( LateLegacyWrites::class )->turnAccountOn(
			fn() => Settings::assoc( 'translation-management', 'doc_translation_method', ICL_TM_TMETHOD_ATE )
		);

		$cache = wpml_get_cache( \WPML_Translation_Roles_Records::CACHE_GROUP );
		$cache->flush_group_cache();

		$ateApi = make( \WPML_TM_AMS_API::class );
		$status = $ateApi->get_status();
		if ( Obj::propOr( false, 'activated', $status ) && $this->holdsKeys() ) {
			$result = Either::right( true );
		} else {
			if ( SubsiteAttach::PENDING === make( SubsiteAttach::class )->beforeRegistration() ) {
				$pending = [ 'subsiteAttach' => SubsiteAttachState::clientState() ];
				self::logFailure(
					[
						'subsiteAttach' => [
							'pending'               => true,
							'canUseSeparateAccount' => $pending['subsiteAttach']['canUseSeparateAccount'],
						],
					]
				);

				return Either::left( $pending );
			}

			$amsUsers = make( \WPML_TM_AMS_Users::class );

			$amsApi = make( \WPML_TM_AMS_API::class );

			$saveLanguageMapping = Fns::tap( [ $this, 'forwardStoredMappings' ] );

			$result = $amsApi->register_manager(
				User::getCurrent(),
				$amsUsers->get_translators(),
				$amsUsers->get_managers()
			)->map( $saveLanguageMapping );

			$ateApi->get_status();
		}

		return $result->map( Fns::tap( [ make( \WPML_TM_AMS_Synchronize_Actions::class ), 'synchronize_translators' ] ) )
									->map( $this->confirmSiteKey() )
		              ->bimap(
		              	$this->formatError(),
		              	Fns::identity()
		              );
	}

	private function holdsKeys() {
		$auth = make( \WPML_TM_ATE_Authentication::class );

		return (bool) $auth->has_keys();
	}

	public function forwardStoredMappings() {
		list( $kept, $refused, , $defaultOntoSecondary ) = LanguageMappings::partitionMappingsOntoDefaultLanguage( (array) Option::getLanguageMappings() );

		$ontoDefault = [];
		foreach ( $refused as $mapping ) {
			Option::removeLanguageMapping( Obj::prop( 'sourceCode', $mapping ) );
			if ( ! in_array( $mapping, $defaultOntoSecondary, true ) ) {
				$ontoDefault[] = $mapping;
			}
		}
		if ( $ontoDefault ) {
			LanguageMappings::logMappings(
				$ontoDefault,
				__( 'A stored language mapping onto the default language was removed and not sent to ATE.', 'sitepress' )
			);
		}
		if ( $defaultOntoSecondary ) {
			LanguageMappings::logMappings(
				$defaultOntoSecondary,
				__( 'A stored mapping of the default language onto one of the site\'s other languages was removed and not sent to ATE.', 'sitepress' )
			);
		}

		if ( $kept ) {
			LanguageMappings::saveMapping( $kept );
		}
	}

	private function confirmSiteKey() {
		return Fns::tap(function() {
			$confirmationService = make( \WPML\TM\ATE\Sitekey\SitekeyConfirmationService::class );
			$confirmationService->confirm();
		});
	}

	private function formatError() {
		return function( $errorData ) {
			self::logFailure( $errorData );

			return [ 'error' => $errorData ];
		};
	}

	public static function logFailure( $errorData ) {
		$entry              = new \WPML\TM\ATE\Log\Entry();
		$entry->eventType   = \WPML\TM\ATE\Log\EventsTypes::SERVER_AMS;
		$entry->description = __( 'Enabling Automatic Translation (ATE registration) failed.', 'sitepress' );
		$entry->extraData   = [ 'errorData' => $errorData ];
		wpml_tm_ate_ams_log( $entry );
	}
}
