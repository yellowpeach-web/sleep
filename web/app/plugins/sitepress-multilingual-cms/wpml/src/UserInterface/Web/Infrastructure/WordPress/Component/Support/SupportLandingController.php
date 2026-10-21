<?php

namespace WPML\UserInterface\Web\Infrastructure\WordPress\Component\Support;

use SitePress;
use WPML\Core\SharedKernel\Component\WpmlOrgClient\Domain\Api\SupportUrl;
use WPML\Core\SharedKernel\Component\WpmlOrgClient\Domain\WpmlOrgOrigin;
use WPML\UserInterface\Web\Core\Component\Support\Application\SupportTool;
use WPML\UserInterface\Web\Core\Component\Support\Application\SupportToolRegistryInterface;
use WPML\UserInterface\Web\Core\SharedKernel\Config\PageRenderInterface;
use WPML\UserInterface\Web\Core\SharedKernel\Domain\SupportForumUrl;


class SupportLandingController implements PageRenderInterface {

  const REPAIR_TOOLS_PROVIDER = 'wpml-troubleshooting';

  const LANDING_TOP_HOOK = 'wpml_admin_support_landing_top';

  private $registry;

  private $unknownTool = '';


  public function __construct( SupportToolRegistryInterface $registry ) {
    $this->registry = $registry;
  }


  public function renderForUnknownTool( string $slug ) {
    $this->unknownTool = $slug;
    $this->render();
  }


  private function requestSupportForumUrl(): string {
    return WpmlOrgOrigin::map( SupportForumUrl::URL );
  }


  public function render() {
      echo '<div class="wrap wpml-support-page">';
      $this->renderInlineStyles();
      echo '<div class="wpml:max-w-3xl wpml:mt-6 wpml:pb-16">';

      $this->renderHeader();
      $this->renderMinimumRequirementsPanel();
      do_action( self::LANDING_TOP_HOOK );
      $this->renderSearchBar();
      $this->renderRequestSupportCard();
      $this->renderTierSections();
      $this->renderInstalledPluginsTable();

      echo '</div>';
      echo '</div>';

      $this->renderSearchScript();
      $this->renderPluginReportScript();
  }


  private function renderHeader(): void {
      echo '<h1 class="wpml:text-2xl wpml:font-semibold wpml:text-gray-900 wpml:mb-5">'
          /* translators: Name of the WPML Support screen: the admin menu item, the page heading, and the back-link that returns to it. Noun (help from the WPML support team), not the verb "to support". */
          . esc_html__( 'Support', 'wpml' )
          . '</h1>';
  }


  private function renderInlineStyles(): void {
    echo '<style>.wpml-support-page a{text-decoration:none}</style>';
  }


  private function readSiteKey(): string {
    if ( ! class_exists( '\WP_Installer' ) ) {
      return '';
    }
    $installer = \WP_Installer::instance();
    if ( ! is_object( $installer ) || ! method_exists( $installer, 'get_repository_site_key' ) ) {
      return '';
    }
    $key = $installer->get_repository_site_key( 'wpml' );
    return is_string( $key ) ? $key : '';
  }


  private function isSubscriptionValid(): bool {
    if ( ! class_exists( '\WP_Installer' ) ) {
      return false;
    }
    $installer = \WP_Installer::instance();
    if ( ! is_object( $installer ) || ! method_exists( $installer, 'repository_has_valid_subscription' ) ) {
      return false;
    }
    return (bool) $installer->repository_has_valid_subscription( 'wpml' );
  }


  private function renderMinimumRequirementsPanel(): void {
    if ( ! class_exists( '\WPML\Support\Initializer' ) ) {
      return;
    }

    $data = \WPML\Support\Initializer::getData();

    if ( empty( $data['showMinRequirementsComponent'] ) ) {
      return;
    }

    echo '<div class="wpml:mb-6">';
    echo '<wc-minimum-requirements items="' . $data['serializedInvalidRequirements'] . '" compact-variant="true"></wc-minimum-requirements>';
    echo '<div class="wpml:px-5 wpml:py-2 wpml:bg-amber-50 wpml:border wpml:border-t-0 wpml:border-amber-200 wpml:rounded-b wpml:text-xs wpml:text-amber-800">';
    echo '<a href="' . esc_url( self::minimumRequirementsUrl() ) . '" target="_blank" class="wpml:hover:underline">';
    echo esc_html__( 'See all WPML minimum requirements ↗', 'wpml' );
    echo '</a>';
    echo '</div>';
    echo '</div>';
  }


