<?php

namespace WPML\Core\Port\Persistence;

interface GroupedOptionsInterface {


  public function get( string $group, string $key, $defaultValue = false );


  public function save( string $group, string $key, $value, bool $autoload = true );


}
