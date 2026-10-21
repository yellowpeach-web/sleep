<?php

namespace WPML\UserInterface\Web\Infrastructure\WordPress\CompositionRoot\Config\Event\Translation\Posts;

use WPML\DicInterface;
use WPML\Infrastructure\PluginFilesMatchLoadedVersionGate;
use WPML\Infrastructure\WordPress\Component\Translation\Application\Event\Posts\LanguageOfAutosavedDraftPostListener;

class PostInsertedEvent {

  use PluginFilesMatchLoadedVersionGate;

  private $dic;

  private $setLanguageToAutosavedDraftPostListener;


  public function __construct( DicInterface $dic, ?callable $filesMatchLoadedVersion = null ) {
    $this->dic                     = $dic;
    $this->filesMatchLoadedVersion = $filesMatchLoadedVersion;
    $this->register();
  }


  public function register() {
    add_action( 'wp_after_insert_post', [ $this, 'onPostInserted' ], 10, 4 );
  }


  public function onPostInserted( $postId, $post, $update, $postBefore ) {
    if ( ! $this->filesMatchLoadedVersion() ) {
      return;
    }
    $this->getSetLanguageToAutosavedDraftPostListener()->setLanguage( $postId, $post, $update, $postBefore );
  }


  private function getSetLanguageToAutosavedDraftPostListener(): LanguageOfAutosavedDraftPostListener {
    if ( $this->setLanguageToAutosavedDraftPostListener === null ) {
      $this->setLanguageToAutosavedDraftPostListener = $this->dic->make(
        LanguageOfAutosavedDraftPostListener::class
      );
    }

    return $this->setLanguageToAutosavedDraftPostListener;
  }


}
