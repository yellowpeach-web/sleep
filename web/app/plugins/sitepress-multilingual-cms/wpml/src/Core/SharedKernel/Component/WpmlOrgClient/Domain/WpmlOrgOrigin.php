<?php

namespace WPML\Core\SharedKernel\Component\WpmlOrgClient\Domain;

class WpmlOrgOrigin {

  const CONSTANT_NAME = 'WPML_ORG_ORIGIN';

  const PRODUCTION = 'https://wpml.org';

  const APP = 'app';
  const API = 'api';
  const CDN = 'cdn';

  const HEALTH = 'health';
  const CDT = 'cdt';
  const PTC = 'ptc';

  const FEEDS_PATH = '/feeds';

  private $site;


  private function __construct( string $site ) {
    $this->site = $site;
  }


  public static function on( string $site ): self {
    $site = self::normalize( $site );

    return new self( '' === $site ? self::PRODUCTION : $site );
  }


  public static function configured(): self {
    if ( ! defined( self::CONSTANT_NAME ) ) {
      return new self( self::PRODUCTION );
    }

    $value = constant( self::CONSTANT_NAME );

    if ( ! is_string( $value ) ) {
      return new self( self::PRODUCTION );
    }

    return self::on( $value );
  }



  public function siteUrl(): string {
    return $this->site;
  }


  public function appUrl(): string {
    return $this->subdomain( self::APP );
  }


  public function apiUrl(): string {
    return $this->subdomain( self::API );
  }


  public function cdnUrl(): string {
    return $this->subdomain( self::CDN );
  }


  public function healthUrl(): string {
    return $this->subdomain( self::HEALTH );
  }


  public function cdtUrl(): string {
    return $this->subdomain( self::CDT );
  }


  public function ptcUrl(): string {
    return $this->subdomain( self::PTC );
  }


  public function feedsUrl(): string {
    return $this->site . self::FEEDS_PATH;
  }


  public function isProductionEstate(): bool {
    return self::PRODUCTION === $this->site;
  }


  public function mapUrl( string $url ): string {
    if ( '' === $url || $this->isProductionEstate() ) {
      return $url;
    }

    if ( ! preg_match( '#^(https?://)([^/?\#]+)(.*)$#i', $url, $matches ) ) {
      return $url;
    }

    $target = $this->frontFor( strtolower( $matches[2] ) );

    return null === $target ? $url : $target . $matches[3];
  }


  public function frontsByProductionHost(): array {
    return [
      'wpml.org'                 => $this->siteUrl(),
      self::APP . '.wpml.org'    => $this->appUrl(),
      self::API . '.wpml.org'    => $this->apiUrl(),
      self::CDN . '.wpml.org'    => $this->cdnUrl(),
      self::HEALTH . '.wpml.org' => $this->healthUrl(),
      self::CDT . '.wpml.org'    => $this->cdtUrl(),
      self::PTC . '.wpml.org'    => $this->ptcUrl(),
    ];
  }



  public static function site(): string {
    return self::configured()->siteUrl();
  }


  public static function app(): string {
    return self::configured()->appUrl();
  }


  public static function api(): string {
    return self::configured()->apiUrl();
  }


  public static function cdn(): string {
    return self::configured()->cdnUrl();
  }


  public static function health(): string {
    return self::configured()->healthUrl();
  }


  public static function cdt(): string {
    return self::configured()->cdtUrl();
  }


  public static function ptc(): string {
    return self::configured()->ptcUrl();
  }


  public static function feeds(): string {
    return self::configured()->feedsUrl();
  }


  public static function isProduction(): bool {
    return self::configured()->isProductionEstate();
  }


  public static function map( string $url ): string {
    return self::configured()->mapUrl( $url );
  }


  public static function fronts(): array {
    return self::configured()->frontsByProductionHost();
  }



  private function frontFor( string $host ) {
    if ( 0 === strpos( $host, 'www.' ) ) {
      $host = substr( $host, 4 );
    }

    $fronts = $this->frontsByProductionHost();

    return isset( $fronts[ $host ] ) ? $fronts[ $host ] : null;
  }


  private function subdomain( string $label ): string {
    if ( $this->isProductionEstate() ) {
      return 'https://' . $label . '.wpml.org';
    }

    if ( ! preg_match( '#^(https?://)(.+)$#i', $this->site, $matches ) ) {
      return $this->site;
    }

    if ( 0 === strpos( strtolower( $matches[2] ), $label . '.' ) ) {
      return $this->site;
    }

    return $matches[1] . $label . '.' . $matches[2];
  }


  private static function normalize( string $value ): string {
    $value = rtrim( trim( $value ), '/' );

    if ( '' === $value ) {
      return '';
    }

    if ( ! preg_match( '#^https?://#i', $value ) ) {
      $value = 'https://' . $value;
    }

    return $value;
  }


}
