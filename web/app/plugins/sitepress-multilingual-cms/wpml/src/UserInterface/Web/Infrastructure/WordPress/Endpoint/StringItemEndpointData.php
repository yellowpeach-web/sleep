<?php

namespace WPML\UserInterface\Web\Infrastructure\WordPress\Endpoint;

class StringItemEndpointData {


  public function getEndpointData(): array {
    return [
      'url'   => $this->getRestUrl( '/wpml/st/v1/strings' ),
    ];
  }


  public function getStringPackagesEndpointData(): array {
    return [
      'url'   => $this->getRestUrl( '/wpml/st/v1/string-packages' ),
    ];
  }


  public function isStPluginActive(): bool {
    return class_exists( 'WPML_String_Translation' );
  }


  public function getAutoregistrationData() {
    $isEnabled = apply_filters( 'wpml_st_get_setting', 'isAutoregisterEnabled' );
    if ( ! is_bool( $isEnabled ) ) {
      return null;
    }

    $isCurrentUserAdmin = apply_filters( 'wpml_st_get_setting', 'isCurrentUserAdmin' );

    return [
      'isEnabled'          => $isEnabled,
      'isCurrentUserAdmin' => true === $isCurrentUserAdmin,
    ];
  }


  private function getRestUrl( string $path ): string {
    return get_rest_url( null, $path );
  }


}
