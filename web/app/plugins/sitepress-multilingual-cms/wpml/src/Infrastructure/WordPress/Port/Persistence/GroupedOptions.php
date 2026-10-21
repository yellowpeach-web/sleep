<?php

namespace WPML\Infrastructure\WordPress\Port\Persistence;

use WPML\Core\Port\Persistence\GroupedOptionsInterface;
use WPML\WP\OptionManager;

class GroupedOptions implements GroupedOptionsInterface {

  private $optionManager;


  public function __construct( OptionManager $optionManager ) {
    $this->optionManager = $optionManager;
  }


  public function get( string $group, string $key, $defaultValue = false ) {
    return $this->optionManager->get( $group, $key, $defaultValue );
  }


  public function save( string $group, string $key, $value, bool $autoload = true ) {
    $this->optionManager->set( $group, $key, $value, $autoload );
  }


}
