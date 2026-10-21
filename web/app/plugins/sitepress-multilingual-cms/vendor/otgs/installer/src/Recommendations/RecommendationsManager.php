<?php

namespace OTGS\Installer\Recommendations;

use OTGS\Installer\Localization\Language;
use OTGS_Installer_Subscription;
use WP_Installer;

class RecommendationsManager {
	private $repositories;

	private $settings;

	private $repositoryRecommendationRules = [
		'wpml' => [
			'owner_plugin_slugs' => [ 'sitepress-multilingual-cms' ],
		],
	];

	private $noticesStorage;

	public function __construct( \OTGS_Installer_Repositories $repositories, Storage $noticesStorage ) {
		$this->repositories   = $repositories;
		$this->noticesStorage = $noticesStorage;
	}

	private function settings() {
		if ( $this->settings === null ) {
			$this->settings = WP_Installer::instance()->get_settings()['repositories'];
		}
		return $this->settings;
	}

	public function addHooks() {
		add_action( 'deactivated_plugin', [ $this, 'deactivatedPluginRecommendation' ] );
		add_action( 'wp_ajax_installer_recommendation_success', [ $this, 'recommendationSuccess' ] );
		add_action( 'current_screen', [ $this, 'checkAllInstalledPluginsForRecommendations' ] );

		add_filter( 'wpml_installer_get_stored_recommendation_notices', [ $this, 'getRecommendationNotices' ] );
	}



	public function checkAllInstalledPluginsForRecommendations( $screen = null ) {
		if ( ! $screen || $screen->id !== 'plugins' ) {
			return;
		}

		$installedPlugins = $this->getInstalledPlugins();

		foreach ( $installedPlugins as $pluginSlug => $pluginData ) {
			if ( ! $pluginData['is_active'] ) {
				continue;
			}

			$pluginInfo     = $this->getPluginData( $pluginSlug . '/plugin.php' );
			$gluePluginData = $pluginInfo->getGluePluginData();

			if ( $gluePluginData
			     && ! $this->isGluePluginActive( $gluePluginData['glue_plugin_slug'] )
			     &&  $this->noticesStorage->missing( $pluginSlug, $gluePluginData['repository_id'] )
			) {
				$this->noticesStorage->save($pluginSlug, $gluePluginData );
			}
		}
	}

	private function isGluePluginActive( $gluePluginSlug ) {
		$pluginData = isset( $this->getInstalledPlugins()[ $gluePluginSlug ] ) ? $this->getInstalledPlugins()[ $gluePluginSlug ] : null;

		return $pluginData && $pluginData['is_active'];
	}

	private function isRepositoryOwnerPluginActive( $repositoryId ) {
		$ownerPluginSlugs = $this->getRepositoryOwnerPluginSlugs( $repositoryId );

		if ( empty( $ownerPluginSlugs ) ) {
			return true;
		}

		$installedPlugins = $this->getInstalledPlugins();

		foreach ( $ownerPluginSlugs as $ownerPluginSlug ) {
			if ( isset( $installedPlugins[ $ownerPluginSlug ] ) && $installedPlugins[ $ownerPluginSlug ]['is_active'] ) {
				return true;
			}
		}

		return false;
	}

	private function getRepositoryIdsForRecommendations() {
		return array_keys( $this->repositoryRecommendationRules );
	}

	private function hasRepositoryRecommendationRule( $repositoryId ) {
		return isset( $this->repositoryRecommendationRules[ $repositoryId ] );
	}

	private function getRepositoryOwnerPluginSlugs( $repositoryId ) {
		if ( ! $this->hasRepositoryRecommendationRule( $repositoryId ) ) {
			return [];
		}

		$rule = $this->repositoryRecommendationRules[ $repositoryId ];

		return isset( $rule['owner_plugin_slugs'] ) ? (array) $rule['owner_plugin_slugs'] : [];
	}

	public function deactivatedPluginRecommendation( $plugin ) {
		$deactivatedPlugin = $this->getPluginData( $plugin );
		$gluePluginData = $deactivatedPlugin->getGluePluginData();
		if ( $gluePluginData ) {
			$this->noticesStorage->delete( $deactivatedPlugin->getPluginSlug(), $gluePluginData['repository_id'] );
		}
	}

