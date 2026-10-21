<?php

namespace WPML\UserInterface\Web\Infrastructure\WordPress\Component\Support\Tool;

use WPML\Core\Component\MinimumRequirements\Application\Service\RequirementsService;
use WPML\Core\SharedKernel\Component\ATE\Application\Query\SiteIDQueryInterface;
use WPML\Core\SharedKernel\Component\ATE\Application\Query\SiteSharedKeyQueryInterface;
use WPML\Core\SharedKernel\Component\Server\Domain\ServerInfoInterface;
use WPML\UserInterface\Web\Core\SharedKernel\Config\PageRenderInterface;
use WPML_Debug_Information;
use Throwable;

/**
 * M9 — `Support → System check`.
 *
 * Server-side render of the `troubleshooting-system-check.html` mockup —
 * Environment block (versions + PHP limits in a 2-column grid),
 * Copy-to-clipboard, Debug information textarea, Connectivity to WPML
 * servers section, Required PHP libraries, WPML installer instances
 * table.
 *
 * Status icons (green / amber / gray): rows that correspond to one of
 * `RequirementsService`'s requirements (wpmldev-8588, F23) — WordPress
 * version, PHP version, database version, and the memory-limit family —
 * carry that requirement's real `isValid()` result (green when valid,
 * amber otherwise). It is the SAME service instance
 * `\WPML\Support\Initializer::getData()` already uses to drive the Support
 * landing's warnings panel — the "environment-check service" this
 * docblock used to say would land in a later phase; `getAllRequirements()`
 * is read with `useCache = true`, the same cache entry that panel already
 * warms. `RequirementsService` also has a WordPress-REST-API requirement,
 * deliberately left on a static icon here: its `isValid()`
 * (`RestEnabledRequirement`) makes a blocking self-request on a cold cache,
 * and this tool page — unlike the landing, which every Support visit
 * already warms the cache from — can be the very first hit of the day.
 * Wiring it in is a Phase 2 concern, once that cold-start cost has its own
 * fix. Rows with no matching requirement at all (web server name,
 * multisite, upload/execution limits, …) are informational only and keep a
 * static icon; there is no WPML minimum to check them against.
 *
 * Disabled stubs:
 * - "Check now" connectivity probe — needs the Installer's connectivity
 *   tester wired up.
 * - "Refresh license" — Installer side, wires up with the connectivity
 *   tester.
 */

class SystemCheckController implements PageRenderInterface {

  private $siteIdQuery;

  private $siteSharedKeyQuery;

  private $requirementsService;

  private $serverInfo;

  private const REQUIREMENT_ID_MEMORY_LIMIT      = 1;
  private const REQUIREMENT_ID_PHP_VERSION       = 2;
  private const REQUIREMENT_ID_DATABASE_VERSION  = 3;
  private const REQUIREMENT_ID_REST_ENABLED      = 4;
  private const REQUIREMENT_ID_WORDPRESS_VERSION = 6;


  public function __construct(
    SiteIDQueryInterface $siteIdQuery,
    SiteSharedKeyQueryInterface $siteSharedKeyQuery,
    RequirementsService $requirementsService,
    ServerInfoInterface $serverInfo
  ) {
    $this->siteIdQuery         = $siteIdQuery;
    $this->siteSharedKeyQuery  = $siteSharedKeyQuery;
    $this->requirementsService = $requirementsService;
    $this->serverInfo          = $serverInfo;
  }


