<?php

namespace WPML\Legacy\Component\Post\Application\LanguagePanel;

use SitePress;
use WPML\Core\Component\Post\Application\LanguagePanel\TranslatableRestPostTypesQueryInterface;

class TranslatableRestPostTypesQuery implements TranslatableRestPostTypesQueryInterface {

  private $sitepress;

  private $names = null;


  public function __construct( SitePress $sitepress ) {
    $this->sitepress = $sitepress;
  }


  public function names(): array {
    if ( null === $this->names ) {
      $this->names = $this->read();
    }

    return $this->names;
  }


  private function read(): array {
    $names = [];
    foreach ( (array) $this->sitepress->get_translatable_documents() as $name => $postType ) {
      $type = is_object( $postType ) ? get_object_vars( $postType ) : [];
      if ( empty( $type['show_in_rest'] ) ) {
        continue;
      }
      $typeName = is_string( $name ) ? $name : ( isset( $type['name'] ) && is_string( $type['name'] ) ? $type['name'] : '' );
      if ( '' !== $typeName ) {
        $names[] = $typeName;
      }
    }

    return $names;
  }


}
