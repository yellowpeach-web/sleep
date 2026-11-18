<?php

namespace WPML\Infrastructure\WordPress\Port\Translation;

use WPML\Core\SharedKernel\Component\Translation\Domain\PackageOwnerInterface;

class PackageOwner implements PackageOwnerInterface {

  public function ridesOwner( int $elementId ): bool {
    return (bool) apply_filters( 'wpml_string_package_rides_owner', false, $elementId );
  }


  public function getOwner( int $elementId ): ?array {
    $owner = apply_filters( 'wpml_string_package_owner', null, $elementId );
    return $owner;
  }


  public function message( ?array $owner ): string {
    $type = $owner['element_type'] ?? '';
    $id   = $owner['element_id'] ?? 0;
    if ( 0 === strpos( $type, 'tax_' ) ) {
      $typeName  = substr( $type, 4 );
      $typeModel = get_taxonomy( $typeName );
      $element   = get_term_by( 'term_taxonomy_id', $id );
      $title     = is_object( $element ) && ! $element instanceof \WP_Error ? $element->name : '';
    } else {
      $typeName  = substr( $type, 5 );
      $typeModel = get_post_type_object( $typeName );
      $element   = get_post( $id );
      $title     = is_object( $element ) ? $element->post_title : '';
    }
    $label = is_object( $typeModel ) && isset( $typeModel->labels->singular_name ) ? $typeModel->labels->singular_name : '';
    if ( '' === trim( $label ) ) {
      /* translators: Generic content type used when the owner type has no singular label. */
      $label = __( 'content', 'wpml' );
    }
    if ( '' === trim( $title ) ) {
      /* translators: %s is the singular post type or taxonomy label of the content whose translation carries this text. */
      return sprintf( __( 'Translated together with the %s it belongs to.', 'wpml' ), $label );
    }
    /* translators: %1$s is the singular post type or taxonomy label, %2$s is the content title. */
    return sprintf( __( 'Translated together with the %1$s "%2$s".', 'wpml' ), $label, $title );
  }


  public function changed( array $owner, int $packageId ): void {
    do_action( 'wpml_string_package_owner_changed', $owner['element_type'], $owner['element_id'], $packageId );
  }

}
