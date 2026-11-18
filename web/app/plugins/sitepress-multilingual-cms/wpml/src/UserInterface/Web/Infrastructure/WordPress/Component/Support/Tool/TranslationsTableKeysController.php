<?php

namespace WPML\UserInterface\Web\Infrastructure\WordPress\Component\Support\Tool;

use WPML\UserInterface\Web\Core\SharedKernel\Config\PageRenderInterface;


class TranslationsTableKeysController implements PageRenderInterface {

  const TOOL_SLUG = 'translations-table-keys';


  public static function isApplicable(): bool {
      $engine = self::engine();

      return $engine !== null && $engine->needsRepair();
  }


  public function render() {
      $engine = self::engine();
      $plan   = $engine === null ? null : $engine->plan();
    ?>
      <h1 class="wpml:text-2xl wpml:font-semibold wpml:text-gray-900 wpml:mb-1">
        <?php echo esc_html( self::title() ); ?>
      </h1>
      <?php if ( $plan === null || $plan['refusal'] === 'already-right' ) : ?>
        <p class="wpml:text-sm wpml:text-gray-700 wpml:mb-6">
          <?php echo esc_html( self::refusalMessage( 'already-right', '', '' ) ); ?>
        </p>
        <?php
          return;
      endif;
    ?>
      <p class="wpml:text-sm wpml:text-gray-500 wpml:mb-6">
        <?php echo esc_html( self::intro( $plan['missing'] ) ); ?>
      </p>
      <section id="translations-table-keys-plan" class="wpml:bg-white wpml:border wpml:border-gray-200 wpml:rounded-md wpml:p-5 wpml:mb-5">
        <ul class="wpml:text-sm wpml:text-gray-700 wpml:mb-4 wpml:space-y-1">
          <?php foreach ( self::planLines( $plan ) as $line ) : ?>
            <li><?php echo esc_html( $line ); ?></li>
          <?php endforeach; ?>
        </ul>
        <?php if ( $plan['refusal'] !== null ) : ?>
          <p class="wpml:text-sm wpml:text-gray-700 wpml:mb-0" id="translations-table-keys-refusal">
            <?php echo esc_html( self::refusalMessage( $plan['refusal'], '', '' ) ); ?>
          </p>
        <?php else : ?>
          <button type="button" id="wpml-translations-table-keys-repair"
            data-rest-url="<?php echo esc_attr( esc_url_raw( rest_url( 'wpml/v1/troubleshooting/translations-table-keys/repair' ) ) ); ?>"
            data-rest-nonce="<?php echo esc_attr( wp_create_nonce( 'wp_rest' ) ); ?>"
            class="wpml-button base-btn wpml-button--outlined wpml:text-sm wpml:disabled:opacity-50 wpml:disabled:cursor-not-allowed">
            <?php echo esc_html( self::title() ); ?>
          </button>
          <p id="wpml-translations-table-keys-status" class="wpml:text-sm wpml:text-gray-700 wpml:mt-3 wpml:mb-0" aria-live="polite"></p>
        <?php endif; ?>
      </section>
      <script>
      (function () {
        var btn = document.getElementById('wpml-translations-table-keys-repair');
        if (!btn) { return; }
        var status = document.getElementById('wpml-translations-table-keys-status');
        var i18n = {
          running: '<?php echo esc_js( /* translators: Status shown on WPML → Support while a repair runs. */ __( 'Repairing…', 'wpml' ) ); ?>',
          error:   '<?php echo esc_js( /* translators: Status shown under the repair button on WPML → Support when the request failed before the repair could answer. */ __( 'The repair could not run. Reload the page and try again.', 'wpml' ) ); ?>'
        };
        btn.addEventListener('click', function () {
          btn.disabled = true;
          status.textContent = i18n.running;
          fetch(btn.getAttribute('data-rest-url'), {
            method: 'POST',
            credentials: 'same-origin',
            headers: { 'Content-Type': 'application/json', 'X-WP-Nonce': btn.getAttribute('data-rest-nonce') }
          }).then(function (r) {
            if (!r.ok) { throw new Error('http ' + r.status); }
            return r.json();
          }).then(function (json) {
            var data = json && json.data ? json.data : json;
            if (!data || typeof data.message !== 'string') { throw new Error('no answer'); }
            status.textContent = data.message;
            if (data.done) {
              // The dry read described the table before the repair; the answer replaces it.
              var plan = document.querySelector('#translations-table-keys-plan ul');
              if (plan) { plan.parentNode.removeChild(plan); }
              btn.parentNode.removeChild(btn);
            } else {
              btn.disabled = false;
            }
          }).catch(function () {
            status.textContent = i18n.error;
            btn.disabled = false;
          });
        });
      })();
      </script>
      <?php
  }


