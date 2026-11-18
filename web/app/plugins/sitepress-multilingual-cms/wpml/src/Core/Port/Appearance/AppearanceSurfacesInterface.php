<?php

namespace WPML\Core\Port\Appearance;

interface AppearanceSurfacesInterface {

  public function surfaces(): array;


  public function setSurfaces( array $switches ): void;


}
