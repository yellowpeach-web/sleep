<?php

namespace WPML\UserInterface\Web\Infrastructure\WordPress\Component\Translations;

use WPML\UserInterface\Web\Core\SharedKernel\Config\PageRenderInterface;
use WPML\UserInterface\Web\Infrastructure\WordPress\Component\Ate\AmsWidgetEmbed;

class GlossaryTabBody implements PageRenderInterface {

  const TAB = 'glossary';
  const ROUTE = 'glossary';


  public function __construct() {
    $tab = isset( $_GET['tab'] ) && is_string( $_GET['tab'] ) ? $_GET['tab'] : '';
    if ( $tab === self::TAB ) {
      add_action( 'admin_enqueue_scripts', array( AmsWidgetEmbed::class, 'enqueue' ) );
    }
  }


  public function render(): void {
    echo '<style>wpml-ams-widget[route="' . esc_attr( self::ROUTE )
      . '"] .wpml-glossary_index__GlossarySection > h2{display:none!important;}</style>';

    AmsWidgetEmbed::renderWithLoadingReveal(
      'wpml-glossary-content',
      'wpml-ams-widget[route="' . self::ROUTE . '"]',
      /* translators: Shown on WPML → Translations while the glossary is still being fetched. */
      __( 'Loading glossary…', 'wpml' ),
      static function () {
        AmsWidgetEmbed::renderElement( self::ROUTE, AmsWidgetEmbed::currentUserAttributes() );
      },
      40,
      true
    );
  }


}
