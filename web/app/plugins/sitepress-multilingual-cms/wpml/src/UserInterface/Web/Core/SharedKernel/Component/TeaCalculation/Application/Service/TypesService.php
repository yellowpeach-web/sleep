<?php

namespace WPML\UserInterface\Web\Core\SharedKernel\Component\TeaCalculation\Application\Service;

use WPML\Core\SharedKernel\Component\Item\Application\Query\UntranslatedTypesCountQueryInterface;
use WPML\Core\SharedKernel\Component\Item\Application\Service\UntranslatedService;
use WPML\Core\SharedKernel\Component\Post\Domain\TranslateEverythingScope;
use WPML\UserInterface\Web\Core\SharedKernel\Component\TeaCalculation\Application\Repository\ParkedTypesOfferRepositoryInterface;
use WPML\UserInterface\Web\Core\SharedKernel\Component\TeaCalculation\Application\Repository\PostTypesSinceRepositoryInterface;
use WPML\UserInterface\Web\Core\SharedKernel\Component\TeaCalculation\Application\Repository\PendingTranslatableOfferRepositoryInterface;
use WPML\UserInterface\Web\Core\SharedKernel\Component\TeaCalculation\Application\Validator\TypeIncludeRules;
use WPML\UserInterface\Web\Core\SharedKernel\Component\TeaCalculation\Application\Service\Dto\TypeDto;

class TypesService {

  const TYPES_TO_EXCLUDE = TranslateEverythingScope::TYPES_NOT_OFFERED;

  private $untranslatedService;

  private $postTypesRepository;

  private $pendingOffer;

  private $parkedOffer;


  public function __construct(
    UntranslatedService $untranslatedService,
    PostTypesSinceRepositoryInterface $postTypesRepository,
    PendingTranslatableOfferRepositoryInterface $pendingOffer,
    ParkedTypesOfferRepositoryInterface $parkedOffer
  ) {
    $this->untranslatedService = $untranslatedService;
    $this->postTypesRepository = $postTypesRepository;
    $this->pendingOffer        = $pendingOffer;
    $this->parkedOffer         = $parkedOffer;
  }


  public function getTypesWithUntranslatedItems(): array {
    $offer = $this->pendingOffer->getPendingTypes();

    $parkedLabels = [];
    foreach ( $this->parkedOffer->getTypeLabels() as $slug => $label ) {
      if ( TranslateEverythingScope::isOffered( $slug ) ) {
        $parkedLabels[ $slug ] = $label;
      }
    }

    $counts = $this->untranslatedService->getUntranslatedTypesCounts( array_keys( $offer ), $parkedLabels );
    $sinceDates = $this->postTypesRepository->getPostTypesSinceDates();

    $mapped = [];
    $taxonomyItems = null;
    $taxonomyHeldOut = 0;
    $taxonomyCount = 0;

    foreach ( $counts as $count ) {
      $type = $count->toArray();
      if ( in_array( $type['type'], self::TYPES_TO_EXCLUDE, true ) ) {
        continue;
      }

      if ( $type['kind'] === UntranslatedTypesCountQueryInterface::KIND_TAXONOMY ) {
        $taxonomyItems = ( $taxonomyItems ?? 0 ) + $type['count'];
        $taxonomyHeldOut += $type['heldOutCount'];
        $taxonomyCount++;
        continue;
      }

      $typeDto = new TypeDto(
        $type['kind'],
        $type['type'],
        $type['nameSingular'],
        $type['namePlural'],
        $sinceDates[ $type['type'] ] ?? '0000-00-00',
        $type['count'],
        $type['heldOutCount']
      );
      $type = empty( $type['type'] ) ? $type['kind'] : $type['type'];
      $mapped[$type] = $typeDto;
    }

    if ( $taxonomyItems !== null ) {
      $mapped[ UntranslatedTypesCountQueryInterface::KIND_TAXONOMY ] = new TypeDto(
        UntranslatedTypesCountQueryInterface::KIND_TAXONOMY,
        '',
        /* translators: Singular name of a kind of content: a category, a tag or another way of grouping content. Used as a tab title on WPML → Translations and on the Translate Everything word-count list. */
        __( 'Taxonomy', 'wpml' ),
        /* translators: Plural of the same name on the Translate Everything word-count list: categories, tags and other ways of grouping content. */
        __( 'Taxonomies', 'wpml' ),
        '0000-00-00',
        $taxonomyItems,
        $taxonomyHeldOut,
        $taxonomyCount
      );
    }

    foreach ( $offer as $slug => $modes ) {
      if ( ! isset( $mapped[ $slug ] ) ) {
        continue;
      }
      $offeredMode = $modes['new'];
      $mapped[ $slug ]->includeSince = $offeredMode === 2
        ? TypeIncludeRules::NONE
        : TypeIncludeRules::ALL;
    }

    uksort(
      $mapped,
      function ( $a, $b ) use ( $mapped ) {
        $order = [ 'page', 'post', 'product' ];
        $aIndex = array_search( $a, $order, true );
        $bIndex = array_search( $b, $order, true );
        if ( $aIndex === false ) {
          $aIndex = count( $order );
        }
        if ( $bIndex === false ) {
          $bIndex = count( $order );
        }
        if ( $aIndex === $bIndex ) {
          return $mapped[$b]->itemsTotal <=> $mapped[$a]->itemsTotal;
        }
        return $aIndex <=> $bIndex;
      }
    );

    return $mapped;
  }


  public function updateTypeSinceDate( string $type, string $since ): void {
    $this->postTypesRepository->updatePostTypeSinceDate( $type, $since );
  }


  public function persistOfferedTypeDefaults(): void {
    $offeredPostTypes = [];

    foreach ( $this->getTypesWithUntranslatedItems() as $type => $dto ) {
      if ( $dto->kind === UntranslatedTypesCountQueryInterface::KIND_POST ) {
        $offeredPostTypes[] = $type;
      }
    }

    $this->postTypesRepository->fillMissingSinceDates( $offeredPostTypes );
  }


}
