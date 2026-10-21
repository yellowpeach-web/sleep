<?php

namespace WPML\Infrastructure\WordPress\Component\Setting\Application\Service;

use WPML\Core\Port\Persistence\OptionsInterface;
use WPML\Core\SharedKernel\Component\Setting\Application\Service\TranslationEditorWriterInterface;
use WPML\Core\SharedKernel\Component\Setting\Domain\TranslationEditorSetting;

class TranslationEditorWriter implements TranslationEditorWriterInterface {

  const STORED_ACTION = 'wpml_translation_editor_stored';

  private $settingsReader;

  private $options;


  public function __construct( SitepressSettingsReader $settingsReader, OptionsInterface $options ) {
    $this->settingsReader = $settingsReader;
    $this->options        = $options;
  }


  public function save( TranslationEditorSetting $editor ) {
    $stored  = TranslationEditorStorageFormat::toStored( $editor->getValue() );
    $current = $this->settingsReader->getTranslationManagementSetting( TranslationEditorStorageFormat::KEY );

    if ( is_scalar( $current ) && (string) $current === (string) $stored ) {
      return;
    }

    $rawSitepressOptions = $this->options->get( SitepressSettingsReader::SITEPRESS_OPTIONS );
    $rawSitepressOptions = is_array( $rawSitepressOptions ) ? $rawSitepressOptions : [];

    $translationManagement = $rawSitepressOptions[ SitepressSettingsReader::TRANSLATION_MANAGEMENT ] ?? [];
    $translationManagement = is_array( $translationManagement ) ? $translationManagement : [];

    $translationManagement[ TranslationEditorStorageFormat::KEY ]           = $stored;
    $rawSitepressOptions[ SitepressSettingsReader::TRANSLATION_MANAGEMENT ] = $translationManagement;

    $this->options->save( SitepressSettingsReader::SITEPRESS_OPTIONS, $rawSitepressOptions );

    do_action( self::STORED_ACTION, $stored );
  }


}
