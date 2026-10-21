<?php

namespace WPML\UserInterface\Web\Infrastructure\WordPress\Component\Support\Tool;

use WPML\TM\ATE\Log\EventsTypes;
use WPML\TM\ATE\Log\Storage as ATELogStorage;
use WPML\UserInterface\Web\Core\SharedKernel\Config\PageRenderInterface;


class ATEErrorLogsController implements PageRenderInterface {

  const WIDTH_CLASS = 'wpml:max-w-none';

  const REDACTED_KEYS = [ 'headers', 'body' ];


  public function render() {
    $entries = $this->collectEntries();
    ?>
      <h1 class="wpml:text-2xl wpml:font-semibold wpml:text-gray-900 wpml:mb-1">
        <?php esc_html_e( 'Advanced Translation Editor error logs', 'wpml' ); ?>
      </h1>
      <p class="wpml:text-sm wpml:text-gray-500 wpml:mb-6">
        <?php
        echo \wpml_bold_names(
          __( "Errors reported by the <b>Advanced Translation Editor</b> — authentication failures, sync issues, service unavailability. Share with WPML support when translation jobs aren't completing.", 'wpml' )
        );
        ?>
      </p>

      <div class="wpml:bg-white wpml:border wpml:border-gray-200 wpml:rounded-md wpml:overflow-hidden">
        <?php if ( empty( $entries ) ) : ?>
          <p class="wpml:px-5 wpml:py-8 wpml:text-center wpml:text-gray-400 wpml:italic wpml:text-sm">
            <?php esc_html_e( 'No ATE errors logged yet.', 'wpml' ); ?>
          </p>
        <?php else : ?>
          <table class="wpml:w-full wpml:text-sm">
            <thead class="wpml:bg-gray-50 wpml:text-[11px] wpml:font-semibold wpml:tracking-wide wpml:text-gray-500 wpml:uppercase wpml:border-b wpml:border-gray-100">
              <tr>
                <th class="wpml:text-left wpml:px-4 wpml:py-2.5 wpml:font-semibold wpml:whitespace-nowrap"><?php /* translators: Column heading in the error-log table on WPML → Support: when the entry was written. */ esc_html_e( 'Date', 'wpml' ); ?></th>
                <th class="wpml:text-left wpml:px-4 wpml:py-2.5 wpml:font-semibold"><?php /* translators: Column heading in the error-log table on WPML → Support: what happened. */ esc_html_e( 'Event', 'wpml' ); ?></th>
                <th class="wpml:text-left wpml:px-4 wpml:py-2.5 wpml:font-semibold"><?php /* translators: Column heading in the error-log table on WPML → Support: the details of what happened. */ esc_html_e( 'Description', 'wpml' ); ?></th>
                <th class="wpml:text-left wpml:px-4 wpml:py-2.5 wpml:font-semibold wpml:whitespace-nowrap"><?php esc_html_e( 'WPML job ID', 'wpml' ); ?></th>
                <th class="wpml:text-left wpml:px-4 wpml:py-2.5 wpml:font-semibold wpml:whitespace-nowrap"><?php esc_html_e( 'ATE job ID', 'wpml' ); ?></th>
                <th class="wpml:text-left wpml:px-4 wpml:py-2.5 wpml:font-semibold"><?php /* translators: Column heading in the error-log table on WPML → Support: further details of the entry. */ esc_html_e( 'Extra data', 'wpml' ); ?></th>
              </tr>
            </thead>
            <tbody class="wpml:divide-y wpml:divide-gray-100 wpml:text-xs wpml:align-top">
              <?php
              foreach ( $entries as $entry ) :
                if ( ! is_object( $entry ) ) {
                    continue;
                }
                ?>
                <tr>
                  <td class="wpml:px-4 wpml:py-3 wpml:text-gray-600 wpml:whitespace-nowrap"><?php echo esc_html( method_exists( $entry, 'getFormattedDate' ) ? (string) $entry->getFormattedDate() : '' ); ?></td>
                  <td class="wpml:px-4 wpml:py-3 wpml:text-gray-800"><?php echo esc_html( isset( $entry->eventType ) ? EventsTypes::getLabel( $entry->eventType ) : '' ); ?></td>
                  <td class="wpml:px-4 wpml:py-3 wpml:text-gray-700"><?php echo esc_html( isset( $entry->description ) ? (string) $entry->description : '' ); ?></td>
                  <td class="wpml:px-4 wpml:py-3 wpml:text-gray-500"><?php echo esc_html( isset( $entry->wpmlJobId ) ? (string) $entry->wpmlJobId : '0' ); ?></td>
                  <td class="wpml:px-4 wpml:py-3 wpml:text-gray-500"><?php echo esc_html( isset( $entry->ateJobId ) ? (string) $entry->ateJobId : '0' ); ?></td>
                  <td class="wpml:px-4 wpml:py-3 wpml:text-gray-500 wpml:font-mono wpml:text-[11px] wpml:max-w-[280px] wpml:break-all"><?php echo esc_html( $this->extraDataFor( $entry ) ); ?></td>
                </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        <?php endif; ?>
      </div>
      <?php
  }


  private function extraDataFor( $entry ): string {
    $raw = method_exists( $entry, 'getExtraDataToString' ) ? (string) $entry->getExtraDataToString() : '';

    if ( '' === $raw || current_user_can( 'manage_options' ) ) {
      return $raw;
    }

    $data = json_decode( $raw, true );
    if ( ! is_array( $data ) ) {
      return $raw;
    }

    return (string) wp_json_encode( self::redact( $data ) );
  }


  private static function redact( array $data ): array {
    foreach ( $data as $key => $value ) {
      $name = is_string( $key ) ? strtolower( $key ) : '';

      if ( in_array( $name, self::REDACTED_KEYS, true ) ) {
        unset( $data[ $key ] );
      } elseif ( is_array( $value ) ) {
        $data[ $key ] = self::redact( $value );
      } elseif ( is_string( $value ) && 'url' === substr( $name, -3 ) ) {
        $data[ $key ] = substr( $value, 0, strcspn( $value, '?#' ) );
      }
    }

    return $data;
  }


  private function collectEntries(): array {
    if ( ! class_exists( ATELogStorage::class ) ) {
      return array();
    }
    $collection = ATELogStorage::getAll();
    return $collection->toArray();
  }


}
