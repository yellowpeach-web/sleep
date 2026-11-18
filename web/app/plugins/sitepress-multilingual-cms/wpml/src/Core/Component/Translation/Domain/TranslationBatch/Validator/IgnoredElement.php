<?php

namespace WPML\Core\Component\Translation\Domain\TranslationBatch\Validator;

use WPML\Core\Component\Translation\Domain\TranslationMethod\TranslationMethodInterface;
use WPML\Core\Component\Translation\Domain\TranslationType;

class IgnoredElement {

  private $translationType;

  private $elementId;

  private $targetLanguageCode;

  private $translationMethod;

  private $reason;

  private $message;


  public function __construct(
    TranslationType $translationType,
    int $elementId,
    string $targetLanguageCode,
    TranslationMethodInterface $translationMethod,
    string $reason,
    ?string $message = null
  ) {
    $this->translationType    = $translationType;
    $this->elementId          = $elementId;
    $this->targetLanguageCode = $targetLanguageCode;
    $this->translationMethod  = $translationMethod;
    $this->reason             = $reason;
    $this->message            = $message;
  }


  public function getTranslationType(): TranslationType {
    return $this->translationType;
  }


  public function getElementId(): int {
    return $this->elementId;
  }


  public function getTargetLanguageCode(): string {
    return $this->targetLanguageCode;
  }


  public function getTranslationMethod(): TranslationMethodInterface {
    return $this->translationMethod;
  }


  public function getReason(): string {
    return $this->reason;
  }


  public function getMessage(): string {
    return $this->message ?? $this->reason;
  }


}