  private function renderSearchBar(): void {
    ?>
        <div class="wpml:relative wpml:mb-3" id="wpml-support-search-wrapper">
            <label for="wpml-support-search" class="wpml:sr-only">
              <?php /* translators: Label of the search box on WPML → Support, used both for screen readers and as the placeholder text inside the box. Verb phrase, imperative (search the Support screen). */ esc_html_e( 'Search Support', 'wpml' ); ?>
            </label>
            <div class="wpml:relative">
                <svg class="wpml:absolute wpml:left-4 wpml:top-1/2 wpml:-translate-y-1/2 wpml:w-5 wpml:h-5 wpml:text-gray-400 wpml:pointer-events-none" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-5.2-5.2M17 11a6 6 0 11-12 0 6 6 0 0112 0z"/>
                </svg>
                <input id="wpml-support-search" type="text"
                    placeholder="<?php /* translators: Label of the search box on WPML → Support, used both for screen readers and as the placeholder text inside the box. Verb phrase, imperative (search the Support screen). */ esc_attr_e( 'Search Support', 'wpml' ); ?>"
                    autocomplete="off"
                    role="combobox" aria-autocomplete="list" aria-expanded="false"
                    aria-controls="wpml-support-search-results-list"
                    class="wpml:w-full wpml:border-2 wpml:border-gray-200 wpml:focus:border-blue wpml:focus:outline-none wpml:focus:ring-4 wpml:focus:ring-blue/10 wpml:rounded-lg wpml:pl-12 wpml:pr-12 wpml:py-4 wpml:text-base wpml:bg-white wpml:transition-colors"/>
                <button id="wpml-support-search-clear" type="button" tabindex="-1"
                    aria-label="<?php /* translators: Accessible label (screen readers) of the button that empties the search box on WPML → Support. Verb phrase, imperative. */ esc_attr_e( 'Clear search', 'wpml' ); ?>"
                    class="wpml:hidden wpml:absolute wpml:right-3 wpml:top-1/2 wpml:-translate-y-1/2 wpml:text-gray-400 wpml:hover:text-gray-600 wpml:p-1">
                    <svg class="wpml:w-5 wpml:h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                    </svg>
                </button>
            </div>
        </div>

        <div id="wpml-support-search-results" class="wpml:hidden wpml:bg-white wpml:border wpml:border-gray-200 wpml:rounded-md wpml:overflow-hidden wpml:mb-6">
            <div id="wpml-support-search-results-list" role="listbox" aria-label="<?php /* translators: Accessible label (screen readers) of the list of search results on WPML → Support. Noun phrase. */ esc_attr_e( 'Search results', 'wpml' ); ?>" class="wpml:divide-y wpml:divide-gray-100"></div>
            <div id="wpml-support-no-results" class="wpml:hidden wpml:px-5 wpml:py-10 wpml:text-center">
                <p class="wpml:text-sm wpml:font-medium wpml:text-gray-700 wpml:mb-1">
                  <?php
                  /* translators: %s is the user's search term. */
                  printf(
                    /* translators: Message on WPML → Support when the tool search finds nothing. %s: the words the user typed. */
                    esc_html__( 'No troubleshooting tools match "%s"', 'wpml' ),
                    '<span id="wpml-support-no-results-term"></span>'
                  );
                  ?>
                </p>
                <p class="wpml:text-xs wpml:text-gray-500">
                    <?php
                    printf(
                      /* translators: %s is the URL to the WPML support forum. */
                      esc_html__( 'Try a different keyword, or %s.', 'wpml' ),
                      /* translators: Link text inside the sentence "Try a different keyword, or open a support request." on WPML → Support. Lower case because it sits inside that sentence. */
                      '<a href="' . esc_url( $this->requestSupportForumUrl() ) . '" target="_blank" class="wpml:text-blue wpml:hover:underline">' . esc_html__( 'open a support request', 'wpml' ) . ' ↗</a>'
                    );
                    ?>
                </p>
            </div>
        </div>
        <?php
  }


