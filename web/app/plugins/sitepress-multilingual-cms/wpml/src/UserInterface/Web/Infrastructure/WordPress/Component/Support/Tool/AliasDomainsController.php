<?php

namespace WPML\UserInterface\Web\Infrastructure\WordPress\Component\Support\Tool;

use WPML\Core\SharedKernel\Component\Site\Application\Query\SiteUrlQueryInterface;
use WPML\TM\ATE\ClonedSites\SecondaryDomains;
use WPML\UserInterface\Web\Core\Port\Script\ScriptDataProviderInterface;
use WPML\UserInterface\Web\Core\Port\Script\ScriptPrerequisitesInterface;
use WPML\UserInterface\Web\Core\SharedKernel\Config\PageRenderInterface;

class AliasDomainsController implements
  PageRenderInterface,
  ScriptDataProviderInterface,
  ScriptPrerequisitesInterface {

  private $secondaryDomains;

  private $siteUrlQuery;


  public function __construct(
    SecondaryDomains $secondaryDomains,
    SiteUrlQueryInterface $siteUrlQuery
  ) {
    $this->secondaryDomains = $secondaryDomains;
    $this->siteUrlQuery     = $siteUrlQuery;
  }


  public function render() {
    ?>
      <h1 class="wpml:text-2xl wpml:font-semibold wpml:text-gray-900 wpml:mb-1">
        <?php /* translators: Name of a Support tool: its entry in the tool list on WPML → Support and the heading of its own screen. Other web addresses that reach the same site. */ esc_html_e( 'Alias domains', 'wpml' ); ?>
      </h1>
      <p class="wpml:text-sm wpml:text-gray-500 wpml:mb-6">
        <?php esc_html_e( 'Register another URL that points to this same WordPress installation.', 'wpml' ); ?>
      </p>
      <div id="wpml-troubleshooting-container-new"></div>
    <?php
  }


  public function jsWindowKey(): string {
    return 'troubleShootingScriptData';
  }


  public function initialScriptData(): array {
    return [
      'aliasDomain' => [
        'aliasDomains'   => $this->secondaryDomains->getInfo(),
        'currentSiteUrl' => $this->siteUrlQuery->get(),
      ],
    ];
  }


  public function scriptPrerequisitesMet(): bool {
    return isset( $_GET['page'], $_GET['tool'] ) &&
           $_GET['page'] === 'sitepress-multilingual-cms/menu/support.php' &&
           $_GET['tool'] === 'alias-domains';
  }


}
