<?php

namespace WPML\Legacy\Component\Post\Application\Query;

class PostTypeObjectCache {

  private $objects = [];


  public function get( string $postType ) {
    return $this->objects[ $postType ] ?? null;
  }


  public function set( string $postType, $postTypeObject ) {
    $this->objects[ $postType ] = $postTypeObject;
  }


}
