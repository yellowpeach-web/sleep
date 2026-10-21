<?php

namespace OTGS\Installer\Api\Endpoint;

use OTGS\Installer\Api\Exception\InvalidResponseException;
use OTGS\Installer\Api\Exception\InvalidSubscription;
use OTGS\Installer\Api\Exception\InvalidSubscriptionResponseException;
use OTGS\Installer\Api\Exception\RateLimited;
use OTGS\Installer\Api\Exception\ServiceUnavailable;
use OTGS\Installer\Api\SiteUrl;
use OTGS_Installer_Plugin_Finder;

class Subscription {

	private $siteUrl;

	private $plugin_finder;

	private $repositoryId;

	public function __construct( $repositoryId, SiteUrl $siteUrl, OTGS_Installer_Plugin_Finder $plugin_finder ) {
		$this->siteUrl       = $siteUrl;
		$this->plugin_finder = $plugin_finder;
		$this->repositoryId = $repositoryId;
	}

	public function prepareRequest( $siteKey, $source ) {
		$requestParameters = [
			'action'            => 'site_key_validation',
			'site_key'          => $siteKey,
			'site_url'          => $this->siteUrl->get( $this->repositoryId ),
			'source'            => $source,
			'installer_version' => WP_INSTALLER_VERSION,
			'theme'             => wp_get_theme()->get( 'Name' ),
			'site_name'         => get_bloginfo( 'name' ),
			'wp_version'        => get_bloginfo( 'version' ),
			'phpversion'        => phpversion(),
			'repository_id'     => $this->repositoryId,
			'versions'          => $this->plugin_finder->getLocalPluginVersions(),
		];

		if ( $this->repositoryId === 'wpml' ) {
			$requestParameters['using_icl']    = function_exists( 'wpml_site_uses_icl' ) && wpml_site_uses_icl();
			$requestParameters['wpml_version'] = defined( 'ICL_SITEPRESS_VERSION' ) ? ICL_SITEPRESS_VERSION : '';
		}

		return apply_filters( 'installer_fetch_subscription_data_request', $requestParameters );
	}

	public function parseResponse( $response ) {
		$status = (int) wp_remote_retrieve_response_code( $response );
		if ( 429 === $status ) {
			throw new RateLimited( RateLimited::sentence( null ) );
		}
		if ( 0 !== $status && 200 !== $status ) {
			throw new ServiceUnavailable( $status );
		}

		$body = wp_remote_retrieve_body( $response );
		if ( ! $body || ! is_serialized( $body ) || ! ( $apiResponse = @unserialize( $body ) ) ) {
			throw new InvalidResponseException();
		}

		if ( RateLimited::answers( $apiResponse ) ) {
			throw new RateLimited( RateLimited::sentence( $apiResponse ) );
		}

		if ( isset( $apiResponse->error ) ) {
			throw new InvalidSubscription( $apiResponse->error );
		}

		if ( isset( $apiResponse->subscription_data )
		     && isset( $apiResponse->site_key )) {
			return $apiResponse;
		}

		throw  new InvalidSubscriptionResponseException();
	}
}