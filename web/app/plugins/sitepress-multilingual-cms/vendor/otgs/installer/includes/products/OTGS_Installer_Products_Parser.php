<?php

class OTGS_Installer_Products_Parser {

	const RELEASE_FIELDS = [ 'version', 'date', 'url', 'changelog', 'tested', 'requires', 'requires_php', 'channels' ];

	const REQUIRED_RELEASE_FIELDS = [ 'version', 'date', 'url' ];

	private $logger;

	private $installerChannels;

	private $product_notices = array();

	private $defaultProducts;

	public function __construct(
		WP_Installer_Channels $installerChannels,
		OTGS_Products_Config_Xml $productsConfigXml,
		OTGS_Installer_Logger_Storage $logger
	) {
		$this->logger            = $logger;
		$this->installerChannels = $installerChannels;
		$this->defaultProducts   = $productsConfigXml->get_repository_products_default_data();
	}

	public function get_products_from_response( $products_url, $repository_id, $response, $releases = null, $releases_url = null ) {
		$products = $this->parse_products_response( $products_url, $response );
		$products = $this->merge_releases( $products, $releases, $releases_url );
		$products = $this->validate_products_plugins( $products_url, $products );
		$products['downloads'] = $this->prepare_products_downloads( $repository_id, $products );

		return $products;
	}

	private function merge_releases( $products, $releases, $releases_url ) {
		if ( null === $releases || ! isset( $products['downloads']['plugins'] ) || ! is_array( $products['downloads']['plugins'] ) ) {
			return $products;
		}

		$release_plugins = isset( $releases['plugins'] ) && is_array( $releases['plugins'] ) ? $releases['plugins'] : [];

		foreach ( array_keys( $products['downloads']['plugins'] ) as $slug ) {
			$release = isset( $release_plugins[ $slug ] ) && is_array( $release_plugins[ $slug ] ) ? $release_plugins[ $slug ] : [];

			if ( ! $this->is_installable_release( $release ) ) {
				unset( $products['downloads']['plugins'][ $slug ] );
				$this->log_missing_release( $releases_url, $slug );
				continue;
			}

			foreach ( self::RELEASE_FIELDS as $field ) {
				if ( isset( $release[ $field ] ) ) {
					$products['downloads']['plugins'][ $slug ][ $field ] = $release[ $field ];
				}
			}

			if ( ! isset( $products['downloads']['plugins'][ $slug ]['changelog'] ) ) {
				$products['downloads']['plugins'][ $slug ]['changelog'] = '';
			}
		}

		return $products;
	}

	private function is_installable_release( $release ) {
		foreach ( self::REQUIRED_RELEASE_FIELDS as $field ) {
			if ( empty( $release[ $field ] ) ) {
				return false;
			}
		}

		return true;
	}

	private function log_missing_release( $releases_url, $slug ) {
		$this->store_log(
			$releases_url,
			sprintf(
				'No usable release information for %s. The plugin is left out of this products refresh.',
				$slug
			)
		);
	}

	public function get_default_products( $repository_id ) {
		return isset( $this->defaultProducts[ $repository_id ] ) ? $this->defaultProducts[ $repository_id ] : null;
	}

	private function parse_products_response( $products_url, $response ) {
		$body = wp_remote_retrieve_body( $response );
		if ( $body ) {
			$json = json_decode( $body, true );

			if ( $json ) {
				return $json;
			}
		}

		throw OTGS_Installer_Products_Parsing_Exception::createForResponse( $products_url );
	}

	private function validate_products_plugins( $products_url, $products ) {
		if ( is_array( $products ) ) {
			foreach ( $products['downloads']['plugins'] as $product_id => $product ) {
				if ( empty( $product['slug'] )
				     || empty( $product['name'] )
				     || empty( $product['version'] )
				     || empty( $product['date'] )
				     || empty( $product['url'] )
				     || empty( $product['basename'] )
				) {
					$this->handle_product_parsing_error( $products_url, $product_id );
					throw OTGS_Installer_Products_Parsing_Exception::createForResponse( $products_url );
				}
			}
		}
		return $products;
	}

	private function handle_product_parsing_error( $products_url, $product_id ) {
		$error = sprintf( __( 'Information about versions of %s are invalid. It may be a temporary communication problem, please check for updates again.', 'installer' ), $product_id );
		$this->store_log( $products_url, $error );
		$this->product_notices[] = $error;
	}

	private function store_log( $url, $response ) {
		$log = new OTGS_Installer_Log();
		$log->set_request_url( $url )
		    ->set_component( OTGS_Installer_Logger_Storage::COMPONENT_PRODUCTS_PARSING )
		    ->set_response( $response );

		$this->logger->add( $log );
	}

	private function prepare_products_downloads( $repository_id, $products ) {
		$downloads = $this->installerChannels->filter_downloads_by_channel( $repository_id, $products['downloads'] );
		$downloads = $this->add_release_notes( $downloads );

		return $downloads;
	}

	private function add_release_notes( $products_downloads ) {
		foreach ( $products_downloads as $kind => $downloads ) {
			foreach ( $downloads as $slug => $download ) {
				$release_notes = '';
				$heading       = '<h4>' . $download['version'] . '</h4>';
				$start         = strpos( $download['changelog'], $heading );
				if ( $start !== false ) {
					$start += strlen( $heading );
					$end    = strpos( $download['changelog'], '<h4>', $start );
					if ( $end !== false ) {
						$release_notes = substr( $download['changelog'], $start, $end - $start );
					} else {
						$release_notes = substr( $download['changelog'], $start );
					}
				}
				$products_downloads[ $kind ][ $slug ]['release-notes'] = $release_notes;
			}
		}

		return $products_downloads;
	}

	public function get_product_notices() {
		return $this->product_notices;
	}
}
