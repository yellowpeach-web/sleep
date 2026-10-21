<?php

namespace WPML\UserInterface\Web\Infrastructure\WordPress\Component\Support;

use WPML\UserInterface\Web\Core\Component\Support\Application\SupportTool;
use WPML\UserInterface\Web\Infrastructure\WordPress\Component\Support\Tool\ATEErrorLogsController;
use WPML\UserInterface\Web\Infrastructure\WordPress\Component\Support\Tool\AliasDomainsController;
use WPML\UserInterface\Web\Infrastructure\WordPress\Component\Support\Tool\CommunicationLogController;
use WPML\UserInterface\Web\Infrastructure\WordPress\Component\Support\Tool\FixTranslationsController;
use WPML\UserInterface\Web\Infrastructure\WordPress\Component\Support\Tool\InstallerLogController;
use WPML\UserInterface\Web\Infrastructure\WordPress\Component\Support\Tool\JobLogsController;
use WPML\UserInterface\Web\Infrastructure\WordPress\Component\Support\Tool\OrphanLanguageCodesController;
use WPML\UserInterface\Web\Infrastructure\WordPress\Component\Support\Tool\RemoteXMLConfigLogController;
use WPML\UserInterface\Web\Infrastructure\WordPress\Component\Support\Tool\SystemCheckController;
use WPML\UserInterface\Web\Infrastructure\WordPress\Component\Support\Tool\UsageTrackingController;
use WPML\UserInterface\Web\Infrastructure\WordPress\Component\Support\Tool\WcmlConfigLogController;
use WPML\UserInterface\Web\Infrastructure\WordPress\Component\Support\Tool\WcmlStatusController;
use WPML\UserInterface\Web\Infrastructure\WordPress\Component\Wcml\WcmlBonded;

class CoreSupportTools {

  private $systemCheck;

  private $fixTranslations;

  private $orphanLanguageCodes;

  private $communicationLog;

  private $ateErrorLogs;

  private $jobLogs;

  private $installerLog;

  private $remoteXmlConfigLog;

  private $usageTracking;

  private $aliasDomains;

  private $wcmlStatus;

  private $wcmlConfigLog;


  public function __construct(
    SystemCheckController $systemCheck,
    FixTranslationsController $fixTranslations,
    OrphanLanguageCodesController $orphanLanguageCodes,
    CommunicationLogController $communicationLog,
    ATEErrorLogsController $ateErrorLogs,
    JobLogsController $jobLogs,
    InstallerLogController $installerLog,
    RemoteXMLConfigLogController $remoteXmlConfigLog,
    UsageTrackingController $usageTracking,
    AliasDomainsController $aliasDomains,
    WcmlStatusController $wcmlStatus,
    WcmlConfigLogController $wcmlConfigLog
  ) {
    $this->systemCheck                = $systemCheck;
    $this->fixTranslations            = $fixTranslations;
    $this->orphanLanguageCodes        = $orphanLanguageCodes;
    $this->communicationLog           = $communicationLog;
    $this->ateErrorLogs               = $ateErrorLogs;
    $this->jobLogs                    = $jobLogs;
    $this->installerLog               = $installerLog;
    $this->remoteXmlConfigLog         = $remoteXmlConfigLog;
    $this->usageTracking              = $usageTracking;
    $this->aliasDomains               = $aliasDomains;
    $this->wcmlStatus                 = $wcmlStatus;
    $this->wcmlConfigLog              = $wcmlConfigLog;
  }


  public function register( $tools ): array {
    $tools = is_array( $tools ) ? $tools : [];

    return array_merge( $tools, $this->tier1(), $this->tier2(), $this->tier3(), $this->wcml() );
  }


