<?php

namespace WPML\UserInterface\Web\Infrastructure\WordPress\Component\Settings;

class SettingsPageChrome {


  public static function printTitle( $title, $subtitle, $subtitleHtml = null ) {
    echo '<h1 class="wpml:text-2xl wpml:font-semibold wpml:text-gray-900 wpml:mb-1">'
      . esc_html( $title )
      . '</h1>';
    echo '<p class="wpml:text-sm wpml:text-gray-500 wpml:mb-6" style="margin-top:0">'
      . ( null === $subtitleHtml
        ? esc_html( $subtitle )
        : wp_kses( $subtitleHtml, array( 'a' => array( 'href' => array() ) ) ) )
      . '</p>';
  }


}
