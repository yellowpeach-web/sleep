<?php

namespace WPML\Legacy\Component\Translation\Application\Query;

use WPML\Core\Component\Translation\Application\Query\AutomaticTranslationSupportQueryInterface;
use WPML\TM\API\ATE\LanguageMappings;
use WPML\TM\ATE\AutomaticPairSupport;

class AutomaticTranslationSupportQuery implements AutomaticTranslationSupportQueryInterface {


  public function supportFrom( string $sourceLanguageCode ): array {
    if ( ! class_exists( AutomaticPairSupport::class ) ) {
      return [];
    }

    $out = [];
    foreach ( AutomaticPairSupport::supportFrom( $sourceLanguageCode ) as $code => $supported ) {
      $refused = false === $supported;

      $out[ $code ] = [
        'supported' => $refused ? false : ( null === $supported ? null : true ),
        'reason'    => $refused ? $this->reason( $code, $sourceLanguageCode ) : null,
      ];
    }

    return $out;
  }


  private function reason( string $code, string $sourceLanguageCode ): string {
    $sameLanguage = class_exists( LanguageMappings::class )
      && LanguageMappings::resolvesToSameAteLanguageAsDefault( $code, $sourceLanguageCode );

    return $sameLanguage ? 'same-as-default' : 'manual-only';
  }


}
