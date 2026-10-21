<?php

namespace WPML\Legacy\Component\ATE\Application\Service;

use WPML\Core\Component\ATE\Application\Service\Dto\Engine\FormalityLevelDto;
use WPML\Core\Component\ATE\Application\Service\Dto\Engine\FormalitySettingDto;
use WPML\Core\Component\ATE\Application\Service\Dto\EngineDto;
use WPML\Core\Component\ATE\Application\Service\Dto\UpdateEngine\FormalitySettingDto as UpdateEngineFormalitySettingDto;
use WPML\Core\Component\ATE\Application\Service\Dto\UpdateEngineDto;
use WPML\Core\Component\ATE\Application\Service\EngineServiceException;
use WPML\Core\Component\ATE\Application\Service\EnginesServiceInterface;
use WPML\Core\SharedKernel\Component\Language\Application\Query\LanguagesQueryInterface;
use WPML\TM\API\ATE\CachedLanguageMappings;
use WPML\TM\ATE\API\CachedAMSAPI;

class EnginesService implements EnginesServiceInterface {

  const CATALOG_CACHE_KEY = 'wpml_ams_engines_catalog';
  const CATALOG_CACHE_TTL = 60;

  const ENGINES_CACHE_KEY = 'wpml_ams_engines_list';

  const CATALOG_FAILURE_CACHE_KEY = 'wpml_ams_engines_catalog_failed';

  private $amsApi;

  private $languagesQuery;


  public function __construct( \WPML_TM_AMS_API $amsApi, LanguagesQueryInterface $languagesQuery ) {
    $this->amsApi         = $amsApi;
    $this->languagesQuery = $languagesQuery;
  }


  public function getList(): array {
    $catalog              = $this->getRawCatalog();
    $engines              = $catalog['engines'];
    $availableFormalities = $catalog['formalities'];

    $result = [];
    foreach ( $engines as $engineData ) {
      $availableFormalitiesOfEngine = $availableFormalities[ $engineData['engine'] ]['languages'] ?? [];
      $result[]                     = $this->buildEngineFromArray( $engineData, $availableFormalitiesOfEngine );
    }

    return $result;
  }


  public function getListWithoutFormalities(): array {
    $result = [];
    foreach ( $this->getRawEngines() as $engineData ) {
      $result[] = new EngineDto(
        $engineData['engine'],
        $engineData['formal_name'],
        $engineData['cost'] ?? 0,
        $engineData['enabled'],
        $engineData['formality_available']
      );
    }

    return $result;
  }


  private function getRawCatalog(): array {
    $cached = $this->readCatalogCache();
    if ( $cached !== null ) {
      return $cached;
    }

    $this->throwIfARecentReadFailed();

    try {
      $catalog = [
        'engines'     => $this->fetchEnginesData(),
        'formalities' => $this->fetchAvailableFormalities(),
      ];
    } catch ( EngineServiceException $e ) {
      $this->writeFailureCache();
      throw $e;
    }

    $this->writeCatalogCache( $catalog );

    return $catalog;
  }


  private function getRawEngines(): array {
    $catalog = $this->readCatalogCache();
    if ( $catalog !== null ) {
      return $catalog['engines'];
    }

    $cached = $this->readEnginesCache();
    if ( $cached !== null ) {
      return $cached;
    }

    $this->throwIfARecentReadFailed();

    try {
      $engines = $this->fetchEnginesData();
    } catch ( EngineServiceException $e ) {
      $this->writeFailureCache();
      throw $e;
    }

    if ( function_exists( 'set_transient' ) ) {
      set_transient( self::ENGINES_CACHE_KEY, $engines, self::CATALOG_CACHE_TTL );
    }

    return $engines;
  }


  private function readEnginesCache() {
    if ( ! function_exists( 'get_transient' ) ) {
      return null;
    }

    $cached = get_transient( self::ENGINES_CACHE_KEY );

    $engines = is_array( $cached ) ? $cached : null;

    return $engines;
  }


  private function throwIfARecentReadFailed() {
    if ( function_exists( 'get_transient' ) && get_transient( self::CATALOG_FAILURE_CACHE_KEY ) ) {
      throw new EngineServiceException( 'The translation engines could not be read from AMS a moment ago.' );
    }
  }


  private function writeFailureCache() {
    if ( function_exists( 'set_transient' ) ) {
      set_transient( self::CATALOG_FAILURE_CACHE_KEY, 1, self::CATALOG_CACHE_TTL );
    }
  }


  private function readCatalogCache() {
    if ( ! function_exists( 'get_transient' ) ) {
      return null;
    }

    $cached = get_transient( self::CATALOG_CACHE_KEY );

    if (
      is_array( $cached )
      && isset( $cached['engines'] ) && is_array( $cached['engines'] )
      && isset( $cached['formalities'] ) && is_array( $cached['formalities'] )
    ) {
      return $cached;
    }

    return null;
  }


