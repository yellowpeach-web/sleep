<?php

namespace WPML\UserInterface\Web\Core\SharedKernel\Component\TeaCalculation\Application\Endpoint;

use WPML\Core\Component\Translation\Application\Repository\SettingsRepository;
use WPML\Core\Port\Endpoint\EndpointInterface;
use WPML\Core\SharedKernel\Component\ATE\Application\Service\AtePingerInterface;
use WPML\Core\SharedKernel\Component\Item\Application\Query\UntranslatedTypesCountQueryInterface;
use WPML\UserInterface\Web\Core\SharedKernel\Component\TeaCalculation\Application\Repository\ParkedTypesOfferRepositoryInterface;
use WPML\UserInterface\Web\Core\SharedKernel\Component\TeaCalculation\Application\Repository\PendingTranslatableOfferRepositoryInterface;
use WPML\UserInterface\Web\Core\SharedKernel\Component\TeaCalculation\Application\Repository\PostTypesSinceRepositoryInterface;
use WPML\UserInterface\Web\Core\SharedKernel\Component\TeaCalculation\Application\Validator\TypeIncludeRules;


class UpdateTypesIncludeBatch implements EndpointInterface {

  private $repository;

  private $rules;

  private $atePinger;

  private $settingsRepository;

  private $pendingOffer;

  private $parkedOffer;


  public function __construct(
    PostTypesSinceRepositoryInterface $repository,
    TypeIncludeRules $rules,
    AtePingerInterface $atePinger,
    SettingsRepository $settingsRepository,
    PendingTranslatableOfferRepositoryInterface $pendingOffer,
    ParkedTypesOfferRepositoryInterface $parkedOffer
  ) {
    $this->repository         = $repository;
    $this->rules              = $rules;
    $this->atePinger          = $atePinger;
    $this->settingsRepository = $settingsRepository;
    $this->pendingOffer       = $pendingOffer;
    $this->parkedOffer        = $parkedOffer;
  }


  public function handle( $requestData = null ): array {
    $rows = isset( $requestData['types'] ) && is_array( $requestData['types'] )
      ? $requestData['types']
      : null;

    if ( $rows === null ) {
      return [
        'success' => false,
        'message' => 'Types array is required.',
      ];
    }

    list( $updated, $skipped, $candidates, $kinds ) = $this->partitionRows( $rows );

    $changedDates = $this->repository->applySinceDates( $candidates );

    $markedAsCompletedTypes   = [];
    $markedAsUncompletedTypes = [];

    foreach ( $changedDates as $type => $includeSince ) {
      $isPackageKind = ( $kinds[ $type ] ?? null ) === UntranslatedTypesCountQueryInterface::KIND_PACKAGE;

      if ( $includeSince === TypeIncludeRules::NONE ) {
        $isPackageKind
          ? $this->repository->markPackageKindAsCompleted( $type )
          : $this->repository->markPostTypeAsCompleted( $type );
        $markedAsCompletedTypes[] = $type;
      } else {
        $isPackageKind
          ? $this->repository->markPackageKindAsUncompleted( $type )
          : $this->repository->markPostTypeAsUncompleted( $type );
        $markedAsUncompletedTypes[] = $type;
      }
    }

    $this->pendingOffer->commit();

    $this->parkedOffer->forget( $updated );

    $this->notifyAteIfScopeWidened( $markedAsUncompletedTypes );

    return [
      'success'                  => true,
      'message'                  => 'Type since dates updated successfully.',
      'updated'                  => $updated,
      'skipped'                  => $skipped,
      'markedAsCompletedTypes'   => $markedAsCompletedTypes,
      'markedAsUncompletedTypes' => $markedAsUncompletedTypes,
    ];
  }


  private function notifyAteIfScopeWidened( array $markedAsUncompletedTypes ) {
    if ( $markedAsUncompletedTypes === [] ) {
      return;
    }

    if ( ! $this->settingsRepository->getSettings()->getTranslateEverything()->isEnabled() ) {
      return;
    }

    $this->atePinger->notifyTeaEnabled( AtePingerInterface::TRIGGER_TEA_CUTOFF_ADJUSTED );
  }


  private function partitionRows( array $rows ): array {
    $updated    = [];
    $skipped    = [];
    $candidates = [];
    $kinds      = [];

    foreach ( $rows as $row ) {
      $row          = is_array( $row ) ? $row : [];
      $type         = isset( $row['type'] ) ? $row['type'] : null;
      $includeSince = isset( $row['includeSince'] ) ? $row['includeSince'] : null;
      $kind         = isset( $row['kind'] ) ? $row['kind'] : null;

      $reason = $this->rules->validate( $type, $includeSince, $kind );
      if ( $reason !== null ) {
        $skipped[] = [
          'type'   => is_string( $type ) ? $type : null,
          'reason' => $reason,
        ];
        continue;
      }
      assert( is_string( $type ) && is_string( $includeSince ) );

      $candidates[ $type ] = $includeSince;
      $kinds[ $type ]      = is_string( $kind ) ? $kind : null;
      $updated[]           = $type;
    }

    return [ $updated, $skipped, $candidates, $kinds ];
  }


}