  private function tier1(): array {
    return [
      [
        'slug'        => 'system-check',
        /* translators: Name of a Support tool: its entry in the tool list on WPML → Support and the heading of its own screen. */
        'title'       => __( 'System check', 'wpml' ),
        'description' => __( 'Debug information for support and connectivity tests to WPML servers', 'wpml' ),
        'tier'        => SupportTool::TIER_SAFE,
        'order'       => 10,
        'controller'  => $this->systemCheck,
        'icon'        => 'M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z',
        'search'      => [
          /* translators: Name of a section of the System check tool on WPML → Support: its heading and its entry in the Support search list. */
          [ 'label' => __( 'Debug information', 'wpml' ), 'anchor' => 'debug-info' ],
          [ 'label' => __( 'Copy system info to clipboard', 'wpml' ), 'anchor' => 'debug-info' ],
          /* translators: Name of a section of the System check tool on WPML → Support: its entry in the Support search list. It checks the part of WPML that installs and updates plugins. */
          [ 'label' => __( 'Installer support', 'wpml' ), 'anchor' => 'installer' ],
          [ 'label' => __( 'Check connectivity to WPML servers', 'wpml' ), 'anchor' => 'installer' ],
          [ 'label' => __( 'Required PHP libraries', 'wpml' ), 'anchor' => 'installer' ],
          [ 'label' => __( 'Refresh license data', 'wpml' ), 'anchor' => 'refresh-license' ],
        ],
      ],
      [
        'slug'        => 'fix-translations',
        /* translators: Name of the Clear cache Support tool: its entry in the tool list on WPML → Support, the heading of its screen and the button that runs it. Verb phrase, imperative. */
        'title'       => __( 'Clear cache', 'wpml' ),
        'description' => __( 'Clear the cache in WPML', 'wpml' ),
        'tier'        => SupportTool::TIER_SAFE,
        'order'       => 30,
        'controller'  => $this->fixTranslations,
        'icon'        => 'M15.232 5.232l3.536 3.536M9 11l6-6 3.536 3.536L12.536 14.536H9V11zM4 20h16',
        'search'      => [
          [ 'label' => __( 'Clear the cache in WPML', 'wpml' ), 'anchor' => 'clear-cache' ],
        ],
      ],
      [
        'slug'        => OrphanLanguageCodesController::TOOL_SLUG,
        'title'       => __( 'Content from unknown languages', 'wpml' ),
        'description' => __( 'Some content is assigned to language codes this site does not define. See which codes, and what carries them.', 'wpml' ),
        'tier'        => SupportTool::TIER_SAFE,
        'order'       => 60,
        'controller'  => $this->orphanLanguageCodes,
        'applicable'  => [ OrphanLanguageCodesController::class, 'isApplicable' ],
        'icon'        => 'M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15',
        'search'      => [
          [ 'label' => __( 'Unknown language codes found', 'wpml' ), 'anchor' => 'orphan-codes-list' ],
          [ 'label' => __( 'What you can do', 'wpml' ), 'anchor' => 'orphan-codes-what-to-do' ],
        ],
      ],
    ];
  }


  private function tier2(): array {
    return [
      [
        'slug'        => 'communication-log',
        /* translators: Name of a Support tool: its entry in the tool list on WPML → Support and the heading of its own screen. It records the traffic between the site and the translation services. */
        'title'       => __( 'Communication log', 'wpml' ),
        'description' => __( 'API traffic between your site and the translation services', 'wpml' ),
        'tier'        => SupportTool::TIER_LOGS,
        'order'       => 10,
        'controller'  => $this->communicationLog,
        'icon'        => 'M8 12h.01M12 12h.01M16 12h.01M21 12c0 4.418-4.03 8-9 8a9.77 9.77 0 01-4-.8L3 20l1.3-3.9A7.95 7.95 0 013 12c0-4.418 4.03-8 9-8s9 3.582 9 8z',
        'search'      => [
          [ 'label' => __( 'Translation proxy requests and responses', 'wpml' ), 'anchor' => '' ],
          [ 'label' => __( 'TP communication trace', 'wpml' ), 'anchor' => '' ],
        ],
      ],
      // license has neither ATE nor translation jobs (wpmldev-7163).
      [
        'slug'        => 'ate-error-logs',
        'title'       => __( 'Advanced Translation Editor error logs', 'wpml' ),
        'description' => __( 'Server-side errors encountered by the Advanced Translation Editor', 'wpml' ),
        'tier'        => SupportTool::TIER_LOGS,
        'order'       => 20,
        'controller'  => $this->ateErrorLogs,
        'requiresTm'  => true,
        'icon'        => 'M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z',
        'search'      => [
          [ 'label' => __( 'Automatic translation errors', 'wpml' ), 'anchor' => '' ],
          [ 'label' => __( 'ATE client error trace', 'wpml' ), 'anchor' => '' ],
        ],
      ],
      [
        'slug'        => 'job-logs',
        'title'       => __( 'Translation job logs', 'wpml' ),
        'description' => __( 'Lifecycle of translation jobs sent through WPML', 'wpml' ),
        'tier'        => SupportTool::TIER_LOGS,
        'order'       => 30,
        'controller'  => $this->jobLogs,
        'requiresTm'  => true,
        'icon'        => 'M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-6 9h2m-2 4h6m-6-8h6',
        'search'      => [
          [ 'label' => __( 'Per-job audit trail', 'wpml' ), 'anchor' => '' ],
          [ 'label' => __( 'Translation pipeline history', 'wpml' ), 'anchor' => '' ],
        ],
      ],
      [
        'slug'        => 'installer-log',
        /* translators: Name of a Support tool: its entry in the tool list on WPML → Support and the heading of its own screen. */
        'title'       => __( 'Installer log', 'wpml' ),
        'description' => __( "Licensing and subscription-update calls made by WPML's installer", 'wpml' ),
        'tier'        => SupportTool::TIER_LOGS,
        'order'       => 40,
        'controller'  => $this->installerLog,
        'icon'        => 'M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4',
        'search'      => [
          [ 'label' => __( 'Plugin install and update history', 'wpml' ), 'anchor' => '' ],
          [ 'label' => __( 'OTGS Installer events', 'wpml' ), 'anchor' => '' ],
        ],
      ],
      [
        'slug'        => 'remote-xml-config-log',
        'title'       => __( 'Remote XML config log', 'wpml' ),
        /* translators: Description of a Support tool in the tool list on WPML → Support. A noun phrase with no verb. wpml-config.xml is a file name and stays as it is. */
        'description' => __( 'wpml-config.xml files WPML has read from themes and plugins', 'wpml' ),
        'tier'        => SupportTool::TIER_LOGS,
        'order'       => 50,
        'controller'  => $this->remoteXmlConfigLog,
        'icon'        => 'M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z',
        'search'      => [
          [ 'label' => __( 'Remote language-configuration fetches', 'wpml' ), 'anchor' => '' ],
          [ 'label' => __( 'XML config retrieval history', 'wpml' ), 'anchor' => '' ],
        ],
      ],
    ];
  }


