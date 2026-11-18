<?php

namespace WPML\Core\Component\Translation\Domain\TranslationBatch\Validator;

use WPML\Core\Component\Translation\Domain\TranslationBatch\TargetLanguage;
use WPML\Core\Component\Translation\Domain\TranslationBatch\TranslationBatch;
use WPML\Core\Component\Translation\Domain\TranslationType;
use WPML\Core\SharedKernel\Component\Translation\Domain\PackageOwnerInterface;

class PackageRidesOwnerValidator implements ValidatorInterface {

  const IGNORED_ELEMENT_REASON = 'package_rides_owner';

  private $owners;


  public function __construct( PackageOwnerInterface $owners ) {
    $this->owners = $owners;
  }


  public function validate( TranslationBatch $translationBatch ): array {
    $ignoredElements = [];
    $targetLanguages = [];
    foreach ( $translationBatch->getTargetLanguages() as $targetLanguage ) {
      $validElements = [];
      foreach ( $targetLanguage->getElements() as $element ) {
        $ridesOwner = $element->getType()->get() === TranslationType::PACKAGE && $this->owners->ridesOwner( $element->getElementId() );
        if ( $ridesOwner ) {
          $owner             = $this->owners->getOwner( $element->getElementId() );
          $ignoredElements[] = new IgnoredElement(
            $element->getType(),
            $element->getElementId(),
            $targetLanguage->getLanguageCode(),
            $targetLanguage->getMethod(),
            self::IGNORED_ELEMENT_REASON,
            $this->owners->message( $owner )
          );
        } else {
          $validElements[] = $element;
        }
      }
      $targetLanguages[] = new TargetLanguage( $targetLanguage->getLanguageCode(), $targetLanguage->getMethod(), $validElements );
    }
    return [ $translationBatch->copyWithNewTargetLanguages( $targetLanguages ), $ignoredElements ];
  }

}
