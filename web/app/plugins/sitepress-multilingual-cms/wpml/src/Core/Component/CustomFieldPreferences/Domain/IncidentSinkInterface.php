<?php

namespace WPML\Core\Component\CustomFieldPreferences\Domain;

interface IncidentSinkInterface {


  public function record( string $code, string $type, array $context );


  public function resolve( string $code, string $type );


}
