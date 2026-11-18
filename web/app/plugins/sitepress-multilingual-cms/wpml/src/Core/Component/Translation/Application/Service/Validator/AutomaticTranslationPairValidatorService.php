<?php

namespace WPML\Core\Component\Translation\Application\Service\Validator;

use WPML\Core\Component\Translation\Application\Query\AutomaticTranslationSupportQueryInterface;
use WPML\Core\Component\Translation\Application\Service\Dto\SendToTranslationDto;
use WPML\Core\Component\Translation\Application\Service\Dto\TargetLanguageMethodDto;
use WPML\Core\Component\Translation\Application\Service\Validator\Dto\ValidationResultDto;
use WPML\Core\SharedKernel\Component\Translation\Domain\TranslationMethod\TargetLanguageMethodType;

class AutomaticTranslationPairValidatorService implements ValidatorServiceInterface {

  const TYPE = 'automatic-translation-pair-validation';

  private $supportQuery;


  public function __construct( AutomaticTranslationSupportQueryInterface $supportQuery ) {
    $this->supportQuery = $supportQuery;
  }


  public function validate( SendToTranslationDto $sendToTranslationDto ): ValidationResultDto {
    $automaticTargets = $this->automaticTargets( $sendToTranslationDto );
    if ( ! $automaticTargets ) {
      return new ValidationResultDto( $this->getType(), true );
    }

    $support = $this->supportQuery->supportFrom( $sendToTranslationDto->getSourceLanguageCode() );

    $refused = [];
    foreach ( $automaticTargets as $code ) {
      if ( isset( $support[ $code ] ) && false === $support[ $code ]['supported'] ) {
        $refused[] = $code;
      }
    }

    return new ValidationResultDto( $this->getType(), ! $refused, $refused );
  }


  public function getType(): string {
    return self::TYPE;
  }


  private function automaticTargets( SendToTranslationDto $sendToTranslationDto ): array {
    $codes = [];
    foreach ( $sendToTranslationDto->getTargetLanguageMethods() as $targetLanguageMethod ) {
      if ( TargetLanguageMethodType::AUTOMATIC === $targetLanguageMethod->getTranslationMethod() ) {
        $codes[] = $targetLanguageMethod->getTargetLanguageCode();
      }
    }

    return array_values( array_unique( $codes ) );
  }


}
