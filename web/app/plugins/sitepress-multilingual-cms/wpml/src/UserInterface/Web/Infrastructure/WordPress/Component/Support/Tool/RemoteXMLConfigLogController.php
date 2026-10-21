<?php

namespace WPML\UserInterface\Web\Infrastructure\WordPress\Component\Support\Tool;

use WPML\UserInterface\Web\Core\SharedKernel\Config\PageRenderInterface;
use WPML_Config_Update_Log;


class RemoteXMLConfigLogController implements PageRenderInterface {

  const WIDTH_CLASS = 'wpml:max-w-5xl';


  public function render() {
    $entries    = $this->collectEntries();
    $columns    = $this->collectColumns( $entries );
    $rest_url   = esc_url_raw( rest_url( 'wpml/v1/troubleshooting/remote-xml-config-log/clear' ) );
    $rest_nonce = wp_create_nonce( 'wp_rest' );
    ?>
      <h1 class="wpml:text-2xl wpml:font-semibold wpml:text-gray-900 wpml:mb-1">
        <?php esc_html_e( 'Remote XML config log', 'wpml' ); ?>
      </h1>
      <p class="wpml:text-sm wpml:text-gray-500 wpml:mb-6">
        <?php
        printf(
          /* translators: Description of a Support tool on WPML → Support. %1$s: the file name "wpml-config.xml", shown in a code box, %2$s: a link reading "Settings → Custom XML Configuration". */
          esc_html__( "Tracks the %1\$s files WPML has read from themes and plugins, including remote configurations fetched on update. Use this log when a custom config file from a theme or plugin doesn't seem to apply: support uses it to confirm WPML actually saw and parsed the file. Read the description in %2\$s for context on what these files do.", 'wpml' ),
          '<code class="wpml:text-[11px] wpml:bg-gray-100 wpml:px-1 wpml:rounded">wpml-config.xml</code>',
          '<a href="' . esc_url( admin_url( 'admin.php?page=tm/menu/settings&section=custom-xml' ) ) . '" class="wpml:text-blue wpml:hover:underline">' . esc_html__( 'Settings → Custom XML Configuration', 'wpml' ) . '</a>'
        );
        ?>
      </p>

      <div id="wpml-xml-config-log-table" class="wpml:bg-white wpml:border wpml:border-gray-200 wpml:rounded-md wpml:overflow-hidden wpml:mb-4">
        <?php if ( empty( $entries ) ) : ?>
          <p class="wpml:px-5 wpml:py-8 wpml:text-center wpml:text-gray-400 wpml:italic wpml:text-sm">
            <?php esc_html_e( 'The remote XML config log is empty.', 'wpml' ); ?>
          </p>
        <?php else : ?>
          <table class="wpml:w-full wpml:text-sm">
            <thead class="wpml:bg-gray-50 wpml:text-[11px] wpml:font-semibold wpml:tracking-wide wpml:text-gray-500 wpml:uppercase wpml:border-b wpml:border-gray-100">
              <tr>
                <th class="wpml:text-left wpml:px-4 wpml:py-2 wpml:font-semibold wpml:whitespace-nowrap"><?php /* translators: Column heading in a log table on WPML → Support: when the entry was written. */ esc_html_e( 'Time', 'wpml' ); ?></th>
                <?php foreach ( $columns as $column ) : ?>
                  <th class="wpml:text-left wpml:px-4 wpml:py-2 wpml:font-semibold"><?php echo esc_html( $column ); ?></th>
                <?php endforeach; ?>
              </tr>
            </thead>
            <tbody class="wpml:divide-y wpml:divide-gray-100 wpml:text-xs">
              <?php foreach ( $entries as $timestamp => $entry ) : ?>
                <tr>
                  <td class="wpml:px-4 wpml:py-2 wpml:text-gray-600 wpml:whitespace-nowrap wpml:font-mono"><?php echo esc_html( $this->formatTimestamp( $timestamp ) ); ?></td>
                  <?php foreach ( $columns as $column ) : ?>
                    <td class="wpml:px-4 wpml:py-2 wpml:text-gray-700"><?php echo esc_html( $this->cellValue( $entry, $column ) ); ?></td>
                  <?php endforeach; ?>
                </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        <?php endif; ?>
      </div>

      <p class="wpml:text-sm wpml:text-gray-500 wpml:mb-4">
        <?php
        echo wp_kses(
          sprintf(
            /* translators: Line on the Remote XML config log page telling the user where to load the configuration files again. %1$s: the opening tag of a link to the WordPress Updates screen, %2$s: its closing tag. The words between them name that screen. */
            esc_html__( 'To read the configuration files again, use the Update button under "Theme and Plugins compatibility with WPML" on %1$sDashboard → Updates%2$s. WPML adds a new entry here on every read.', 'wpml' ),
            '<a href="' . esc_url( admin_url( 'update-core.php#icl_theme_plugins_compatibility' ) ) . '" class="wpml:text-blue wpml:hover:underline">',
            '</a>'
          ),
          array( 'a' => array( 'href' => array(), 'class' => array() ) )
        );
        ?>
      </p>

      <div class="wpml:bg-white wpml:border wpml:border-gray-200 wpml:rounded-md wpml:p-4">
        <h2 class="wpml:text-sm wpml:font-semibold wpml:text-gray-900 wpml:mb-1">
          <?php esc_html_e( 'Clear the log', 'wpml' ); ?>
        </h2>
        <p class="wpml:text-xs wpml:text-gray-500 wpml:mb-3">
          <?php
          printf(
            /* translators: Description of the Remove log button on WPML → Support. %s is the literal `wpml-config.xml`, shown in a code box. */
            esc_html__( 'Removes the entries above. WPML starts a new log on the next config-file scan. It also empties the log itself after every successful read of the %s files, so an empty log does not mean nothing was read.', 'wpml' ),
            '<code class="wpml:text-[11px] wpml:bg-gray-100 wpml:px-1 wpml:rounded">wpml-config.xml</code>'
          );
          ?>
        </p>
        <button type="button" id="wpml-xml-config-log-clear"
          data-rest-url="<?php echo esc_attr( $rest_url ); ?>"
          data-rest-nonce="<?php echo esc_attr( $rest_nonce ); ?>"
          class="wpml-button base-btn wpml-button--outlined wpml:text-sm wpml:disabled:opacity-50 wpml:disabled:cursor-not-allowed">
          <?php /* translators: Button label on WPML → Support that deletes the log. Verb phrase, imperative. */ esc_html_e( 'Remove log', 'wpml' ); ?>
        </button>
        <span id="wpml-xml-config-log-clear-status" class="wpml:ml-2 wpml:text-xs wpml:text-gray-500" aria-live="polite"></span>
      </div>

      <script>
      (function () {
        var btn = document.getElementById('wpml-xml-config-log-clear');
        if (!btn) { return; }
        var status = document.getElementById('wpml-xml-config-log-clear-status');
        var table  = document.getElementById('wpml-xml-config-log-table');
        var i18n = {
          clearing: '<?php echo esc_js( /* translators: Status shown next to the Remove log button on WPML → Support while the log is being deleted. */ __( 'Removing…', 'wpml' ) ); ?>',
          done:     '<?php echo esc_js( /* translators: Status shown next to the Remove log button on WPML → Support once the log has been deleted. */ __( 'The log is empty.', 'wpml' ) ); ?>',
          error:    '<?php echo esc_js( /* translators: Status shown next to the Remove log button on WPML → Support when the log could not be deleted. */ __( 'Could not remove the log. Please retry.', 'wpml' ) ); ?>',
          empty:    '<?php echo esc_js( __( 'The remote XML config log is empty.', 'wpml' ) ); ?>'
        };

        btn.addEventListener('click', function () {
          btn.disabled = true;
          if (status) { status.textContent = i18n.clearing; }

          fetch(btn.getAttribute('data-rest-url'), {
            method: 'POST',
            credentials: 'same-origin',
            headers: {
              'Content-Type': 'application/json',
              'X-WP-Nonce': btn.getAttribute('data-rest-nonce')
            }
          }).then(function (r) {
            if (!r.ok) { throw new Error('http ' + r.status); }
            return r.json();
          }).then(function (json) {
            if (!json || json.success !== true) { throw new Error('refused'); }
            // The rows are gone server-side; render the same empty state the
            // page would render on a reload, rather than leaving stale rows up.
            if (table) {
              var p = document.createElement('p');
              p.className = 'wpml:px-5 wpml:py-8 wpml:text-center wpml:text-gray-400 wpml:italic wpml:text-sm';
              p.textContent = i18n.empty;
              table.textContent = '';
              table.appendChild(p);
            }
            if (status) { status.textContent = i18n.done; }
          }).catch(function () {
            if (status) { status.textContent = i18n.error; }
            btn.disabled = false;
          });
        });
      })();
      </script>
      <?php
  }


