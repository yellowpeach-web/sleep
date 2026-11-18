<?php

namespace WPML\Legacy\Component\Translation\Sender;

use WPML\Core\Component\Translation\Domain\TranslationBatch\TranslationBatch;
use WPML\Core\Component\Translation\Domain\TranslationBatch\Validator\IgnoredElement;
use WPML\Core\Component\Translation\Domain\TranslationBatch\Validator\ReviewOpenInAteValidator;
use WPML\Core\Component\Translation\Domain\TranslationBatch\Validator\ValidatorInterface;
use WPML\TM\ATE\Review\ReviewRulesFactory;

class ReviewOpenInAteGate implements ValidatorInterface {

  private $validator;


  public function __construct( ReviewOpenInAteValidator $validator ) {
    $this->validator = $validator;
  }


  public function validate( TranslationBatch $translationBatch ): array {
    if ( ! ReviewRulesFactory::create()->refusesSendOfReviewOpenInAte() ) {
      return [ $translationBatch, [] ];
    }

    return $this->validator->validate( $translationBatch );
  }


}
