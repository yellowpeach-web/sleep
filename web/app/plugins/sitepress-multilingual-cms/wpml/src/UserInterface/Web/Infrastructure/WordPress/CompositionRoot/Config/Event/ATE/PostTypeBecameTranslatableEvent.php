<?php

namespace WPML\UserInterface\Web\Infrastructure\WordPress\CompositionRoot\Config\Event\ATE;

use WPML\DicInterface;
use WPML\Infrastructure\PluginFilesMatchLoadedVersionGate;
use WPML\UserInterface\Web\Infrastructure\WordPress\Events\ATE\PostTypeBecameTranslatableEventListener;

class PostTypeBecameTranslatableEvent {

  use PluginFilesMatchLoadedVersionGate;

  const EVENT_NAME = 'wpml_save_cpt_sync_settings';

  private $dic;

  private $isTmLoaded;

  private $listener;


  public function __construct( DicInterface $dic, ?callable $isTmLoaded = null, ?callable $filesMatchLoadedVersion = null ) {
    $this->dic                     = $dic;
    $this->isTmLoaded              = $isTmLoaded ?: [ TranslationManagementLoaded::class, 'forRequest' ];
    $this->filesMatchLoadedVersion = $filesMatchLoadedVersion;
    $this->register();
  }


  public function register() {
    add_action( self::EVENT_NAME, [ $this, 'onPostTypeSyncSettingsSaved' ] );
  }


  /**
   * Gated before the make(), not inside doActions(): see TranslationManagementLoaded.
   * (wpmldev-8391: on a Blog license the Post Types save was written, then the
   * ajax died with a 500 building this listener.) The wpmldev-11371 version
   * gate runs first: on the update request the old injector cannot build the
   * listener from the new class files at all.
   *
   * @return void
   */
  public function onPostTypeSyncSettingsSaved() {
    if ( ! $this->filesMatchLoadedVersion() ) {
      return;
    }
    if ( ! call_user_func( $this->isTmLoaded ) ) {
      return;
    }
    $this->getListener()->doActions();
  }


  private function getListener(): PostTypeBecameTranslatableEventListener {
    if ( $this->listener === null ) {
      $this->listener = $this->dic->make( PostTypeBecameTranslatableEventListener::class );
    }

    return $this->listener;
  }


}
