<?php

namespace WPML\UserInterface\Web\Infrastructure\WordPress\Component\Wcml;

class WcmlEmbed {


  public static function embed( string $key ): void {
    $filtered = apply_filters( 'wpml_render_embedded_wcml_body', '', $key );
    $body     = is_string( $filtered ) ? $filtered : '';

    if ( $body !== '' ) {
      echo $body;
      return;
    }

    echo '<div class="notice notice-warning"><p>';
    echo esc_html__(
      'WPML Multilingual & Multicurrency for WooCommerce (WCML) is not active. Activate WCML to use this page.',
      'wpml'
    );
    echo '</p></div>';
  }


}
