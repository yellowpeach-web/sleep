<?php

namespace WPML\Core\Component\Translation\Domain\TranslationBatch\Validator;

use WPML\Core\Component\Translation\Domain\TranslationBatch\Element;
use WPML\Core\Component\Translation\Domain\TranslationBatch\TargetLanguage;
use WPML\Core\Component\Translation\Domain\TranslationBatch\TranslationBatch;
use WPML\Core\SharedKernel\Component\Translation\Domain\ReviewStatus;

class ReviewOpenInAteValidator implements ValidatorInterface {

  const IGNORED_ELEMENT_REASON = 'review_open_in_ate';


  public function validate( TranslationBatch $translationBatch ): array {
    $ignoredElements = [];
    $targetLanguages = [];

    foreach ( $translationBatch->getTargetLanguages() as $targetLanguage ) {
      $languageCode    = $targetLanguage->getLanguageCode();
      $correctElements = [];

      foreach ( $targetLanguage->getElements() as $element ) {
        if ( $this->hasReviewOpenInAte( $element, $languageCode ) ) {
          $ignoredElements[] = new IgnoredElement(
            $element->getType(),
            $element->getElementId(),
            $languageCode,
            $targetLanguage->getMethod(),
            self::IGNORED_ELEMENT_REASON
          );
        } else {
          $correctElements[] = $element;
        }
      }

      $targetLanguages[] = new TargetLanguage( $languageCode, $targetLanguage->getMethod(), $correctElements );
    }

    return [ $translationBatch->copyWithNewTargetLanguages( $targetLanguages ), $ignoredElements ];
  }


  private function hasReviewOpenInAte( Element $element, string $languageCode ): bool {
    foreach ( $element->getExistingTranslations() as $translation ) {
      if ( $translation->getTargetLanguageCode() !== $languageCode ) {
        continue;
      }

      $reviewStatus = $translation->getReviewStatus();
      if ( $reviewStatus && ReviewStatus::EDITING === $reviewStatus->getValue() ) {
        return true;
      }
    }

    return false;
  }


}
