<?php

namespace WPML\Legacy\Component\Cdt\Query;

use WPML\Core\Component\Cdt\Application\Query\SiteIdentityQueryInterface;
use WPML\Core\SharedKernel\Component\Installer\Application\Query\WpmlSiteKeyQueryInterface;

class SiteIdentityQuery implements SiteIdentityQueryInterface {

  const REPOSITORY = 'wpml';

  private $siteKeyQuery;

  private $siteUrl;


  public function __construct( WpmlSiteKeyQueryInterface $siteKeyQuery, $siteUrl = null ) {
    $this->siteKeyQuery = $siteKeyQuery;
    $this->siteUrl      = is_callable( $siteUrl ) ? $siteUrl : null;
  }


  public function getSiteKey(): ?string {
    $key = $this->siteKeyQuery->get();

    return is_string( $key ) && $key !== '' ? $key : null;
  }


  public function getSiteUrl(): string {
    if ( $this->siteUrl ) {
      return (string) call_user_func( $this->siteUrl );
    }

    if ( class_exists( \OTGS\Installer\Api\SiteUrl::class ) ) {
      return ( new \OTGS\Installer\Api\SiteUrl() )->get( self::REPOSITORY );
    }

    return get_site_url();
  }


}
