<?php

namespace WPML\UserInterface\Web\Infrastructure\WordPress\CompositionRoot\Config;

use WPML\ConfigEventsInterface;
use WPML\DicInterface;
use WPML\UserInterface\Web\Infrastructure\WordPress\CompositionRoot\Config\Event\SiteLock\SiteLockDetectedEvent;
use WPML\UserInterface\Web\Infrastructure\WordPress\CompositionRoot\Config\Event\Item\WordCount\Events as WordCountEvents;
use WPML\UserInterface\Web\Infrastructure\WordPress\CompositionRoot\Config\Event\UrlHandling\SlugPercentEncodedFixEvent;
use WPML\Infrastructure\WordPress\Component\PostHog\Application\Event\SetupWizardEventQueueReplayScheduler;
use WPML\UserInterface\Web\Infrastructure\WordPress\CompositionRoot\Config\Event\WpmlPosthog\PostHogShouldRecordEvent;
use WPML\UserInterface\Web\Infrastructure\WordPress\CompositionRoot\Config\Event\TeaUpgrade\DisplayedTrackingEvent;


class ConfigEvents implements ConfigEventsInterface {

  private $dic;


  public function __construct( DicInterface $dic ) {
    $this->dic = $dic;
  }


  public function loadEvents() {
    new Event\Translation\Links\ItemUpdateEvent( $this->dic );
    new Event\Translation\Posts\PostInsertedEvent( $this->dic );
    new Event\Translation\Posts\PageBuilderEditWarningEvent( $this->dic );
    new Event\Translation\StartUsingDashboardBanner\Events( $this->dic );
    new WordCountEvents( $this->dic );
    new PostHogShouldRecordEvent( $this->dic );
    new SetupWizardEventQueueReplayScheduler( $this->dic );
    new SiteLockDetectedEvent( $this->dic );
    new Event\ReportContentStats\LanguageChangeEvent( $this->dic );
    new Event\ReportContentStats\TranslationCompletedEvent( $this->dic );
    new Event\ReportContentStats\ContentChangeEvent( $this->dic );
    new Event\ReportContentStats\TeaToggleEvent( $this->dic );
    new Event\UrlHandling\SlugPercentEncodedFixEvent( $this->dic );
    new Event\ATE\SetupCompletedEvent( $this->dic );
    new Event\ATE\LanguageAddedEvent( $this->dic );
    new Event\ATE\PostTypeBecameTranslatableEvent( $this->dic );
    new Event\ATE\TeaRecheckScopeEvent( $this->dic );
    new Event\Setting\TranslationEditorStorageHealEvent( $this->dic );
    new DisplayedTrackingEvent( $this->dic );
    new Event\Support\SupportToolsEvent( $this->dic );
    new Event\Cdt\CdtEvents( $this->dic );
  }


}
