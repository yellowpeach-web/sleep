<?php

namespace WPML\UserInterface\Web\Infrastructure\WordPress\Component\Support\Tool;

use WPML\UserInterface\Web\Core\SharedKernel\Config\PageRenderInterface;
use WPML_TranslationProxy_Communication_Log;


class CommunicationLogController implements PageRenderInterface {


  public function render() {
    $entries = $this->collectEntries();
    $count   = count( $entries );
    ?>
      <h1 class="wpml:text-2xl wpml:font-semibold wpml:text-gray-900 wpml:mb-1">
        <?php /* translators: Name of a Support tool: its entry in the tool list on WPML → Support and the heading of its own screen. It records the traffic between the site and the translation services. */ esc_html_e( 'Communication log', 'wpml' ); ?>
      </h1>
      <p class="wpml:text-sm wpml:text-gray-500 wpml:mb-6">
        <?php
        echo \wpml_bold_names(
          __( 'Every call WPML makes to its translation services — ICanLocalize, the <b>Advanced Translation Editor</b> server, and the installer — is recorded here with its response. Share it with support when a translation job is stuck, your balance of translation words looks wrong, or automatic translation stops working. No content, passwords, or personal data is logged; only API endpoints, parameters, and status codes.', 'wpml' )
        );
        ?>
      </p>

      <section class="wpml:bg-white wpml:border wpml:border-gray-200 wpml:rounded-md wpml:p-5">
        <div class="wpml:flex wpml:items-center wpml:justify-between wpml:mb-3">
          <div class="wpml:flex wpml:items-center wpml:gap-2">
            <span class="wpml:relative wpml:flex wpml:h-2 wpml:w-2">
              <span class="wpml:relative wpml:inline-flex wpml:rounded-full wpml:h-2 wpml:w-2 wpml:bg-gray-400"></span>
            </span>
            <span class="wpml:text-xs wpml:text-gray-600">
              <?php
              printf(
                /* translators: Count above the communication log on WPML → Support. %d: how many entries the log holds. Singular and plural forms. */
                esc_html( _n( '%d entry', '%d entries', $count, 'wpml' ) ),
                $count
              );
              ?>
            </span>
          </div>
          <div class="wpml:flex wpml:items-center wpml:gap-2">
            <button type="button" disabled
              title="<?php esc_attr_e( 'Wires up in a phase-2 follow-up — the legacy clear-log handler has no nonce protection; a wp-ajax wrapper needs to land first.', 'wpml' ); ?>"
              class="wpml-button base-btn wpml-button--outlined wpml:text-sm wpml:disabled:opacity-50 wpml:disabled:cursor-not-allowed">
              <?php /* translators: Button label on WPML → Support that empties the log. Verb phrase, imperative. */ esc_html_e( 'Clear log', 'wpml' ); ?>
            </button>
            <button type="button" disabled
              title="<?php esc_attr_e( 'Wires up in a phase-2 follow-up — same nonce gap as Clear log.', 'wpml' ); ?>"
              class="wpml-button base-btn wpml-button--outlined wpml:text-sm wpml:disabled:opacity-50 wpml:disabled:cursor-not-allowed">
              <?php /* translators: Button label on WPML → Support that switches logging off. Verb phrase, imperative. */ esc_html_e( 'Disable logging', 'wpml' ); ?>
            </button>
          </div>
        </div>

        <pre class="wpml:bg-gray-50 wpml:border wpml:border-gray-200 wpml:rounded wpml:p-3 wpml:text-[11px] wpml:font-mono wpml:text-gray-700 wpml:h-80 wpml:overflow-auto wpml:leading-relaxed"><?php
        if ( $count === 0 ) {
          echo "\n  " . esc_html__( '(empty — no API calls recorded yet)', 'wpml' );
        } else {
          echo esc_html( $this->formatEntries( $entries ) );
        }
        ?></pre>
      </section>
      <?php
  }


  private function collectEntries(): array {
    if ( ! class_exists( WPML_TranslationProxy_Communication_Log::class ) ) {
      return array();
    }

    $sitepress = isset( $GLOBALS['sitepress'] ) ? $GLOBALS['sitepress'] : null;
    if ( ! $sitepress ) {
      return array();
    }

    $log = new WPML_TranslationProxy_Communication_Log( $sitepress );
    return $this->parseLog( $log->get_log() );
  }


  private function parseLog( string $log ): array {
    $entries = array();
    $lines = preg_split( '/\r\n|\r|\n/', trim( $log, "\r\n" ) );
    foreach ( ( false === $lines ? array() : $lines ) as $line ) {
      if ( '' === trim( $line ) ) {
        continue;
      }

      if ( ! preg_match( '/^(.+? [ap]m) - (call|response|error|xml-rpc) - (.*)$/s', $line, $matches ) ) {
        $last = count( $entries ) - 1;
        if ( $last >= 0 ) {
          $body = isset( $entries[ $last ]['args'] ) ? 'args' : 'response';
          $entries[ $last ][ $body ] = ( isset( $entries[ $last ][ $body ] ) ? $entries[ $last ][ $body ] . PHP_EOL : '' ) . $line;
          continue;
        }

        $entries[] = array(
          'timestamp' => '',
          'direction' => 'entry',
          'response'  => $line,
        );
        continue;
      }

      $entry = array(
        'timestamp' => $matches[1],
        'direction' => $matches[2],
      );

      if ( 'call' === $matches[2] && false !== strpos( $matches[3], ' - ' ) ) {
        list( $entry['url'], $entry['args'] ) = explode( ' - ', $matches[3], 2 );
      } else {
        $entry['response'] = $matches[3];
      }

      $entries[] = $entry;
    }

    return $entries;
  }


  private function formatEntries( array $entries ): string {
    $lines = array();
    foreach ( $entries as $entry ) {
      if ( ! is_array( $entry ) ) {
        continue;
      }
      $timestamp = isset( $entry['timestamp'] ) ? (string) $entry['timestamp'] : '';
      $direction = isset( $entry['direction'] ) ? (string) $entry['direction'] : ( isset( $entry['type'] ) ? (string) $entry['type'] : 'entry' );
      $url       = isset( $entry['url'] ) ? (string) $entry['url'] : '';
      $body      = '';
      if ( isset( $entry['response'] ) ) {
        $body = is_string( $entry['response'] ) ? $entry['response'] : (string) wp_json_encode( $entry['response'] );
      } elseif ( isset( $entry['args'] ) ) {
        $body = is_string( $entry['args'] ) ? $entry['args'] : (string) wp_json_encode( $entry['args'] );
      }

      $line = trim( $timestamp . ' · ' . $direction . ( $url ? ' · ' . $url : '' ) . ( $body ? ' · ' . $body : '' ) );
      $lines[] = $line;
    }
    return implode( "\n", $lines );
  }


}