	public function recommendationSuccess() {
		if ( array_key_exists( 'nonce', $_POST )
		     && array_key_exists( 'pluginData', $_POST )
		     && wp_verify_nonce( $_POST['nonce'], 'recommendation_success_nonce' ) ) {
			$data = json_decode( base64_decode( sanitize_text_field( $_POST['pluginData'] ) ), true );
			$this->noticesStorage->delete( $data['slug'], $data['repository_id'] );
		}

	}

	private function getActivatedPluginGlue( $activatedPluginSlug ) {
		$language = $this->getCurrentLanguage();

		foreach ( $this->getRepositoryIdsForRecommendations() as $repositoryId ) {
			if ( ! $this->isRepositoryOwnerPluginActive( $repositoryId ) ) {
				continue;
			}

			$downloads = isset( $this->settings()[ $repositoryId ]['data']['downloads']['plugins'] )
				? $this->settings()[ $repositoryId ]['data']['downloads']['plugins'] : [];
			foreach ( $downloads as $pluginData ) {
				$gluePluginSlug = isset( $pluginData['glue_check_slug'] ) ? $pluginData['glue_check_slug'] : false;
				if ( $gluePluginSlug && $activatedPluginSlug === $pluginData['glue_check_slug'] ) {

					return $this->prepareRecommendedPluginData( $repositoryId, $pluginData, $language );

				}
			}
		}

		return null;
	}

	private function getGluePluginData( $gluePluginSlug, $mappingData, $requireRepositoryOwnerPluginActive = true ) {

		$language = $this->getCurrentLanguage();

		foreach ( $this->getRepositoryIdsForRecommendations() as $repositoryId ) {
			if ( $requireRepositoryOwnerPluginActive && ! $this->isRepositoryOwnerPluginActive( $repositoryId ) ) {
				continue;
			}

			$downloads = isset( $this->settings()[ $repositoryId ]['data']['downloads']['plugins'] )
				? $this->settings()[ $repositoryId ]['data']['downloads']['plugins'] : [];
			if ( isset( $downloads[ $gluePluginSlug ] ) ) {
				$pluginData = $downloads[ $gluePluginSlug ];

				return $this->prepareRecommendedPluginData( $repositoryId, $pluginData, $language, $mappingData );
			}
		}

		return null;
	}

	private function getNotificationForLanguage( $pluginData, $language ) {
		$default = isset( $pluginData['recommendation_notification']['en'] ) ? $pluginData['recommendation_notification']['en'] : '';

		return isset( $pluginData['recommendation_notification'][ $language ] )
			? $pluginData['recommendation_notification'][ $language ]
			: $default;
	}

	public function getRepositoryPluginsRecommendations() {
		$pluginsRecommendations = [];
		$pluginsData            = [];
		$language               = $this->getCurrentLanguage();

		foreach ( $this->getRepositoryIdsForRecommendations() as $repositoryId ) {
			$repository = $this->repositories->get( $repositoryId );

			if ( $this->settings()[ $repositoryId ]['data']['downloads']['plugins']
			     && $this->settings()[ $repositoryId ]['data']['recommendation_sections'] ) {
				$downloads = $this->settings()[ $repositoryId ]['data']['downloads']['plugins'];
				$sections  = $this->settings()[ $repositoryId ]['data']['recommendation_sections'];
			} else {
				continue;
			}

			$subscription = $repository->get_subscription();
			if ( ! $subscription ) {
				continue;
			}

			$available_plugins_list = $this->getAvailablePluginsForSubscription( $repository );
			$installedPlugins       = $this->getInstalledPlugins();

			foreach ( $downloads as $pluginData ) {
				if ( $this->isRecommended( $pluginData )
				     && in_array( $pluginData['slug'], $available_plugins_list, true )
				     && $this->shouldBeDisplayed( $pluginData )
				) {
					$isInstalled = isset( $installedPlugins[ $pluginData['slug'] ] );
					$isActive    = $isInstalled && $installedPlugins[ $pluginData['slug'] ]['is_active'];

					if ( ! $isInstalled || ! $isActive ) {
						$recommendedByRule = $this->matchesRecommendationRule( $pluginData );

						$recommendation = $this->preparePluginData(
							$language,
							$pluginData,
							$subscription->get_site_key(),
							$repositoryId,
							$subscription->get_site_url(),
							$isInstalled,
							$isActive,
							$recommendedByRule
						);

						$sectionPlugin = $this->prepareSectionPlugin(
							$language,
							$pluginData,
							$isInstalled,
							$isActive,
							$recommendedByRule
						);

						if (
							array_key_exists( 'download_recommendation_section', $pluginData ) &&
							is_string( $pluginData['download_recommendation_section']) &&  !empty( $pluginData['download_recommendation_section'] )
						) {
							$pluginsRecommendations[ $pluginData['download_recommendation_section'] ]['plugins'][ $pluginData['slug'] ] = $sectionPlugin;
							$pluginsData[ $pluginData['slug'] ]                                                                         = $recommendation;
						}
					}
				}
			}

			$recommendationsForInstallerPlugins = $this->prepareRecommendationsForInstalledPlugins( $repositoryId, $subscription, $downloads, $installedPlugins, $pluginsRecommendations, $pluginsData );
			$pluginsData = $recommendationsForInstallerPlugins->getPluginsData();

			$pluginsRecommendations = $recommendationsForInstallerPlugins->getRecommendations();

			foreach ( $recommendationsForInstallerPlugins->getRecommendations() as $section => $plugins_recommendation ) {
				$pluginsRecommendations[ $section ]['title'] = $sections[ $section ][ $language ]['name'] ?? $sections[ $section ]['en']['name'] ?? '';
				$pluginsRecommendations[ $section ]['order'] = $sections[ $section ][ $language ]['order'] ?? $sections[ $section ]['en']['order'] ?? '';

			}
		}

		uasort( $pluginsRecommendations, function ( $a, $b ) {
			return (int) $a['order'] - (int) $b['order'];
		} );

		return [ 'sections' => $pluginsRecommendations, 'plugins' => $pluginsData ];
	}

