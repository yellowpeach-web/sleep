<?php

namespace WPML\LanguageEditor\Endpoint;

use WPML\Ajax\IHandler;
use WPML\Collect\Support\Collection;
use WPML\Core\Component\LanguageEditor\Application\Service\SaveLanguagesService;
use WPML\Core\Component\LanguageEditor\Domain\CustomLanguageFormat;
use WPML\FP\Either;
use WPML\Localization\LanguagePacksOnVersionBump;
use WPML\LanguageEditor\Adapter\AteMapping;
use WPML\LanguageEditor\Adapter\LabelsRepository;
use WPML\LanguageEditor\Adapter\LanguageRepository;
use WPML\LanguageEditor\Adapter\Settings;
use WPML\LanguageEditor\Cache;
use WPML\LanguageEditor\LanguageDomains;
use WPML\LanguageEditor\LanguagesOrder;
use WPML\LanguageEditor\PageData;
use WPML\LanguageEditor\PostAddSetup;
use WPML\LanguageEditor\SetupWizardAnalytics;
use WPML\TM\ATE\AutoTranslate\Endpoint\ActivateLanguage;
use function WPML\Container\make;

class SaveLanguages implements IHandler {

	public function run( Collection $data ) {
		if ( ! current_user_can( 'manage_options' ) ) {
			return Either::left( [ 'error' => 'forbidden' ] );
		}

		global $sitepress;

		try {
			$oldActiveLanguages = $sitepress ? $sitepress->get_active_languages() : [];

			$previousDefault = $sitepress ? $sitepress->get_default_language() : null;

			$languages   = array_values( (array) $data->get( 'languages', [] ) );
			$defaultCode = (string) $data->get( 'defaultCode', '' );

			$domainMode    = $sitepress && LanguageDomains::isDomainMode( $sitepress );
			$posted        = $data->get( 'languageDomains', [] );
			$postedDomains = $domainMode ? LanguageDomains::posted( $posted, LanguageDomains::stored( $sitepress ) ) : [];
			$domainRefusal = $domainMode ? LanguageDomains::refusalForInvalid( $postedDomains, $posted ) : null;
			if ( null === $domainRefusal && $domainMode ) {
				$domainRefusal = LanguageDomains::refusalForSave( $sitepress, $languages, $defaultCode, $postedDomains );
			}
			if ( null !== $domainRefusal ) {
				return Either::left( $domainRefusal );
			}
			$demoted = $domainMode ? LanguageDomains::demoted( $sitepress, $defaultCode ) : null;
			if ( null !== $demoted && isset( $postedDomains[ $demoted ] ) ) {
				LanguageDomains::assign( $sitepress, $demoted, $postedDomains[ $demoted ], false );
			}

			$service = new SaveLanguagesService(
				new LanguageRepository(),
				new Settings(),
				new AteMapping(),
				new LabelsRepository()
			);

			$result = $service->run( $languages, $defaultCode );

			if ( ! $result->isOk() ) {
				return Either::left( $result->payload() );
			}

			$payload    = $result->payload();
			$addedCodes = array_map( 'strval', array_values( (array) ( $payload['added'] ?? [] ) ) );

			if ( $domainMode ) {
				foreach ( (array) ( $payload['added'] ?? [] ) as $clientCode => $activatedCode ) {
					$host = $postedDomains[ (string) $clientCode ] ?? $postedDomains[ (string) $activatedCode ] ?? null;
					if ( null !== $host ) {
						LanguageDomains::assign( $sitepress, (string) $activatedCode, $host );
					}
				}
			}
			foreach ( $addedCodes as $activatedCode ) {
				PostAddSetup::run( $activatedCode );
			}

			if ( ! LanguagePacksOnVersionBump::holdWhileStIsOutdated( $addedCodes ) ) {
				PostAddSetup::deferDownloadLanguagePacks( $addedCodes );
			}

			if ( $sitepress && $sitepress->get_default_language() !== $previousDefault ) {
				PostAddSetup::syncSiteLocaleOption();
			}

			$translationScopes = (array) $data->get( 'translationScopes', [] );
			foreach ( (array) ( $payload['added'] ?? [] ) as $clientCode => $activatedCode ) {
				$scope = $translationScopes[ $clientCode ]
					?? $translationScopes[ $activatedCode ]
					?? null;

				if ( null === $scope ) {
					continue;
				}

				make( ActivateLanguage::class )->run(
					wpml_collect(
						[
							'languages'                  => [ (string) $activatedCode ],
							'translate-existing-content' => 'existing' === $scope,
						]
					)
				);
			}

			$displayCodeErrors = [];
			if ( ! wpml_is_setup_complete() ) {
				$displayCodeErrors = self::persistDisplayCodesDuringSetup(
					array_values( (array) $data->get( 'languages', [] ) ),
					(array) ( $payload['added'] ?? [] )
				);
			}

			Cache::flush( $oldActiveLanguages );

			if ( $sitepress ) {
				LanguagesOrder::sync( $sitepress );
			}

			$active  = PageData::active();
			$default = (string) ( $payload['default'] ?? '' );

			if (
				class_exists( \WPML_Languages_Notices::class )
				&& function_exists( 'wpml_get_admin_notices' )
			) {
				( new \WPML_Languages_Notices( wpml_get_admin_notices() ) )
					->maybe_create_notice_missing_menu_items( count( (array) $active ) );
			}

			if ( class_exists( \WPML\Setup\Option::class ) ) {
				$secondaries = [];
				foreach ( (array) $active as $row ) {
					$code = isset( $row['code'] ) ? (string) $row['code'] : '';
					if ( '' !== $code && $code !== $default ) {
						$secondaries[] = $code;
					}
				}
				if ( '' !== $default ) {
					\WPML\Setup\Option::setOriginalLang( $default );
				}
				\WPML\Setup\Option::setTranslationLangs( $secondaries );
			}

			if ( ! wpml_is_setup_complete() ) {
				try {
					SetupWizardAnalytics::record( $data->get( 'analytics', [] ) );
				} catch ( \Throwable $e ) {
				}
			}

			return Either::right(
				[
					'active'  => $active,
					'default' => $default,
					'kept'    => self::keptClientCodes(
						array_values( (array) $data->get( 'languages', [] ) ),
						(array) ( $payload['added'] ?? [] ),
						(array) $active
					),
					'displayCodeErrors' => $displayCodeErrors,
				]
			);
		} catch ( \Throwable $e ) {
			return Either::left( self::exceptionPayload( $e ) );
		}
	}

