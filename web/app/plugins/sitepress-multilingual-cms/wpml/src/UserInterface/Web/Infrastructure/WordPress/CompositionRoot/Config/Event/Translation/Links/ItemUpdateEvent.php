<?php

namespace WPML\UserInterface\Web\Infrastructure\WordPress\CompositionRoot\Config\Event\Translation\Links;

use WPML\DicInterface;
use WPML\Infrastructure\PluginFilesMatchLoadedVersionGate;
use WPML\Infrastructure\WordPress\Component\Translation\Application\Event\Links\ItemUpdateEventListenerAdapter;
use WP_Post;

class ItemUpdateEvent {

  use PluginFilesMatchLoadedVersionGate;

  private $dic;

  private $itemUpdateEventListenerAdapter;


  public function __construct( DicInterface $dic, ?callable $filesMatchLoadedVersion = null ) {
    $this->dic                     = $dic;
    $this->filesMatchLoadedVersion = $filesMatchLoadedVersion;
    $this->register();
  }


  private function getItemsUpdateEventAdapter(): ItemUpdateEventListenerAdapter {
    if ( $this->itemUpdateEventListenerAdapter === null ) {
      $this->itemUpdateEventListenerAdapter =
        $this->dic->make( ItemUpdateEventListenerAdapter::class );
    }

    return $this->itemUpdateEventListenerAdapter;
  }


  public function register() {
    add_action(
      'post_updated',
      function( $_, $__, $postBeforeSave ) {
        if ( ! $this->filesMatchLoadedVersion() ) {
          return;
        }
        $this->getItemsUpdateEventAdapter()->onPostUpdate( $postBeforeSave );
      },
      10,
      3
    );

    add_action( 'save_post', [ $this, 'onPostSaved' ], 10, 2 );

    add_action(
      'create_term',
      function( int $termId ) {
        if ( ! $this->filesMatchLoadedVersion() ) {
          return;
        }
        $this->getItemsUpdateEventAdapter()->onTermCreation( $termId );
      }
    );

    add_action(
      'edit_terms',
      function( int $termId ) {
        if ( ! $this->filesMatchLoadedVersion() ) {
          return;
        }
        $this->getItemsUpdateEventAdapter()->beforeTermUpdate( $termId );
      }
    );

    add_action(
      'shutdown',
      function() {
        if ( ! $this->filesMatchLoadedVersion() ) {
          return;
        }
        $this->getItemsUpdateEventAdapter()->onShutdown();
      }
    );
  }


  public function onPostSaved( $postId, $post ) {
    if ( ! $post instanceof WP_Post ) {
      return;
    }

    if ( ! $this->filesMatchLoadedVersion() ) {
      return;
    }

    $this->getItemsUpdateEventAdapter()->onPostSave( (int) $postId, $post );
  }


}