	private function prepareRecommendationsForInstalledPlugins( $repositoryId, OTGS_Installer_Subscription $subscription, $downloads, $installedPlugins, $pluginsRecommendations, $pluginsData ) {
		$language = $this->getCurrentLanguage();

		if ( isset($this->settings()[ $repositoryId ]['data']['glue_plugins_mapping']) ) {
			$gluePluginsMapping = $this->settings()[ $repositoryId ]['data']['glue_plugins_mapping'];
		} else {
			return new RecommendationsForInstallerPlugins( $pluginsRecommendations, $pluginsData );
		}

		foreach ( $installedPlugins as $pluginSlug => $pluginData ) {
			if(isset($pluginData['is_active']) && !$pluginData['is_active']) {
				continue;
			}

			if ( isset( $gluePluginsMapping[ $pluginSlug ] ) ) {
				$gluePluginSlug = $gluePluginsMapping[ $pluginSlug ]['glue_plugin'];
				$gluePluginData = $this->getGluePluginData( $gluePluginSlug, $gluePluginsMapping[ $pluginSlug ], false );

				if ( $gluePluginData ) {
					$isGlueInstalled = isset( $installedPlugins[ $gluePluginSlug ] );
					$isGlueActive    = $isGlueInstalled && $installedPlugins[ $gluePluginSlug ]['is_active'];

					if ( ! $isGlueInstalled || ! $isGlueActive ) {
						$recommendation = $this->preparePluginData(
							$language,
							$downloads[ $gluePluginSlug ],
							$subscription->get_site_key(),
							$repositoryId,
							$subscription->get_site_url(),
							$isGlueInstalled,
							$isGlueActive,
							true
						);

						$sectionPlugin = $this->prepareSectionPlugin(
							$language,
							$downloads[ $gluePluginSlug ],
							$isGlueInstalled,
							$isGlueActive,
							true
						);

						if ( $downloads[ $gluePluginSlug ] ) {
							$pluginsRecommendations[ $downloads[ $gluePluginSlug ]['download_recommendation_section'] ]['plugins'][ $gluePluginSlug ] = $sectionPlugin;
							$pluginsData[ $gluePluginSlug ]                                                                                           = $recommendation;
						}
					}
				}
			}
		}

		return new RecommendationsForInstallerPlugins( $pluginsRecommendations, $pluginsData );
	}

	private function getCurrentLanguage() {
		global $sitepress;

		return $sitepress ? $sitepress->get_admin_language() : 'en';
	}

	private function getAvailablePluginsForSubscription( \OTGS_Installer_Repository $repository ) {
		$product = $repository->get_product_by_subscription_type();
		if ( ! $product ) {
			$product = $repository->get_product_by_subscription_type_equivalent();
		}

		if ( ! $product ) {
			return [];
		}

		return $product->get_plugins();
	}

	private function getInstalledPlugins() {
		$installed_plugins = [];

		foreach ( get_plugins() as $plugin_id => $plugin_data ) {
			$installed_plugins[ dirname( $plugin_id ) ] = [
				'is_active' => is_plugin_active( $plugin_id ),
			];
		}

		return $installed_plugins;
	}