  private function renderRequestSupportCard(): void {
    ?>
        <div id="wpml-support-default-content">
            <div class="wpml:bg-white wpml:border wpml:border-gray-200 wpml:rounded-md wpml:p-5 wpml:mb-6">
                <div class="wpml:flex wpml:flex-wrap wpml:items-start wpml:gap-4">
                    <div class="wpml:flex-1 wpml:min-w-[14rem]">
                        <p class="wpml:font-medium wpml:text-gray-900 wpml:mb-1">
                          <?php /* translators: Heading of the block on WPML → Support that opens a support ticket. Verb phrase, imperative. */ esc_html_e( 'Request support', 'wpml' ); ?>
                        </p>
                        <p class="wpml:text-xs wpml:text-gray-500 wpml:mb-3">
                          <?php esc_html_e( "Can't find what you need below? Open a support request and our team will help.", 'wpml' ); ?>
                        </p>
                        <?php if ( $this->readSiteKey() === '' ) : ?>
                            <p class="wpml:text-xs wpml:text-gray-600 wpml:mb-3">
                              <?php /* translators: Message on WPML → Support. "It" is registering WPML. */ esc_html_e( 'To open a support request, first register WPML on this site. It only takes a minute.', 'wpml' ); ?>
                            </p>
                            <a href="<?php echo esc_url( admin_url( 'admin.php?page=wpml-activate-update' ) ); ?>"
                                class="wpml:inline-flex wpml:items-center wpml:gap-1.5 wpml:bg-blue wpml:hover:bg-blue-700 wpml:text-white wpml:text-sm wpml:font-medium wpml:px-4 wpml:py-1.5 wpml:rounded wpml:transition-colors">
                              <?php /* translators: Link text on WPML → Support that opens the registration screen. Verb phrase, imperative (register WPML on this site). */ esc_html_e( 'Register WPML', 'wpml' ); ?>
                            </a>
                        <?php elseif ( ! $this->isSubscriptionValid() ) : ?>
                            <?php  ?>
                            <p class="wpml:text-xs wpml:text-gray-600">
                              <?php esc_html_e( 'Your WPML account has expired. Support is available while your account is active. Renew it, then come back here to open your support request.', 'wpml' ); ?>
                            </p>
                        <?php else : ?>
                            <a href="<?php echo esc_url( ( new SupportUrl() )->get() . '/support' ); ?>" target="_blank"
                                id="wpml-support-open-request"
                                data-rest-url="<?php echo esc_attr( esc_url_raw( rest_url( 'wpml/v1/support/plugin-report' ) ) ); ?>"
                                data-rest-nonce="<?php echo esc_attr( wp_create_nonce( 'wp_rest' ) ); ?>"
                                class="wpml:inline-flex wpml:items-center wpml:gap-1.5 wpml:bg-blue wpml:hover:bg-blue-700 wpml:text-white wpml:text-sm wpml:font-medium wpml:px-4 wpml:py-1.5 wpml:rounded wpml:transition-colors">
                              <?php esc_html_e( 'Open a support request', 'wpml' ); ?>
                                <svg class="wpml:w-3.5 wpml:h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 6H6a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-4M14 4h6m0 0v6m0-6L10 14"/>
                                </svg>
                            </a>
                            <span id="wpml-support-open-request-status" class="wpml:block wpml:text-[11px] wpml:text-gray-500 wpml:mt-1" aria-live="polite"></span>
                            <p class="wpml:text-xs wpml:text-gray-400 wpml:mt-3">
                              <?php /* translators: Note on WPML → Support. "This" is sending that information. */ esc_html_e( 'Opening a support request sends WPML.org information about your server setup, active theme, and plugins. This is required for support. No passwords or personal information are sent.', 'wpml' ); ?>
                            </p>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        <?php
  }


  private function renderTierSections(): void {
      echo '<div id="wpml-support-sections-all">';
      $this->renderTier1();
      $this->renderTier2();
      $this->renderTier3();
      echo '</div>';
      echo '</div>';
  }


  private function renderTier1(): void {
      $entries = $this->tileEntries( SupportTool::TIER_SAFE );

      ?>
        <div class="wpml:mb-7">
            <div class="wpml:flex wpml:items-center wpml:gap-2 wpml:mb-2 wpml:px-1">
                <span class="wpml:inline-flex wpml:items-center wpml:gap-1.5 wpml:text-[11px] wpml:font-semibold wpml:uppercase wpml:tracking-wide wpml:text-green-700 wpml:bg-green-100 wpml:border wpml:border-green-200 wpml:px-2 wpml:py-0.5 wpml:rounded-full">
                    <svg class="wpml:w-3 wpml:h-3" fill="none" stroke="currentColor" stroke-width="3" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"/></svg>
                  <?php esc_html_e( 'Safe to run yourself', 'wpml' ); ?>
                </span>
            </div>
            <div class="wpml:bg-white wpml:border wpml:border-gray-200 wpml:divide-y wpml:divide-gray-100 wpml:rounded-md wpml:overflow-hidden">
              <?php
              $this->renderPluginHint();
              foreach ( $entries as $entry ) :
                ?>
                    <a href="<?php echo esc_url( $entry['href'] ); ?>"
                      class="wpml:flex wpml:items-center wpml:gap-4 wpml:px-5 wpml:py-4 wpml:hover:bg-sky-50">
                        <span class="wpml:shrink-0 wpml:w-9 wpml:h-9 wpml:rounded-md wpml:bg-blue-50 wpml:text-blue wpml:flex wpml:items-center wpml:justify-center">
                            <svg class="wpml:w-5 wpml:h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="<?php echo esc_attr( $entry['icon'] ); ?>"/>
                            </svg>
                        </span>
                        <span class="wpml:flex-1">
                            <span class="wpml:block wpml:font-medium wpml:text-gray-900"><?php echo esc_html( $entry['title'] ); ?></span>
                            <span class="wpml:block wpml:text-xs wpml:text-gray-500"><?php echo esc_html( $entry['desc'] ); ?></span>
                        </span>
                        <svg class="wpml:w-4 wpml:h-4 wpml:text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/>
                        </svg>
                    </a>
              <?php endforeach; ?>
            </div>
        </div>
        <?php
  }


