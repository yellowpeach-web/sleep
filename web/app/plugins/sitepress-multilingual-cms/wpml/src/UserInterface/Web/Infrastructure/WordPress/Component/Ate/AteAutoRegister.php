<?php

namespace WPML\UserInterface\Web\Infrastructure\WordPress\Component\Ate;

class AteAutoRegister {


  const THROTTLE_KEY = 'wpml_ate_autoreg_attempt';


  public static function attempt() {
    \WPML\TM\ATE\Registration\AutoRegister::attempt( \WPML\TM\ATE\Registration\AutoRegister::TRIGGER_PAGE );
  }


}
