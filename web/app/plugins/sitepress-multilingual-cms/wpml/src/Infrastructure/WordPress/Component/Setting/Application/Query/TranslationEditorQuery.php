<?php

namespace WPML\Infrastructure\WordPress\Component\Setting\Application\Query;

use WPML\Core\Port\Persistence\OptionsInterface;
use WPML\Core\SharedKernel\Component\Post\Domain\TranslationEditorPreference;
use WPML\Core\SharedKernel\Component\Setting\Application\Query\TranslationEditorQueryInterface;
use WPML\Core\SharedKernel\Component\Setting\Domain\TranslationEditorSetting;
use WPML\Infrastructure\WordPress\Component\Setting\Application\Service\SitepressSettingsReader;
use WPML\Infrastructure\WordPress\Component\Setting\Application\Service\TranslationEditorStorageFormat;

class TranslationEditorQuery implements TranslationEditorQueryInterface {

  private $settingsReader;

  private $options;


  public function __construct( SitepressSettingsReader $settingsReader, OptionsInterface $options ) {
    $this->settingsReader = $settingsReader;
    $this->options        = $options;
  }


  public function getTranslationEditorSetting() {
    $docTranslationMethod = $this->settingsReader->getTranslationManagementSetting( 'doc_translation_method' );
    if ( ! is_scalar( $docTranslationMethod ) ) {
      return null;
    }

    $editorSettings = new TranslationEditorSetting(
      TranslationEditorStorageFormat::toDomain( (string) $docTranslationMethod ),
      $this->useNativeEditorGlobally(),
      $this->getPostTypesUsingNativeEditor()
    );

    if ( $editorSettings->getValue() === TranslationEditorSetting::ATE ) {
      $optionValue = $this->options->get( 'wpml-old-jobs-editor' );
      $editorSettings->setUseAteForOldTranslationsCreatedWithCte( $optionValue === 'ate' );
    }

    return $editorSettings;
  }


  private function useNativeEditorGlobally(): bool {
    $editor = $this->settingsReader->getTranslationManagementSetting(
      TranslationEditorPreference::TM_KEY_GLOBAL_EDITOR
    );
    if ( $this->isValidEditor( $editor ) ) {
      return $editor === TranslationEditorPreference::EDITOR_NATIVE;
    }

    return $this->settingsReader->getTranslationManagementSetting(
      TranslationEditorPreference::TM_KEY_GLOBAL_USE_NATIVE,
      false
    ) === true;
  }


  private function getPostTypesUsingNativeEditor(): array {
    $perPostType = (array) $this->settingsReader->getTranslationManagementSetting(
      TranslationEditorPreference::TM_KEY_FOR_POST_TYPE_USE_NATIVE,
      []
    );

    $consolidated = (array) $this->settingsReader->getTranslationManagementSetting(
      TranslationEditorPreference::TM_KEY_FOR_POST_TYPE_EDITOR,
      []
    );
    foreach ( $consolidated as $postType => $editor ) {
      if ( $this->isValidEditor( $editor ) ) {
        $perPostType[ (string) $postType ] = $editor === TranslationEditorPreference::EDITOR_NATIVE;
      }
    }

    return $perPostType;
  }


  private function isValidEditor( $value ): bool {
    return in_array(
      $value,
      [
        TranslationEditorPreference::EDITOR_NATIVE,
        TranslationEditorPreference::EDITOR_WPML,
        TranslationEditorPreference::EDITOR_DASHBOARD,
      ],
      true
    );
  }


}