  private function renderTier2(): void {
      $entries = $this->tileEntries( SupportTool::TIER_LOGS );

      ?>
        <div class="wpml:mb-7">
            <div class="wpml:flex wpml:items-center wpml:gap-2 wpml:mb-2 wpml:px-1">
                <span class="wpml:inline-flex wpml:items-center wpml:gap-1.5 wpml:text-[11px] wpml:font-semibold wpml:uppercase wpml:tracking-wide wpml:text-sky-700 wpml:bg-sky-100 wpml:border wpml:border-sky-200 wpml:px-2 wpml:py-0.5 wpml:rounded-full">
                    <svg class="wpml:w-3 wpml:h-3" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                  <?php esc_html_e( 'Logs — read only', 'wpml' ); ?>
                </span>
            </div>
            <div class="wpml:bg-white wpml:border wpml:border-gray-200 wpml:divide-y wpml:divide-gray-100 wpml:rounded-md wpml:overflow-hidden">
              <?php foreach ( $entries as $entry ) : ?>
                    <a href="<?php echo esc_url( $entry['href'] ); ?>" class="wpml:flex wpml:items-center wpml:gap-4 wpml:px-5 wpml:py-4 wpml:hover:bg-sky-50">
                        <span class="wpml:shrink-0 wpml:w-9 wpml:h-9 wpml:rounded-md wpml:bg-blue-50 wpml:text-blue wpml:flex wpml:items-center wpml:justify-center">
                            <svg class="wpml:w-5 wpml:h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="<?php echo esc_attr( $entry['icon'] ); ?>"/>
                            </svg>
                        </span>
                        <span class="wpml:flex-1">
                            <span class="wpml:block wpml:font-medium wpml:text-gray-900"><?php echo esc_html( $entry['title'] ); ?></span>
                            <span class="wpml:block wpml:text-xs wpml:text-gray-500"><?php echo esc_html( $entry['desc'] ); ?></span>
                        </span>
                        <svg class="wpml:w-4 wpml:h-4 wpml:text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/>
                        </svg>
                    </a>
              <?php endforeach; ?>
            </div>
        </div>
        <?php
  }


  private function renderTier3(): void {
      $entries = $this->tileEntries( SupportTool::TIER_ADVANCED );

    ?>
        <div class="wpml-support-advanced wpml:mb-6">
            <div class="wpml:mb-2 wpml:px-1">
                <span class="wpml-support-advanced-pill wpml:inline-flex wpml:items-center wpml:gap-1.5 wpml:text-[11px] wpml:font-semibold wpml:uppercase wpml:tracking-wide wpml:px-2 wpml:py-0.5 wpml:rounded-full">
                    <svg class="wpml:w-3 wpml:h-3" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M12 9v2m0 4h.01M4.93 4.93a10 10 0 1114.14 14.14 10 10 0 01-14.14-14.14z"/></svg>
                  <?php /* translators: Text on the badge that marks the risky tools on WPML → Support. A noun followed by a warning; there is no verb. */ esc_html_e( 'Advanced — only when WPML Support asks you to', 'wpml' ); ?>
                </span>
            </div>
            <div class="wpml:bg-white wpml:border wpml:border-red-200 wpml:rounded-md wpml:overflow-hidden">
                <div class="wpml:px-5 wpml:py-3 wpml:bg-red-50 wpml:border-b wpml:border-red-100 wpml:text-xs wpml:text-red-900 wpml:leading-relaxed">
                  <?php esc_html_e( 'These tools are rarely needed and can cause data loss or break your multilingual site if used incorrectly. Use them only when instructed by WPML Support — do not experiment.', 'wpml' ); ?>
                </div>
                <div class="wpml:divide-y wpml:divide-gray-100">
                  <?php foreach ( $entries as $entry ) : ?>
                        <a href="<?php echo esc_url( $entry['href'] ); ?>" class="wpml:flex wpml:items-center wpml:gap-4 wpml:px-5 wpml:py-4 wpml:hover:bg-sky-50">
                            <span class="wpml:shrink-0 wpml:w-9 wpml:h-9 wpml:rounded-md wpml:bg-red-50 wpml:text-red-600 wpml:flex wpml:items-center wpml:justify-center">
                                <svg class="wpml:w-5 wpml:h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="<?php echo esc_attr( $entry['icon'] ); ?>"/>
                                </svg>
                            </span>
                            <span class="wpml:flex-1">
                                <span class="wpml:block wpml:font-medium wpml:text-gray-900"><?php echo esc_html( $entry['title'] ); ?></span>
                                <span class="wpml:block wpml:text-xs wpml:text-gray-500"><?php echo esc_html( $entry['desc'] ); ?></span>
                            </span>
                            <svg class="wpml:w-4 wpml:h-4 wpml:text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/>
                            </svg>
                        </a>
                  <?php endforeach; ?>
                </div>
            </div>
        </div>
        <?php
  }


