<?php

namespace WPML\LanguageEditor\Endpoint;

use WPML\Ajax\IHandler;
use WPML\Collect\Support\Collection;
use WPML\FP\Either;
use WPML\FP\Obj;
use WPML\LanguageEditor\LanguageCodeResolution;
use WPML\LanguageEditor\MappingKind;
use WPML\Core\Component\ATE\Application\Service\ActiveEngineQueryInterface;
use WPML\Core\Component\ATE\Application\Service\Dto\EngineDto;
use WPML\Legacy\Component\ATE\Application\Service\ActiveEngineQuery;
use WPML\TM\API\ATE\CachedLanguageMappings;
use WPML\TM\API\ATE\LanguageMappings;
use WPML\TM\ATE\AutomaticTranslationCapabilities;
use WPML\Setup\Option;
use function WPML\Container\make;

class GetAutomaticTranslationInfo implements IHandler {

	const PTC_ENGINE = 'llm';

	private $activeEngineQuery;

	public function __construct( $activeEngineQuery = null ) {
		$this->activeEngineQuery = $activeEngineQuery;
	}

	public function run( Collection $data ) {
		if ( ! current_user_can( 'manage_options' ) ) {
			return Either::left( [ 'error' => 'forbidden' ] );
		}

		if ( ! AutomaticTranslationCapabilities::isAvailable() ) {
			return Either::right( [ 'enabled' => false, 'engine' => null, 'languages' => (object) [] ] );
		}

		try {
			$resolution = $this->activeEngineQuery()->resolve();

			if ( $resolution->isNoneEnabled() ) {
				$payload = [
					'enabled'         => true,
					'engine'          => null,
					'noEngineEnabled' => true,
					'settingsUrl'     => self::aiTranslationSettingsUrl(),
					'languages'       => (object) [],
				];
			} else {
				$engine = $this->engine( $resolution->getEngine() );

				if ( null === $engine ) {
					return Either::left( [ 'error' => 'ateUnreachable' ] );
				}

				global $sitepress;
				$activeLanguages = (array) $sitepress->get_active_languages();

				$payload = [
					'enabled'   => true,
					'engine'    => $engine,
					'languages' => $this->languages( $activeLanguages ),
				];
			}

			return Either::right( $payload );
		} catch ( \Throwable $e ) {
			return Either::left( [ 'error' => 'ateUnreachable' ] );
		}
	}

	private function engine( $engine ) {
		if ( null === $engine ) {
			return null;
		}

		$codeName = $engine->getCodeName();

		return [
			'codeName'    => $codeName,
			'formalName'  => $engine->getFormalName(),
			'isPtc'       => self::PTC_ENGINE === $codeName,
			'settingsUrl' => self::aiTranslationSettingsUrl(),
		];
	}

	private static function aiTranslationSettingsUrl() {
		return admin_url( 'admin.php?page=tm/menu/settings&section=ai-translation' );
	}

	private function activeEngineQuery() {
		if ( null === $this->activeEngineQuery ) {
			$this->activeEngineQuery = new ActiveEngineQuery();
		}

		return $this->activeEngineQuery;
	}

	private function languages( array $activeLanguages ) {
		$input = array_map(
			function ( $lang ) {
				$lang = (array) $lang;
				return [
					'code' => isset( $lang['code'] ) ? (string) $lang['code'] : '',
				];
			},
			array_values( $activeLanguages )
		);

		$capabilities = AutomaticTranslationCapabilities::withCapabilityInfo( $input );

		$mappings = $this->explicitMappings();
		$ptc      = $this->ptcSupportMap();

		$defaultCode = (string) apply_filters( 'wpml_default_language', null );

		$out = [];
		foreach ( (array) $capabilities as $lang ) {
			$code = (string) Obj::propOr( '', 'code', $lang );
			if ( '' === $code ) {
				continue;
			}

			$ateLang = Obj::propOr( null, 'effective_ate_language_code', $lang );
			$ateLang = $ateLang ? (string) $ateLang : null;

			$canBeTranslatedAutomatically = (bool) Obj::propOr( false, 'can_be_translated_automatically', $lang );

			$mapping = isset( $mappings[ strtolower( $code ) ] ) ? $mappings[ strtolower( $code ) ] : null;

			$needsMapping = null === $mapping
				&& $code !== $defaultCode
				&& LanguageCodeResolution::isCustomOnCatalogueCode( $code );

			$sameAteAsDefault = ! $canBeTranslatedAutomatically
				&& ! $needsMapping
				&& LanguageMappings::resolvesToSameAteLanguageAsDefault( $code, $defaultCode );

			$out[ $code ] = [
				'canBeTranslatedAutomatically' => $canBeTranslatedAutomatically,
				'ateLang'                      => $ateLang,
				'mapping'                      => $mapping,
				'ptcSupported'                 => self::ptcSupportedFor( $ptc, $code, $mapping, $ateLang ),
				'sameAteAsDefault'             => $sameAteAsDefault,
				'needsMapping'                 => $needsMapping,
			];
		}

		return $out;
	}

	private function explicitMappings() {
		$map = [];
		$all = array_merge(
			(array) CachedLanguageMappings::get(),
			(array) Option::getLanguageMappings()
		);
		foreach ( $all as $mapping ) {
			$source = (string) $mapping->sourceCode;
			$target = (string) $mapping->targetCode;
			if ( '' === $source || '' === $target ) {
				continue;
			}

			$map[ strtolower( $source ) ] = [
				'targetCode'    => $target,
				'targetCountry' => LanguageCodeResolution::country( str_replace( '_', '-', strtolower( $target ) ) ),
				'kind'          => MappingKind::of( $source, $target ),
			];
		}

		return $map;
	}

	private function ptcSupportMap() {
		$api = $this->ateApi();
		if ( ! $api || ! method_exists( $api, 'get_available_languages' ) ) {
			return [];
		}

		$feed = $api->get_available_languages();
		if ( ! is_array( $feed ) ) {
			return [];
		}

		return self::ptcSupportFromFeed( $feed );
	}

	private static function ptcSupportFromFeed( array $feed ) {
		$byIso      = [];
		$byWpmlCode = [];
		foreach ( $feed as $entry ) {
			$supported = '' !== (string) Obj::propOr( '', 'llm_api_iso', $entry );

			$iso = strtolower( (string) Obj::propOr( '', 'iso', $entry ) );
			if ( '' !== $iso ) {
				$byIso[ $iso ] = $supported;
			}

			$wpmlCode = strtolower( (string) Obj::propOr( '', 'wpml_default_code', $entry ) );
			if ( '' !== $wpmlCode ) {
				$byWpmlCode[ $wpmlCode ] = $supported;
			}
		}

		return $byWpmlCode + $byIso;
	}

	private static function ptcSupportedFor( array $ptc, $code, $mapping, $ateLang ) {
		$candidates = [ $code ];
		if ( is_array( $mapping ) && isset( $mapping['targetCode'] ) ) {
			$candidates[] = (string) $mapping['targetCode'];
		}
		if ( null !== $ateLang ) {
			$candidates[] = $ateLang;
		}

		foreach ( $candidates as $candidate ) {
			$key = str_replace( '_', '-', strtolower( (string) $candidate ) );
			if ( '' !== $key && array_key_exists( $key, $ptc ) ) {
				return $ptc[ $key ];
			}
		}

		return false;
	}

	private function ateApi() {
		try {
			return make( \WPML_TM_ATE_API::class );
		} catch ( \Throwable $e ) {
			return null;
		}
	}
}
