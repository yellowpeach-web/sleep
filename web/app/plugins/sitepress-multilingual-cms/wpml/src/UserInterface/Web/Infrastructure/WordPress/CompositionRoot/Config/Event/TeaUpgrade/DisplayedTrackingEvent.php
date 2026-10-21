<?php

namespace WPML\UserInterface\Web\Infrastructure\WordPress\CompositionRoot\Config\Event\TeaUpgrade;

use WPML\DicInterface;
use WPML\Infrastructure\PluginFilesMatchLoadedVersionGate;
use WPML\UserInterface\Web\Infrastructure\WordPress\Component\Notices\TeaUpgrade\TeaUpgradeCompactNoticeRenderer;

class DisplayedTrackingEvent {

  use PluginFilesMatchLoadedVersionGate;


  public function __construct( DicInterface $dic, ?callable $filesMatchLoadedVersion = null ) {
    $this->filesMatchLoadedVersion = $filesMatchLoadedVersion;
    if ( ! $this->filesMatchLoadedVersion() ) {
      return;
    }

    $renderer = $dic->make( TeaUpgradeCompactNoticeRenderer::class );
    $renderer->registerWpDashboardHook();
  }


}
