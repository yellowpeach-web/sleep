<?php

namespace WPML\UserInterface\Web\Infrastructure\WordPress\CompositionRoot\Config\Event\ReportContentStats;

use WPML\DicInterface;
use WPML\Infrastructure\PluginFilesMatchLoadedVersionGate;
use WPML\UserInterface\Web\Infrastructure\WordPress\Events\Cdt\AutoTranslationStampsListener;
use WPML\UserInterface\Web\Infrastructure\WordPress\Events\ReportContentStats\TranslationCompletedEventListener;

class TranslationCompletedEvent {

  use PluginFilesMatchLoadedVersionGate;

  const EVENT_NAME = 'wpml_pro_translation_completed';

  private $dic;

  private $listener;

  private $stampsListener;


  public function __construct( DicInterface $dic, ?callable $filesMatchLoadedVersion = null ) {
    $this->dic                     = $dic;
    $this->filesMatchLoadedVersion = $filesMatchLoadedVersion;
    $this->register();
  }


  public function register() {
    add_action(
      self::EVENT_NAME,
      function ( $newPostId = null, $fields = null, $job = null ) {
        if ( ! $this->filesMatchLoadedVersion() ) {
          return;
        }
        $this->getListener()->doActions();
        $this->getStampsListener()->onTranslationCompleted( $job );
      },
      10,
      3
    );
  }


  private function getStampsListener(): AutoTranslationStampsListener {
    if ( $this->stampsListener === null ) {
      $this->stampsListener = $this->dic->make( AutoTranslationStampsListener::class );
    }

    return $this->stampsListener;
  }


  private function getListener(): TranslationCompletedEventListener {
    if ( $this->listener === null ) {
      $this->listener = $this->dic->make( TranslationCompletedEventListener::class );
    }

    return $this->listener;
  }


}
