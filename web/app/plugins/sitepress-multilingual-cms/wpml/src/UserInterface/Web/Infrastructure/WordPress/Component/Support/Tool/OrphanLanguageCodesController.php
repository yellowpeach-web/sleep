<?php

namespace WPML\UserInterface\Web\Infrastructure\WordPress\Component\Support\Tool;

use WPML\Core\Component\LanguageEditor\Domain\CustomLanguageFormat;
use WPML\UserInterface\Web\Core\SharedKernel\Config\PageRenderInterface;


class OrphanLanguageCodesController implements PageRenderInterface {

  const TOOL_SLUG = 'orphan-language-codes';


  public static function isApplicable(): bool {
      return self::report() !== [];
  }


  public function render() {
      $report     = self::report();
      $scanError  = self::scanError();
      $emptyCodes = self::hasEmptyCode();

      $this->renderHeading( $report !== [] );

    if ( $scanError !== null ) {
        $this->renderScanFailure( $scanError );
    }

    if ( $report !== [] ) {
        $this->renderList( $report );
        $this->renderWhatToDo( $report );
    } elseif ( $scanError === null ) {
        $this->renderCleanState( $emptyCodes );
    }

    if ( $emptyCodes ) {
        $this->renderEmptyCodeNote();
    }
  }


  private function renderList( array $rows ): void {
    ?>
      <section id="orphan-codes-list" class="wpml:bg-white wpml:border wpml:border-gray-200 wpml:rounded-md wpml:p-5 wpml:mb-5 wpml:scroll-mt-8">
        <h2 class="wpml:text-sm wpml:font-semibold wpml:text-gray-900 wpml:mb-1">
          <?php esc_html_e( 'Unknown language codes found', 'wpml' ); ?>
        </h2>
        <p class="wpml:text-xs wpml:text-gray-500 wpml:mb-3">
          <?php
          echo esc_html(
            sprintf(
                  /* translators: %d: number of language codes with no matching language. */
              _n(
                '%d language code in your database has no matching language on this site.',
                '%d language codes in your database have no matching language on this site.',
                count( $rows ),
                'wpml'
              ),
              count( $rows )
            )
          );
          ?>
        </p>
        <ul class="wpml:text-sm wpml:text-gray-700 wpml:mb-0 wpml:space-y-1">
          <?php foreach ( $rows as $row ) : ?>
            <li>
              <code><?php echo esc_html( $row['code'] ); ?></code> &mdash;
              <?php echo esc_html( self::summarize( $row ) ); ?>
            </li>
          <?php endforeach; ?>
        </ul>
      </section>
      <?php
  }


