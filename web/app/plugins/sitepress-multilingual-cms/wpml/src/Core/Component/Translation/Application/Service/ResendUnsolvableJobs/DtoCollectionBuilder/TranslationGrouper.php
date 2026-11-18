<?php

namespace WPML\Core\Component\Translation\Application\Service\ResendUnsolvableJobs\DtoCollectionBuilder;

use WPML\Core\Component\Translation\Domain\Translation;
use WPML\Core\Component\Translation\Domain\TranslationType;


class TranslationGrouper {

  const TERMS = 'terms';

  const LANGUAGES = 'languages:';


  public function groupBySourceLanguage( array $translations ): array {
    $grouped = [];

    foreach ( $translations as $translation ) {
      $sourceLanguage = $translation->getSourceLanguageCode();

      if ( ! isset( $grouped[ $sourceLanguage ] ) ) {
        $grouped[ $sourceLanguage ] = [];
      }

      $grouped[ $sourceLanguage ][] = $translation;
    }

    return $grouped;
  }


  public function groupByTargetLanguages( array $translations ): array {
    $languagesByElement = [];
    foreach ( $translations as $translation ) {
      $languagesByElement[ $this->elementKey( $translation ) ][ $translation->getTargetLanguageCode() ] = true;
    }

    $grouped = [];
    foreach ( $translations as $translation ) {
      if ( TranslationType::TAXONOMY === $translation->getType()->get() ) {
        $grouped[ self::TERMS ][] = $translation;
        continue;
      }

      $languages = array_keys( $languagesByElement[ $this->elementKey( $translation ) ] );
      sort( $languages );
      $grouped[ self::LANGUAGES . implode( ',', $languages ) ][] = $translation;
    }

    return $grouped;
  }


  private function elementKey( Translation $translation ): string {
    return $translation->getType()->get() . ':' . $translation->getOriginalElementId();
  }


}
