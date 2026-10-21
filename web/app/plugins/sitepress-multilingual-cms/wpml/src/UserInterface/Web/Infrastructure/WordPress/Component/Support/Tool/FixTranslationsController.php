<?php

namespace WPML\UserInterface\Web\Infrastructure\WordPress\Component\Support\Tool;

use WPML\UserInterface\Web\Core\SharedKernel\Config\PageRenderInterface;


class FixTranslationsController implements PageRenderInterface {


  public function render() {
    $nonces = array(
      'cache_clear' => wp_create_nonce( 'cache_clear' ),
    );
    $i18n = array(
      /* translators: Status shown next to a button on WPML → Support once its action has finished. */
      'done'         => __( 'Done.', 'wpml' ),
      /* translators: Status shown next to a button on WPML → Support while its action is running. */
      'workingTitle' => __( 'Working…', 'wpml' ),
      'errorTitle'   => __( 'Something went wrong. Please retry.', 'wpml' ),
    );
    ?>
      <h1 class="wpml:text-2xl wpml:font-semibold wpml:text-gray-900 wpml:mb-1">
        <?php /* translators: Name of the Clear cache Support tool: its entry in the tool list on WPML → Support, the heading of its screen and the button that runs it. Verb phrase, imperative. */ esc_html_e( 'Clear cache', 'wpml' ); ?>
      </h1>
      <p class="wpml:text-sm wpml:text-gray-500 wpml:mb-6">
        <?php esc_html_e( 'The repair tools of this page live in WPML Troubleshooting; WPML itself keeps the cache clear.', 'wpml' ); ?>
      </p>

      <!-- Clear cache -->
      <section id="clear-cache" class="wpml:bg-white wpml:border wpml:border-gray-200 wpml:rounded-md wpml:p-5 wpml:mb-5 wpml:scroll-mt-8">
        <h2 class="wpml:text-sm wpml:font-semibold wpml:text-gray-900 wpml:mb-1">
          <?php esc_html_e( 'Clear the cache in WPML', 'wpml' ); ?>
        </h2>
        <p class="wpml:text-xs wpml:text-gray-500 wpml:mb-4">
          <?php esc_html_e( "Flushes WPML's internal cache. Often the first thing to try after migrations, plugin updates, or when something looks out of sync.", 'wpml' ); ?>
        </p>
        <button type="button"
          data-wpml-support-action="cache_clear"
          data-wpml-support-done="<?php /* translators: Status shown next to the Clear cache button on WPML → Support once the cache has been cleared. */ echo esc_attr__( "WPML's cache was cleared.", 'wpml' ); ?>"
          data-wpml-support-failed="<?php /* translators: Status shown next to the Clear cache button on WPML → Support when the cache could not be cleared. */ echo esc_attr__( 'Could not clear the cache — please retry.', 'wpml' ); ?>"
          class="wpml-button base-btn wpml:text-sm wpml:disabled:opacity-50 wpml:disabled:cursor-not-allowed">
          <?php /* translators: Name of the Clear cache Support tool: its entry in the tool list on WPML → Support, the heading of its screen and the button that runs it. Verb phrase, imperative. */ esc_html_e( 'Clear cache', 'wpml' ); ?>
        </button>
        <span id="status-clear-cache" class="wpml:text-xs wpml:text-gray-500 wpml:ml-3" aria-live="polite"></span>
      </section>

      <script>
      (function () {
        var nonces = <?php echo wp_json_encode( $nonces ); ?>;
        var i18n   = <?php echo wp_json_encode( $i18n ); ?>;

        // Built server-side: a root-relative '/wp-admin/…' would resolve against the
        // domain, not the WordPress root, and 404 on subdirectory installs (wpmldev-7682).
        var adminUrl = <?php echo wp_json_encode( admin_url( 'admin.php' ) ); ?>;

        // Phase 1 dispatcher: POST admin.php?debug_action=…&nonce=…
        function postDispatcher(action) {
          var url = adminUrl + '?debug_action=' + encodeURIComponent(action) +
                    '&nonce=' + encodeURIComponent(nonces[action] || '');
          return fetch(url, { method: 'POST', credentials: 'same-origin' });
        }

        // wpmldev-8466: HTTP 200 on its own is not proof the server did the work.
        // The `debug_action` dispatcher answers JSON: `{"success":true|false}`, and a
        // refusal carries 400/403. Anything else — an empty body, an HTML admin page,
        // `success:false` — is a refusal and must not read "Done".
        function isSuccessBody(text) {
          var payload;
          try {
            payload = JSON.parse(text);
          } catch (e) {
            return false;
          }
          if (!payload || typeof payload !== 'object') { return false; }
          if (typeof payload.success !== 'undefined') { return payload.success === true; }
          return true;
        }

        // wpmldev-8558: the outcome goes on the tool's own status line — the
        // `<span id="status-…" aria-live="polite">` beside the button, which is
        // how every other Support tool reports (Database & strings maintenance,
        // the address-cache block, the usage-reporting card). It used to be
        // written over the button's own label and put back after 1.5s, so a
        // tool that finished instantly — "Clear cache" — confirmed nothing a
        // reader could catch, and a screen reader was never told at all.
        // A tool with a sentence of its own carries it on the button as
        // `data-wpml-support-done` / `-failed`; the rest use these two.
        function statusLineFor(btn) {
          var section = btn.closest('section');
          return section ? section.querySelector('[id^="status-"]') : null;
        }

        function withFeedback(btn, fn) {
          var status = statusLineFor(btn);
          var doneText   = btn.getAttribute('data-wpml-support-done') || i18n.done;
          var failedText = btn.getAttribute('data-wpml-support-failed') || i18n.errorTitle;

          btn.disabled = true;
          if (status) { status.textContent = i18n.workingTitle; }

          fn().then(function (r) {
            if (!r.ok) { throw new Error('http ' + r.status); }
            return r.text();
          }).then(function (text) {
            if (!isSuccessBody(text)) { throw new Error('refused'); }
            if (status) { status.textContent = doneText; }
            btn.disabled = false;
          }).catch(function () {
            if (status) { status.textContent = failedText; }
            btn.disabled = false;
          });
        }

        document.querySelectorAll('button[data-wpml-support-action]').forEach(function (btn) {
          btn.addEventListener('click', function () {
            var action = btn.getAttribute('data-wpml-support-action');
            if (!action) { return; }
            withFeedback(btn, function () { return postDispatcher(action); });
          });
        });
      })();
      </script>
      <?php
  }


}