  private function collectEntries(): array {
    if ( ! class_exists( WPML_Config_Update_Log::class ) ) {
      return array();
    }

    $log     = new WPML_Config_Update_Log();
    $entries = $log->get();
    if ( ! is_array( $entries ) ) {
      return array();
    }
    krsort( $entries );
    return $entries;
  }


  private function collectColumns( array $entries ): array {
    $columns = array();
    foreach ( $entries as $entry ) {
      if ( is_array( $entry ) ) {
        $columns = array_merge( $columns, array_keys( $entry ) );
      }
    }
    return array_values( array_unique( $columns ) );
  }


  private function cellValue( $entry, string $column ): string {
    if ( ! is_array( $entry ) || ! isset( $entry[ $column ] ) ) {
      return '';
    }
    $value = $entry[ $column ];
    if ( is_scalar( $value ) || ( is_object( $value ) && method_exists( $value, '__toString' ) ) ) {
      return (string) $value;
    }
    return (string) wp_json_encode( $value );
  }


  private function formatTimestamp( string $raw ): string {
    if ( $raw === '' ) {
      return '';
    }
    $seconds = is_numeric( $raw ) ? (int) $raw : 0;
    if ( $seconds <= 0 ) {
      return $raw;
    }
    return (string) wp_date( 'Y-m-d H:i:s', $seconds );
  }


}
