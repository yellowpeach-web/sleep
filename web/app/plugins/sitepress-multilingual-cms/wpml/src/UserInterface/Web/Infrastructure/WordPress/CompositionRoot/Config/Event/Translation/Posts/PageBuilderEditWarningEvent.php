<?php

namespace WPML\UserInterface\Web\Infrastructure\WordPress\CompositionRoot\Config\Event\Translation\Posts;

use WPML\DicInterface;
use WPML\Infrastructure\PluginFilesMatchLoadedVersionGate;
use WPML\UserInterface\Web\Core\Component\Notices\WarningTranslationEdit\Application\WarningTranslationEditController;

class PageBuilderEditWarningEvent {

  use PluginFilesMatchLoadedVersionGate;

  private $dic;

  private $warningTranslationEditController;


  public function __construct( DicInterface $dic, ?callable $filesMatchLoadedVersion = null ) {
    $this->dic                     = $dic;
    $this->filesMatchLoadedVersion = $filesMatchLoadedVersion;
    $this->register();
  }


  public function register() {
    add_action(
      'wpml_maybe_display_modal_page_builder_warning',
      function( int $postId, string $pageBuilderName, array $args = [] ) {
        if ( ! $this->filesMatchLoadedVersion() ) {
          return;
        }
        $this->getWarningTranslationEditController()->maybeShowPageBuilderWarning( $postId, $pageBuilderName, $args );
      },
      10,
      3
    );

  }


  private function getWarningTranslationEditController(): WarningTranslationEditController {

    if ( $this->warningTranslationEditController === null ) {
      $this->warningTranslationEditController = $this->dic->make(
        WarningTranslationEditController::class
      );
    }

    return $this->warningTranslationEditController;

  }


}