  private function renderWhatToDo( array $rows ): void {
      $blocked = array_values(
        array_filter(
          $rows,
          function ( array $row ) {
              return ! self::canBeDefined( $row );
          }
        )
      );

      $addable = count( $rows ) - count( $blocked );

      $hasListedContent = false;
      $hasStringsOnly   = false;
    foreach ( $rows as $row ) {
      if ( self::canBeDefined( $row ) && $row['total'] > 0 ) {
          $hasListedContent = true;
      }
      if ( self::canBeDefined( $row ) && ( $row['stringOnly'] || $row['sourceOnly'] ) ) {
          $hasStringsOnly = true;
      }
    }
    ?>
      <section id="orphan-codes-what-to-do" class="wpml:bg-white wpml:border wpml:border-gray-200 wpml:rounded-md wpml:p-5 wpml:mb-5 wpml:scroll-mt-8">
        <h2 class="wpml:text-sm wpml:font-semibold wpml:text-gray-900 wpml:mb-1">
          <?php esc_html_e( 'What you can do', 'wpml' ); ?>
        </h2>
        <?php if ( $addable > 0 ) : ?>
          <p class="wpml:text-sm wpml:text-gray-700 wpml:mb-0">
            <?php
            printf(
              /* translators: Instruction on WPML → Support. %1$s: opening link tag, %2$s: closing link tag; together they turn "Settings → Languages" into a link. */
              esc_html__(
                'Add the language back on the %1$sSettings → Languages%2$s page, using the code shown above in lower case.',
                'wpml'
              ),
              '<a href="' . esc_url( self::languagesPageUrl() ) . '">',
              '</a>'
            );
            ?>
            <?php if ( $hasListedContent ) : ?>
              <?php esc_html_e( 'Posts, pages and terms in that language are then listed and translatable again.', 'wpml' ); ?>
            <?php endif; ?>
          </p>
          <?php if ( $hasStringsOnly ) : ?>
            <p class="wpml:text-sm wpml:text-gray-700 wpml:mt-3 wpml:mb-0">
              <?php esc_html_e( 'Content that exists only as string or package translations is not brought back this way — it stays outside the string translation screens even once the language exists. Contact WPML support to have that content reassigned.', 'wpml' ); ?>
            </p>
          <?php endif; ?>
        <?php endif; ?>
        <p class="wpml:text-sm wpml:text-gray-700 wpml:mt-3 wpml:mb-0">
          <?php esc_html_e( 'If you no longer want a language listed here, contact WPML support to have its content reassigned.', 'wpml' ); ?>
        </p>
        <?php if ( $blocked !== [] ) : ?>
          <p class="wpml:text-sm wpml:text-gray-700 wpml:mt-3 wpml:mb-2">
            <?php
            echo esc_html(
              /* translators: Message on WPML → Support about language codes that cannot be restored. "This code" is the code listed below it. Singular and plural forms; the sentence ends with a colon because that list follows. */
              _n(
                'This code cannot be added back as a language: a language code is at most seven characters and can only contain letters, numbers, hyphens and underscores. Its content can only be reassigned to an existing language:',
                'These codes cannot be added back as languages: a language code is at most seven characters and can only contain letters, numbers, hyphens and underscores. Their content can only be reassigned to an existing language:',
                count( $blocked ),
                'wpml'
              )
            );
            ?>
          </p>
          <ul class="wpml:text-sm wpml:text-gray-700 wpml:mb-0 wpml:space-y-1">
            <?php foreach ( $blocked as $row ) : ?>
              <li><code><?php echo esc_html( $row['code'] ); ?></code></li>
            <?php endforeach; ?>
          </ul>
        <?php endif; ?>
      </section>
      <?php
  }


  private static function canBeDefined( array $row ): bool {
    if ( empty( $row['synthesizable'] ) ) {
        return false;
    }

    return 1 === preg_match( CustomLanguageFormat::DISPLAY_CODE_FORMAT, $row['code'] );
  }


  private static function languagesPageUrl(): string {
    return admin_url( 'admin.php?page=tm/menu/settings&section=languages' );
  }


  private function renderScanFailure( string $error ): void {
    ?>
      <section id="orphan-codes-scan-failed" class="wpml:bg-white wpml:border wpml:border-gray-200 wpml:rounded-md wpml:p-5 wpml:mb-5 wpml:scroll-mt-8">
        <h2 class="wpml:text-sm wpml:font-semibold wpml:text-gray-900 wpml:mb-1">
          <?php esc_html_e( 'The check could not finish', 'wpml' ); ?>
        </h2>
        <p class="wpml:text-sm wpml:text-gray-700 wpml:mb-0">
          <?php
          echo esc_html(
            sprintf(
                  /* translators: %s: the database error reported by the failed query. */
              __( 'WPML could not scan for unknown language codes, so this page may be incomplete. The database reported: %s', 'wpml' ),
              $error
            )
          );
          ?>
        </p>
      </section>
      <?php
  }


  private function renderEmptyCodeNote(): void {
    ?>
      <section id="orphan-codes-empty" class="wpml:bg-white wpml:border wpml:border-gray-200 wpml:rounded-md wpml:p-5 wpml:mb-5 wpml:scroll-mt-8">
        <p class="wpml:text-sm wpml:text-gray-700 wpml:mb-0">
          <?php /* translators: Message on WPML → Support. "Those" are the records that carry an empty language code. */ esc_html_e( 'Some translation records carry an empty language code. Those are broken records, not a language — no language can be added back for them; the Cleanup tools in Support cover them.', 'wpml' ); ?>
        </p>
      </section>
      <?php
  }


