<?php

namespace OTGS\Installer\WpmlOrg;

class WpmlOrgOrigin {

	const CONSTANT_NAME = 'WPML_ORG_ORIGIN';

	const PRODUCTION = 'https://wpml.org';

	const API = 'api';

	private $site;

	private function __construct( $site ) {
		$this->site = $site;
	}

	public static function on( $site ) {
		$site = self::normalize( (string) $site );

		return new self( '' === $site ? self::PRODUCTION : $site );
	}

	public static function configured() {
		if ( ! defined( self::CONSTANT_NAME ) ) {
			return new self( self::PRODUCTION );
		}

		$value = constant( self::CONSTANT_NAME );

		if ( ! is_string( $value ) ) {
			return new self( self::PRODUCTION );
		}

		return self::on( $value );
	}

	public function siteUrl() {
		return $this->site;
	}

	public function apiUrl() {
		return $this->subdomain( self::API );
	}

	public function isProductionEstate() {
		return self::PRODUCTION === $this->site;
	}

	public function mapUrl( $url ) {
		$url = (string) $url;

		if ( '' === $url || $this->isProductionEstate() ) {
			return $url;
		}

		if ( ! preg_match( '#^(https?://)([^/?\#]+)(.*)$#i', $url, $matches ) ) {
			return $url;
		}

		$target = $this->frontFor( strtolower( $matches[2] ) );

		return null === $target ? $url : $target . $matches[3];
	}

	public function frontsByProductionHost() {
		return [
			'wpml.org'              => $this->siteUrl(),
			self::API . '.wpml.org' => $this->apiUrl(),
		];
	}


	public static function site() {
		return self::configured()->siteUrl();
	}

	public static function api() {
		return self::configured()->apiUrl();
	}

	public static function map( $url ) {
		return self::configured()->mapUrl( $url );
	}


	private function frontFor( $host ) {
		if ( 0 === strpos( $host, 'www.' ) ) {
			$host = substr( $host, 4 );
		}

		$fronts = $this->frontsByProductionHost();

		return isset( $fronts[ $host ] ) ? $fronts[ $host ] : null;
	}

	private function subdomain( $label ) {
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

	private static function normalize( $value ) {
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
