<?php

namespace WPML\Core\Component\Translation\Application\Service\Validator;

use WPML\Core\Component\Translation\Application\Repository\SettingsRepository;
use WPML\Core\Component\Translation\Application\Service\Dto\SendToTranslationDto;
use WPML\Core\Component\Translation\Application\Service\Dto\TargetLanguageMethodDto;
use WPML\Core\Component\Translation\Application\Service\Validator\Dto\ValidationResultDto;
use WPML\Core\Component\Translation\Domain\TranslationMethod\AutomaticMethod;
use WPML\Core\Component\Translation\Domain\TranslationMethod\Validator\TranslationEditorTypeValidator;
use WPML\Core\SharedKernel\Component\ATE\Application\Query\AutomaticTranslationAccountQueryInterface;
use WPML\Core\SharedKernel\Component\Translation\Domain\TranslationMethod\TargetLanguageMethodType;

class TranslationEditorValidatorService implements ValidatorServiceInterface {

  const TYPE = 'translation-editor-validation';

  const TYPE_ACCOUNT = 'automatic-translation-account-validation';

  private $settingsRepository;

  private $accountQuery;


  public function __construct(
    SettingsRepository $settingsRepository,
    AutomaticTranslationAccountQueryInterface $accountQuery
  ) {
    $this->settingsRepository = $settingsRepository;
    $this->accountQuery       = $accountQuery;
  }


  public function validate( SendToTranslationDto $sendToTranslationDto ): ValidationResultDto {
    $automaticMethods = $this->extractAutomaticMethods( $sendToTranslationDto );

    if ( $this->accountQuery->needsAccountOnly() ) {
      return new ValidationResultDto(
        self::TYPE_ACCOUNT,
        ! count( $automaticMethods ) || $this->accountQuery->isUsable()
      );
    }

    $translationEditorSetting   = $this->settingsRepository->getSettings()->getTranslationEditor();
    $translationEditorValidator = new TranslationEditorTypeValidator( $translationEditorSetting );

    $result = $translationEditorValidator->validate( $automaticMethods );

    return new ValidationResultDto( $this->getType(), $result );
  }


  public function getType(): string {
    return self::TYPE;
  }


  public function extractAutomaticMethods( SendToTranslationDto $sendToTranslationDto ): array {
    $automaticMethods = array_filter(
      $sendToTranslationDto->getTargetLanguageMethods(),
      function ( TargetLanguageMethodDto $targetLanguageMethodDto ) {
        return $targetLanguageMethodDto->getTranslationMethod() === TargetLanguageMethodType::AUTOMATIC;
      }
    );

    return array_map(
      function ( TargetLanguageMethodDto $automaticMethod ) {
        return new AutomaticMethod();
      },
      $automaticMethods
    );
  }


}
