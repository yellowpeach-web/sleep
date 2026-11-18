<?php

namespace WPML\UserInterface\Web\Infrastructure\WordPress\Component\Translations;

use WPML\UserInterface\Web\Core\SharedKernel\Config\PageRenderInterface;

class CorrectionsTabBody implements PageRenderInterface {

  const TAB = 'corrections';

  const FILTER = 'wpml_translations_corrections_tab';

  const BODY_ACTION = 'wpml_translations_corrections_tab_body';


  public static function definition() {
    $answer = apply_filters( self::FILTER, null );

    if ( ! is_array( $answer ) || ! isset( $answer['label'] ) || ! is_string( $answer['label'] ) || '' === $answer['label'] ) {
      return null;
    }

    return array(
      'label'     => $answer['label'],
      'tooltip'   => isset( $answer['tooltip'] ) && is_string( $answer['tooltip'] ) ? $answer['tooltip'] : '',
      'visible'   => ! empty( $answer['visible'] ),
      'reachable' => ! empty( $answer['reachable'] ),
    );
  }


  public function render() {
    do_action( self::BODY_ACTION );
  }


}