  public static function title(): string {
      /* translators: Name of a Support tool on WPML → Support, the heading of its screen and the button that runs it. "The translations table" is the database table where WPML records which content is a translation of which. Verb phrase, imperative. */
      return __( 'Repair the translations table', 'wpml' );
  }


  public static function intro( array $missing ): string {
    if ( $missing === [] ) {
        /* translators: On WPML → Support, when rows of the translations table have no ID but the table has its keys. */
        return __( 'Some rows of the table that links your content to its translations have no ID, so WPML cannot use them. WPML saves a copy of the table, then gives those rows an ID.', 'wpml' );
    }

      return __( 'The table that links your content to its translations lost its keys. WPML saves a copy of the table, then restores the keys and gives an ID to the rows that lost theirs.', 'wpml' );
  }


  public static function planLines( array $plan ): array {
      $lines = [
        sprintf(
          /* translators: Line of the dry read on WPML → Support. %1$d: rows of the translations table whose ID is 0, %2$d: the records they become after the repair (the plural follows this number). */
          _n( 'Rows without an ID: %1$d. They become %2$d record.', 'Rows without an ID: %1$d. They become %2$d records.', $plan['renumbered'], 'wpml' ),
          $plan['zero'],
          $plan['renumbered']
        ),
        sprintf(
          /* translators: Line of the dry read on WPML → Support. %d: rows that repeat another row and are removed by the repair. */
          __( 'Duplicate rows to remove: %d.', 'wpml' ),
          $plan['removed']
        ),
        sprintf(
          /* translators: Line of the dry read on WPML → Support. %d: how many keys and indexes of the table the repair adds back. */
          __( 'Keys to add: %d.', 'wpml' ),
          count( $plan['missing'] )
        ),
      ];

    if ( $plan['orphanStatuses'] > 0 ) {
        $lines[] = sprintf(
          /* translators: Line of the dry read on WPML → Support. %d: translation statuses that name a row with ID 0, which no repaired row can be matched to. */
          __( 'Translation statuses that point to no row: %d. They are left as they are.', 'wpml' ),
          $plan['orphanStatuses']
        );
    }

      return $lines;
  }


  public static function resultMessage( array $result ): string {
    if ( $result['done'] ) {
        return sprintf(
          /* translators: Result of the repair on WPML → Support. %1$d: rows given an ID, %2$d: duplicate rows removed, %3$d: keys and indexes added, %4$s: the name of the database table that holds the copy made before the repair. */
          __( 'Done. Rows given an ID: %1$d. Duplicate rows removed: %2$d. Keys added: %3$d. A copy of the table from before the repair is saved as %4$s.', 'wpml' ),
          $result['renumbered'],
          $result['removed'],
          $result['keysAdded'],
          $result['backup']
        );
    }

      return self::refusalMessage( (string) $result['refusal'], $result['error'], $result['backup'] );
  }


  public static function refusalMessage( string $refusal, string $error, string $backup ): string {
    switch ( $refusal ) {
      case 'already-right':
          /* translators: On WPML → Support, when the translations table has its keys. */
          return __( 'The translations table has all its keys. There is nothing to repair.', 'wpml' );
      case 'collision':
      case 'conflict':
          /* translators: On WPML → Support, when the repair refuses to run because rows of the translations table cannot be told apart safely. */
          return __( 'Some rows of the translations table cannot be told apart safely, so nothing was changed. Contact WPML support.', 'wpml' );
      case 'backup':
          return sprintf(
            /* translators: On WPML → Support, when the repair could not save a copy of the table first. %s: the error the database reported. */
            __( 'WPML could not save a copy of the table, so nothing was changed. The database reported: %s', 'wpml' ),
            $error
          );
      default:
          return sprintf(
            /* translators: On WPML → Support, when the repair stopped after it saved its copy. %1$s: the error the database reported, %2$s: the name of the database table that holds the copy made before the repair. */
            __( 'The repair stopped. The database reported: %1$s. The table from before the repair is saved as %2$s.', 'wpml' ),
            $error,
            $backup
          );
    }
  }


  public static function engine(): ?\WPML\Troubleshooting\TranslationsTableKeys {
    if ( ! class_exists( \WPML\Troubleshooting\TranslationsTableKeys::class ) ) {
        return null;
    }

      global $wpdb;

      return new \WPML\Troubleshooting\TranslationsTableKeys( $wpdb );
  }


}
