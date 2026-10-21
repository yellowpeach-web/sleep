<?php

namespace WPML\UserInterface\Web\Core\Component\Notices\TeaUpgrade\Application;

use WPML\Core\Port\Persistence\OptionsInterface;

class NoticeInstanceIdProvider {

  const OPTION_NAME = 'wpml_tea_upgrade_notice_instance_id';

  private $options;


  public function __construct( OptionsInterface $options ) {
    $this->options = $options;
  }


  public function get(): string {
    $existing = $this->options->get( self::OPTION_NAME, '' );
    if ( is_string( $existing ) && $existing !== '' ) {
      return $existing;
    }

    $uuid = $this->generateUuid();
    $this->options->add( self::OPTION_NAME, $uuid, false );

    $stored = $this->options->get( self::OPTION_NAME, $uuid );

    return is_string( $stored ) && $stored !== '' ? $stored : $uuid;
  }


  protected function generateUuid(): string {
    return wp_generate_uuid4();
  }


}
