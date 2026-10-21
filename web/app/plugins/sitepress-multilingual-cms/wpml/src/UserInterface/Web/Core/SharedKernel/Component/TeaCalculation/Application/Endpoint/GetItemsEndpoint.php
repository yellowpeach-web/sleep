<?php

namespace WPML\UserInterface\Web\Core\SharedKernel\Component\TeaCalculation\Application\Endpoint;

use WPML\Core\Component\WordsToTranslate\Application\Service\WordsForEstimate;
use WPML\Core\Component\WordsToTranslate\Application\Service\WordsToTranslateService;
use WPML\Core\Port\Endpoint\EndpointInterface;
use WPML\Core\Port\PluginInterface;
use WPML\Core\SharedKernel\Component\Item\Application\Service\UntranslatedService;
use WPML\Core\SharedKernel\Component\Language\Application\ProspectiveLanguages;
use WPML\Core\SharedKernel\Component\Language\Application\Query\LanguagesQueryInterface;
use WPML\Core\SharedKernel\Component\Language\Domain\LanguageCode;
use WPML\PHP\Exception\InvalidArgumentException;

class GetItemsEndpoint implements EndpointInterface {

  private $untranslatedService;

  private $wordsToTranslateService;

  private $plugin;

  private $languagesQuery;

  private $prospectiveLanguages;


  public function __construct(
    UntranslatedService $untranslatedService,
    WordsToTranslateService $wordsToTranslateServices,
    PluginInterface $plugin,
    LanguagesQueryInterface $languagesQuery,
    ProspectiveLanguages $prospectiveLanguages
  ) {
    $this->untranslatedService = $untranslatedService;
    $this->wordsToTranslateService = $wordsToTranslateServices;
    $this->plugin = $plugin;
    $this->languagesQuery = $languagesQuery;
    $this->prospectiveLanguages = $prospectiveLanguages;
  }


  public function handle( $requestData = null ): array {
    $start = microtime( true );

    $result = [
      'processingTime' => 0,
      'types' => [],
    ];

    if (
      ! is_array( $requestData )
      || ( ! isset( $requestData['types'] ) || ! is_array( $requestData['types'] ) )
      || ( ! isset( $requestData['langs'] ) || ! is_array( $requestData['langs'] ) )
      || ( ! isset( $requestData['numberOfItemsToFetch'] ) || ! is_numeric( $requestData['numberOfItemsToFetch'] ) )
    ) {
      throw new InvalidArgumentException( 'Invalid request data.' );
    }

    $this->prospectiveLanguages->setFromRequest(
      isset( $requestData['prospectiveLangs'] ) ? $requestData['prospectiveLangs'] : null
    );

    $langs = $requestData['langs'];
    $numberOfItemsToFetch = (int) $requestData['numberOfItemsToFetch'];

    $isSetupComplete = $this->plugin->isSetupComplete();

    $requestDefaultLang = isset( $requestData['defaultLang'] ) && is_string( $requestData['defaultLang'] )
      ? $requestData['defaultLang']
      : '';
    $defaultLang = $isSetupComplete
      ? null
      : ( $requestDefaultLang !== '' ? $requestDefaultLang : $this->languagesQuery->getDefaultCode() );

    foreach ( $requestData['types'] as $key => $value ) {
      if (
        ! is_array( $value )
        || ! isset( $value['kind'] ) || ! is_string( $value['kind'] )
        || ! isset( $value['offset'] ) || ! is_numeric( $value['offset'] )
      ) {
        throw new InvalidArgumentException( 'Invalid type data.' );
      }

      $kind = $value['kind'];
      $type = isset( $value['type'] ) && ! empty( $value['type'] ) ? $value['type'] : $kind;
      $offset = (int) $value['offset'];

      $ids = $this->untranslatedService->getSomeUntranslatedIds(
        $numberOfItemsToFetch,
        $offset,
        $kind,
        $type
      );

      $resultType = [];

      $wttKind = $kind === 'package' ? 'stringPackage' : $kind;

      foreach ( $ids as $id ) {
        try {
          $item = $this->wordsToTranslateService->getForIdAndType( $id, $wttKind, $langs );
        } catch ( \Throwable $e ) {
          if ( ! array_key_exists( '0000-00-00', $resultType ) ) {
            $resultType['0000-00-00'] = $this->itemsPerDate( '0000-00-00' );
          }

          $resultType['0000-00-00']['items']++;
          continue;
        }

        $date = $item->getDateModified();
        if ( ! array_key_exists( $date, $resultType ) ) {
          $resultType[ $date ] = $this->itemsPerDate( $date );
        }

        $resultType[ $date ]['items']++;

        foreach ( $langs as $lang ) {
          $sourceLang = $isSetupComplete ? $item->getSourceLang() : $defaultLang;
          if ( strtolower( $lang ) === strtolower( (string) $sourceLang ) ) {
            $resultType[ $date ]['source'][ $lang ] =
              ( $resultType[ $date ]['source'][ $lang ] ?? 0 ) + ( $item->getContentCount() ?? 0 );
            continue;
          }
          if ( $kind === 'string' && LanguageCode::isEnglishTargetOfEnglishSource( $sourceLang, $lang ) ) {
            continue;
          }
          $words = WordsForEstimate::of( $item, $lang );
          $resultType[ $date ]['words'][ $lang ] =
            ( $resultType[ $date ]['words'][ $lang ] ?? 0 ) + $words;
        }
      }

      $result['types'][ $type ] = $resultType;
    }

    $end = microtime( true );
    $result['processingTime'] = ( $end - $start ) * 1000;

    return $result;
  }


  private function itemsPerDate( $date ) {
    return [
      'date' => $date,
      'items' => 0,
      'words' => [],
      'source' => []
    ];
  }


}
