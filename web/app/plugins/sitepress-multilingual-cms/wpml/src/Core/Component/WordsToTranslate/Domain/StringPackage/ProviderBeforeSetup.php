<?php

namespace WPML\Core\Component\WordsToTranslate\Domain\StringPackage;

use WPML\Core\Component\WordsToTranslate\Domain\Calculator\WordsToTranslate;
use WPML\Core\Component\WordsToTranslate\Domain\Item;
use WPML\Core\Component\WordsToTranslate\Domain\LastTranslation;
use WPML\Core\Component\WordsToTranslate\Domain\ProviderInterface;
use WPML\Core\Component\WordsToTranslate\Domain\StringPackage\Query\BeforeSetupPackagesQueryInterface;
use WPML\Core\Component\WordsToTranslate\Domain\TranslatableDTO;

class ProviderBeforeSetup implements ProviderInterface {

  const TYPE = Provider::TYPE;

  private $packagesQuery;

  private $wordsToTranslate;


  public function __construct( BeforeSetupPackagesQueryInterface $packagesQuery, WordsToTranslate $wordsToTranslate ) {
    $this->packagesQuery    = $packagesQuery;
    $this->wordsToTranslate = $wordsToTranslate;
  }


  public function getByIdAndTypeForLangs( $id, $type, $langs, $freshTranslation = false ) {
    if ( $type !== self::TYPE || empty( $langs ) ) {
      return false;
    }

    foreach ( $this->packagesQuery->getAll() as $package ) {
      if ( $package['id'] !== $id ) {
        continue;
      }

      $item  = new Item( $package['id'], 'package_' . $package['kindSlug'], $langs[0] );
      $words = $this->wordsToTranslate->forContent( $package['strings'], $langs[0] );

      $item->setContentCount( $words );
      foreach ( $langs as $lang ) {
        $lastTranslation = new LastTranslation( $lang );
        $lastTranslation->setWordsToTranslate( $words );
        $item->addLastTranslation( $lastTranslation );
      }

      return $item;
    }

    return false;
  }


  public function useThisContentForItem( $id, $type, $content ) {
  }


}
