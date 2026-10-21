<?php

namespace WPML\Core\Component\Translation\Application\Repository;

interface TranslateEverythingCompletedMapsInterface {

  const POSTS    = 'translate-everything-posts';
  const PACKAGES = 'translate-everything-packages';


  public function removeTypes( string $map, array $types );


}
