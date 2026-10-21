<?php

namespace OTGS\Installer\Products;

use OTGS_Products_Bucket_Repository;
use OTGS_Products_Config_Db_Storage;

class ExternalProductsUrls {
	private $products_bucket_repository;

	private $products_config_storage;

	public function __construct(
		OTGS_Products_Config_Db_Storage $products_config_storage,
		OTGS_Products_Bucket_Repository $products_bucket_repository
	) {
		$this->products_config_storage    = $products_config_storage;
		$this->products_bucket_repository = $products_bucket_repository;
	}

	public function fetchProductUrl( $repository_id, $api_url, $site_key, $site_url ) {
		if ( ! $site_key ) {
			$this->products_config_storage->clear_repository_products_url( $repository_id );
			$this->products_config_storage->clear_repository_product_version( $repository_id );

			return null;
		}

		$products_url = $this->products_config_storage->get_repository_products_url( $repository_id );
		if ( $products_url ) {
			return $products_url;
		}

		return $this->get_products_url_from_otgs( $repository_id, $api_url, $site_key, $site_url );
	}

	public function fetchReleasesUrl( $repository_id, $api_url, $site_key, $site_url ) {
		if ( ! $site_key ) {
			$this->products_config_storage->clear_repository_releases_url( $repository_id );

			return null;
		}

		$releases_url = $this->products_config_storage->get_repository_releases_url( $repository_id );
		if ( $releases_url ) {
			return $releases_url;
		}

		return $this->get_releases_url_from_otgs( $repository_id, $api_url, $site_key, $site_url );
	}

	private function get_releases_url_from_otgs( $repository_id, $api_url, $site_key, $site_url ) {
		$releases_url = $this->products_bucket_repository->get_releases_bucket_url( $api_url, $site_key, $site_url );
		if ( $releases_url ) {
			$this->products_config_storage->store_repository_releases_url( $repository_id, $releases_url );
		}

		return $releases_url;
	}

	private function get_products_url_from_otgs( $repository_id, $api_url, $site_key, $site_url ) {
		$products_url = $this->products_bucket_repository->get_products_bucket_url( $api_url, $site_key, $site_url );
		if ( $products_url ) {
			$this->products_config_storage->store_repository_products_url( $repository_id, $products_url );
		}

		return $products_url;
	}

}