<?php

namespace WPML\UserInterface\Web\Infrastructure\WordPress\Component\Ate;

class AteAutoRegister {


  const THROTTLE_KEY = 'wpml_ate_autoreg_attempt';


  public static function attempt() {
    if ( ! \WPML_TM_ATE_Status::is_enabled() || \WPML_TM_ATE_Status::is_active() ) {
      return;
    }

    if ( ! current_user_can( 'manage_options' ) ) {
      return;
    }

    if ( get_transient( self::THROTTLE_KEY ) ) {
      return;
    }

    $sitekeyProvider = \WPML\Container\make( '\WPML\TM\ATE\Sitekey\SitekeyProvider' );
    if ( ! $sitekeyProvider->hasSitekey() ) {
      return;
    }

    set_transient( self::THROTTLE_KEY, 1, 5 * MINUTE_IN_SECONDS );

    try {
      \WPML\Container\make( \WPML\TM\ATE\AutoTranslate\Endpoint\EnableATE::class )
        ->enable();
    } catch ( \Throwable $e ) {
      unset( $e );
    }
  }


}
