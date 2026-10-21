<?php

namespace WPML\UserInterface\Web\Infrastructure\WordPress\Component\Capabilities;

class ManageTranslationsImpliesTranslate implements \IWPML_Backend_Action, \IWPML_REST_Action {


  public function add_hooks(): void {
    add_filter( 'user_has_cap', array( $this, 'grantTranslate' ), 10, 4 );
  }


  public function grantTranslate( $allcaps, $caps, $args, $user ) {
    if ( ! in_array( 'translate', $caps, true ) ) {
      return $allcaps;
    }
    if ( ! empty( $allcaps['manage_translations'] ) ) {
      $allcaps['translate'] = true;
    }
    return $allcaps;
  }


}
