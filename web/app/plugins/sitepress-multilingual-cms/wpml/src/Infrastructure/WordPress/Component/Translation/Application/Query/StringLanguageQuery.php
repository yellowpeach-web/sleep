<?php

namespace WPML\Infrastructure\WordPress\Component\Translation\Application\Query;

use WPML\Core\Component\Translation\Application\Query\ItemLanguageQueryInterface;
use WPML\Core\Component\Translation\Domain\TranslationType;
use WPML\Core\SharedKernel\Component\Language\Application\Query\Dto\LanguageDto;
use WPML\Core\SharedKernel\Component\Language\Application\Query\LanguagesQueryInterface;
use WPML\Core\SharedKernel\Component\Language\Domain\LanguageCode;
use WPML\Core\SharedKernel\Component\String\Application\Query\StringLanguageQueryInterface;

class StringLanguageQuery implements ItemLanguageQueryInterface {

  private $stringLanguageQuery;

  private $languagesQuery;

  private $englishSourceCode;


  public function __construct(
    StringLanguageQueryInterface $stringLanguageQuery,
    LanguagesQueryInterface $languagesQuery
  ) {
    $this->stringLanguageQuery = $stringLanguageQuery;
    $this->languagesQuery      = $languagesQuery;
  }


  public function getManyOriginalLanguagesOfItems( array $items ): array {
      $stringItems = array_filter(
        $items,
        function ( $item ) {
          return $item['type']->get() === TranslationType::STRING;
        }
      );

    if ( ! $stringItems ) {
        return [];
    }

      $stringIds = array_map(
        function ( $item ) {
          return $item['itemId'];
        },
        $stringItems
      );

      $stringLanguages = $this->stringLanguageQuery->getStringLanguages( $stringIds );

      $english = $this->englishSourceCode();

      return array_map(
        function ( $item ) use ( $stringLanguages, $english ) {
          return [
          'itemId'   => $item['itemId'],
          'type'     => $item['type'],
          'language' => $stringLanguages[ $item['itemId'] ] ?? $english,
          ];
        },
        $stringItems
      );
  }


  private function englishSourceCode(): string {
    if ( null === $this->englishSourceCode ) {
      $activeCodes = array_map(
        function ( LanguageDto $language ) {
          return $language->getCode();
        },
        $this->languagesQuery->getActive()
      );

      $this->englishSourceCode = LanguageCode::englishSourceCode( $activeCodes, $this->languagesQuery->getDefaultCode() );
    }

    return $this->englishSourceCode;
  }


}
