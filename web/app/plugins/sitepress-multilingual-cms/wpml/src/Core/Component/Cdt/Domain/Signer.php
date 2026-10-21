<?php

namespace WPML\Core\Component\Cdt\Domain;

class Signer {


  public function sign( string $siteKey, string $siteUrl, int $ts, string $body ): string {
    return hash_hmac(
      'sha256',
      $siteKey . '|' . $siteUrl . '|' . $ts . '|' . hash( 'sha256', $body ),
      $siteKey
    );
  }


}