  public function render() {
    $requirementStatuses           = $this->collectRequirementStatuses();
    $versions                      = $this->collectVersions( $requirementStatuses );
    $limits                        = $this->collectPhpLimits( $requirementStatuses );
    $debug                         = $this->collectDebugInformation();
    $automaticTranslationAccountId = $this->getAutomaticTranslationAccountId();
    $nonces                        = array(
      'connectivity'   => wp_create_nonce( 'otgs_installer_test_connection' ),
      'refresh_license' => wp_create_nonce( 'update_site_key_wpml' ),
    );
    ?>
      <h1 class="wpml:text-2xl wpml:font-semibold wpml:text-gray-900 wpml:mb-1">
        <?php /* translators: Name of a Support tool: its entry in the tool list on WPML → Support and the heading of its own screen. */ esc_html_e( 'System check', 'wpml' ); ?>
      </h1>
      <p class="wpml:text-sm wpml:text-gray-500 wpml:mb-6">
        <?php esc_html_e( "Export system information for support and test connectivity to WPML's servers.", 'wpml' ); ?>
      </p>

      <?php if ( $automaticTranslationAccountId !== null ) : ?>
        <section id="automatic-translation-account" class="wpml:bg-sky-50 wpml:border wpml:border-sky-200 wpml:rounded-md wpml:p-5 wpml:mb-5">
          <h2 class="wpml:text-sm wpml:font-semibold wpml:text-gray-900 wpml:mb-2">
            <?php esc_html_e( 'Your Automatic Translation account id is', 'wpml' ); ?>
          </h2>
          <code id="wpml-support-automatic-translation-account-id" class="wpml:text-xs wpml:text-gray-700">
            <?php echo esc_html( $automaticTranslationAccountId ); ?>
          </code>
        </section>
      <?php endif; ?>

      <!-- Environment -->
      <section id="versions" class="wpml:bg-white wpml:border wpml:border-gray-200 wpml:rounded-md wpml:p-5 wpml:mb-5 wpml:scroll-mt-8">
        <h2 class="wpml:text-sm wpml:font-semibold wpml:text-gray-900 wpml:mb-1">
          <?php /* translators: Heading of the section on WPML → Support that lists the server and site details. */ esc_html_e( 'Environment', 'wpml' ); ?>
        </h2>
        <p class="wpml:text-xs wpml:text-gray-500 wpml:mb-4">
          <?php esc_html_e( 'Supporters ask about these versions first. Copy this block along with Debug information below when opening a ticket.', 'wpml' ); ?>
        </p>

        <div class="wpml:grid wpml:grid-cols-1 wpml:md:grid-cols-2 wpml:gap-6">
          <div>
            <h3 class="wpml:text-[11px] wpml:font-semibold wpml:uppercase wpml:tracking-wide wpml:text-gray-500 wpml:mb-2">
              <?php /* translators: Heading of the list of version numbers on WPML → Support. */ esc_html_e( 'Versions', 'wpml' ); ?>
            </h3>
            <ul class="wpml:divide-y wpml:divide-gray-100 wpml:border wpml:border-gray-100 wpml:rounded wpml:overflow-hidden wpml:text-sm">
              <?php foreach ( $versions as $row ) : ?>
                <?php $this->renderEnvironmentRow( $row ); ?>
              <?php endforeach; ?>
            </ul>
          </div>

          <div>
            <h3 class="wpml:text-[11px] wpml:font-semibold wpml:uppercase wpml:tracking-wide wpml:text-gray-500 wpml:mb-2">
              <?php esc_html_e( 'PHP limits & runtime', 'wpml' ); ?>
            </h3>
            <ul class="wpml:divide-y wpml:divide-gray-100 wpml:border wpml:border-gray-100 wpml:rounded wpml:overflow-hidden wpml:text-sm">
              <?php foreach ( $limits as $row ) : ?>
                <?php $this->renderEnvironmentRow( $row ); ?>
              <?php endforeach; ?>
            </ul>
          </div>
        </div>

        <button type="button" id="wpml-support-copy-environment"
          class="wpml-button base-btn wpml:inline-flex wpml:items-center wpml:gap-2 wpml:mt-4 wpml:text-sm">
          <svg class="wpml:w-4 wpml:h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 16H6a2 2 0 01-2-2V6a2 2 0 012-2h8a2 2 0 012 2v2m-6 12h8a2 2 0 002-2v-8a2 2 0 00-2-2h-8a2 2 0 00-2 2v8a2 2 0 002 2z"/>
          </svg>
          <span><?php esc_html_e( 'Copy to clipboard', 'wpml' ); ?></span>
        </button>
        <p class="wpml:text-xs wpml:text-gray-500 wpml:mt-4">
          <?php
          printf(
            /* translators: %s is the minimum-requirements help page link. */
            esc_html__( 'Status icons reflect a real check against WPML\'s minimum requirements where one exists; other rows are informational only — see %s for the canonical list.', 'wpml' ),
            '<a href="' . esc_url( self::minimumRequirementsUrl() ) . '" target="_blank" class="wpml:text-blue wpml:hover:underline">' . esc_html__( 'wpml.org/home/minimum-requirements', 'wpml' ) . '</a>'
          );
          ?>
        </p>
      </section>

      <!-- Debug information -->
      <section id="debug-info" class="wpml:bg-white wpml:border wpml:border-gray-200 wpml:rounded-md wpml:p-5 wpml:mb-5 wpml:scroll-mt-8">
        <h2 class="wpml:text-sm wpml:font-semibold wpml:text-gray-900 wpml:mb-1">
          <?php /* translators: Name of a section of the System check tool on WPML → Support: its heading and its entry in the Support search list. */ esc_html_e( 'Debug information', 'wpml' ); ?>
        </h2>
        <p class="wpml:text-xs wpml:text-gray-500 wpml:mb-4">
          <?php /* translators: Note above the debug information on WPML → Support. "It" is the information in the box below. */ esc_html_e( 'Copy this information and paste it into your support ticket. It lists versions and settings only — no passwords or personal data.', 'wpml' ); ?>
        </p>

        <textarea id="wpml-support-debug-info" readonly rows="10"
          class="wpml:w-full wpml:text-[11px] wpml:font-mono wpml:bg-gray-50 wpml:border wpml:border-gray-200 wpml:rounded wpml:p-3 wpml:text-gray-700"><?php echo esc_textarea( $debug ); ?></textarea>

        <div class="wpml:mt-3 wpml:flex wpml:items-center wpml:gap-3">
          <button type="button" id="wpml-support-copy-debug"
            class="wpml-button base-btn wpml:inline-flex wpml:items-center wpml:gap-2 wpml:text-sm">
            <svg class="wpml:w-4 wpml:h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 16H6a2 2 0 01-2-2V6a2 2 0 012-2h8a2 2 0 012 2v2m-6 12h8a2 2 0 002-2v-8a2 2 0 00-2-2h-8a2 2 0 00-2 2v8a2 2 0 002 2z"/>
            </svg>
            <span><?php esc_html_e( 'Copy to clipboard', 'wpml' ); ?></span>
          </button>
          <a href="<?php echo esc_url( self::debugInfoDocUrl() ); ?>" target="_blank" class="wpml:text-xs wpml:text-blue wpml:hover:underline">
            <?php esc_html_e( 'How to share debug info with support ↗', 'wpml' ); ?>
          </a>
        </div>
      </section>

      <!-- Connectivity to WPML servers -->
      <section id="installer" class="wpml:bg-white wpml:border wpml:border-gray-200 wpml:rounded-md wpml:p-5 wpml:mb-5 wpml:scroll-mt-8">
        <h2 class="wpml:text-sm wpml:font-semibold wpml:text-gray-900 wpml:mb-1">
          <?php esc_html_e( 'Connectivity to WPML servers', 'wpml' ); ?>
        </h2>
        <p class="wpml:text-xs wpml:text-gray-500 wpml:mb-4">
          <?php esc_html_e( 'Checks that your site can reach the servers it needs for licensing, updates, and automatic translation.', 'wpml' ); ?>
        </p>

        <div class="wpml:flex wpml:items-center wpml:gap-3 wpml:mb-4">
          <button id="wpml-support-check-connectivity" type="button"
            data-nonce="<?php echo esc_attr( $nonces['connectivity'] ); ?>"
            class="wpml-button base-btn wpml-button--outlined wpml:inline-flex wpml:items-center wpml:gap-1.5 wpml:text-sm wpml:disabled:opacity-50 wpml:disabled:cursor-not-allowed">
            <svg class="wpml:w-4 wpml:h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/>
            </svg>
            <?php /* translators: Button label on WPML → Support that tests the connection to the WPML servers. Verb phrase, imperative. */ esc_html_e( 'Check now', 'wpml' ); ?>
          </button>
          <span id="wpml-support-connectivity-status" class="wpml:text-xs wpml:text-gray-500" aria-live="polite"></span>
        </div>

        <ul class="wpml:divide-y wpml:divide-gray-100 wpml:border wpml:border-gray-100 wpml:rounded wpml:overflow-hidden wpml:text-sm">
          <li class="wpml:flex wpml:items-center wpml:gap-3 wpml:px-4 wpml:py-2.5" data-connectivity-row="wpml">
            <span class="wpml:w-4 wpml:h-4" data-connectivity-icon>
              <svg class="wpml:w-4 wpml:h-4 wpml:text-gray-400" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24" aria-hidden="true">
                <path stroke-linecap="round" stroke-linejoin="round" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
              </svg>
            </span>
            <span class="wpml:flex-1 wpml:text-gray-800"><?php esc_html_e( 'WPML API server', 'wpml' ); ?></span>
            <span class="wpml:text-xs wpml:text-gray-500 wpml:font-mono" data-connectivity-value><?php /* translators: Value on WPML → Support shown before the connection test has been run. Past participle: nobody has tested it yet. */ esc_html_e( 'Not checked', 'wpml' ); ?></span>
          </li>
          <li class="wpml:flex wpml:items-center wpml:gap-3 wpml:px-4 wpml:py-2.5" data-connectivity-row="toolset">
            <span class="wpml:w-4 wpml:h-4" data-connectivity-icon>
              <svg class="wpml:w-4 wpml:h-4 wpml:text-gray-400" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24" aria-hidden="true">
                <path stroke-linecap="round" stroke-linejoin="round" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
              </svg>
            </span>
            <span class="wpml:flex-1 wpml:text-gray-800"><?php esc_html_e( 'Toolset API server', 'wpml' ); ?></span>
            <span class="wpml:text-xs wpml:text-gray-500 wpml:font-mono" data-connectivity-value><?php /* translators: Value on WPML → Support shown before the connection test has been run. Past participle: nobody has tested it yet. */ esc_html_e( 'Not checked', 'wpml' ); ?></span>
          </li>
        </ul>

        <div id="wpml-support-connectivity-help" class="wpml:mt-4 wpml:text-xs wpml:text-gray-600" hidden>
          <p class="wpml:mb-2">
            <?php esc_html_e( 'Ask your hosting provider to allow these domains:', 'wpml' ); ?>
          </p>
          <p class="wpml:font-mono wpml:text-gray-800 wpml:mb-2">wpml.org, cdn.wpml.org, api.wpml.org, api.toolset.com, cloudfront.net</p>
          <p>
            <?php
            $supportTicketLink = '<a href="' . esc_url( self::supportTicketUrl() ) . '" target="_blank" class="wpml:text-blue wpml:hover:underline">'
              /* translators: Link text inside the sentence "If your site still cannot connect, please open a support ticket." It starts in lower case because it sits inside that sentence. */
              . esc_html__( 'open a support ticket', 'wpml' )
              . '</a>';

            echo wp_kses(
              sprintf(
                /* translators: Offer shown on WPML > Support when a server cannot be reached. %s: a link, already wrapped in its tags, whose text is "open a support ticket". */
                esc_html__( 'If your site still cannot connect, please %s.', 'wpml' ),
                $supportTicketLink
              ),
              array( 'a' => array( 'href' => array(), 'target' => array(), 'class' => array() ) )
            );
            ?>
          </p>
        </div>

        <div id="refresh-license" class="wpml:mt-4 wpml:pt-4 wpml:border-t wpml:border-gray-100 wpml:scroll-mt-8">
          <h3 class="wpml:text-sm wpml:font-semibold wpml:text-gray-900 wpml:mb-1">
            <?php esc_html_e( 'Refresh license data', 'wpml' ); ?>
          </h3>
          <p class="wpml:text-xs wpml:text-gray-500 wpml:mb-3">
            <?php esc_html_e( 'Re-checks your WPML subscription with wpml.org and updates the license details cached on this site. Useful after renewing, upgrading, or moving the site to a different account.', 'wpml' ); ?>
          </p>
          <button id="wpml-support-refresh-license" type="button"
            data-nonce="<?php echo esc_attr( $nonces['refresh_license'] ); ?>"
            class="wpml-button base-btn wpml:text-sm wpml:disabled:opacity-50 wpml:disabled:cursor-not-allowed">
            <?php /* translators: Button label on WPML → Support that fetches the site's registration again. Verb phrase, imperative. */ esc_html_e( 'Refresh license', 'wpml' ); ?>
          </button>
          <span id="wpml-support-refresh-license-status" class="wpml:text-xs wpml:text-gray-500 wpml:ml-3" aria-live="polite"></span>
        </div>

        <h3 class="wpml:text-sm wpml:font-semibold wpml:text-gray-900 wpml:mt-6 wpml:mb-2">
          <?php esc_html_e( 'Required PHP libraries', 'wpml' ); ?>
        </h3>
        <div class="wpml:flex wpml:flex-wrap wpml:gap-4 wpml:text-sm">
          <?php foreach ( $this->collectPhpLibraries() as $lib ) : ?>
            <span class="wpml:inline-flex wpml:items-center wpml:gap-1.5 wpml:text-gray-800">
              <?php $this->renderStatusIcon( $lib['status'] ); ?>
              <?php echo esc_html( $lib['label'] ); ?>
            </span>
          <?php endforeach; ?>
        </div>

        <h3 class="wpml:text-sm wpml:font-semibold wpml:text-gray-900 wpml:mt-6 wpml:mb-2">
          <?php esc_html_e( 'WPML installer instances on this site', 'wpml' ); ?>
        </h3>
        <?php $this->renderInstallerInstancesTable(); ?>

      </section>

      <script>
      (function () {
        // Built server-side: a root-relative '/wp-admin/…' would resolve against the
        // domain, not the WordPress root, and 404 on subdirectory installs (wpmldev-7682).
        var ajaxUrl = <?php echo wp_json_encode( admin_url( 'admin-ajax.php' ) ); ?>;

        function copy(text) {
          if (navigator.clipboard && navigator.clipboard.writeText) {
            return navigator.clipboard.writeText(text);
          }
          var ta = document.createElement('textarea');
          ta.value = text;
          document.body.appendChild(ta);
          ta.select();
          try { document.execCommand('copy'); } catch (e) {}
          document.body.removeChild(ta);
          return Promise.resolve();
        }

        function flashLabel(btn, text) {
          var span = btn.querySelector('span') || btn;
          var prev = span.textContent;
          span.textContent = text;
          setTimeout(function () { span.textContent = prev; }, 1200);
        }

        var envBtn = document.getElementById('wpml-support-copy-environment');
        if (envBtn) {
          envBtn.addEventListener('click', function () {
            var rows = [];
            document.querySelectorAll('#versions ul li').forEach(function (li) {
              var label = li.children[1] ? li.children[1].textContent.trim() : '';
              var value = li.children[2] ? li.children[2].textContent.trim() : '';
              if (label) { rows.push(label + ': ' + value); }
            });
            copy(rows.join('\n')).then(function () { flashLabel(envBtn, '<?php echo esc_js( /* translators: Confirmation that flashes on a copy button on WPML → Support once the text has been placed on the clipboard. Past participle (it has been copied). */ __( 'Copied!', 'wpml' ) ); ?>'); });
          });
        }

        var dbgBtn = document.getElementById('wpml-support-copy-debug');
        var dbgTxt = document.getElementById('wpml-support-debug-info');
        if (dbgBtn && dbgTxt) {
          dbgBtn.addEventListener('click', function () {
            copy(dbgTxt.value).then(function () { flashLabel(dbgBtn, '<?php echo esc_js( /* translators: Confirmation that flashes on a copy button on WPML → Support once the text has been placed on the clipboard. Past participle (it has been copied). */ __( 'Copied!', 'wpml' ) ); ?>'); });
          });
        }

        // Connectivity probe — fires `otgs_installer_test_connection` per repo.
        var connBtn    = document.getElementById('wpml-support-check-connectivity');
        var connStatus = document.getElementById('wpml-support-connectivity-status');
        if (connBtn) {
          connBtn.addEventListener('click', function () {
            connBtn.disabled = true;
            connStatus.textContent = '<?php echo esc_js( /* translators: Status shown on WPML → Support while the connection test is running. */ __( 'Checking…', 'wpml' ) ); ?>';

            function setRow(repo, ok) {
              var row = document.querySelector('[data-connectivity-row="' + repo + '"]');
              if (!row) { return; }
              var iconWrap = row.querySelector('[data-connectivity-icon]');
              var valueEl  = row.querySelector('[data-connectivity-value]');
              if (ok) {
                iconWrap.innerHTML = '<svg class="wpml:w-4 wpml:h-4 wpml:text-green-600" fill="none" stroke="currentColor" stroke-width="3" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"/></svg>';
                valueEl.textContent = 'OK';
                valueEl.classList.remove('wpml:text-red-600');
              } else {
                iconWrap.innerHTML = '<svg class="wpml:w-4 wpml:h-4 wpml:text-red-600" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M12 9v2m0 4h.01M4.93 4.93a10 10 0 1114.14 14.14 10 10 0 01-14.14-14.14z"/></svg>';
                valueEl.textContent = 'Unreachable';
                valueEl.classList.add('wpml:text-red-600');
              }
            }

            function probe(repo) {
              var body = new URLSearchParams();
              body.append('action', 'otgs_installer_test_connection');
              body.append('nonce', connBtn.getAttribute('data-nonce'));
              body.append('type', 'api');
              body.append('repository', repo);
              return fetch(ajaxUrl, {
                method: 'POST',
                credentials: 'same-origin',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: body.toString()
              }).then(function (r) {
                if (!r.ok) { return false; }
                return r.json().then(function (j) { return !!(j && j.success); });
              }).catch(function () { return false; });
            }

            // One probe at a time, on purpose: the installer appends each failed
            // probe to its debug log with a read-modify-write of one option and
            // no lock, so two probes failing at the same moment lose one of the
            // two rows (wpmldev-8588, measured 9 rounds out of 10). Sequential
            // probes keep every failure in the log a supporter reads later.
            probe('wpml').then(function (wpmlOk) {
              setRow('wpml', wpmlOk);
              return probe('toolset').then(function (toolsetOk) {
                setRow('toolset', toolsetOk);
                return [wpmlOk, toolsetOk];
              });
            }).then(function (results) {
              var allOk = results[0] && results[1];
              connStatus.textContent = allOk
                ? '<?php echo esc_js( __( 'All servers reachable', 'wpml' ) ); ?>'
                : '<?php echo esc_js( __( 'One or more servers unreachable', 'wpml' ) ); ?>';
              // The allow-list is only worth reading when something is actually blocked.
              var connHelp = document.getElementById('wpml-support-connectivity-help');
              if (connHelp) { connHelp.hidden = allOk; }
              connBtn.disabled = false;
            });
          });
        }

        <?php $this->renderRefreshLicenseScript(); ?>
      })();
      </script>
      <?php
  }


