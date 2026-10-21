<?php

namespace WPML\Core\Component\ATE\Application\Service;

use WPML\Core\Component\ATE\Application\Query\WebsiteContextException;
use WPML\Core\Component\ATE\Application\Query\WebsiteContextQueryInterface;
use WPML\Core\Port\PluginInterface;

class TranslateEverythingPrerequisites {

  const REFUSAL_AI_SETUP = 'tea-requires-ai-setup';

  const REFUSAL_SITE_DESCRIPTION = 'tea-requires-site-description';

  private $plugin;

  private $ptcEngineStatus;

  private $websiteContextQuery;


  public function __construct(
    PluginInterface $plugin,
    PtcEngineStatus $ptcEngineStatus,
    WebsiteContextQueryInterface $websiteContextQuery
  ) {
    $this->plugin              = $plugin;
    $this->ptcEngineStatus     = $ptcEngineStatus;
    $this->websiteContextQuery = $websiteContextQuery;
  }


  public function getRefusalKey() {
    if ( $this->plugin->isAiSetupSkipped() ) {
      return self::REFUSAL_AI_SETUP;
    }

    if ( ! $this->plugin->isSetupComplete() ) {
      return null;
    }

    if ( ! $this->ptcEngineStatus->isDefaultEngine() ) {
      return null;
    }

    try {
      if ( ! $this->websiteContextQuery->isContextPresent() ) {
        return self::REFUSAL_SITE_DESCRIPTION;
      }
    } catch ( WebsiteContextException $e ) {
      return self::REFUSAL_SITE_DESCRIPTION;
    }

    return null;
  }


}
