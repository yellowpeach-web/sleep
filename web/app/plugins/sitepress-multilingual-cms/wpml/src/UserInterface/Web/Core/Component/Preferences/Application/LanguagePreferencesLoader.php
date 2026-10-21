<?php

namespace WPML\UserInterface\Web\Core\Component\Preferences\Application;

use WPML\Core\Port\PluginInterface;
use WPML\Core\SharedKernel\Component\Language\Application\Query\Dto\LanguageDto;
use WPML\Core\SharedKernel\Component\Language\Application\Query\LanguagesQueryInterface;

class LanguagePreferencesLoader {

  private $languagesQuery;

  private $pluginInterface;


  public function __construct( LanguagesQueryInterface $languagesQuery, PluginInterface $pluginInterface ) {
    $this->languagesQuery = $languagesQuery;
    $this->pluginInterface = $pluginInterface;
  }


  private function getLanguages(): array {

    return array_reduce(
      $this->languagesQuery->getActive(),
      function ( array $carry, LanguageDto $language ) {
        $carry[ $language->getCode() ] = [
          'code'                             => $language->getCode(),
          'name'                             => $language->getDisplayName(),
          'language'                         => $this->publishedLanguageOf( $language->getCode() ),
          'flagUrl'                          => $language->getCountryFlagUrl(),
          'homeUrl'                         =>  $this->pluginInterface->getLanguageHomeUrl( $language->getCode() ),
          'doesSupportAutomaticTranslations' => $language->doesSupportAutomaticTranslations(),
          'excludedReason'                   => $language->getAutomaticTranslationsUnavailableReason(),
          'translationPaused'                => $language->isTranslationPaused(),
        ];

        return $carry;
      },
      []
    );
  }


  private function publishedLanguageOf( string $code ): string {
    if ( ! class_exists( \WPML\LanguageEditor\LanguageCodeResolution::class ) ) {
      return '';
    }

    return \WPML\LanguageEditor\LanguageCodeResolution::publishedIdentity( $code )['language'];
  }


  private function getLanguagesTo(): array {
    $codes = array_map(
      function ( LanguageDto $language ) {
        return $language->getCode();
      },
      $this->languagesQuery->getSecondary( true )
    );

    if ( ! class_exists( \WPML\LanguageEditor\TranslationPause::class ) ) {
      return $codes;
    }

    return \WPML\LanguageEditor\TranslationPause::filterTranslatable( $codes );
  }


  public function get(): array {
    return [
      'languages'         => $this->getLanguages(),
      'languagesSettings' => [
        'from'    => $this->languagesQuery->getCurrentLanguageCode(),
        'to'      => $this->getLanguagesTo(),
        'default' => $this->languagesQuery->getDefaultCode(),
      ]
    ];
  }


}