  private function renderInstalledPluginsTable(): void {
    if ( ! class_exists( SitePress::class ) || ! method_exists( SitePress::class, 'get_installed_plugins' ) ) {
        return;
    }

      $plugins = SitePress::get_installed_plugins();
    ?>
        <section class="wpml:mt-10">
            <div class="wpml:flex wpml:items-center wpml:justify-between wpml:mb-2 wpml:px-1">
                <h2 class="wpml:text-sm wpml:font-semibold wpml:text-gray-900">
                  <?php /* translators: Heading above the plugin table on WPML → Support. */ esc_html_e( 'Installed plugins', 'wpml' ); ?>
                </h2>
            </div>
            <p class="wpml:text-xs wpml:text-gray-500 wpml:mb-3 wpml:px-1">
              <?php esc_html_e( 'Support frequently asks about the plugins running on your site. Use this list when opening a ticket.', 'wpml' ); ?>
            </p>
            <div class="wpml:bg-white wpml:border wpml:border-gray-200 wpml:rounded-md wpml:overflow-hidden">
                <table class="wpml:w-full wpml:text-xs">
                    <thead>
                        <tr class="wpml:text-left wpml:text-gray-500 wpml:bg-gray-50 wpml:border-b wpml:border-gray-200">
                            <th class="wpml:px-4 wpml:py-2"><?php /* translators: Column heading in the plugin table on WPML → Support: the name of the plugin. */ esc_html_e( 'Plugin', 'wpml' ); ?></th>
                            <th class="wpml:px-4 wpml:py-2 wpml:w-24"><?php /* translators: Column heading in a table on WPML → Support: the version number. */ esc_html_e( 'Version', 'wpml' ); ?></th>
                            <th class="wpml:px-4 wpml:py-2 wpml:w-20"><?php /* translators: Column heading in the plugin table on WPML → Support: whether the plugin is switched on. */ esc_html_e( 'Status', 'wpml' ); ?></th>
                        </tr>
                    </thead>
                    <tbody class="wpml:divide-y wpml:divide-gray-100">
                      <?php foreach ( $plugins as $name => $plugin_data ) : ?>
                            <?php
                            $file       = $plugin_data['file'] ?? '';
                            $installed  = ! empty( $plugin_data['plugin'] );
                            $is_active  = $installed && is_plugin_active( $file );
                            $version    = $plugin_data['plugin']['Version'] ?? '';
                            ?>
                            <tr>
                                <td class="wpml:px-4 wpml:py-2 wpml:text-gray-800"><?php echo esc_html( $name ); ?></td>
                                <td class="wpml:px-4 wpml:py-2 wpml:font-mono wpml:text-gray-700"><?php echo esc_html( $version ?: '—' ); ?></td>
                                <td class="wpml:px-4 wpml:py-2">
                                    <?php if ( $is_active ) : ?>
                                        <span class="wpml:inline-flex wpml:items-center wpml:gap-1 wpml:text-green-700">
                                            <svg class="wpml:w-3 wpml:h-3" fill="currentColor" viewBox="0 0 20 20" aria-hidden="true"><circle cx="10" cy="10" r="5"/></svg>
                                            <?php /* translators: Value in the plugin table on WPML → Support: the plugin is installed and switched on. Adjective describing the plugin. */ esc_html_e( 'Active', 'wpml' ); ?>
                                        </span>
                                    <?php elseif ( $installed ) : ?>
                                        <span class="wpml:inline-flex wpml:items-center wpml:gap-1 wpml:text-gray-500">
                                            <svg class="wpml:w-3 wpml:h-3 wpml:text-gray-400" fill="currentColor" viewBox="0 0 20 20" aria-hidden="true"><circle cx="10" cy="10" r="5"/></svg>
                                            <?php /* translators: Value in the plugin table on WPML → Support: the plugin is installed but switched off. Adjective describing the plugin. */ esc_html_e( 'Inactive', 'wpml' ); ?>
                                        </span>
                                    <?php else : ?>
                                        <span class="wpml:inline-flex wpml:items-center wpml:gap-1 wpml:text-gray-500">
                                            <?php /* translators: Value in the plugin table on WPML → Support: the plugin is not present on the site. Adjective phrase describing the plugin, not an instruction. */ esc_html_e( 'Not installed', 'wpml' ); ?>
                                        </span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                      <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </section>
        <?php
  }