	private function preparePluginData( $language, $pluginData, $siteKey, $repositoryId, $siteUrl, $isInstalled, $isActive, $recommendedByRule = false ) {
		$url = $this->appendSiteKeyToDownloadUrl( $pluginData['url'], $siteKey, $siteUrl );

		$downloadData = [
			'url'           => $url,
			'slug'          => $pluginData['slug'],
			'nonce'         => wp_create_nonce( 'install_plugin_' . $url ),
			'repository_id' => $repositoryId,
		];

		return array_merge(
			[
				'name'                    => $pluginData['name'],
				'is_installed'            => $isInstalled,
				'is_active'               => $isActive,
				'slug'                    => $pluginData['slug'],
				'recommendation_icon_url' => isset( $pluginData['recommendation_icon_url'] ) ? $pluginData['recommendation_icon_url'] : '',
				'recommended'             => ! empty( $pluginData['recommended'] ),
				'recommended_by_rule'     => (bool) $recommendedByRule,
				'download_data'           => base64_encode( (string) json_encode( $downloadData ) ),
			],
			$this->prepareDescriptions( $language, $pluginData )
		);
	}

	private function prepareSectionPlugin( $language, $pluginData, $isInstalled, $isActive, $recommendedByRule = false ) {
		return array_merge(
			[
				'name'                    => $pluginData['name'],
				'is_installed'            => $isInstalled,
				'is_active'               => $isActive,
				'slug'                    => $pluginData['slug'],
				'recommendation_icon_url' => isset( $pluginData['recommendation_icon_url'] ) ? $pluginData['recommendation_icon_url'] : '',
				'recommended'             => ! empty( $pluginData['recommended'] ),
				'recommended_by_rule'     => (bool) $recommendedByRule,
			],
			$this->prepareDescriptions( $language, $pluginData )
		);
	}

	private function prepareDescriptions( $language, $pluginData ) {
		$whatItDoes       = $this->getLocalizedField( $pluginData, 'what_it_does', $language );
		$leadDescription  = $this->getLocalizedField( $pluginData, 'lead_description', $language );
		$shortDescription = $this->getLocalizedField( $pluginData, 'short_description', $language );

		$description = $whatItDoes;
		if ( '' === $description ) {
			$description = $leadDescription;
		}
		if ( '' === $description ) {
			$description = $shortDescription;
		}

		return [
			'description'       => $description,
			'what_it_does'      => $whatItDoes,
			'missing_impact'    => $this->getLocalizedField( $pluginData, 'missing_impact', $language ),
			'short_description' => $shortDescription,
		];
	}

	private function getLocalizedField( $pluginData, $field, $language ) {
		if ( ! isset( $pluginData[ $field ] ) ) {
			return '';
		}

		return Language::getValue( $pluginData[ $field ], $language );
	}

	private function isRecommended( $pluginData ) {
		if ( ! empty( $pluginData['recommended'] ) ) {
			return true;
		}

		return $this->matchesRecommendationRule( $pluginData );
	}

	private function matchesRecommendationRule( $pluginData ) {
		if ( empty( $pluginData['recommended_when_active'] ) || ! is_array( $pluginData['recommended_when_active'] ) ) {
			return false;
		}

		foreach ( $pluginData['recommended_when_active'] as $condition ) {
			if ( $this->matchesActivePluginCondition( $condition ) ) {
				return true;
			}
		}

		return false;
	}

	private function matchesActivePluginCondition( $condition ) {
		if ( ! is_array( $condition ) ) {
			return false;
		}

		$checkType  = $this->readCondition( $condition, 'check_type', 'glue_check_type' );
		$checkValue = $this->readCondition( $condition, 'check_value', 'glue_check_value' );

		if ( ! $checkType || ! $checkValue ) {
			return false;
		}

		return $this->isPresentOnSite( $checkType, $checkValue );
	}

	private function readCondition( $condition, $key, $alternativeKey ) {
		if ( isset( $condition[ $key ] ) ) {
			return $condition[ $key ];
		}

		return isset( $condition[ $alternativeKey ] ) ? $condition[ $alternativeKey ] : null;
	}

	private function isPresentOnSite( $checkType, $checkValue ) {
		switch ( $checkType ) {
			case 'class':
				return class_exists( $checkValue );
			case 'constant':
				return defined( $checkValue );
			case 'function':
				return function_exists( $checkValue );
			default:
				return false;
		}
	}

