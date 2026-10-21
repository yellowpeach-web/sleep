<?php

namespace WPML\Legacy\Component\Language\Application\Query;

use WPML\Core\SharedKernel\Component\Language\Application\ProspectiveLanguages;
use WPML\Core\SharedKernel\Component\Language\Application\Query\Dto\LanguageDto;
use WPML\Core\SharedKernel\Component\Language\Application\Query\LanguagesQueryInterface;

class ProspectiveLanguagesDecoratorForLanguagesQuery implements LanguagesQueryInterface {

  private $languagesQuery;

  private $prospectiveLanguages;

  private $sitepress;

  private $presetLookup;


  public function __construct(
    LanguagesQueryInterface $languagesQuery,
    ProspectiveLanguages $prospectiveLanguages,
    $sitepress,
    $presetLookup = null
  ) {
    $this->languagesQuery       = $languagesQuery;
    $this->prospectiveLanguages = $prospectiveLanguages;
    $this->sitepress            = $sitepress;
    $this->presetLookup         = $presetLookup;
  }


  public function getDefaultCode(): string {
    return $this->languagesQuery->getDefaultCode();
  }


  public function getCurrentLanguageCode(): string {
    return $this->languagesQuery->getCurrentLanguageCode();
  }


  public function getDefault(): LanguageDto {
    return $this->languagesQuery->getDefault();
  }


  public function getActive() {
    return $this->appendProspective( $this->languagesQuery->getActive() );
  }


  public function getSecondary( bool $withRespectToCurrentLang = false, $currentLang = null ) {
    return $this->appendProspective(
      $this->languagesQuery->getSecondary( $withRespectToCurrentLang, $currentLang )
    );
  }


  public function getSecondaryTranslatable(): array {
    $translatable = $this->languagesQuery->getSecondaryTranslatable();

    if ( $this->prospectiveLanguages->isEmpty() ) {
      return $translatable;
    }

    return $this->appendProspective( $translatable, $this->languagesQuery->getSecondary() );
  }


  private function appendProspective( array $languages, array $alsoKnown = [] ) {
    if ( $this->prospectiveLanguages->isEmpty() ) {
      return $languages;
    }

    $knownCodes = [];
    foreach ( array_merge( $languages, $alsoKnown ) as $language ) {
      $knownCodes[ $language->getCode() ] = true;
    }

    $knownCodes[ $this->languagesQuery->getDefaultCode() ] = true;

    foreach ( $this->prospectiveLanguages->get() as $code ) {
      if ( isset( $knownCodes[ $code ] ) ) {
        continue;
      }

      $dto = $this->buildLanguage( $code );
      if ( ! $dto ) {
        continue;
      }

      $languages[]          = $dto;
      $knownCodes[ $code ] = true;
    }

    return $languages;
  }


  private function buildLanguage( $code ) {
    $details = $this->sitepress->get_language_details( $code );

    if (
      ! is_array( $details )
      || ! isset( $details['code'], $details['english_name'] )
    ) {
      return $this->buildFromPresetCatalogue( $code );
    }

    $dto = new LanguageDto(
      $details['code'],
      $details['english_name'],
      isset( $details['native_name'] ) ? $details['native_name'] : $details['english_name'],
      isset( $details['default_locale'] ) ? $details['default_locale'] : '',
      false
    );

    if ( isset( $details['display_name'] ) ) {
      $dto->setDisplayName( $details['display_name'] );
    }

    $flagUrl = $this->sitepress->get_flag_url( $details['code'] );
    if ( $flagUrl ) {
      $dto->setCountryFlagUrl( $flagUrl );
    }

    return $dto;
  }


  private function buildFromPresetCatalogue( $code ) {
    $lookup = $this->presetLookup !== null
      ? $this->presetLookup
      : [ $this, 'lookupPresetByCode' ];

    $preset = call_user_func( $lookup, $code );

    if (
      ! is_array( $preset )
      || ! isset( $preset['english_name'] )
      || ! is_string( $preset['english_name'] )
      || $preset['english_name'] === ''
    ) {
      return null;
    }

    return new LanguageDto(
      $code,
      $preset['english_name'],
      $preset['english_name'],
      '',
      false
    );
  }


  public function lookupPresetByCode( $code ) {
    if ( ! class_exists( \WPML\LanguageEditor\LanguageCodeResolution::class ) ) {
      return null;
    }

    $resolved = \WPML\LanguageEditor\LanguageCodeResolution::resolve( (string) $code );
    if ( $resolved !== null ) {
      return [ 'english_name' => $resolved['english_name'] ];
    }

    $preset = \WPML\LanguageEditor\LanguageCodeResolution::presetByCode( (string) $code );

    return $preset !== null ? [ 'english_name' => $preset['english_name'] ] : null;
  }


}
