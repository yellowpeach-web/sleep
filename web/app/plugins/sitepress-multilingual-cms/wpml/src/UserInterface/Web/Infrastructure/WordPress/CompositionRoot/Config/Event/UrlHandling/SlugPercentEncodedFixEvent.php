<?php

namespace WPML\UserInterface\Web\Infrastructure\WordPress\CompositionRoot\Config\Event\UrlHandling;

use WPML\DicInterface;
use WPML\Infrastructure\PluginFilesMatchLoadedVersionGate;
use WPML\Infrastructure\WordPress\Component\UrlHandling\Application\Hook\SlugPercentEncodedFixAction;

class SlugPercentEncodedFixEvent {

  use PluginFilesMatchLoadedVersionGate;

  private $dic;

  private $slugPercentEncodedFixAction;


  public function __construct( DicInterface $dic, ?callable $filesMatchLoadedVersion = null ) {
    $this->dic                     = $dic;
    $this->filesMatchLoadedVersion = $filesMatchLoadedVersion;
    $this->register();
  }


  public function register() {
    add_filter(
      'sanitize_title',
      function ( $title, $rawTitle = '', $context = '' ) {
        if ( ! $this->filesMatchLoadedVersion() ) {
          return $title;
        }
        return $this->getSlugPercentEncodedFixAction()
            ->fixPercentInTranslationSlug( $title, $rawTitle, $context );
      },
      9,
      3
    );
  }


  private function getSlugPercentEncodedFixAction(): SlugPercentEncodedFixAction {
    if ( $this->slugPercentEncodedFixAction === null ) {
      $this->slugPercentEncodedFixAction = $this->dic->make(
        SlugPercentEncodedFixAction::class
      );
    }

    return $this->slugPercentEncodedFixAction;
  }


}
