<?php

namespace WPML\UserInterface\Web\Infrastructure\WordPress\Component\Translations;

use WPML\UserInterface\Web\Core\SharedKernel\Config\PageRenderInterface;

class PlaceholderTabBody implements PageRenderInterface {

  private $tabLabel;

  private $milestone;

  private $context;


  public function __construct( string $tabLabel, string $milestone = '', string $context = '' ) {
    $this->tabLabel  = $tabLabel;
    $this->milestone = $milestone;
    $this->context   = $context;
  }


  public function render() {
    echo '<div class="notice notice-info wpml:inline" style="margin:0 0 1em 0;">';
    echo '<p><strong>';
    if ( $this->milestone !== '' ) {
      printf(
        /* translators: Message shown on a tab of WPML → Translations that is not built yet. %1$s: the name of the tab, %2$s: the name of the release it arrives in. */
        esc_html__( 'The %1$s tab is implemented in %2$s.', 'wpml' ),
        esc_html( $this->tabLabel ),
        esc_html( $this->milestone )
      );
    } else {
      printf(
        /* translators: %s is the tab name (e.g. "Strings"). */
        esc_html__( 'The %s tab is not implemented yet.', 'wpml' ),
        esc_html( $this->tabLabel )
      );
    }
    echo '</strong>';
    if ( $this->context !== '' ) {
      echo ' ' . esc_html( $this->context );
    }
    echo '</p></div>';
  }


}
