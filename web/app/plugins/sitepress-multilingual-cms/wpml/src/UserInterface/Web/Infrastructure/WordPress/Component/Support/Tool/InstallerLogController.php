<?php

namespace WPML\UserInterface\Web\Infrastructure\WordPress\Component\Support\Tool;

use OTGS_Installer_Log_Factory;
use OTGS_Installer_Logger_Storage;
use WPML\UserInterface\Web\Core\SharedKernel\Config\PageRenderInterface;

/**
 * M10 Tier 2 — `Support → Installer log`.
 *
 * Recent license + subscription-update calls made by the bundled
 * OTGS Installer. Same data source the legacy
 * `WPML → Troubleshooting → Installer support` page surfaces:
 * option `otgs-installer-log` via
 * `OTGS_Installer_Logger_Storage::get()`.
 *
 * Read-only — the legacy log writer rotates entries server-side
 * (50 max); there's no clear-button affordance in the legacy UI,
 * so M10 doesn't add one either.
 */

class InstallerLogController implements PageRenderInterface {

  const WIDTH_CLASS = 'wpml:max-w-5xl';


  public function render() {
    $entries = $this->collectEntries();
    ?>
      <h1 class="wpml:text-2xl wpml:font-semibold wpml:text-gray-900 wpml:mb-1">
        <?php /* translators: Name of a Support tool: its entry in the tool list on WPML → Support and the heading of its own screen. */ esc_html_e( 'Installer log', 'wpml' ); ?>
      </h1>
      <p class="wpml:text-sm wpml:text-gray-500 wpml:mb-6">
        <?php
        printf(
          /* translators: %s is the literal "Subscriptions updated successfully." status string. */
          esc_html__( "Recent license and subscription-update calls made by WPML's installer. Useful when licensing, activation, or plugin updates fail — support can read the response column to see exactly where the call stopped. A successful call shows %s; anything else is what support will investigate.", 'wpml' ),
          '<code class="wpml:text-[11px] wpml:bg-gray-100 wpml:px-1 wpml:rounded">' . esc_html__( 'Subscriptions updated successfully.', 'wpml' ) . '</code>'
        );
        ?>
      </p>

      <div class="wpml:bg-white wpml:border wpml:border-gray-200 wpml:rounded-md wpml:overflow-hidden">
        <?php if ( empty( $entries ) ) : ?>
          <p class="wpml:px-5 wpml:py-8 wpml:text-center wpml:text-gray-400 wpml:italic wpml:text-sm">
            <?php esc_html_e( 'No installer activity logged yet.', 'wpml' ); ?>
          </p>
        <?php else : ?>
          <table class="wpml:w-full wpml:text-sm">
            <thead class="wpml:bg-gray-50 wpml:text-[11px] wpml:font-semibold wpml:tracking-wide wpml:text-gray-500 wpml:uppercase wpml:border-b wpml:border-gray-100">
              <tr>
                <th class="wpml:text-left wpml:px-4 wpml:py-2 wpml:font-semibold"><?php /* translators: Column heading in the installer log table on WPML → Support: the web address that was called. */ esc_html_e( 'Request URL', 'wpml' ); ?></th>
                <th class="wpml:text-left wpml:px-4 wpml:py-2 wpml:font-semibold"><?php /* translators: Column heading in the installer log table on WPML → Support: the values sent with the call. */ esc_html_e( 'Request arguments', 'wpml' ); ?></th>
                <th class="wpml:text-left wpml:px-4 wpml:py-2 wpml:font-semibold"><?php /* translators: Column heading in the installer log table on WPML → Support: what came back from the call. */ esc_html_e( 'Response', 'wpml' ); ?></th>
                <th class="wpml:text-left wpml:px-4 wpml:py-2 wpml:font-semibold"><?php /* translators: Column heading in the installer log table on WPML → Support: which part of WPML made the call. */ esc_html_e( 'Component', 'wpml' ); ?></th>
                <th class="wpml:text-left wpml:px-4 wpml:py-2 wpml:font-semibold wpml:whitespace-nowrap"><?php /* translators: Column heading in a log table on WPML → Support: when the entry was written. */ esc_html_e( 'Time', 'wpml' ); ?></th>
              </tr>
            </thead>
            <tbody class="wpml:divide-y wpml:divide-gray-100 wpml:text-xs">
              <?php foreach ( $entries as $entry ) : ?>
                <tr>
                  <td class="wpml:px-4 wpml:py-2 wpml:text-gray-600 wpml:font-mono wpml:break-all wpml:max-w-[280px]"><?php echo esc_html( $this->safeGet( $entry, 'get_request_url' ) ); ?></td>
                  <td class="wpml:px-4 wpml:py-2 wpml:text-gray-600 wpml:font-mono wpml:text-[11px] wpml:max-w-[180px] wpml:break-all"><?php echo esc_html( $this->safeGet( $entry, 'get_request_args' ) ); ?></td>
                  <td class="wpml:px-4 wpml:py-2 wpml:text-gray-700"><?php echo esc_html( $this->safeGet( $entry, 'get_response' ) ); ?></td>
                  <td class="wpml:px-4 wpml:py-2 wpml:text-gray-700"><?php echo esc_html( $this->safeGet( $entry, 'get_component' ) ); ?></td>
                  <td class="wpml:px-4 wpml:py-2 wpml:text-gray-600 wpml:whitespace-nowrap wpml:font-mono"><?php echo esc_html( $this->safeGet( $entry, 'get_time' ) ); ?></td>
                </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        <?php endif; ?>
      </div>
      <?php
  }


  private function collectEntries(): array {
    if ( ! class_exists( OTGS_Installer_Logger_Storage::class ) || ! class_exists( OTGS_Installer_Log_Factory::class ) ) {
      return array();
    }

    $storage = new OTGS_Installer_Logger_Storage( new OTGS_Installer_Log_Factory() );
    $entries = $storage->get();
    return $entries;
  }


  private function safeGet( $entry, string $getter ): string {
    if ( ! is_object( $entry ) || ! method_exists( $entry, $getter ) ) {
      return '';
    }
    $value = $entry->$getter();
    if ( is_array( $value ) || is_object( $value ) ) {
      return (string) wp_json_encode( $value );
    }
    return (string) $value;
  }


}