  private function renderSearchScript(): void {
      $base       = esc_url_raw(
        admin_url( 'admin.php?page=sitepress-multilingual-cms/menu/support.php' )
      );
      $search_idx = $this->buildSearchIndex( $base );

    ?>
        <script>
        (function () {
            var index = <?php echo wp_json_encode( $search_idx, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT ); ?>;
            var searchEl    = document.getElementById('wpml-support-search');
            var clearBtn    = document.getElementById('wpml-support-search-clear');
            var defaultEl   = document.getElementById('wpml-support-default-content');
            var resultsEl   = document.getElementById('wpml-support-search-results');
            var resultsList = document.getElementById('wpml-support-search-results-list');
            var noResults   = document.getElementById('wpml-support-no-results');
            var noResultsT  = document.getElementById('wpml-support-no-results-term');

            if (!searchEl) { return; }

            var tierBadge = {
                safe:   { text: 'Safe',         cls: 'wpml:text-green-700 wpml:bg-green-100 wpml:border-green-200' },
                log:    { text: 'Log',          cls: 'wpml:text-sky-700 wpml:bg-sky-100 wpml:border-sky-200' },
                danger: { text: 'Support only', cls: 'wpml:text-red-700 wpml:bg-red-100 wpml:border-red-200' }
            };

            function escapeHtml(s) {
                return s.replace(/[&<>"']/g, function (c) {
                    return ({ '&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;' })[c];
                });
            }

            // Bare <mark>: the shared `body .wrap mark` rule in tailwind.css
            // paints the blue search-match treatment (wpmldev-7290), same as
            // Settings' highlight.tsx. No inline style, or it would win over it.
            function highlight(text, term) {
                if (!term) { return escapeHtml(text); }
                var re = new RegExp('(' + term.replace(/[.*+?^${}()|[\]\\]/g,'\\$&') + ')','ig');
                return escapeHtml(text).replace(re, '<mark>$1</mark>');
            }

            function render(term) {
                var t = term.trim().toLowerCase();
                activeIndex = -1;
                searchEl.removeAttribute('aria-activedescendant');
                searchEl.setAttribute('aria-expanded', t ? 'true' : 'false');
                if (!t) {
                    resultsEl.classList.add('wpml:hidden');
                    defaultEl.classList.remove('wpml:hidden');
                    clearBtn.classList.add('wpml:hidden');
                    return;
                }
                clearBtn.classList.remove('wpml:hidden');
                defaultEl.classList.add('wpml:hidden');
                resultsEl.classList.remove('wpml:hidden');
                resultsList.innerHTML = '';

                var hitCount = 0;
                index.forEach(function (sec) {
                    var sectionMatches = sec.section.toLowerCase().indexOf(t) !== -1;
                    var subHits = sec.subs.filter(function (s) { return s.label.toLowerCase().indexOf(t) !== -1; });
                    if (!sectionMatches && subHits.length === 0) { return; }

                    var badge = tierBadge[sec.tier];

                    if (sectionMatches) {
                        hitCount++;
                        resultsList.insertAdjacentHTML('beforeend',
                            '<a href="' + sec.href + '" id="wpml-support-search-hit-' + (hitCount - 1) + '" role="option" aria-selected="false" class="wpml:flex wpml:items-center wpml:gap-4 wpml:px-5 wpml:py-3 wpml:hover:bg-sky-50 wpml:focus:outline-none wpml:focus:bg-sky-50">' +
                                '<span class="wpml:inline-flex wpml:shrink-0 wpml:text-[10px] wpml:font-semibold wpml:uppercase wpml:tracking-wide wpml:border ' + badge.cls + ' wpml:px-1.5 wpml:py-0.5 wpml:rounded wpml:w-20 wpml:justify-center">' + badge.text + '</span>' +
                                '<span class="wpml:flex-1 wpml:font-medium wpml:text-gray-900">' + highlight(sec.section, t) + '</span>' +
                                '<svg class="wpml:w-4 wpml:h-4 wpml:text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/></svg>' +
                            '</a>'
                        );
                    }
                    subHits.forEach(function (s) {
                        hitCount++;
                        var href = !s.anchor ? sec.href : (sec.href + '&flash=' + encodeURIComponent(s.anchor) + '#' + s.anchor);
                        resultsList.insertAdjacentHTML('beforeend',
                            '<a href="' + href + '" id="wpml-support-search-hit-' + (hitCount - 1) + '" role="option" aria-selected="false" class="wpml:flex wpml:items-center wpml:gap-4 wpml:px-5 wpml:py-3 wpml:hover:bg-sky-50 wpml:focus:outline-none wpml:focus:bg-sky-50">' +
                                '<span class="wpml:inline-flex wpml:shrink-0 wpml:text-[10px] wpml:font-semibold wpml:uppercase wpml:tracking-wide wpml:border ' + badge.cls + ' wpml:px-1.5 wpml:py-0.5 wpml:rounded wpml:w-20 wpml:justify-center">' + badge.text + '</span>' +
                                '<span class="wpml:flex-1">' +
                                    '<span class="wpml:block wpml:text-gray-900">' + highlight(s.label, t) + '</span>' +
                                    '<span class="wpml:block wpml:text-xs wpml:text-gray-500">' + escapeHtml(sec.section) + '</span>' +
                                '</span>' +
                                '<svg class="wpml:w-4 wpml:h-4 wpml:text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/></svg>' +
                            '</a>'
                        );
                    });
                });

                if (hitCount === 0) {
                    noResults.classList.remove('wpml:hidden');
                    noResultsT.textContent = term.trim();
                    resultsList.classList.add('wpml:hidden');
                } else {
                    noResults.classList.add('wpml:hidden');
                    resultsList.classList.remove('wpml:hidden');
                }
            }

            searchEl.addEventListener('input', function (e) { render(e.target.value); });
            clearBtn.addEventListener('click', function () { searchEl.value = ''; render(''); searchEl.focus(); });
            // wpmldev-8100: keyboard model, mirrored from the Settings landing.
            // The box keeps the caret while ArrowDown / ArrowUp move a single
            // selection through the rows (clamped), Enter opens the selected row
            // (the first when nothing is selected), Tab hands focus to the first
            // row, Escape clears. Inside the list the arrows move focus between
            // rows and ArrowUp on the first row returns to the box.
            var activeIndex = -1;

            function rows() {
                return Array.prototype.slice.call(resultsList.querySelectorAll('a[role="option"]'));
            }

            function setActive(i) {
                var all = rows();
                activeIndex = i;
                all.forEach(function (row, n) {
                    var on = n === i;
                    row.setAttribute('aria-selected', on ? 'true' : 'false');
                    row.classList.toggle('wpml:bg-sky-50', on);
                });
                if (i >= 0 && all[i]) {
                    searchEl.setAttribute('aria-activedescendant', all[i].id);
                    if (typeof all[i].scrollIntoView === 'function') { all[i].scrollIntoView({ block: 'nearest' }); }
                } else {
                    searchEl.removeAttribute('aria-activedescendant');
                }
            }

            searchEl.addEventListener('keydown', function (e) {
                var all = rows();
                if (e.key === 'Escape') { searchEl.value = ''; render(''); return; }
                if (!all.length || resultsEl.classList.contains('wpml:hidden')) { return; }
                if (e.key === 'ArrowDown') { e.preventDefault(); setActive(Math.min(activeIndex + 1, all.length - 1)); return; }
                if (e.key === 'ArrowUp') { e.preventDefault(); setActive(Math.max(activeIndex - 1, 0)); return; }
                if (e.key === 'Enter') { e.preventDefault(); window.location.assign(all[activeIndex >= 0 ? activeIndex : 0].href); return; }
                if (e.key === 'Tab' && !e.shiftKey) { e.preventDefault(); all[0].focus(); }
            });

            resultsList.addEventListener('keydown', function (e) {
                if (e.key !== 'ArrowDown' && e.key !== 'ArrowUp') { return; }
                var all = rows();
                var current = all.indexOf(e.target);
                if (current === -1) { return; }
                e.preventDefault();
                if (e.key === 'ArrowUp' && current === 0) { searchEl.focus(); return; }
                var next = e.key === 'ArrowDown' ? Math.min(current + 1, all.length - 1) : current - 1;
                all[next].focus();
            });

            // the landing is search-first: the caret starts in the box
            searchEl.focus();
        })();
        </script>
        <?php
  }