  private function writeCatalogCache( array $catalog ) {
    if ( function_exists( 'set_transient' ) ) {
      set_transient( self::CATALOG_CACHE_KEY, $catalog, self::CATALOG_CACHE_TTL );
    }
  }


  private function deleteCatalogCache() {
    if ( function_exists( 'delete_transient' ) ) {
      delete_transient( self::CATALOG_CACHE_KEY );
      delete_transient( self::ENGINES_CACHE_KEY );
      delete_transient( self::CATALOG_FAILURE_CACHE_KEY );
    }
  }


  public function update( array $engines ) {
    $enginesData = [];
    foreach ( $engines as $engine ) {
      $engineRaw = [
        'engine'              => $engine->getEngine(),
        'enabled'             => $engine->isEnabled(),
        'formality_available' => $engine->isFormalityAvailable(),
      ];

      if ( $engine->isFormalityAvailable() && $engine->getFormalitySettings() ) {
        $formalitySettings = $engine->getFormalitySettings();

        $formalitySettingsData = [];
        foreach ( $formalitySettings as $formalitySetting ) {
          $formalitySettingsData[] = [
            'lang_code' => $formalitySetting->getLanguageCode(),
            'formality' => $formalitySetting->getCurrentLevel()->getValue(),
          ];
        }
        $engineRaw['formality_settings'] = [ 'languages' => $formalitySettingsData ];
      }

      $enginesData[] = $engineRaw;
    }

    try {
      $result = $this->amsApi->update_translation_engines( $enginesData );

      if ( is_wp_error( $result ) ) {
        throw new EngineServiceException();
      }

      if ( ! $result ) {
        throw new EngineServiceException();
      }

      $this->deleteCatalogCache();
    } catch ( \Throwable $e ) {
      throw new EngineServiceException( 'The engines settings could not be saved' );
    }
  }


  public function flushCache() {
    CachedLanguageMappings::clearCache();
    $this->flushEnginesCache();
  }


  public function flushEnginesCache() {
    CachedAMSAPI::clearCache();
    $this->deleteCatalogCache();
  }


  private function fetchEnginesData(): array {
    $apiResult = $this->amsApi->get_translation_engines();

    if ( is_wp_error( $apiResult ) ) {
      throw new EngineServiceException(
        $apiResult->get_error_message()
      );
    }

    if ( ! is_array( $apiResult ) || ! isset( $apiResult['list'] ) ) {
      throw new EngineServiceException(
        __( 'Error fetching translation engines', 'wpml' )
      );
    }

    return $apiResult['list'];
  }


  private function fetchAvailableFormalities(): array {
    $apiResult = $this->amsApi->get_available_formalities();

    if ( is_wp_error( $apiResult ) ) {
      throw new EngineServiceException(
        $apiResult->get_error_message()
      );
    }

    if ( ! is_array( $apiResult ) || ! isset( $apiResult['engines'] ) ) {
      throw new EngineServiceException(
        __( 'Error fetching available formalities', 'wpml' )
      );
    }

    return $apiResult['engines'];
  }


  private function buildEngineFromArray( array $enginesData, array $availableFormalitiesOfEngine ): EngineDto {
    $engine             = $enginesData['engine'];
    $formalName         = $enginesData['formal_name'];
    $cost               = $enginesData['cost'] ?? 0;
    $enabled            = $enginesData['enabled'];
    $formalityAvailable = $enginesData['formality_available'];

    $engine = new EngineDto(
      $engine,
      $formalName,
      $cost,
      $enabled,
      $formalityAvailable
    );

    if ( $formalityAvailable ) {
      $currentFormalitySettings = $this->getEngineCurrentFormalitySettingsGroupedByLanguageCode( $enginesData );
      $formalitySettings        = [];

      foreach ( $this->languagesQuery->getSecondary() as $languageDto ) {
        $availableFormalitiesOfLanguage = $availableFormalitiesOfEngine[ $languageDto->getCode() ] ?? [];
        $availableLanguageLevels        = array_map(
          function ( string $levelValue ) {
            return new FormalityLevelDto( $levelValue );
          },
          $availableFormalitiesOfLanguage['available_formalities'] ?? []
        );

        $formalityLevel      = $currentFormalitySettings[ $languageDto->getCode() ] ?? FormalityLevelDto::default();
        $formalitySettings[] = new FormalitySettingDto(
          $languageDto->getCode(),
          $formalityLevel,
          $availableFormalitiesOfLanguage['formality_enabled'] ?? false,
          $availableLanguageLevels
        );
      }
      $engine->setFormalitySettings( $formalitySettings );
    }

    return $engine;
  }


  private function getEngineCurrentFormalitySettingsGroupedByLanguageCode( array $enginesData ): array {
    $formalitySettingsRaw = $enginesData['formality_settings']['languages'] ?? [];

    $result = [];
    foreach ( $formalitySettingsRaw as $row ) {
      $result[ $row['lang_code'] ] = new FormalityLevelDto( $row['formality'] );
    }

    return $result;
  }


}
