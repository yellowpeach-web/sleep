<?php

namespace WPML\Core\Component\Translation\Application\Service\TranslationService\BatchBuilder;

use WPML\Core\Component\Translation\Application\Query\ItemLanguageQueryInterface;
use WPML\Core\Component\Translation\Application\Query\TranslationQueryInterface;
use WPML\Core\Component\Translation\Application\Service\Dto\SendToTranslationDto;
use WPML\Core\Component\Translation\Application\Service\Dto\TargetLanguageMethodDto;
use WPML\Core\Component\Translation\Domain\HowToHandleExistingTranslationType;
use WPML\Core\Component\Translation\Domain\Translation;
use WPML\Core\Component\Translation\Domain\TranslationBatch\DuplicationBatch;
use WPML\Core\Component\Translation\Domain\TranslationBatch\Element;
use WPML\Core\Component\Translation\Domain\TranslationBatch\TargetLanguage;
use WPML\Core\Component\Translation\Domain\TranslationBatch\TranslationBatch;
use WPML\Core\Component\Translation\Domain\TranslationBatch\Validator\IgnoredElement;
use WPML\Core\Component\Translation\Domain\TranslationBatch\Validator\ValidatorInterface;
use WPML\Core\Component\Translation\Domain\TranslationMethod\AutomaticMethod;
use WPML\Core\Component\Translation\Domain\TranslationMethod\DuplicateMethod;
use WPML\Core\Component\Translation\Domain\TranslationMethod\LocalTranslatorMethod;
use WPML\Core\Component\Translation\Domain\TranslationMethod\TranslationMethodInterface;
use WPML\Core\Component\Translation\Domain\TranslationMethod\TranslationServiceMethod;
use WPML\Core\Component\Translation\Domain\TranslationType;
use WPML\Core\SharedKernel\Component\Translation\Domain\TranslationMethod\TargetLanguageMethodType;
use WPML\PHP\DateTime;
use WPML\PHP\Exception\InvalidArgumentException;

class BatchBuilder implements BatchBuilderInterface {

  const IGNORED_ELEMENT_REASON_ORIGINAL_LANGUAGE_NOT_FOUND = 'original_language_not_found';

  private $translationValidator;

  private $translationQuery;

  private $itemLanguageQuery;


  public function __construct(
    ValidatorInterface $translationValidator,
    TranslationQueryInterface $translationQuery,
    ItemLanguageQueryInterface $itemLanguageQuery
  ) {
    $this->translationValidator   = $translationValidator;
    $this->translationQuery       = $translationQuery;
    $this->itemLanguageQuery      = $itemLanguageQuery;
  }


  public function build( SendToTranslationDto $sendToTranslationDto ): array {
    list( $elements, $unresolved ) = $this->buildElements( $sendToTranslationDto );

    $duplicationBatch = $this->buildDuplicationBatch( $sendToTranslationDto, $elements );
    list( $translationBatch, $ignoredElements ) = $this->buildTranslationBatch( $sendToTranslationDto, $elements );

    return [
      $translationBatch,
      $duplicationBatch,
      array_merge( $this->ignoredUnresolvedElements( $sendToTranslationDto, $unresolved ), $ignoredElements ),
    ];
  }


  private function buildElements( SendToTranslationDto $sendToTranslationDto ): array {
    $groupedElementLanguages = $this->getGroupedElementLanguages( $sendToTranslationDto );
    $unresolved              = [];

    $elements = array_merge(
      $this->mapElementsOfGivenType(
        $groupedElementLanguages,
        TranslationType::post(),
        $sendToTranslationDto->getPosts(),
        $unresolved
      ),
      $this->mapElementsOfGivenType(
        $groupedElementLanguages,
        TranslationType::package(),
        $sendToTranslationDto->getPackages(),
        $unresolved
      ),
      $this->mapElementsOfGivenType(
        $groupedElementLanguages,
        TranslationType::string(),
        $sendToTranslationDto->getStrings(),
        $unresolved
      ),
      $this->mapElementsOfGivenType(
        $groupedElementLanguages,
        TranslationType::taxonomy(),
        $sendToTranslationDto->getTaxonomies(),
        $unresolved
      )
    );

    return [ $elements, $unresolved ];
  }


