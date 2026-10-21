<?php

namespace WPML\UserInterface\Web\Infrastructure\WordPress\Component\Translations;

use WPML\UserInterface\Web\Core\SharedKernel\Config\PageRenderInterface;
use WPML\UserInterface\Web\Infrastructure\WordPress\Component\Ate\AmsWidgetEmbed;
use WPML\UserInterface\Web\Infrastructure\WordPress\Component\LanguageEditor\LanguageEditorEmbed;

class ImproveTranslationsTabBody implements PageRenderInterface {

  const TAB = 'improve-translations';
  const ROUTE = 'translation-improvements';


  public function __construct() {
    $tab = isset( $_GET['tab'] ) && is_string( $_GET['tab'] ) ? $_GET['tab'] : '';
    if ( $tab === self::TAB ) {
      add_action( 'admin_enqueue_scripts', array( AmsWidgetEmbed::class, 'enqueue' ) );
    }
  }


  public function render(): void {
    AmsWidgetEmbed::renderWithLoadingReveal(
      'wpml-improve-translations-content',
      'wpml-ams-widget[route="' . self::ROUTE . '"]',
      __( 'Loading translation improvements…', 'wpml' ),
      static function () {
        AmsWidgetEmbed::renderElement( self::ROUTE, AmsWidgetEmbed::currentUserAttributes() );
      },
      5,
      true
    );

    LanguageEditorEmbed::renderModalHost();
  }


}