  private function tier3(): array {
    return [
      // license (wpmldev-7163). ATE sync (order 10) is WPML Troubleshooting's.
      [
        'slug'        => 'alias-domains',
        /* translators: Name of a Support tool: its entry in the tool list on WPML → Support and the heading of its own screen. Other web addresses that reach the same site. */
        'title'       => __( 'Alias domains', 'wpml' ),
        'description' => __( 'Register alternative URLs that point to this same WordPress installation', 'wpml' ),
        'tier'        => SupportTool::TIER_ADVANCED,
        'order'       => 20,
        'controller'  => $this->aliasDomains,
        'requiresTm'  => true,
        'icon'        => 'M13.828 10.172a4 4 0 010 5.656l-2 2a4 4 0 01-5.656-5.656l1.102-1.101m3.898 2.757a4 4 0 010-5.656l2-2a4 4 0 015.656 5.656l-1.1 1.1',
        'search'      => [
          [ 'label' => __( 'Register an alias domain', 'wpml' ), 'anchor' => '' ],
          [ 'label' => __( 'Reset registered alias domains', 'wpml' ), 'anchor' => '' ],
          [ 'label' => __( 'Use an alternative URL for the same WordPress installation', 'wpml' ), 'anchor' => '' ],
        ],
      ],
      [
        'slug'        => 'usage-tracking',
        'title'       => __( 'Usage tracking and reporting', 'wpml' ),
        'description' => __( 'Send WPML performance and error reports to help support diagnose issues', 'wpml' ),
        'tier'        => SupportTool::TIER_ADVANCED,
        'order'       => 50,
        'controller'  => $this->usageTracking,
        'icon'        => 'M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z',
        'search'      => [
          [ 'label' => __( 'Enable or disable anonymous usage tracking', 'wpml' ), 'anchor' => '' ],
          [ 'label' => __( 'PostHog telemetry toggle', 'wpml' ), 'anchor' => '' ],
        ],
      ],
    ];
  }


  private function wcml(): array {
    $applicable = [ WcmlBonded::class, 'rendersEmbeddedBodies' ];
    $cartIcon   = 'M3 3h2l.4 2M7 13h10l4-8H5.4M7 13L5.4 5M7 13l-2.293 2.293c-.63.63-.184 1.707.707 1.707H17m0 0a2 2 0 100 4 2 2 0 000-4zm-8 2a2 2 0 11-4 0 2 2 0 014 0z';

    return [
      [
        'slug'        => 'wcml-status',
        /* translators: Name of a Support tool: its entry in the tool list on WPML → Support and the heading of its own screen. WCML is the short name of WooCommerce Multilingual. */
        'title'       => __( 'WCML status', 'wpml' ),
        'description' => __( 'WooCommerce store-pages, products and taxonomies translation status; multicurrency, plugins, media checks', 'wpml' ),
        'tier'        => SupportTool::TIER_SAFE,
        'order'       => 70,
        'searchOrder' => 9010,
        'controller'  => $this->wcmlStatus,
        'applicable'  => $applicable,
        'icon'        => $cartIcon,
        'search'      => [
          [ 'label' => __( 'WooCommerce store-pages translation status', 'wpml' ), 'anchor' => '' ],
          [ 'label' => __( 'Products and taxonomies translation status', 'wpml' ), 'anchor' => '' ],
          [ 'label' => __( 'Multicurrency / plugins / media checks', 'wpml' ), 'anchor' => '' ],
        ],
      ],
      [
        'slug'        => 'wcml-config-log',
        'title'       => __( 'WCML configuration log', 'wpml' ),
        'description' => __( 'Read-only snapshot of the WooCommerce Multilingual configuration; copy when WPML Support asks for the WCML state', 'wpml' ),
        'tier'        => SupportTool::TIER_LOGS,
        'order'       => 60,
        'searchOrder' => 9030,
        'controller'  => $this->wcmlConfigLog,
        'applicable'  => $applicable,
        'icon'        => $cartIcon,
        'search'      => [
          [ 'label' => __( 'Read-only WCML configuration snapshot', 'wpml' ), 'anchor' => '' ],
        ],
      ],
    ];
  }


}