  private function mapElementsOfGivenType(
    array $groupedElementLanguages,
    TranslationType $type,
    array $elementIds,
    array &$unresolved
  ): array {
    if ( ! count( $elementIds ) ) {
      return [];
    }

    $elementTranslations = $this->groupTranslationByOriginalElementId(
      $this->translationQuery->getManyByElementIds(
        $type,
        $elementIds
      )
    );

    $elements = [];

    foreach ( $elementIds as $elementId ) {
      $language = $groupedElementLanguages[ $type->get() ][ $elementId ] ?? null;
      if ( ! is_string( $language ) || '' === $language ) {
        $unresolved[] = [
          'type'      => $type,
          'elementId' => $elementId,
        ];
        continue;
      }

      $elements[] = new Element(
        $elementId,
        $type,
        $language,
        $elementTranslations[ $elementId ] ?? []
      );
    }

    return $elements;
  }


  private function groupTranslationByOriginalElementId( array $translations ): array {
    $groupedTranslations = [];

    foreach ( $translations as $translation ) {
      $elementId                           = $translation->getOriginalElementId();
      $groupedTranslations[ $elementId ][] = $translation;
    }

    return $groupedTranslations;
  }


  private function getGroupedElementLanguages( SendToTranslationDto $sendToTranslationDto ): array {
    $items = [];

    foreach ( $sendToTranslationDto->getPosts() as $postId ) {
      $items[] = [
        'type'   => TranslationType::post(),
        'itemId' => $postId
      ];
    }

    foreach ( $sendToTranslationDto->getPackages() as $packageId ) {
      $items[] = [
        'type'   => TranslationType::package(),
        'itemId' => $packageId
      ];
    }

    foreach ( $sendToTranslationDto->getStrings() as $stringId ) {
      $items[] = [
        'type'   => TranslationType::string(),
        'itemId' => $stringId
      ];
    }

    foreach ( $sendToTranslationDto->getTaxonomies() as $termTaxonomyId ) {
      $items[] = [
        'type'   => TranslationType::taxonomy(),
        'itemId' => $termTaxonomyId
      ];
    }

    $itemLanguages = $this->itemLanguageQuery->getManyOriginalLanguagesOfItems( $items );

    $groupedLanguages = [];

    foreach ( $itemLanguages as $itemLanguage ) {
      $groupedLanguages[ $itemLanguage['type']->get() ][ $itemLanguage['itemId'] ] = $itemLanguage['language'];
    }

    return $groupedLanguages;
  }


  private function mapTargetLanguageMethod(
    TargetLanguageMethodDto $targetLanguagesMethod
  ): TranslationMethodInterface {
    switch ( $targetLanguagesMethod->getTranslationMethod() ) {
      case TargetLanguageMethodType::DUPLICATE:
        $method = new DuplicateMethod();
        break;
      case TargetLanguageMethodType::LOCAL_TRANSLATOR:
        $method = new LocalTranslatorMethod(
          $targetLanguagesMethod->getTranslatorId() ?? 0,
          $targetLanguagesMethod->getTargetLanguageCode()
        );
        break;
      case TargetLanguageMethodType::TRANSLATION_SERVICE:
        $method = new TranslationServiceMethod(
          $targetLanguagesMethod->getTranslatorId() ?? 0
        );
        break;
      case TargetLanguageMethodType::AUTOMATIC:
        $method = new AutomaticMethod();
        break;
      default:
        throw new InvalidArgumentException( 'Invalid translation method!' );
    }

    return $method;
  }


  private function buildDuplicationBatch( SendToTranslationDto $sendToTranslationDto, array $elements ) {
    $targetLanguages = $this->getTargetLanguagesForDuplication( $sendToTranslationDto );
    if ( ! count( $targetLanguages ) ) {
      return null;
    }

    $postElements = array_filter(
      $elements,
      function ( Element $element ) {
        return $element->getType()->get() === TranslationType::POST;
      }
    );

    $postIds = array_map(
      function ( Element $element ) {
        return $element->getElementId();
      },
      $postElements
    );

    if ( ! count( $postIds ) ) {
      return null;
    }

    return new DuplicationBatch(
      $sendToTranslationDto->getBatchName(),
      $sendToTranslationDto->getSourceLanguageCode(),
      $targetLanguages,
      $postIds
    );
  }