	private function shouldBeDisplayed( $pluginData ) {
		$glueCheckType  = isset( $pluginData['glue_check_type'] ) ? $pluginData['glue_check_type'] : null;
		$glueCheckValue = isset( $pluginData['glue_check_value'] ) ? $pluginData['glue_check_value'] : null;

		if ( $glueCheckType && $glueCheckValue ) {
			return $this->isPresentOnSite( $glueCheckType, $glueCheckValue );
		}

		if ( $pluginData['slug'] === 'wpml-translation-management' ) {
			return false;
		}

		return true;
	}

	private function appendSiteKeyToDownloadUrl( $url, $siteKey, $siteUrl ) {
		return add_query_arg(
			[
				'site_key' => $siteKey,
				'site_url' => $siteUrl,
			],
			$url
		);
	}

	public function getRecommendationNotices( $existingNotices ) {
		$notices = [];

		foreach ( Storage::getAll() as $repositoryId => $recommendations ) {
			if ( ! $this->hasRepositoryRecommendationRule( $repositoryId ) ) {
				continue;
			}

			if ( ! $this->isRepositoryOwnerPluginActive( $repositoryId ) ) {
				continue;
			}

			$repository = $this->repositories->get( $repositoryId );

			$subscription = $repository->get_subscription();
			if ( ! $subscription ) {
				continue;
			}

			foreach ( $recommendations as $recommendationSlug => $recommendation ) {
				if ( isset( $recommendation['notice_dismissed'] ) && $recommendation['notice_dismissed'] === true ) {
					continue;
				}

				if ( ! $this->isGluePluginActive( $recommendation['glue_plugin_slug'] ) ) {
					$url = $this->appendSiteKeyToDownloadUrl( $recommendation['download_data']['url'], $subscription->get_site_key(), $subscription->get_site_url() );

					$appendedDownloadData = [
						'url'           => $url,
						'slug'          => $recommendation['download_data']['slug'],
						'repository_id' => $recommendation['download_data']['repository_id'],
						'nonce'         => wp_create_nonce( 'install_plugin_' . $url ),
					];

					$notices[ $repositoryId ][ $recommendationSlug ] = $recommendation;
					$notices[ $repositoryId ][ $recommendationSlug ]['download_data'] = $appendedDownloadData;
				} else {
					Storage::delete( $recommendationSlug, $repositoryId );
				}
			}
		}

		return array_merge( $existingNotices, $notices );
	}

	private function getPluginData( $plugin ) {
		$pluginSlug     = dirname( $plugin );
		$gluePluginData = $this->getActivatedPluginGlue( $pluginSlug );

		if ( ! $gluePluginData ) {
			foreach ( $this->getRepositoryIdsForRecommendations() as $repositoryId ) {
				if ( isset( $this->settings()[ $repositoryId ]['data']['glue_plugins_mapping'] ) ) {
					$gluePluginsMapping = $this->settings()[ $repositoryId ]['data']['glue_plugins_mapping'];

					if ( isset( $gluePluginsMapping[ $pluginSlug ] ) ) {
						$gluePluginSlug = $gluePluginsMapping[ $pluginSlug ]['glue_plugin'];
						$gluePluginData = $this->getGluePluginData( $gluePluginSlug, $gluePluginsMapping[ $pluginSlug ], true );
					}
				}
			}

		}

		return new GluePluginData( $pluginSlug, $gluePluginData );
	}

	private function prepareRecommendedPluginData( $repositoryId, $pluginData, $language, $mappingData = null ) {
		$repository   = $this->repositories->get( $repositoryId );
		$subscription = $repository->get_subscription();
		if ( ! $subscription ) {
			return null;
		}

		$downloadData = [
			'url'           => $pluginData['url'],
			'slug'          => $pluginData['slug'],
			'repository_id' => $repositoryId,
		];


		return [
			'repository_id'               => $repositoryId,
			'glue_check_slug'             => $mappingData ? $mappingData['glue_check_slug'] : $pluginData['glue_check_slug'],
			'glue_check_name'             => $mappingData ? $mappingData['glue_check_name'] : $pluginData['glue_check_name'],
			'glue_plugin_name'            => $pluginData['name'],
			'glue_plugin_slug'            => $pluginData['slug'],
			'recommendation_notification' => $this->getNotificationForLanguage( $mappingData ?: $pluginData, $language ),
			'download_data'               => $downloadData,
		];
	}
}
