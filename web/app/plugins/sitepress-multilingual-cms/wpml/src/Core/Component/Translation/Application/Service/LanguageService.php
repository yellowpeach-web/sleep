<?php

namespace WPML\Core\Component\Translation\Application\Service;

use WPML\Core\Component\Translation\Application\Authorization\TranslationGroupAuthorizationInterface;
use WPML\Core\Component\Translation\Application\Repository\TranslationNotFoundException;
use WPML\Core\Component\Translation\Application\Repository\TranslationRepositoryInterface;
use WPML\Core\Component\Translation\Domain\TranslationType;
use WPML\Core\Security\ExecutionContext\ExecutionContext;
use WPML\PHP\Exception\InvalidArgumentException;

class LanguageService {

  private $translationRepository;

  private $groupAuthorization;


  public function __construct(
    TranslationRepositoryInterface $translationRepository,
    TranslationGroupAuthorizationInterface $groupAuthorization
  ) {
    $this->translationRepository = $translationRepository;
    $this->groupAuthorization    = $groupAuthorization;
  }


  public function setLanguageOfElement(
    ExecutionContext $context,
    int $elementId,
    string $itemType,
    string $elementType,
    string $languageCode,
    ?string $sourceLanguageCode = null,
    ?int $trid = null
  ) {
    if ( $this->isInvalidTranslationRelation( $sourceLanguageCode, $trid ) ) {
      throw new InvalidArgumentException( 'Source language and trid must be provided together or not at all' );
    }

    $itemType = new TranslationType( $itemType );
    if ( $this->hasAlreadyTranslation( $itemType, $elementType, $elementId ) ) {
      throw new InvalidArgumentException( 'Translation already exists' );
    }

    if ( $trid !== null ) {
      $this->authorizeGroupJoin( $context, $trid, $itemType, $elementType, $languageCode );
    }

    $this->translationRepository->saveElementLanguage(
      $itemType,
      $elementType,
      $elementId,
      $languageCode,
      $sourceLanguageCode,
      $trid
    );
  }


  private function authorizeGroupJoin(
    ExecutionContext $context,
    int $trid,
    TranslationType $itemType,
    string $elementType,
    string $languageCode
  ): void {
    if ( $context->isTrusted() ) {
      return;
    }

    $wpmlElementType = $itemType->get() . '_' . $elementType;

    if ( ! $this->groupAuthorization->currentUserCanJoinGroup( $trid, $wpmlElementType, $languageCode ) ) {
      throw new UnauthorizedTranslationGroupJoinException( $trid, $wpmlElementType, $languageCode );
    }
  }


  private function hasAlreadyTranslation( TranslationType $itemType, string $elementType, int $elementId ): bool {
    try {
      $this->translationRepository->get( $itemType, $elementType, $elementId );

      return true;
    } catch ( TranslationNotFoundException $e ) {
      return false;
    }
  }


  private function isInvalidTranslationRelation( ?string $sourceLanguageCode = null, ?int $trid = null ): bool {
    return ( $sourceLanguageCode === null ) !== ( $trid === null );
  }


}