  private function renderPluginReportScript(): void {
    $supportPageUrl = ( new SupportUrl() )->get() . '/support';
    $errorHtml      = sprintf(
      /* translators: Error on WPML → Support, shown after the support page opened in a new tab without the site's debug information. %1$s: opening link tag to the wpml.org support page, %2$s: closing link tag. */
      esc_html__( 'We could not send your site information, so the support page opened without it. You can continue there, or try again later to attach it: %1$sopen the support page%2$s.', 'wpml' ),
      '<a href="' . esc_url( $supportPageUrl ) . '" target="_blank" class="wpml:text-blue wpml:hover:underline">',
      '</a>'
    );
    $strings = array(
      'preparing'  => __( 'Preparing your support request…', 'wpml' ),
      'opening'    => __( 'Taking you to WPML.org…', 'wpml' ),
      'errorHtml'  => $errorHtml,
      'supportUrl' => $supportPageUrl,
    );
    ?>
    <script>
    (function () {
        var btn = document.getElementById('wpml-support-open-request');
        if (!btn) { return; }
        var status = document.getElementById('wpml-support-open-request-status');
        var i18n = <?php echo wp_json_encode( $strings, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT ); ?>;
        var busy = false;

        btn.addEventListener('click', function (e) {
            e.preventDefault();
            if (busy) { return; }
            busy = true;
            // Open the tab on the user gesture itself; the report URL is set when it arrives.
            var tab = window.open('', '_blank');
            if (status) { status.textContent = i18n.preparing; }

            fetch(btn.getAttribute('data-rest-url'), {
                method: 'POST',
                credentials: 'same-origin',
                headers: {
                    'Content-Type': 'application/json',
                    'X-WP-Nonce': btn.getAttribute('data-rest-nonce')
                }
            }).then(function (r) {
                return r.json().then(function (json) { return { ok: r.ok, json: json }; });
            }).then(function (res) {
                var url = res.json && res.json.success && res.json.data && res.json.data.url;
                if (!res.ok || !url) {
                    var code = res.json && res.json.data && res.json.data.error;
                    throw new Error(code || 'plugin report failed');
                }
                if (tab) { tab.location = url; } else { window.location.href = url; }
                if (status) {
                    status.textContent = i18n.opening;
                    setTimeout(function () { status.textContent = ''; }, 4000);
                }
            }).catch(function (err) {
                var code = err && err.message;
                if (code === 'subscription_expired' || code === 'not_registered') {
                    // Stale page: the account state changed since render. Reload so the
                    // card shows the right state (renew-first / register-first) instead
                    // of a misleading "could not send" line.
                    if (tab) { tab.close(); }
                    window.location.reload();
                    return;
                }
                // The report could not be sent: keep the tab the user opened and land it
                // on the plain support page, which is what the button's href promises.
                if (tab) { tab.location = i18n.supportUrl; } else { window.open(i18n.supportUrl, '_blank'); }
                if (status) { status.innerHTML = i18n.errorHtml; }
            }).finally(function () {
                busy = false;
            });
        });
    })();
    </script>
    <?php
  }


