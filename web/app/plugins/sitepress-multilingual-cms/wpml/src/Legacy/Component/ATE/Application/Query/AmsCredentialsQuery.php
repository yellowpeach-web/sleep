<?php

namespace WPML\Legacy\Component\ATE\Application\Query;

use WPML\Core\SharedKernel\Component\ATE\Application\Query\AmsCredentialsQueryInterface;

class AmsCredentialsQuery implements AmsCredentialsQueryInterface {

  private $amsApi;


  public function __construct( \WPML_TM_AMS_API $amsApi ) {
    $this->amsApi = $amsApi;
  }


  public function fingerprint(): string {
    $keys = $this->keys();

    return $keys === null
      ? ''
      : substr( hash( 'sha256', 'wpml-ams-keys|' . $keys['shared'] . '|' . $keys['secret'] ), 0, 16 );
  }


  public function credentials() {
    $keys = $this->keys();
    if ( $keys === null ) {
      return null;
    }

    $uuid = \get_option( \WPML_Site_ID::SITE_ID_KEY . ':' . \WPML_TM_ATE::SITE_ID_SCOPE, '' );

    return [
      'shared_key'   => $keys['shared'],
      'secret_key'   => $keys['secret'],
      'website_uuid' => is_string( $uuid ) ? $uuid : '',
    ];
  }


  public function signatureRefused(): bool {
    return \WPML_TM_AMS_API::signature_refusal() === \WPML_TM_AMS_API::SIGNATURE_REFUSED;
  }


  private function keys() {
    $data = $this->amsApi->get_registration_data();
    if ( empty( $data['shared'] ) || empty( $data['secret'] )
      || ! is_string( $data['shared'] ) || ! is_string( $data['secret'] ) ) {
      return null;
    }

    return [
      'shared' => $data['shared'],
      'secret' => $data['secret'],
    ];
  }


}
