<?php

use OTGS\Installer\WpmlOrg\WpmlOrgOrigin;

class OTGS_Products_Config_Xml {

	private $repositories_config;

	private $wpml_org_origin;

	public function __construct( $xml_file, $wpml_org_origin = null ) {
		$this->repositories_config = $this->load_configuration( $xml_file );
		$this->wpml_org_origin     = $wpml_org_origin instanceof WpmlOrgOrigin
			? $wpml_org_origin
			: WpmlOrgOrigin::configured();
	}

	private function load_configuration( $xml_file ) {
		if( ! file_exists( $xml_file )) {
			return null;
		}
		return simplexml_load_file( $xml_file );
	}

	public function get_repository_products_url( $repository_id ) {
		foreach ( $this->repositories_config as $repository_config ) {
			if ( isset( $repository_config->id ) && strval( $repository_config->id ) == $repository_id ) {
				return isset( $repository_config->products) ? $this->on_estate( strval( $repository_config->products ) ) : null;
			}
		}

		return null;
	}

	public function get_repository_releases_url( $repository_id ) {
		foreach ( $this->repositories_config as $repository_config ) {
			if ( isset( $repository_config->id ) && strval( $repository_config->id ) == $repository_id ) {
				return isset( $repository_config->releases ) ? $this->on_estate( strval( $repository_config->releases ) ) : null;
			}
		}

		return null;
	}

	public function get_repository_products_default_data() {
		$productDefaults = [];
		foreach ( $this->repositories_config as $repository_config ) {
			$productDefaults[ strval( $repository_config->id ) ] = isset( $repository_config->default_products )
				? json_decode( strval( $repository_config->default_products ), true ) : null;
		}

		return $productDefaults;
	}

	public function get_products_api_urls() {
		$urls = [];

		foreach ( $this->repositories_config as $repository_config ) {
			if ( isset( $repository_config->apiurl ) ) {
				$urls[strval( $repository_config->id )] = $this->on_estate( strval( $repository_config->apiurl ) );
			}

			$repo_upper = strtoupper( $repository_config->id );
			if ( defined( "OTGS_INSTALLER_{$repo_upper}_API_URL" ) ) {
				$urls[strval( $repository_config->id )] = constant( "OTGS_INSTALLER_{$repo_upper}_API_URL" );
			}
		}

		return $urls;
	}

	private function on_estate( $url ) {
		return $this->wpml_org_origin->mapUrl( $url );
	}
}