	private static function keptClientCodes( array $languages, array $added, array $active ) {
		$activeCodes = [];
		foreach ( $active as $row ) {
			$row = (array) $row;
			if ( isset( $row['code'] ) ) {
				$activeCodes[ strtolower( (string) $row['code'] ) ] = true;
			}
		}

		$kept = [];
		foreach ( $languages as $row ) {
			$row  = (array) $row;
			$code = isset( $row['code'] ) ? (string) $row['code'] : '';
			if ( '' === $code ) {
				continue;
			}
			$activated = isset( $added[ $code ] ) ? strtolower( (string) $added[ $code ] ) : strtolower( $code );
			if ( isset( $activeCodes[ $activated ] ) ) {
				$kept[] = $code;
			}
		}

		return $kept;
	}

	private static function persistDisplayCodesDuringSetup( array $languages, array $added ): array {
		$repo = new LanguageRepository();
		$owners = null;
		$errors = [];

		foreach ( $languages as $row ) {
			$row  = (array) $row;
			$code = isset( $row['code'] ) ? (string) $row['code'] : '';

			if ( '' === $code || ! array_key_exists( 'displayCode', $row ) ) {
				continue;
			}
			if ( array_key_exists( $code, $added ) ) {
				continue;
			}

			$displayCode = strtolower( trim( (string) $row['displayCode'] ) );
			if ( '' === $displayCode ) {
				continue;
			}
			if ( '' !== CustomLanguageFormat::validateCode( $displayCode ) ) {
				continue;
			}

			if ( null === $owners ) {
				$owners = self::activeDisplayCodeOwners();
			}

			$owner = isset( $owners[ $displayCode ] ) ? $owners[ $displayCode ] : null;
			if ( null !== $owner && $owner !== strtolower( $code ) ) {
				$errors[] = [
					'code'        => $code,
					'error'       => 'display_code_in_use',
					'displayCode' => $displayCode,
				];
				continue;
			}

			if ( $repo->isActive( $code ) ) {
				$stored = $repo->setDisplayCode( $code, $displayCode ) ? $repo->storedDisplayCode( $code ) : null;
				if ( null === $stored || 0 !== strcasecmp( $stored, $displayCode ) ) {
					$errors[] = [
						'code'        => $code,
						'error'       => 'display_code_not_saved',
						'displayCode' => $displayCode,
					];
					continue;
				}

				foreach ( $owners as $taken => $ownerCode ) {
					if ( $ownerCode === strtolower( $code ) ) {
						unset( $owners[ $taken ] );
					}
				}
				$owners[ $displayCode ] = strtolower( $code );
			}
		}

		return $errors;
	}

	private static function activeDisplayCodeOwners(): array {
		$owners = [];

		foreach ( \WPML\Language\ActiveLanguagesReadModel::rows() as $code => $row ) {
			$row         = (array) $row;
			$displayCode = isset( $row['display_code'] ) ? strtolower( trim( (string) $row['display_code'] ) ) : '';

			if ( '' !== $displayCode ) {
				$owners[ $displayCode ] = strtolower( (string) $code );
			}
		}

		return $owners;
	}

	private static function exceptionPayload( \Throwable $e ): array {
		\WPML\PHP\Logger\error(
			sprintf(
				'SaveLanguages failed: %s: %s at %s:%d | trace: %s',
				get_class( $e ),
				$e->getMessage(),
				$e->getFile(),
				$e->getLine(),
				$e->getTraceAsString()
			)
		);

		return [
			'error'   => 'exception',
			'message' => $e instanceof \WPML\LanguageEditor\Adapter\SaveFailedException
				? $e->getMessage()
				: 'Internal server error.',
		];
	}
}