  private function getTargetLanguagesForDuplication( SendToTranslationDto $sendToTranslationDto ): array {
    $filtered = array_filter(
      $sendToTranslationDto->getTargetLanguageMethods(),
      function ( TargetLanguageMethodDto $targetLanguageMethodDto ) {
        return $targetLanguageMethodDto->getTranslationMethod() === TargetLanguageMethodType::DUPLICATE;
      }
    );

    return array_map(
      function ( TargetLanguageMethodDto $targetLanguageMethodDto ) {
        return $targetLanguageMethodDto->getTargetLanguageCode();
      },
      $filtered
    );
  }


  private function buildTranslationBatch( SendToTranslationDto $sendToTranslationDto, array $elements ): array {
    $translationMethods = [];
    foreach ( $sendToTranslationDto->getTargetLanguageMethods() as $targetLanguageMethodDto ) {
      $method = $this->mapTargetLanguageMethod( $targetLanguageMethodDto );
      if ( ! $method instanceof DuplicateMethod ) {
        $targetElements = $this->elementsForTarget(
          $elements,
          $sendToTranslationDto->getTaxonomyTargets(),
          $targetLanguageMethodDto->getTargetLanguageCode()
        );

        if ( ! $targetElements ) {
          continue;
        }

        $translationMethods[] = new TargetLanguage(
          $targetLanguageMethodDto->getTargetLanguageCode(),
          $method,
          $targetElements
        );
      }
    }

    if ( ! count( $translationMethods ) ) {
      return [ null, [] ];
    }

    $extraInfo = $sendToTranslationDto->getExtraInformation();

    $translationBatch = new TranslationBatch(
      $sendToTranslationDto->getBatchName(),
      $sendToTranslationDto->getSourceLanguageCode(),
      $translationMethods,
      $extraInfo ?
        $extraInfo->getHowToHandleExistingTranslations() :
        HowToHandleExistingTranslationType::HANDLE_EXISTING_LEAVE,
      $extraInfo ? $extraInfo->getTranslationServiceExtraFields() : null,
      $extraInfo ? DateTime::create( $extraInfo->getDeadline() ) : null,
      $sendToTranslationDto->getOperationId()
    );

    return $this->translationValidator->validate( $translationBatch );
  }


  private function elementsForTarget( array $elements, array $taxonomyTargets, string $targetLanguage ): array {
    if ( ! $taxonomyTargets ) {
      return $elements;
    }

    $termIds = $taxonomyTargets[ $targetLanguage ] ?? [];

    return array_values(
      array_filter(
        $elements,
        function ( Element $element ) use ( $termIds ) {
          return TranslationType::TAXONOMY !== $element->getType()->get()
            || in_array( $element->getElementId(), $termIds, true );
        }
      )
    );
  }


  private function ignoredUnresolvedElements( SendToTranslationDto $sendToTranslationDto, array $unresolved ): array {
    if ( ! $unresolved ) {
      return [];
    }

    $taxonomyTargets = $sendToTranslationDto->getTaxonomyTargets();
    $ignoredElements = [];

    foreach ( $sendToTranslationDto->getTargetLanguageMethods() as $targetLanguageMethodDto ) {
      $targetLanguage = $targetLanguageMethodDto->getTargetLanguageCode();
      $method         = $this->mapTargetLanguageMethod( $targetLanguageMethodDto );

      foreach ( $unresolved as $item ) {
        $isOtherTermsTarget = TranslationType::TAXONOMY === $item['type']->get()
          && $taxonomyTargets
          && ! in_array( $item['elementId'], $taxonomyTargets[ $targetLanguage ] ?? [], true );
        if ( $isOtherTermsTarget ) {
          continue;
        }

        $ignoredElements[] = new IgnoredElement(
          $item['type'],
          $item['elementId'],
          $targetLanguage,
          $method,
          self::IGNORED_ELEMENT_REASON_ORIGINAL_LANGUAGE_NOT_FOUND
        );
      }
    }

    return $ignoredElements;
  }


}
