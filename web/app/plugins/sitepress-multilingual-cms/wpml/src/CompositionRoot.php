<?php

namespace WPML;

use WPML\Core\SharedKernel\Component\Language\Application\ProspectiveLanguages;
use WPML\Legacy\Component\WordsToTranslate\Domain\Post\JobQuery;
use WPML\Legacy\Component\Post\Application\Query\PostTypeObjectCache;
use WPML\Legacy\Component\WordsToTranslate\Domain\StringPackage\JobQuery as StringPackageJobQuery;
use WPML\UserInterface\Web\Infrastructure\WordPress\Component\Support\SupportToolRegistry;
use WPML\Core\Component\Cdt\Application\Service\CheckinRequest;
use WPML\Core\Component\Cdt\Application\Service\CheckinTrigger;
use WPML\Core\Component\Cdt\Application\Service\EventQueue;
use WPML\UserInterface\Web\Infrastructure\WordPress\Component\Translations\TranslationsTabStripDispatcher;

class CompositionRoot {

  private $dic;

  private $config;

  private $configEvents;


  public function __construct(
    DicInterface $dic,
    ConfigInterface $config,
    ConfigEventsInterface $configEvents
  ) {
    $this->dic          = $dic;
    $this->config       = $config;
    $this->configEvents = $configEvents;

    $this->defineShares();
    $this->defineAliases();
    $this->defineClasses();
  }


  public function loadRESTEndpoints() {
    $this->config->loadRESTEndpoints();
  }


  public function loadAjaxEndpoints() {
    $this->config->loadAjaxEndpoints();
  }


  public function registerAdminPages() {
    $this->config->registerAdminPages();
  }


  public function loadAdminNotices() {
    $this->config->loadAdminNotices();
  }


  public function loadEventListeners() {
    $this->configEvents->loadEvents();
  }


  public function loadAdminStyles() {
    $this->config->loadAdminStyles();
  }


  public function loadAdminScripts() {
    $this->config->loadAdminScripts();
  }


  public function prepareUpdates() {
    $this->config->prepareUpdates();
  }


  public function loadContentStatsScripts() {
    $this->config->loadContentStatsScripts();
  }


  public function loadCheckPosthogShouldRecordScript() {
    $this->config->loadCheckPosthogShouldRecordScript();
  }


  private function defineAliases() {
    foreach ( $this->config->getInterfaceMappings() as $interface => $class ) {
      $this->dic->alias( $interface, $class );
    }
  }


  private function defineClasses() {
    foreach ( $this->config->getClassDefinitions() as $class => $args ) {
      if ( is_callable( $args ) ) {
        $wrappedFactory = function () use ( $args ) {
          return call_user_func( $args, $this->dic, $this->config->getPlugin() );
        };

        $this->dic->delegate( $class, $wrappedFactory );
      } else {
        $this->dic->define( $class, $args );
      }
    }
  }


  private function defineShares() {
    global $wpdb, $sitepress;
    $this->dic->share( $wpdb );
    $this->dic->share( $sitepress );
    $this->dic->share( JobQuery::class );
    $this->dic->share( StringPackageJobQuery::class );
    $this->dic->share( ProspectiveLanguages::class );
    $this->dic->share( TranslationsTabStripDispatcher::class );
    $this->dic->share( SupportToolRegistry::class );
    $this->dic->share( CheckinRequest::class );
    $this->dic->share( EventQueue::class );
    $this->dic->share( CheckinTrigger::class );
    $this->dic->share( new PostTypeObjectCache() );
    $this->dic->defineParam( 'wpdb', $wpdb );
    $this->dic->defineParam( 'sitepress', $sitepress );

    $wpdbClass = is_object( $wpdb ) ? get_class( $wpdb ) : \wpdb::class;
    if ( $wpdbClass !== \wpdb::class ) {
      $this->dic->alias( \wpdb::class, $wpdbClass );
    }
  }


}
