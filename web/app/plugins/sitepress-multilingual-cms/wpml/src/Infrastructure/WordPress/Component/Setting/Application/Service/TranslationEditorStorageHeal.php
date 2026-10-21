<?php

namespace WPML\Infrastructure\WordPress\Component\Setting\Application\Service;

use WPML\Core\SharedKernel\Component\Setting\Application\Service\TranslationEditorWriterInterface;
use WPML\Core\SharedKernel\Component\Setting\Domain\TranslationEditorSetting;

class TranslationEditorStorageHeal {

  private $settingsReader;

  private $writer;


  public function __construct( SitepressSettingsReader $settingsReader, TranslationEditorWriterInterface $writer ) {
    $this->settingsReader = $settingsReader;
    $this->writer         = $writer;
  }


  public function run() {
    $stored = $this->settingsReader->getTranslationManagementSetting( TranslationEditorStorageFormat::KEY );
    if ( ! TranslationEditorStorageFormat::isBadString( $stored ) ) {
      return;
    }

    $this->writer->save( new TranslationEditorSetting( TranslationEditorStorageFormat::toDomain( $stored ) ) );
  }


}