  private function buildSearchIndex( string $base ): array {
      $badges = array(
          SupportTool::TIER_SAFE     => 'safe',
          SupportTool::TIER_LOGS     => 'log',
          SupportTool::TIER_ADVANCED => 'danger',
      );

      $entries = array();
    foreach ( $this->offeredTools() as $tool ) {
        $entries[] = array(
            'section'     => $tool->title(),
            'tier'        => $badges[ $tool->tier() ],
            'href'        => $base . '&tool=' . $tool->slug(),
            'subs'        => $tool->search(),
            'searchOrder' => $tool->searchOrder(),
        );
    }

      return array_map(
        function ( array $entry ) {
            unset( $entry['searchOrder'] );
            return $entry;
        },
        self::sortedBy( 'searchOrder', $entries )
      );
  }


  /**
   * The tools the landing offers, every tier: valid, applicable on this
   * site, permitted for this user, on this license — the registry's own
   * rule ({@see SupportToolRegistryInterface::byTier()}), asked once per
   * tier so the tiles and the search index cannot disagree.
   *
   * @return SupportTool[]
   */
  private function offeredTools(): array {
    return array_merge(
      $this->registry->byTier( SupportTool::TIER_SAFE ),
      $this->registry->byTier( SupportTool::TIER_LOGS ),
      $this->registry->byTier( SupportTool::TIER_ADVANCED )
    );
  }


  private function tileEntries( int $tier ): array {
      $base    = admin_url( 'admin.php?page=sitepress-multilingual-cms/menu/support.php' );
      $entries = array();
    foreach ( $this->registry->byTier( $tier ) as $tool ) {
        $entries[] = array(
            'href'  => $base . '&tool=' . $tool->slug(),
            'title' => $tool->title(),
            'desc'  => $tool->description(),
            'icon'  => $tool->icon(),
        );
    }

      return $entries;
  }


  private static function sortedBy( string $key, array $entries ): array {
      $indexed = array();
    foreach ( array_values( $entries ) as $i => $entry ) {
        $indexed[] = array( $entry, $i );
    }
      usort(
        $indexed,
        function ( array $a, array $b ) use ( $key ) {
            return array( $a[0][ $key ], $a[1] ) <=> array( $b[0][ $key ], $b[1] );
        }
      );

      $sorted = array();
    foreach ( $indexed as $pair ) {
        $sorted[] = $pair[0];
    }

      return $sorted;
  }


  /**
   * One plain line at the top of Tier 1 while the repair tools are not on
   * this site (wpmldev-8103): no registry entry names WPML Troubleshooting
   * as its provider. Not a notice box — the tile list is the place a client
   * looks for those tools, so the line stands where they stood. When the
   * request asked for a `&tool=` nobody registered, the line names it
   * first; a slug that IS registered but out of reach here (no capability,
   * a TM tool on a blog license) is not blamed on the plugin.
   *
   * @return void
   */
  private function renderPluginHint(): void {
    if ( $this->hasRepairToolsProvider() ) {
      return;
    }

    echo '<p class="wpml-support-plugin-hint wpml:px-5 wpml:py-4 wpml:text-xs wpml:text-gray-500" style="margin:0">';
    if ( $this->unknownTool !== '' && ! $this->isRegisteredSlug( $this->unknownTool ) ) {
      printf(
        /* translators: Line on WPML → Support, shown when the address bar asked for a Support tool that is part of the WPML Troubleshooting plugin and that plugin is not installed. %s: the tool's identifier from the address bar, e.g. "reset". WPML Troubleshooting is a plugin name and stays as it is. */
        esc_html__( 'The tool you asked for (%s) is part of WPML Troubleshooting.', 'wpml' ),
        esc_html( $this->unknownTool )
      );
      echo ' ';
    }
    /* translators: Line on WPML → Support where the repair tools used to be listed, shown when the WPML Troubleshooting plugin is not installed. WPML Troubleshooting is a plugin name and stays as it is. */
    echo esc_html__( 'Repair tools are now a separate plugin, WPML Troubleshooting.', 'wpml' );
    echo ' <a href="' . esc_url( admin_url( 'admin.php?page=wpml-activate-update' ) ) . '" class="wpml:text-blue wpml:hover:underline">';
    /* translators: Link or button text on WPML → Support and its tool pages that opens the WPML → Activate & Update screen. Verb phrase, imperative. "Activate & Update" is that screen's name. */
    echo esc_html__( 'Go to Activate & Update', 'wpml' );
    echo '</a></p>';
  }


  private function hasRepairToolsProvider(): bool {
    foreach ( $this->registry->all() as $tool ) {
      if ( $tool->provider() === self::REPAIR_TOOLS_PROVIDER ) {
        return true;
      }
    }

    return false;
  }


  private function isRegisteredSlug( string $slug ): bool {
    foreach ( $this->registry->all() as $tool ) {
      if ( $tool->slug() === $slug ) {
        return true;
      }
    }

    return false;
  }


  private static function minimumRequirementsUrl(): string {
    $url = 'https://wpml.org/home/minimum-requirements/';

    if ( class_exists( '\WPML\OutboundLinks\OutboundLinks' ) ) {
      return \WPML\OutboundLinks\OutboundLinks::to(
        $url,
        [ 'medium' => 'support', 'campaign' => 'requirements' ]
      );
    }

    return $url;
  }


}
