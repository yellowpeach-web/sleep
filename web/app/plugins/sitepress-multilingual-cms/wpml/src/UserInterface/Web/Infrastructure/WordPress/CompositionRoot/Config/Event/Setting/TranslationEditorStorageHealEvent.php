<?php

namespace WPML\UserInterface\Web\Infrastructure\WordPress\CompositionRoot\Config\Event\Setting;

use WPML\DicInterface;
use WPML\Infrastructure\PluginFilesMatchLoadedVersionGate;
use WPML\Infrastructure\WordPress\Component\Setting\Application\Service\TranslationEditorStorageHeal;

class TranslationEditorStorageHealEvent {

  use PluginFilesMatchLoadedVersionGate;

  const HOOK     = 'init';
  const PRIORITY = 0;

  private $dic;


  public function __construct( DicInterface $dic, ?callable $filesMatchLoadedVersion = null ) {
    $this->dic                     = $dic;
    $this->filesMatchLoadedVersion = $filesMatchLoadedVersion;
    add_action( self::HOOK, [ $this, 'onInit' ], self::PRIORITY );
  }


  public function onInit() {
    if ( ! $this->filesMatchLoadedVersion() ) {
      return;
    }
    $heal = $this->dic->make( TranslationEditorStorageHeal::class );
    $heal->run();
  }


}