  /**
   * The "Refresh license" click handler — its own method so it can be proven
   * (`SystemCheckControllerTest`) without paying for the rest of `render()`'s
   * dependencies (the Environment/Debug-information sections' WordPress and
   * `WPML_Debug_Information` calls), and so the diff for wpmldev-8588 stays
   * inside the one control it changes.
   *
   * wpmldev-8588: `OTGS_Installer_Site_Key_Ajax::update()` (vendor/otgs/installer,
   * not editable here — bumped by composer lock in a separate repo) always
   * answers HTTP 200 through `wp_send_json_success()`, whether the revalidation
   * succeeded or not; whether the key is still good travels in the JSON body's
   * `error` field (and, for a key the API refused, `invalid_site_key`), never in
   * the HTTP status. Reading only `r.ok` meant a key the API rejected or an
   * unreachable service was reported "License refreshed." exactly like a real
   * refresh. This makes the tool report what the Installer actually answered.
   *
   * It cannot make the stored subscription match that report: on a rejected key,
   * `WP_Installer::mark_site_key_as_invalid()` (same vendor file) unsets the
   * subscription's `data`/`key_type` and sets `site_key_invalid`, but keeps
   * `subscription.key` — the "clean rejection" branch that clears the key
   * entirely (`$repository->set_subscription( null )`) only runs when the API
   * answers with no error at all, which never happens for an error response
   * shaped like the one every real rejection sends. That state is vendor-owned;
   * this method only stops the page from lying about it.
   */
  private function renderRefreshLicenseScript(): void {
    ?>
        // Refresh license — `update_site_key` AJAX with the wpml repo nonce.
        var licBtn    = document.getElementById('wpml-support-refresh-license');
        var licStatus = document.getElementById('wpml-support-refresh-license-status');
        if (licBtn) {
          licBtn.addEventListener('click', function () {
            licBtn.disabled = true;
            licStatus.textContent = '<?php echo esc_js( /* translators: Status shown on WPML → Support while the site's registration is being fetched again. */ __( 'Refreshing…', 'wpml' ) ); ?>';
            var body = new URLSearchParams();
            body.append('action', 'update_site_key');
            body.append('nonce', licBtn.getAttribute('data-nonce'));
            body.append('repository_id', 'wpml');
            fetch(ajaxUrl, {
              method: 'POST',
              credentials: 'same-origin',
              headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
              body: body.toString()
            }).then(function (r) {
              if (!r.ok) { throw new Error('http ' + r.status); }
              return r.json();
            }).then(function (json) {
              // The handler answers HTTP 200 either way (wp_send_json_success());
              // a key the API rejected or could not reach carries `data.error` and
              // must be reported as what it is, not as a refresh that happened.
              var data = (json && json.data) || {};
              var message = data.error ? String(data.error).replace(/<[^>]*>/g, '').trim() : '';
              licStatus.textContent = message || '<?php echo esc_js( /* translators: Status shown on WPML → Support once the site's registration has been fetched again. */ __( 'License refreshed.', 'wpml' ) ); ?>';
            }).catch(function () {
              licStatus.textContent = '<?php echo esc_js( __( 'Could not refresh — please retry.', 'wpml' ) ); ?>';
            }).finally(function () {
              licBtn.disabled = false;
            });
          });
        }
    <?php
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


  private static function supportTicketUrl(): string {
    $url = 'https://app.wpml.org/support';

    if ( class_exists( '\WPML\OutboundLinks\OutboundLinks' ) ) {
      return \WPML\OutboundLinks\OutboundLinks::to(
        $url,
        [ 'medium' => 'support', 'campaign' => 'support' ]
      );
    }

    return $url;
  }


  private static function debugInfoDocUrl(): string {
    $url = 'https://wpml.org/troubleshooting/debug-info-for-support/';

    if ( class_exists( '\WPML\OutboundLinks\OutboundLinks' ) ) {
      return \WPML\OutboundLinks\OutboundLinks::to(
        $url,
        [ 'medium' => 'support', 'campaign' => 'support' ]
      );
    }

    return $url;
  }

  private function renderEnvironmentRow( array $row ): void {
    $mono = ! empty( $row['mono'] );
    ?>
    <li class="wpml:flex wpml:items-center wpml:gap-3 wpml:px-4 wpml:py-2">
      <?php $this->renderStatusIcon( $row['status'] ); ?>
      <span class="wpml:flex-1 wpml:text-gray-800"><?php echo esc_html( $row['label'] ); ?></span>
      <span class="wpml:text-xs wpml:text-gray-700 <?php echo $mono ? 'wpml:font-mono' : ''; ?>">
        <?php echo esc_html( $row['value'] ); ?>
      </span>
    </li>
    <?php
  }


  private function renderStatusIcon( string $status ): void {
    $cls = 'wpml:text-gray-400';
    if ( $status === 'ok' ) {
      $cls = 'wpml:text-green-600';
    } elseif ( $status === 'warn' ) {
      $cls = 'wpml:text-amber-500';
    }

    if ( $status === 'info' ) {
      ?>
      <svg class="wpml:w-4 wpml:h-4 <?php echo esc_attr( $cls ); ?> wpml:shrink-0" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24" aria-hidden="true">
        <path stroke-linecap="round" stroke-linejoin="round" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
      </svg>
      <?php
      return;
    }
    ?>
    <svg class="wpml:w-4 wpml:h-4 <?php echo esc_attr( $cls ); ?> wpml:shrink-0" fill="none" stroke="currentColor" stroke-width="3" viewBox="0 0 24 24" aria-hidden="true">
      <path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"/>
    </svg>
    <?php
  }


  private function collectRequirementStatuses(): array {
    try {
      $statuses = [];
      foreach ( $this->requirementsService->getAllRequirements( true ) as $requirement ) {
        $statuses[ $requirement['id'] ] = $requirement['isValid'];
      }

      return $statuses;
    } catch ( Throwable $e ) {
      return [];
    }
  }


  private function requirementRowStatus( array $statuses, int $requirementId ): string {
    if ( ! array_key_exists( $requirementId, $statuses ) ) {
      return 'ok';
    }

    return $statuses[ $requirementId ] ? 'ok' : 'warn';
  }


  private function collectVersions( array $statuses ): array {
    $wpdb = $GLOBALS['wpdb'];

    /* translators: Value in the system information table on WPML → Support when a version, a server name or the REST API check has no answer. Lower case because it sits among other values. */
    $wpml_version = defined( 'ICL_SITEPRESS_VERSION' ) ? (string) ICL_SITEPRESS_VERSION : __( 'unknown', 'wpml' );
    $webserver  = isset( $_SERVER['SERVER_SOFTWARE'] )
      ? sanitize_text_field( wp_unslash( $_SERVER['SERVER_SOFTWARE'] ) )
      /* translators: Value in the system information table on WPML → Support when a version, a server name or the REST API check has no answer. Lower case because it sits among other values. */
      : __( 'unknown', 'wpml' );
    $multisite  = is_multisite()
      /* translators: Value in the system information table on WPML → Support: the answer is yes for the property named on the left, such as "Multisite" or "REST enabled". */
      ? __( 'Yes', 'wpml' )
      /* translators: Value in the system information table on WPML → Support: the answer is no for the property named on the left, such as "Multisite" or "REST enabled". */
      : __( 'No', 'wpml' );
    if ( ! array_key_exists( self::REQUIREMENT_ID_REST_ENABLED, $statuses ) ) {
      /* translators: Value in the system information table on WPML → Support when a version, a server name or the REST API check has no answer. Lower case because it sits among other values. */
      $restEnabled = __( 'unknown', 'wpml' );
      $restStatus  = 'info';
    } else {
      $restEnabled = $statuses[ self::REQUIREMENT_ID_REST_ENABLED ]
        /* translators: Value in the system information table on WPML → Support: the answer is yes for the property named on the left, such as "Multisite" or "REST enabled". */
        ? __( 'Yes', 'wpml' )
        /* translators: Value in the system information table on WPML → Support: the answer is no for the property named on the left, such as "Multisite" or "REST enabled". */
        : __( 'No', 'wpml' );
      $restStatus  = $this->requirementRowStatus( $statuses, self::REQUIREMENT_ID_REST_ENABLED );
    }
    $memory     = defined( 'WP_MEMORY_LIMIT' ) ? WP_MEMORY_LIMIT : '—';
    $maxMemory  = defined( 'WP_MAX_MEMORY_LIMIT' ) ? WP_MAX_MEMORY_LIMIT : '—';

    return array(
      /* translators: Row label in the system information table on WPML → Support: the WordPress version. Product name: keep it as it is. */
      array( 'label' => __( 'WordPress', 'wpml' ),             'value' => get_bloginfo( 'version' ),                                                'status' => $this->requirementRowStatus( $statuses, self::REQUIREMENT_ID_WORDPRESS_VERSION ), 'mono' => true ),
      array( 'label' => __( 'WPML Multilingual CMS', 'wpml' ), 'value' => $wpml_version,                                                            'status' => 'ok',   'mono' => true ),
      /* translators: Row label in the system information table on WPML → Support: the PHP version. Keep the name as it is. */
      array( 'label' => __( 'PHP', 'wpml' ),                   'value' => PHP_VERSION,                                                              'status' => $this->requirementRowStatus( $statuses, self::REQUIREMENT_ID_PHP_VERSION ), 'mono' => true ),
      /* translators: Row label in the system information table on WPML → Support: the database version. Keep the name as it is. */
      array( 'label' => __( 'MySQL', 'wpml' ),                 'value' => (string) $wpdb->db_version(),                                             'status' => $this->requirementRowStatus( $statuses, self::REQUIREMENT_ID_DATABASE_VERSION ), 'mono' => true ),
      /* translators: Row label in the system information table on WPML → Support: which web server the site runs on. */
      array( 'label' => __( 'Web server', 'wpml' ),            'value' => $webserver,                                                               'status' => 'ok',   'mono' => true ),
      /* translators: Row label in the system information table on WPML → Support: whether the site is part of a WordPress multisite network. */
      array( 'label' => __( 'Multisite', 'wpml' ),             'value' => $multisite,                                                               'status' => 'info' ),
      array( 'label' => 'WP_MEMORY_LIMIT',                     'value' => $memory,                                                                  'status' => $this->requirementRowStatus( $statuses, self::REQUIREMENT_ID_MEMORY_LIMIT ), 'mono' => true ),
      array( 'label' => 'WP_MAX_MEMORY_LIMIT',                 'value' => $maxMemory,                                                               'status' => $this->requirementRowStatus( $statuses, self::REQUIREMENT_ID_MEMORY_LIMIT ), 'mono' => true ),
      /* translators: Row label in the system information table on WPML → Support: whether the WordPress REST API answers on this site. */
      array( 'label' => __( 'REST enabled', 'wpml' ),          'value' => $restEnabled,                                                             'status' => $restStatus ),
    );
  }


  private function collectPhpLimits( array $statuses ): array {
    $wpdb = $GLOBALS['wpdb'];

    $opcache_enabled = function_exists( 'opcache_get_status' );
    $utf8mb4         = is_object( $wpdb ) && method_exists( $wpdb, 'has_cap' ) && $wpdb->has_cap( 'utf8mb4' )
      /* translators: Value in the system information table on WPML → Support: the answer is yes for the property named on the left, such as "Multisite" or "REST enabled". */
      ? __( 'Yes', 'wpml' )
      /* translators: Value in the system information table on WPML → Support: the answer is no for the property named on the left, such as "Multisite" or "REST enabled". */
      : __( 'No', 'wpml' );

    $memory_used = sprintf( '%.2f MB', memory_get_usage( true ) / 1024 / 1024 );
    $memory_peak = sprintf( '%.2f MB', memory_get_peak_usage( true ) / 1024 / 1024 );

    $opcacheState = $opcache_enabled
      /* translators: Value in the system information table on WPML → Support: the PHP feature named on the left is switched on. Adjective, not a verb. */
      ? __( 'Enabled', 'wpml' )
      /* translators: Value in the system information table on WPML → Support: the PHP feature named on the left is switched off. Adjective, not a verb. */
      : __( 'Disabled', 'wpml' );
    $simpleXmlState = class_exists( 'SimpleXMLElement' )
      /* translators: Value in the system information table on WPML → Support: the PHP extension named on the left is present and in use. Past participle, not a verb. */
      ? __( 'Loaded', 'wpml' )
      /* translators: Value in the system information table on WPML → Support: the PHP extension named on the left is not installed. Adjective, not the gerund of "to miss". */
      : __( 'Missing', 'wpml' );
    $mbstringState = extension_loaded( 'mbstring' )
      /* translators: Value in the system information table on WPML → Support: the PHP extension named on the left is present and in use. Past participle, not a verb. */
      ? __( 'Loaded', 'wpml' )
      /* translators: Value in the system information table on WPML → Support: the PHP extension named on the left is not installed. Adjective, not the gerund of "to miss". */
      : __( 'Missing', 'wpml' );

    return array(
      /* translators: Name of one requirement on the notice WPML shows when the server does not meet its requirements, and a row label in the system information table on WPML → Support: how much memory PHP may use. */
      array( 'label' => __( 'Memory limit', 'wpml' ),               'value' => $this->memoryLimitRowValue(),                                        'status' => $this->requirementRowStatus( $statuses, self::REQUIREMENT_ID_MEMORY_LIMIT ), 'mono' => true ),
      array( 'label' => __( 'Max upload size', 'wpml' ),            'value' => (string) size_format( wp_max_upload_size() ),                        'status' => 'ok',   'mono' => true ),
      array( 'label' => __( 'Max post size', 'wpml' ),              'value' => (string) ini_get( 'post_max_size' ),                                 'status' => 'ok',   'mono' => true ),
      array( 'label' => __( 'Max execution time', 'wpml' ),         'value' => ini_get( 'max_execution_time' ) . 's',                               'status' => 'ok',   'mono' => true ),
      array( 'label' => __( 'Max input vars', 'wpml' ),             'value' => (string) ini_get( 'max_input_vars' ),                                'status' => 'ok',   'mono' => true ),
      /* translators: Row label in the system information table on WPML → Support: a PHP feature that speeds up code. Keep the name as it is. */
      array( 'label' => __( 'OPCache', 'wpml' ),                    'value' => $opcacheState,                                                       'status' => 'ok' ),
      array( 'label' => __( 'PHP memory used', 'wpml' ),            'value' => $memory_used . ' / ' . $memory_peak,                                 'status' => 'info', 'mono' => true ),
      /* translators: Row label in the system information table on WPML → Support: a character set of the database. Keep the name utf8mb4 as it is. */
      array( 'label' => __( 'Utf8mb4 charset', 'wpml' ),            'value' => $utf8mb4,                                                            'status' => 'ok' ),
      /* translators: Row label in the system information table on WPML → Support: a PHP extension. Keep the extension name as it is. */
      array( 'label' => __( 'SimpleXML extension', 'wpml' ),        'value' => $simpleXmlState,                                                     'status' => class_exists( 'SimpleXMLElement' ) ? 'ok' : 'warn' ),
      array( 'label' => __( 'Multibyte String extension', 'wpml' ), 'value' => $mbstringState,                                                      'status' => extension_loaded( 'mbstring' ) ? 'ok' : 'warn' ),
    );
  }


  private function memoryLimitRowValue(): string {
    $runtime  = (string) $this->serverInfo->getIniGet( 'memory_limit' );
    $original = $this->serverInfo->getOriginalIniGet( 'memory_limit' );

    if ( ! is_string( $original ) || $original === '' || $original === $runtime ) {
      return $runtime;
    }

    return sprintf(
      /* translators: Value of the "Memory limit" row in the system information table on WPML → Support, shown when the two limits differ. %1$s: the memory limit set in the php.ini file, such as 120M. %2$s: the memory limit in use on this page, such as 256M. Keep "php.ini" as it is. */
      __( '%1$s in php.ini, %2$s at runtime', 'wpml' ),
      $original,
      $runtime
    );
  }


  private function collectPhpLibraries(): array {
    return array(
      array( 'label' => 'cURL',      'status' => function_exists( 'curl_init' ) ? 'ok' : 'warn' ),
      array( 'label' => 'simpleXML', 'status' => class_exists( 'SimpleXMLElement' ) ? 'ok' : 'warn' ),
    );
  }


  private function collectDebugInformation(): string {
    try {
      $debug_info = new WPML_Debug_Information( $GLOBALS['wpdb'], $GLOBALS['sitepress'] );

      return (string) $debug_info->do_json_encode( $debug_info->run() );
    } catch ( \Throwable $e ) {
      \WPML\PHP\Logger\error(
        'Failed to collect debug information: ' . $e->getMessage() . ' ' . $e->getTraceAsString()
      );

      return sprintf(
        /* translators: Shown in the box on the WPML System check page that normally holds the debug information to copy. %s: the error message PHP reported. */
        __( 'WPML could not collect the debug information. Error: %s', 'wpml' ),
        $e->getMessage()
      );
    }
  }


  private function getAutomaticTranslationAccountId() {
    $siteId    = $this->siteIdQuery->get();
    $sharedKey = $this->siteSharedKeyQuery->get();

    if ( ! $siteId || ! $sharedKey ) {
      return null;
    }

    return $siteId . '#' . $sharedKey;
  }


  private function renderInstallerInstancesTable(): void {
    $instances = $this->collectInstallerInstances();

    if ( empty( $instances ) ) {
      echo '<p class="wpml:text-xs wpml:text-gray-500">' . esc_html__( 'No installer instances detected.', 'wpml' ) . '</p>';
      return;
    }
    ?>
    <div class="wpml:bg-white wpml:border wpml:border-gray-200 wpml:rounded-md wpml:overflow-hidden">
      <table class="wpml:w-full wpml:text-sm">
        <thead class="wpml:bg-gray-50 wpml:text-[11px] wpml:font-semibold wpml:tracking-wide wpml:text-gray-500 wpml:uppercase wpml:border-b wpml:border-gray-100">
          <tr>
            <th class="wpml:text-left wpml:px-4 wpml:py-2 wpml:font-semibold"><?php /* translators: Column heading in the loaded-files table on WPML → Support: where the file sits on the server. */ esc_html_e( 'Path', 'wpml' ); ?></th>
            <th class="wpml:text-left wpml:px-4 wpml:py-2 wpml:font-semibold wpml:w-24"><?php /* translators: Column heading in a table on WPML → Support: the version number. */ esc_html_e( 'Version', 'wpml' ); ?></th>
            <th class="wpml:text-center wpml:px-4 wpml:py-2 wpml:font-semibold wpml:w-28"><?php /* translators: Column heading in the loaded-files table on WPML → Support: whether the file is loaded before the others. */ esc_html_e( 'High priority', 'wpml' ); ?></th>
            <th class="wpml:text-center wpml:px-4 wpml:py-2 wpml:font-semibold wpml:w-24"><?php /* translators: Column heading in the loaded-files table on WPML → Support: whether the file's work is handed over to another file. Adjective, not a verb. */ esc_html_e( 'Delegated', 'wpml' ); ?></th>
          </tr>
        </thead>
        <tbody class="wpml:divide-y wpml:divide-gray-100 wpml:text-xs wpml:font-mono wpml:text-gray-700">
          <?php foreach ( $instances as $row ) : ?>
            <tr<?php echo ! empty( $row['delegated'] ) ? ' class="wpml:bg-green-50/40"' : ''; ?>>
              <td class="wpml:px-4 wpml:py-2 <?php echo ! empty( $row['delegated'] ) ? 'wpml:text-green-700' : ''; ?>"><?php echo esc_html( $row['path'] ); ?></td>
              <td class="wpml:px-4 wpml:py-2"><?php echo esc_html( $row['version'] ); ?></td>
              <td class="wpml:text-center">
                <?php if ( ! empty( $row['high_priority'] ) ) : ?>
                  <svg class="wpml:w-4 wpml:h-4 wpml:text-green-600 wpml:inline" fill="none" stroke="currentColor" stroke-width="3" viewBox="0 0 24 24" aria-hidden="true">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"/>
                  </svg>
                <?php endif; ?>
              </td>
              <td class="wpml:text-center">
                <?php if ( ! empty( $row['delegated'] ) ) : ?>
                  <svg class="wpml:w-4 wpml:h-4 wpml:text-green-600 wpml:inline" fill="none" stroke="currentColor" stroke-width="3" viewBox="0 0 24 24" aria-hidden="true">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"/>
                  </svg>
                <?php endif; ?>
              </td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
    <?php
  }


  private function collectInstallerInstances(): array {
    $instances = isset( $GLOBALS['wp_installer_instances'] ) ? $GLOBALS['wp_installer_instances'] : array();
    if ( ! is_array( $instances ) || empty( $instances ) ) {
      return array();
    }

    $rows = array();
    foreach ( $instances as $instance ) {
      if ( ! is_array( $instance ) ) {
        continue;
      }
      $rows[] = array(
        'path'          => isset( $instance['bootfile'] ) ? (string) $instance['bootfile'] : '—',
        'version'       => isset( $instance['version'] ) ? (string) $instance['version'] : '—',
        'delegated'     => ! empty( $instance['delegated'] ),
        'high_priority' => ! empty( $instance['high_priority'] ),
      );
    }

    return $rows;
  }


}
