<?php

namespace WPML\Core\Component\Translation\Application\Repository;

use WPML\Core\Component\Translation\Domain\Settings\ReviewMode;
use WPML\Core\Component\Translation\Domain\Settings\Settings;
use WPML\Core\Component\Translation\Domain\Settings\TranslateAutomaticallyPerPostType;
use WPML\Core\Component\Translation\Domain\Settings\TranslateEverything;
use WPML\Core\Port\Persistence\GroupedOptionsInterface;
use WPML\Core\Port\Persistence\OptionsInterface;
use WPML\Core\SharedKernel\Component\Setting\Application\Query\TranslationEditorQueryInterface;
use WPML\Core\SharedKernel\Component\Setting\Application\Service\TranslationEditorWriterInterface;
use WPML\Core\SharedKernel\Component\Setting\Domain\TranslationEditorSetting;

class SettingsRepository {

  const SETUP_OPTIONS = 'WPML(setup)';
  const SETUP_OPTIONS_GROUP = 'setup';

  const AUTOMATIC_PER_POST_TYPE = 'WPML(post-type)';

  private $options;

  private $groupedOptions;

  private $settingTranslationEditorQuery;

  private $translationEditorWriter;

  private $completedMaps;


  public function __construct(
    OptionsInterface $options,
    TranslationEditorQueryInterface $settingTranslationEditorService,
    GroupedOptionsInterface $groupedOptions,
    TranslationEditorWriterInterface $translationEditorWriter,
    TranslateEverythingCompletedMapsInterface $completedMaps
  ) {
    $this->options = $options;
    $this->settingTranslationEditorQuery = $settingTranslationEditorService;
    $this->groupedOptions = $groupedOptions;
    $this->translationEditorWriter = $translationEditorWriter;
    $this->completedMaps = $completedMaps;
  }


  public function saveReviewMode( ReviewMode $reviewOption ) {
    $this->groupedOptions->save(
      self::SETUP_OPTIONS_GROUP,
      'review-mode',
      $reviewOption->getValue()
    );
  }


  public function getSettings(): Settings {
    $rawSetupOptions = $this->getSetupOptions();

    $rawAutomaticPerPostTypeOptions = $this->getOptions( self::AUTOMATIC_PER_POST_TYPE );

    $reviewMode = isset( $rawSetupOptions['review-mode'] ) && is_string( $rawSetupOptions['review-mode'] ) ?
      new ReviewMode( $rawSetupOptions['review-mode'] ) :
      null;

    $translationEditor = $this->getTranslationEditorSetting();

    return new Settings(
      isset( $rawSetupOptions['is-tm-allowed'] ) ? (bool) $rawSetupOptions['is-tm-allowed'] : false,
      $this->getTranslateEverythingSettings( $rawSetupOptions ),
      new TranslateAutomaticallyPerPostType( $rawAutomaticPerPostTypeOptions ),
      $reviewMode,
      $translationEditor
    );
  }


  public function shouldTranslateAutomaticallyDrafts(): bool {
    return (bool) $this->groupedOptions->get(
      self::SETUP_OPTIONS_GROUP,
      'translate-everything-drafts',
      false
    );
  }


  public function saveShouldTranslateAutomaticallyDrafts( bool $flag ) {
    $this->groupedOptions->save(
      self::SETUP_OPTIONS_GROUP,
      'translate-everything-drafts',
      $flag ? '1' : '0'
    );
  }


  private function getTranslationEditorSetting() {
    return $this->settingTranslationEditorQuery->getTranslationEditorSetting();
  }


