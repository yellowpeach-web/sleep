<?php
class WPML_Requirements {
	private $active_plugins       = array();
	private $disabled_plugins     = array();
	private $missing_requirements = array();

	const ACCOUNT_DOWNLOADS_URL = 'https://app.wpml.org/account/downloads';

	private $plugins = array(
		'wpml-media-translation'      => array(
			'version' => '2.1.24',
			'name'    => 'WPML Media Translation',
		),
		'wpml-string-translation'     => array(
			'version' => '2.5.2',
			'name'    => 'WPML String Translation',
		),
		'wpml-translation-management' => array(
			'version' => '2.2.7',
			'name'    => 'WPML Translation Management',
		),
		'woocommerce-multilingual'    => array(
			'version' => '4.7.0',
			'name'    => 'WooCommerce Multilingual',
			'url'     => self::ACCOUNT_DOWNLOADS_URL,
		),
		'gravityforms-multilingual'   => array(
			'name' => 'GravityForms Multilingual',
			'url'  => self::ACCOUNT_DOWNLOADS_URL,
		),
		'buddypress-multilingual'     => array(
			'name' => 'BuddyPress Multilingual',
			'url'  => self::ACCOUNT_DOWNLOADS_URL,
		),
		'wp-seo-multilingual'         => array(
			'name' => 'Yoast SEO Multilingual',
			'url'  => self::ACCOUNT_DOWNLOADS_URL,
		),
	);

	private $modules = array(
		'page-builders'                  => array(
			'url'          => '#',
			'requirements' => array(
				'wpml-string-translation',
			),
		),
		'gravityforms'                   => array(
			'url'          => '#',
			'requirements' => array(
				'gravityforms-multilingual',
				'wpml-string-translation',
			),
		),
		'buddypress'                     => array(
			'url'          => '#',
			'requirements' => array(
				'buddypress-multilingual',
			),
		),
		'bb-plugin'                      => array(
			'url'          => '#',
			'requirements' => array(
				'wpml-string-translation',
			),
		),
		'elementor-plugin'               => array(
			'url'          => '#',
			'requirements' => array(
				'wpml-string-translation',
			),
		),
		'wordpress-seo'                  => array(
			'url'          => '#',
			'requirements' => array(
				'wp-seo-multilingual',
			),
		),
	);

	public function __construct() {
		$this->plugins = $this->tagRequirementUrls( $this->plugins );
		$this->modules = $this->tagRequirementUrls( $this->modules );

		if ( function_exists( 'get_plugins' ) ) {
			$installed_plugins = get_plugins();
			foreach ( $installed_plugins as $plugin_file => $plugin_data ) {
				$plugin_slug = $this->get_plugin_slug( $plugin_data );
				if ( is_plugin_active( $plugin_file ) ) {
					$this->active_plugins[ $plugin_slug ] = $plugin_data;
				} else {
					$this->disabled_plugins[ $plugin_slug ] = $plugin_file;
				}
			}
		}
	}

	private function tagRequirementUrls( array $items ) {
		foreach ( $items as $key => $item ) {
			if ( isset( $item['url'] ) && is_string( $item['url'] ) ) {
				$items[ $key ]['url'] = \WPML\OutboundLinks\OutboundLinks::to(
					$item['url'],
					array(
						'medium'   => 'notice',
						'campaign' => 'requirements',
					)
				);
			}
		}

		return $items;
	}

	public function is_plugin_active( $plugin_slug ) {
		return array_key_exists( $plugin_slug, $this->active_plugins );
	}

	public function get_plugin_slug( array $plugin_data ) {
		$plugin_slug = null;
		if ( array_key_exists( 'Plugin Slug', $plugin_data ) && $plugin_data['Plugin Slug'] ) {
			$plugin_slug = $plugin_data['Plugin Slug'];
		} elseif ( array_key_exists( 'TextDomain', $plugin_data ) && $plugin_data['TextDomain'] ) {
			$plugin_slug = $plugin_data['TextDomain'];
		} elseif ( array_key_exists( 'Name', $plugin_data ) && $plugin_data['Name'] ) {
			$plugin_slug = $plugin_data['Name'];
		}

		return $plugin_slug;
	}

	public function get_missing_requirements() {
		return $this->missing_requirements;
	}

	public function get_requirements( $type, $slug ) {
		$missing_plugins = $this->get_missing_plugins_for_type( $type, $slug );

		$requirements = array();

		if ( $missing_plugins ) {
			foreach ( $this->get_components_requirements_by_type( $type, $slug ) as $plugin_slug ) {
				$requirement            = $this->get_plugin_data( $plugin_slug );
				$requirement['missing'] = false;
				if ( in_array( $plugin_slug, $missing_plugins, true ) ) {
					$requirement['missing'] = true;

					if ( array_key_exists( $plugin_slug, $this->disabled_plugins ) ) {
						$requirement['disabled']         = true;
						$requirement['plugin_file']      = $this->disabled_plugins[ $plugin_slug ];
						$requirement['activation_nonce'] = wp_create_nonce( 'activate_' . $this->disabled_plugins[ $plugin_slug ] );
					}

					$this->missing_requirements[] = $requirement;
				}
				$requirements[] = $requirement;
			}
		}

		return $requirements;
	}

	function get_plugin_data( $slug ) {
		if ( array_key_exists( $slug, $this->plugins ) ) {
			return $this->plugins[ $slug ];
		}

		return array();
	}

	private function get_missing_plugins_for_type( $type, $slug ) {
		$requirements_keys   = $this->get_components_requirements_by_type( $type, $slug );
		$active_plugins_keys = array_keys( $this->active_plugins );

		return array_diff( $requirements_keys, $active_plugins_keys );
	}

	private function get_components() {
		return apply_filters( 'wpml_requirements_components', $this->modules );
	}

	private function get_components_by_type( $type, $slug ) {
		$components = $this->get_components();
		if ( array_key_exists( $type, $components ) ) {
			return $components[ $type ];
		}
		if ( array_key_exists( $slug, $components ) ) {
			return $components[ $slug ];
		}

		return array();
	}

	private function get_components_requirements_by_type( $type, $slug ) {
		$components_requirements = $this->get_components_by_type( $type, $slug );
		$requirements            = array();

		if ( array_key_exists( 'requirements', $components_requirements ) ) {
			$requirements = $components_requirements['requirements'];
		}

		return $requirements;
	}
}
