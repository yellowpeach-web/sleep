<?php

namespace WPML\Core\Component\WordsToTranslate\Domain\Job;

use WPML\Core\Component\WordsToTranslate\Domain\Evidence\ManifestBuilder;
use WPML\Core\Component\WordsToTranslate\Domain\Item;
use WPML\Core\Component\WordsToTranslate\Domain\Job\Query\JobQueryInterface;
use WPML\Core\Component\WordsToTranslate\Domain\Job\Query\PtcEngineStatusQueryInterface;
use WPML\Core\Component\WordsToTranslate\Domain\Job\Query\TranslationEngineQueryInterface;
use WPML\Core\Component\WordsToTranslate\Domain\ProviderInterface;
use WPML\PHP\Exception\InvalidArgumentException;
use WPML\PHP\Exception\RuntimeException;

class Provider {

  private $jobQuery;

  private $translationEngineQuery;

  private $providers = [];

  private $ptcEngineStatus;

  private $manifestBuilder;

  private $isPtcEngine = null;


  public function __construct(
    $jobQuery,
    $translationEngineQuery,
    $providers,
    PtcEngineStatusQueryInterface $ptcEngineStatus,
    ManifestBuilder $manifestBuilder
  ) {
    $this->jobQuery = $jobQuery;
    $this->translationEngineQuery = $translationEngineQuery;
    $this->providers = $providers;
    $this->ptcEngineStatus = $ptcEngineStatus;
    $this->manifestBuilder = $manifestBuilder;
  }


  public function getById( $id, $freshTranslation = false ) {
    $job = $this->getWithItemById( $id, $freshTranslation );

    return new JobDTO(
      $job->getId(),
      $job->getWordsToTranslate(),
      $job->getAutomaticTranslationCosts(),
      $job->getPreviousAteJobIds(),
      $this->manifestBuilder->buildForJob( $job )
    );
  }


  public function getWithItemById( $id, $freshTranslation = false ) {
    $sourceLang = $this->jobQuery->getSourceLang( $id );
    $targetLang = $this->jobQuery->getTargetLang( $id );
    $isAutomatic = $this->jobQuery->isAutomatic( $id );
    $previousAteJobIds = $freshTranslation
      ? []
      : $this->jobQuery->getPreviousAteJobIds( $id );

    $jobItemId = $this->jobQuery->getJobItemId( $id );
    $jobItemType = $this->jobQuery->getJobItemType( $id );

    $item = false;
    $content = $this->jobQuery->getContent( $id );

    foreach ( $this->providers as $provider ) {
      $provider->useThisContentForItem( $jobItemId, $jobItemType, $content );
      if ( $item = $provider->getByIdAndTypeForLangs( $jobItemId, $jobItemType, [ $targetLang ], $freshTranslation ) ) {
        break;
      }
    }

    if ( ! $item ) {
      throw new InvalidArgumentException(
        sprintf(
          'Item with id %d and type %s not found',
          $jobItemId,
          $jobItemType
        )
      );
    }

    if ( $this->jobQuery->wasCreatedByTEA( $id ) ) {
      $this->applyTeaPricingGate( $id, $item );
    }

    return new Job(
      $id,
      $sourceLang,
      $targetLang,
      $item,
      $isAutomatic,
      $previousAteJobIds,
      $this->translationEngineQuery->getCostsPerWordForLang( $targetLang )
    );
  }


  private function applyTeaPricingGate( $id, $item ) {
    $contentCount = $item->getContentCount();
    if ( $contentCount === null ) {
      return;
    }

    $lifetimeMax = $this->jobQuery->getLifetimeMaxWordsToTranslate( $id );

    if ( $contentCount <= $lifetimeMax ) {
      if ( $this->isPtcEngineActive() ) {
        foreach ( $item->getLastTranslations() as $lastTranslation ) {
          $lastTranslation->setWordsToTranslate( 0 );
        }
      }
      return;
    }

    $additionalWords = $contentCount - $lifetimeMax;
    foreach ( $item->getLastTranslations() as $lastTranslation ) {
      $perTargetDiff = $lastTranslation->getWordsToTranslate() ?? 0;
      $lastTranslation->setWordsToTranslate( min( $perTargetDiff, $additionalWords ) );
    }
    $this->jobQuery->setLifetimeMaxWordsToTranslate( $id, $contentCount );
  }


  private function isPtcEngineActive() {
    if ( $this->isPtcEngine === null ) {
      $this->isPtcEngine = $this->ptcEngineStatus->isDefaultEngine();
    }

    return $this->isPtcEngine;
  }


}
