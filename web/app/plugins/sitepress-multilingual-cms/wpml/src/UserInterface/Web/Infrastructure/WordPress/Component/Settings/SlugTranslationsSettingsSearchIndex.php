<?php

namespace WPML\UserInterface\Web\Infrastructure\WordPress\Component\Settings;

class SlugTranslationsSettingsSearchIndex implements \IWPML_Backend_Action {

  private $rendersSection;


  public function __construct( $rendersSection = null ) {
    $this->rendersSection = $rendersSection ?: array( UrlsAndSeoController::class, 'rendersSlugTranslationsSection' );
  }


  public function add_hooks() {
    add_filter( 'wpml_settings_search_index', array( $this, 'removeSubs' ) );
  }


  public function removeSubs( $index ) {
    if ( ! is_array( $index ) || call_user_func( $this->rendersSection ) ) {
      return $index;
    }

    foreach ( $index as $i => $section ) {
      if ( ! is_array( $section ) || ! isset( $section['subs'] ) || ! is_array( $section['subs'] ) ) {
        continue;
      }

      $subs = array_values(
        array_filter(
          $section['subs'],
          static function ( $sub ) {
            return ! (
              is_array( $sub )
              && isset( $sub['anchor'] )
              && UrlsAndSeoController::SLUG_TRANSLATIONS_SECTION_ID === $sub['anchor']
              && ! isset( $sub['href'] )
            );
          }
        )
      );

      if ( count( $subs ) !== count( $section['subs'] ) ) {
        $index[ $i ]['subs'] = $subs;
      }
    }

    return $index;
  }
}
