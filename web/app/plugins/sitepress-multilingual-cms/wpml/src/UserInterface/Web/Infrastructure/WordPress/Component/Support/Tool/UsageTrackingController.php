<?php

namespace WPML\UserInterface\Web\Infrastructure\WordPress\Component\Support\Tool;

use WPML\Core\Component\PostHog\Domain\TrackingMode;
use WPML\Infrastructure\WordPress\Component\PostHog\Application\Repository\PostHogStateRepository;
use WPML\UserInterface\Web\Core\SharedKernel\Config\PageRenderInterface;


class UsageTrackingController implements PageRenderInterface {


  public function render() {
    $is_enabled = $this->readEnabledState();
    $site_key   = $this->readSiteKey();
    $rest_url   = esc_url_raw( rest_url( 'wpml/v1/troubleshooting/posthog' ) );
    $rest_nonce = wp_create_nonce( 'wp_rest' );
    ?>
      <h1 class="wpml:text-2xl wpml:font-semibold wpml:text-gray-900 wpml:mb-1">
        <?php esc_html_e( 'Usage tracking and reporting', 'wpml' ); ?>
      </h1>
      <p class="wpml:text-sm wpml:text-gray-500 wpml:mb-6">
        <?php esc_html_e( 'Performance and error reporting that helps WPML Support diagnose issues on your site.', 'wpml' ); ?>
      </p>

      <div class="wpml-danger-box wpml:p-4 wpml:mb-6 wpml:flex wpml:items-start wpml:gap-2">
        <svg class="wpml:w-5 wpml:h-5 wpml:text-red-600 wpml:shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01M4.93 4.93a10 10 0 1114.14 14.14 10 10 0 01-14.14-14.14z"/>
        </svg>
        <div>
          <p class="wpml:text-sm wpml:font-semibold wpml:text-red-900 wpml:mb-0.5">
            <?php /* translators: Warning above the usage-reporting switch on WPML → Support. "This" is usage reporting. */ esc_html_e( 'Turn this on only when WPML Support asks you to', 'wpml' ); ?>
          </p>
          <p class="wpml:text-xs wpml:text-red-900/80">
            <?php /* translators: Explanation of usage reporting on WPML → Support. "This" and "It" are usage reporting. */ esc_html_e( "While this is on, WPML sends performance and error data off your server. It's off by default and should stay off unless a supporter needs it to reproduce a specific issue you reported.", 'wpml' ); ?>
          </p>
        </div>
      </div>

      <section class="wpml:bg-white wpml:border wpml:border-gray-200 wpml:rounded-md wpml:p-5 wpml:mb-5">
        <label class="wpml:flex wpml:items-start wpml:gap-3 wpml:cursor-pointer">
          <input id="wpml-usage-tracking" type="checkbox"
            <?php checked( $is_enabled ); ?>
            <?php disabled( $site_key === '' ); ?>
            data-rest-url="<?php echo esc_attr( $rest_url ); ?>"
            data-rest-nonce="<?php echo esc_attr( $rest_nonce ); ?>"
            data-site-key="<?php echo esc_attr( $site_key ); ?>"
            class="wpml:mt-0.5 wpml:w-4 wpml:h-4 wpml:rounded wpml:border-gray-300 wpml:accent-blue"/>
          <span class="wpml:flex-1">
            <span class="wpml:block wpml:text-sm wpml:font-medium wpml:text-gray-900 wpml:mb-1">
              <?php esc_html_e( 'Send usage and error reports to WPML', 'wpml' ); ?>
            </span>
            <span class="wpml:block wpml:text-xs wpml:text-gray-500">
              <?php esc_html_e( "Sends timing and error metrics to WPML's diagnostics endpoint. No post content, user data, or credentials are transmitted.", 'wpml' ); ?>
            </span>
            <span id="wpml-usage-tracking-status" class="wpml:block wpml:text-[11px] wpml:text-gray-400 wpml:mt-1" aria-live="polite"></span>
          </span>
        </label>
        <?php if ( $site_key === '' ) : ?>
          <p class="wpml:mt-3 wpml:text-xs wpml:text-amber-700">
            <?php esc_html_e( 'WPML is not registered with a site key yet — register it from the System check page before turning usage reporting on.', 'wpml' ); ?>
          </p>
        <?php endif; ?>
      </section>

      <script>
      (function () {
        var cb = document.getElementById('wpml-usage-tracking');
        if (!cb || cb.disabled) { return; }
        var status = document.getElementById('wpml-usage-tracking-status');
        var i18n = {
          saving: '<?php echo esc_js( /* translators: Status shown next to the Save button on WPML → Settings while the settings are being stored. */ __( 'Saving…', 'wpml' ) ); ?>',
          on:     '<?php echo esc_js( __( 'Usage reporting is on.', 'wpml' ) ); ?>',
          off:    '<?php echo esc_js( __( 'Usage reporting is off.', 'wpml' ) ); ?>',
          error:  '<?php echo esc_js( __( 'Could not save — please retry.', 'wpml' ) ); ?>'
        };

        // wpmldev-8557 — the setting takes effect in this page, not only on the
        // server. The PostHog client is loaded by a dynamic import inside the
        // bundles, so `initPostHog` registers `optOut()` / `optIn()` on
        // `window.wpmlPostHog` for inline scripts like this one; without them
        // the session recorder kept uploading, and the analytics cookies and
        // PostHog's own storage stayed, until the next page load. A page with
        // no PostHog instance on it has nothing to call, and needs nothing:
        // the next load reads the setting that was just stored.
        function applyInPage(enabled, mode) {
          var api = window.wpmlPostHog;
          if (!api) { return; }

          if (!enabled && typeof api.optOut === 'function') {
            api.optOut();
          } else if (enabled && typeof api.optIn === 'function') {
            api.optIn(mode);
          }
        }

        cb.addEventListener('change', function () {
          cb.disabled = true;
          if (status) { status.textContent = i18n.saving; }
          var desired = cb.checked;

          fetch(cb.getAttribute('data-rest-url'), {
            method: 'POST',
            credentials: 'same-origin',
            headers: {
              'Content-Type': 'application/json',
              'X-WP-Nonce': cb.getAttribute('data-rest-nonce')
            },
            body: JSON.stringify({
              enabled: desired,
              siteKey: cb.getAttribute('data-site-key')
            })
          }).then(function (r) {
            if (!r.ok) { throw new Error('http ' + r.status); }
            return r.json();
          }).then(function (json) {
            // The endpoint wraps success in { success, data: { enabled } }.
            var enabled = json && json.data && typeof json.data.enabled === 'boolean'
              ? json.data.enabled
              : desired;
            var mode = json && json.data && typeof json.data.trackingMode === 'string'
              ? json.data.trackingMode
              : null;
            cb.checked = enabled;
            applyInPage(enabled, mode);
            if (status) { status.textContent = enabled ? i18n.on : i18n.off; }
          }).catch(function () {
            cb.checked = !desired;
            if (status) { status.textContent = i18n.error; }
          }).finally(function () {
            cb.disabled = false;
          });
        });
      })();
      </script>
      <?php
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


  private function readEnabledState(): bool {
    $mode = get_option( PostHogStateRepository::OPTION_NAME_TRACKING_MODE, TrackingMode::DISABLED );
    return is_string( $mode ) && TrackingMode::toBool( $mode );
  }


}
