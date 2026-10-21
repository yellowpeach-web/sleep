<?php

namespace OTGS\Installer\Subscription;

use OTGS\Installer\Api\Exception\InvalidProductBucketUrl;
use OTGS\Installer\Api\InstallerApiClient;
use OTGS\Installer\SubscriptionFetchGuard;
use OTGS_Products_Config_Db_Storage;

class SubscriptionManager {

	private $apiClient;

	private $productsConfigStorage;

	private $repositoryId;

	public function __construct( $repositoryId, InstallerApiClient $apiClient, OTGS_Products_Config_Db_Storage $productsConfigStorage ) {
		$this->repositoryId          = $repositoryId;
		$this->apiClient             = $apiClient;
		$this->productsConfigStorage = $productsConfigStorage;
	}

	/**
	 * Asks the API at most once per request and repository key, and not while a failed or refused answer's wait
	 * runs (SubscriptionFetchGuard, wpmldev-10865).
	 *
	 * @param string $siteKey
	 * @param int $source
	 * @param bool $userRequested A person asked for this check (registration, "check order status", "Refresh
	 *                            license", an update or a download): it may run once a minute inside a wait.
	 *
	 * @throws \OTGS_Installer_Fetch_Subscription_Postponed_Exception when the check has to wait
	 * @throws \OTGS_Installer_Fetch_Subscription_Exception
	 * @throws InvalidProductBucketUrl
	 */
	public function fetch( $siteKey, $source, $userRequested = false ) {
		return SubscriptionFetchGuard::run(
			$this->repositoryId,
			$siteKey,
			$userRequested,
			function () use ( $siteKey, $source ) {
				$fetchSubscriptionResult = $this->apiClient->fetchSubscription( $siteKey, $source );
				$this->maybeUpdateBuckets( $fetchSubscriptionResult, $siteKey );

				do_action( 'installer_fetched_subscription_data', $fetchSubscriptionResult, $this->repositoryId );

				return [ $fetchSubscriptionResult->subscription_data, $fetchSubscriptionResult->site_key ];
			}
		);
	}

	private function maybeUpdateBuckets( $fetchSubscriptionResult, $siteKey ) {
		if ( isset ( $fetchSubscriptionResult->bucket_version )
		     && $this->shouldRefetchProductUrl( $this->repositoryId, $fetchSubscriptionResult->bucket_version ) ) {
			$productUrl = $this->apiClient->fetchProductUrl( $siteKey );
			if ( $productUrl ) {
				$this->productsConfigStorage->store_repository_products_url( $this->repositoryId, $productUrl );
				$this->productsConfigStorage->clear_repository_releases_url( $this->repositoryId );
				$this->productsConfigStorage->update_repository_product_version( $this->repositoryId, $fetchSubscriptionResult->bucket_version );
			}

		}
	}

	public function shouldRefetchProductUrl( $repository_id, $fetchVersion ) {
		$currentVersion = $this->productsConfigStorage->get_repository_product_version( $repository_id );

		return ! $currentVersion || $currentVersion < $fetchVersion;
	}
}