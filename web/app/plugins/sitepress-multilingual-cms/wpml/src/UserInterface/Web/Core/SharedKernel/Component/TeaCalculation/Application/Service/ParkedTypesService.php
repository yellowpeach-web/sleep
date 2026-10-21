<?php

namespace WPML\UserInterface\Web\Core\SharedKernel\Component\TeaCalculation\Application\Service;

use WPML\Core\Component\Translation\Application\Repository\SettingsRepository;
use WPML\Core\SharedKernel\Component\Item\Application\Query\UntranslatedTypesCountQueryInterface;
use WPML\Core\SharedKernel\Component\Item\Application\Service\UntranslatedService;
use WPML\UserInterface\Web\Core\SharedKernel\Component\TeaCalculation\Application\Repository\ParkedTypesOfferRepositoryInterface;
use WPML\UserInterface\Web\Core\SharedKernel\Component\TeaCalculation\Application\Repository\PostTypesSinceRepositoryInterface;

class ParkedTypesService {

  const TYPE_ORDER = [ 'page', 'post', 'product' ];

  private $untranslatedService;

  private $parkedOffer;

  private $postTypesRepository;

  private $settingsRepository;


  public function __construct(
    UntranslatedService $untranslatedService,
    ParkedTypesOfferRepositoryInterface $parkedOffer,
    PostTypesSinceRepositoryInterface $postTypesRepository,
    SettingsRepository $settingsRepository
  ) {
    $this->untranslatedService = $untranslatedService;
    $this->parkedOffer         = $parkedOffer;
    $this->postTypesRepository = $postTypesRepository;
    $this->settingsRepository  = $settingsRepository;
  }


  public function parkTypesTheSinceMapNeverGot( string $door ): array {
    if ( ! $this->settingsRepository->getSettings()->getTranslateEverything()->isEnabled() ) {
      return [];
    }

    $sinceDates = $this->postTypesRepository->getPostTypesSinceDates();

    if ( $sinceDates === [] ) {
      return [];
    }

    $missing = [];
    foreach ( $this->parkedOffer->getTranslatableTypes() as $type ) {
      if ( in_array( $type, TypesService::TYPES_TO_EXCLUDE, true ) ) {
        continue;
      }
      if ( isset( $sinceDates[ $type ] ) ) {
        continue;
      }

      $missing[] = $type;
    }

    if ( $missing === [] ) {
      return [];
    }

    $this->postTypesRepository->holdBack( $missing );
    $this->parkedOffer->record( $missing, $door );

    return $missing;
  }


  public function getParkedTypesOffer(): array {
    $this->parkTypesTheSinceMapNeverGot( ParkedTypesOfferRepositoryInterface::DOOR_DASHBOARD );

    $record = $this->parkedOffer->getParkedTypes();

    if ( $record === [] ) {
      return [];
    }

    $rows = [];
    foreach ( $this->untranslatedService->getUntranslatedTypesCounts() as $count ) {
      $type = $count->toArray();

      if ( $type['kind'] !== UntranslatedTypesCountQueryInterface::KIND_POST ) {
        continue;
      }
      if ( ! isset( $record[ $type['type'] ] ) ) {
        continue;
      }

      $rows[] = [
        'slug'      => $type['type'],
        'label'     => $type['namePlural'],
        'items'     => $type['count'],
        'dismissed' => $record[ $type['type'] ]['dismissed'],
        'parkedAt'  => $record[ $type['type'] ]['parked_at'],
      ];
    }

    usort( $rows, [ $this, 'compareRows' ] );

    $labels = [];
    $kept   = $this->parkedOffer->getTypeLabels();
    foreach ( $rows as $row ) {
      if ( $row['label'] !== '' && ( $kept[ $row['slug'] ] ?? null ) !== $row['label'] ) {
        $labels[ $row['slug'] ] = $row['label'];
      }
    }
    if ( $labels !== [] ) {
      $this->parkedOffer->rememberLabels( $labels );
    }

    return $rows;
  }


  private function compareRows( array $a, array $b ): int {
    $aIndex = array_search( $a['slug'], self::TYPE_ORDER, true );
    $bIndex = array_search( $b['slug'], self::TYPE_ORDER, true );

    if ( $aIndex === false ) {
      $aIndex = count( self::TYPE_ORDER );
    }
    if ( $bIndex === false ) {
      $bIndex = count( self::TYPE_ORDER );
    }

    if ( $aIndex === $bIndex ) {
      return $b['items'] <=> $a['items'];
    }

    return $aIndex <=> $bIndex;
  }


}