  private function getTranslateEverythingSettings( array $rawSetupOptions ): TranslateEverything {
    $isTranslateEverythingEnabled = isset( $rawSetupOptions['translate-everything'] ) ?
      (bool) $rawSetupOptions['translate-everything'] :
      false;

    $translateEverything = new TranslateEverything(
      $isTranslateEverythingEnabled,
      isset( $rawSetupOptions['has-translate-everything-been-ever-used'] )
      && $rawSetupOptions['has-translate-everything-been-ever-used']
    );

    if (
      isset( $rawSetupOptions['translate-everything-posts'] ) &&
      $this->isValidStringArrayMap( $rawSetupOptions['translate-everything-posts'] )
    ) {
      $completedPosts = $this->prepareForJsonEncode( $rawSetupOptions['translate-everything-posts'] );
      $translateEverything->setCompletedPosts( $completedPosts );
    }
    if (
      isset( $rawSetupOptions['translate-everything-packages'] ) &&
      $this->isValidStringArrayMap( $rawSetupOptions['translate-everything-packages'] )
    ) {
      $completedPackages = $this->prepareForJsonEncode( $rawSetupOptions['translate-everything-packages'] );
      $translateEverything->setCompletedPackages( $completedPackages );
    }
    if (
      isset( $rawSetupOptions['translate-everything-strings'] ) &&
      $this->isValidCompletedStrings( $rawSetupOptions['translate-everything-strings'] )
    ) {
      $completedStrings = $this->prepareForJsonEncode( $rawSetupOptions['translate-everything-strings'], true );
      $translateEverything->setCompletedStrings( $completedStrings );
    }

    return $translateEverything;
  }


  private function isValidStringArrayMap( $data ): bool {
    if ( ! is_array( $data ) ) {
      return false;
    }

    foreach ( $data as $key => $value ) {
      if ( ! is_string( $key ) || ! is_array( $value ) ) {
        return false;
      }

      foreach ( $value as $element ) {
        if ( ! is_string( $element ) ) {
          return false;
        }
      }
    }

    return true;
  }


  private function isValidCompletedStrings( $completedStrings ): bool {
    if ( ! is_array( $completedStrings ) ) {
      return false;
    }

    foreach ( $completedStrings as $language ) {
      if ( ! is_string( $language ) ) {
        return false;
      }
    }

    return true;
  }


  public function deleteAutomaticPerPostTypeOption() {
    $this->options->delete( self::AUTOMATIC_PER_POST_TYPE );
  }


  public function saveSettings( Settings $settings ) {
    $reviewMode = $settings->getReviewMode();

    $data = [
      'is-tm-allowed'                           => $settings->isTMAllowed(),
      'translate-everything'                    => $settings->getTranslateEverything()->isEnabled(),
      'has-translate-everything-been-ever-used' => $settings->getTranslateEverything()->hasEverBeenEnabled(),
      'review-mode'                             => $reviewMode ? $reviewMode->getValue() : null,
    ];

    foreach ( $data as $key => $value ) {
      $this->groupedOptions->save( self::SETUP_OPTIONS_GROUP, $key, $value );
    }

    $this->maybeSaveTranslationEditor( $settings );
  }


  public function removeTranslateEverythingCompleted( array $postTypes, array $packageTypes ) {
    if ( $postTypes ) {
      $this->completedMaps->removeTypes( TranslateEverythingCompletedMapsInterface::POSTS, array_values( $postTypes ) );
    }

    if ( $packageTypes ) {
      $this->completedMaps->removeTypes(
        TranslateEverythingCompletedMapsInterface::PACKAGES,
        array_values( array_unique( array_merge( $packageTypes, array_map( 'ucfirst', $packageTypes ) ) ) )
      );
    }
  }


  private function maybeSaveTranslationEditor( Settings $settings ) {
    $editor = $settings->getTranslationEditor();
    if ( $editor ) {
      $this->translationEditorWriter->save( $editor );
    }
  }


  private function getOptions( string $optionsKey ): array {
    $option = $this->options->get( $optionsKey );

    return is_array( $option ) ? $option : [];
  }


  private function getSetupOptions(): array {
    $keys = [
      'is-tm-allowed',
      'review-mode',
      'translate-everything',
      'has-translate-everything-been-ever-used',
      'translate-everything-posts',
      'translate-everything-packages',
      'translate-everything-strings',
    ];

    $options = [];
    foreach ( $keys as $key ) {
      $value = $this->groupedOptions->get( self::SETUP_OPTIONS_GROUP, $key, null );
      if ( $value !== null ) {
        $options[ $key ] = $value;
      }
    }

    return $options;
  }


  private function prepareForJsonEncode( array $completedItems, bool $resetKeysForParentArray = false ): array {
    if ( $resetKeysForParentArray ) {
      return array_values( $completedItems );
    }

    return array_map(
      function ( $args ) {
        return is_array( $args ) ? array_values( $args ) : $args;
      },
      $completedItems
    );
  }


}
