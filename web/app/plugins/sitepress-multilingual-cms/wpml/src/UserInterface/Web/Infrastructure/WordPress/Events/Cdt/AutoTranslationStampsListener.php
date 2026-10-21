<?php

namespace WPML\UserInterface\Web\Infrastructure\WordPress\Events\Cdt;

use WPML\Core\Component\Cdt\Application\Service\AutoTranslationStamps;

class AutoTranslationStampsListener {

  private $stamps;


  public function __construct( AutoTranslationStamps $stamps ) {
    $this->stamps = $stamps;
  }


  public function onTranslationCompleted( $job ): void {
    if ( is_object( $job ) ) {
      $job = get_object_vars( $job );
    }
    if ( ! is_array( $job ) ) {
      return;
    }

    $editor = isset( $job['editor'] ) && is_scalar( $job['editor'] ) ? (string) $job['editor'] : '';

    $this->stamps->onJobCompleted( ! empty( $job['automatic'] ), $editor, time() );
  }


}
