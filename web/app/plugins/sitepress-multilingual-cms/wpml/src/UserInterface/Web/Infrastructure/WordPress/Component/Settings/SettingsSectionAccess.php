<?php

namespace WPML\UserInterface\Web\Infrastructure\WordPress\Component\Settings;

/**
 * Who may open which section of WPML → Settings (wpmldev-12323).
 *
 * Each row of the Settings index (Core's `SearchIndex` plus the
 * `wpml_settings_search_index` filter) may carry:
 *
 * - `capability`: the user needs it to open the section. When it is
 *   missing, the section needs `manage_translations`.
 * - `standalone_capability`: a user who does not have the Settings page
 *   capability may still open this section, when it has this capability
 *   and the row `capability`. A glue plugin sets it on its own row, the
 *   way WCML opens Multicurrency to a shop manager.
 *
 * For a user without the Settings page capability, the Settings menu
 * item takes the first `standalone_capability` that the user has, and the
 * landing lists only those sections. This is the pattern of the
 * Translations menu for translators: the page capability is one the user
 * has, and no role or stored capability changes. A user who has the page
 * capability sees no change.
 */
class SettingsSectionAccess {

  const STANDALONE_CAPABILITY = 'standalone_capability';

  const PAGE_SLUG = 'tm/menu/settings';

  const PAGE_CAPABILITIES = array(
    WPML_CAP_MANAGE_OPTIONS,
    WPML_CAP_MANAGE_TRANSLATIONS,
  );


  public static function canOpenPage(): bool {
    foreach ( self::PAGE_CAPABILITIES as $capability ) {
      if ( current_user_can( $capability ) ) {
        return true;
      }
    }

    return false;
  }


  public static function standaloneCapability( array $index ): string {
    foreach ( $index as $row ) {
      if ( is_array( $row ) && self::isOpen( $row, false ) ) {
        return self::standaloneCapabilityOf( $row );
      }
    }

    return '';
  }


  public static function index( array $coreIndex ): array {
    $index = apply_filters( 'wpml_settings_search_index', $coreIndex );

    return self::rows( $index );
  }


  private static function rows( $index ): array {
    return is_array( $index ) ? array_values( $index ) : array();
  }


  public static function filterIndex( array $index, bool $canOpenPage ): array {
    return array_values(
      array_filter(
        $index,
        static function ( $row ) use ( $canOpenPage ) {
          return is_array( $row ) && self::isOpen( $row, $canOpenPage );
        }
      )
    );
  }


  public static function sectionSlugs( array $index ): array {
    $slugs = array();
    foreach ( $index as $row ) {
      $slug = is_array( $row ) ? self::sectionSlugOf( $row ) : '';
      if ( $slug !== '' ) {
        $slugs[] = $slug;
      }
    }

    return $slugs;
  }


  public static function sectionSlugOf( array $row ): string {
    if ( ! isset( $row['href'] ) || ! is_string( $row['href'] ) ) {
      return '';
    }
    $args = array();
    parse_str( (string) wp_parse_url( $row['href'], PHP_URL_QUERY ), $args );

    return isset( $args['section'] ) && is_string( $args['section'] ) ? $args['section'] : '';
  }


  public static function isOpen( array $row, bool $canOpenPage ): bool {
    $capability = isset( $row['capability'] ) && is_string( $row['capability'] ) && $row['capability'] !== ''
      ? $row['capability']
      : WPML_CAP_MANAGE_TRANSLATIONS;

    if ( ! current_user_can( $capability ) ) {
      return false;
    }

    if ( $canOpenPage ) {
      return true;
    }

    $standalone = self::standaloneCapabilityOf( $row );

    return $standalone !== '' && current_user_can( $standalone );
  }


  private static function standaloneCapabilityOf( array $row ): string {
    $capability = $row[ self::STANDALONE_CAPABILITY ] ?? '';

    return is_string( $capability ) ? $capability : '';
  }


}