  private function renderCleanState( bool $emptyCodes ): void {
    ?>
      <section class="wpml:bg-white wpml:border wpml:border-gray-200 wpml:rounded-md wpml:p-5 wpml:mb-5">
        <p class="wpml:text-sm wpml:text-gray-700 wpml:mb-0">
          <?php esc_html_e( 'Every language code your content uses is defined on this site.', 'wpml' ); ?>
          <?php if ( ! $emptyCodes ) : ?>
            <?php esc_html_e( 'There is nothing to report.', 'wpml' ); ?>
          <?php endif; ?>
        </p>
      </section>
      <?php
  }


  private function renderHeading( bool $found ): void {
    ?>
      <h1 class="wpml:text-2xl wpml:font-semibold wpml:text-gray-900 wpml:mb-1">
        <?php esc_html_e( 'Content from unknown languages', 'wpml' ); ?>
      </h1>
      <p class="wpml:text-sm wpml:text-gray-500 wpml:mb-6">
        <?php if ( $found ) : ?>
          <?php esc_html_e( 'Some content is assigned to language codes this site does not define. WPML cannot display or manage that content until the language is defined again.', 'wpml' ); ?>
        <?php else : ?>
          <?php esc_html_e( 'This tool finds content assigned to language codes this site does not define — content WPML cannot display or manage until the language is defined again.', 'wpml' ); ?>
        <?php endif; ?>
      </p>
      <?php
  }


  private static function summarize( array $row ): string {
      $parts = [];
      $other = 0;

    foreach ( $row['types'] as $type ) {
      if ( $type['kind'] === 'other' ) {
          $other += $type['count'];
          continue;
      }
        /* translators: One entry in a comma-separated list on WPML → Support, such as "3 Posts". %1$d: how many items there were found, %2$s: the name of the kind of content, already in the plural. */
        $parts[] = sprintf( __( '%1$d %2$s', 'wpml' ), $type['count'], $type['label'] );
    }

    if ( $other > 0 ) {
        $parts[] = sprintf(
          /* translators: %d: number of database rows whose content type is not registered. */
          _n( '%d other database row', '%d other database rows', $other, 'wpml' ),
          $other
        );
    }

    if ( $row['strings'] > 0 ) {
        /* translators: %d: number of string translations. */
        $parts[] = sprintf( __( 'String translations: %d', 'wpml' ), $row['strings'] );
    }

    if ( $row['sourceOnly'] ) {
        /* translators: One item of a comma-separated list on WPML → Support describing what was found for a language code. Lower case because it sits inside that list. */
        $parts[] = __( 'referenced only as a source language', 'wpml' );
    }

    if ( $parts === [] ) {
        /* translators: Shown on WPML → Support in place of that list when nothing was found for a language code. Lower case because it sits where the list would be. */
        return __( 'no content found', 'wpml' );
    }

      return implode( ', ', $parts );
  }


  private static function report(): array {
    if ( ! class_exists( '\WPML\Troubleshooting\OrphanLanguageCodes' ) ) {
        return [];
    }

      return \WPML\Troubleshooting\OrphanLanguageCodes::report();
  }


  private static function scanError(): ?string {
    if ( ! class_exists( '\WPML\Troubleshooting\OrphanLanguageCodes' ) ) {
        return null;
    }

      return \WPML\Troubleshooting\OrphanLanguageCodes::lastScanError();
  }


  private static function hasEmptyCode(): bool {
    if ( ! class_exists( '\WPML\Troubleshooting\OrphanLanguageCodes' ) ) {
        return false;
    }

      return \WPML\Troubleshooting\OrphanLanguageCodes::hasEmptyCode();
  }


}
