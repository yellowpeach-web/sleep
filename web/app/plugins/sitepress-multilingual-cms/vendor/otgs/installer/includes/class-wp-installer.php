<?php

use OTGS\Installer\Collection;
use OTGS\Installer\Rest\Push;
use OTGS\Installer\Recommendations\RecommendationsManager;
use OTGS\Installer\CommercialTab\DownloadFilter;
use OTGS\Installer\CommercialTab\PlanPlugins;
use OTGS\Installer\CommercialTab\SectionsManager;
use OTGS\Installer\Recommendations\Storage;
use OTGS\Installer\Settings;
use OTGS\Installer\Api\SiteUrl;
use OTGS\Installer\FP\Obj;
use OTGS\Installer\Subscription\SubscriptionManagerFactory;
use OTGS\Installer\Subscription_Warning_Message;
use OTGS\Installer\Upgrade\IncludeAutoUpgrade;
use OTGS\Installer\WpmlOrg\WpmlOrgOrigin;

class WP_Installer {

	const COMPACT_BOX_RENAMED_PRODUCTS = [
		'multilingual-cms'    => 'wpml-multilingual-cms-2',
		'multilingual-cms-2'  => 'wpml-multilingual-cms-2',
		'multilingual-agency' => 'wpml-multilingual-agency-2',
		'multilingual-blog-2' => 'wpml-multilingual-blog-copy',
	];
	const TOOLSET_TYPES = 'Toolset Types';
	const LEGACY_FREE_TYPES_SUBSCRIPTION_ID = 5495;
	const GRACE_TIME = MONTH_IN_SECONDS;

	const HARDCODED_SITE_KEY_REFUSAL = 'otgs_installer_hardcoded_key_refusal_';
	const HARDCODED_SITE_KEY_BACKOFF = HOUR_IN_SECONDS;
	const CHECK_ORDER_STATUS_ACTION = 'installer_check_order_status';

	protected static $_instance = null;

	private $settings = array();

	private $subscriptions_as_read;

	const SUBSCRIPTION_FIELDS = array( 'subscription', 'site_key_url', 'last_successful_subscription_fetch' );

	private $repositories = array();

	protected $api_debug = '';

	private $config = array();

	protected $_plugins_renew_warnings = array();

	private $admin_messages = array();

	private $ajax_messages = array();

	private $_using_icl = false;
	private $_wpml_version = false;

	private $package_source = array();

	private $plugin_finder;

	public $installer_embedded_plugins;

	const SITE_KEY_VALIDATION_SOURCE_OTHER = 0;
	const SITE_KEY_VALIDATION_SOURCE_DOWNLOAD_SPECIFIC = 1;
	const SITE_KEY_VALIDATION_SOURCE_DOWNLOAD_REPORT = 2;
	const SITE_KEY_VALIDATION_SOURCE_REGISTRATION = 3;
	const SITE_KEY_VALIDATION_SOURCE_REVALIDATION = 4;
	const SITE_KEY_VALIDATION_SOURCE_UPDATES_CHECK = 5;
	const SITE_KEY_VALIDATION_SOURCE_REVALIDATION_DAILY = 6;

	public $dependencies;

	private $components_setting;

	private $repositories_already_refreshed = false;

	private $feed_read_failed = false;

	private $products_config_xml;

	private $default_products;

	private $products_manager;

	private $recommendations_manager;

	private $download_http_logger;

	public static function instance() {
		if ( is_null( self::$_instance ) ) {
			self::$_instance = new self();
		}

		return self::$_instance;
	}

	public function __construct() {
		add_action( 'admin_notices', array( $this, 'show_admin_messages' ) );

		add_action( 'admin_init', array( $this, 'load_embedded_plugins' ), 0 );
		add_action( 'admin_init', array( $this, 'load_hardcoded_site_keys' ), 0 );

		add_action( 'otgs_installer_site_key_update', array( $this, 'forget_hardcoded_site_key_refusal' ) );

		add_action( 'admin_menu', array( $this, 'menu_setup' ) );
		add_action( 'network_admin_menu', array( $this, 'menu_setup' ) );
		add_filter( 'plugins_api', array( $this, 'custom_plugins_api_call' ), 10, 3 );

		$this->load_repositories_list();
		$this->products_config_xml = $this->prepare_products_config_xml();
		$this->products_manager    = $this->prepare_products_manager();

		$this->config['plugins_install_tab'] = false;

		add_action( 'init', array( $this, 'init' ) );
		add_action( 'init', array( $this, 'load_locale' ) );
	}

	public function __get( $name ) {
		if ( 'settings' === $name ) {
			return $this->settings();
		}
	}

	private function settings() {
		if ( empty( $this->settings ) ) {
			$this->get_settings();
		}

		return $this->settings;
	}

	private static function isFreeToolsetTypes( $name, $plugin ) {
		return 'Toolset Types' === $name && version_compare( $plugin['Version'], '3.0', '<' );
	}

	public function get_repositories() {
		return $this->repositories;
	}

	public function get_products_manager() {
		return $this->products_manager;
	}

	public function set_config( $key, $value ) {
		$this->config[ $key ] = $value;
	}

	public function init() {
		global $pagenow;

		$this->dependencies = new Installer_Dependencies;
		if ( $this->dependencies->php_libraries_missing() ) {
			return false;
		}

		if ( ! function_exists( 'get_plugins' ) ) {
			require_once ABSPATH . 'wp-admin/includes/plugin.php';
		}

		$forced_check = isset( $_GET['force-check'] ) && $_GET['force-check'] == 1 && current_user_can( 'manage_options' );
		if ( $forced_check || Settings::requires_update() ) {
			$this->refresh_subscriptions_data( $forced_check );
			$this->refresh_repositories_data();
		}

		$this->_using_icl    = function_exists( 'wpml_site_uses_icl' ) && wpml_site_uses_icl();
		$this->_wpml_version = defined( 'ICL_SITEPRESS_VERSION' ) ? ICL_SITEPRESS_VERSION : '';

		wp_register_script( 'installer-admin', $this->res_url() . '/res/js/admin.js', array( 'jquery' ), $this->version() );

		if ( is_multisite() || ! $this->is_installer_running_on_otgs_plugin() || ( $this->is_commercial_page() && $this->is_installer_running_on_otgs_plugin() ) || $this->is_wpml_activate_update_page() ) {
			$this->enqueue_admin_assets();
		}

		if ( $this->is_commercial_page() || $this->is_wpml_activate_update_page() ) {
			wp_enqueue_script( 'expired-notice', $this->res_url() . '/dist/js/expired-notice/app.js', [], $this->version() );
		}

		wp_enqueue_script( 'installer-dismiss-nag', $this->res_url() . '/res/js/dismiss-nag.js', array( 'jquery' ), $this->version(), true );
		wp_enqueue_script( 'install-recommended_plugin', $this->res_url() . '/res/js/install_recommended_plugin.js', array( 'jquery' ), $this->version(), true );

		$translation_array = array(
			'installing' => __( 'Installing %s', 'installer' ),
			'updating'   => __( 'Updating %s', 'installer' ),
			'activating'        => __( 'Activating %s', 'installer' ),
			'download_failed'   => __( 'Download failed', 'installer' ),
			'activation_failed' => __( 'Activation failed', 'installer' ),
		);

		wp_localize_script( 'installer-admin', 'installer_strings', $translation_array );

		if ( $pagenow == 'plugins.php' ) {
			add_action( 'admin_notices', array( $this, 'setup_plugins_page_notices' ) );
			add_action( 'admin_notices', array( $this, 'setup_plugins_renew_warnings' ), 10 );
			add_action( 'admin_notices', array( $this, 'queue_plugins_renew_warnings' ), 20 );

			add_action( 'admin_init', array( $this, 'setup_plugins_action_links' ) );

			wp_enqueue_script( 'installer-plugins', $this->res_url() . '/res/js/plugins.js', array( 'jquery' ), $this->version() );
			wp_enqueue_style( 'installer-admin-notices', $this->res_url() . '/res/css/admin-notices.css', array( 'common' ), $this->version() );
		}

		if ( $this->is_repositories_page() ) {
			add_action( 'admin_init', array( $this, 'validate_repository_subscription' ) );
		}
		add_action( 'admin_post_' . self::CHECK_ORDER_STATUS_ACTION, array( $this, 'check_order_status_from_notice' ) );

		if ( defined( 'DOING_AJAX' ) ) {
			add_action( 'wp_ajax_installer_download_plugin', array( $this, 'download_plugin_ajax_handler' ) );
			add_action( 'wp_ajax_installer_activate_plugin', array( $this, 'activate_plugin' ) );
		}

		if ( $pagenow === 'update.php' ) {
			if ( isset( $_GET['action'] ) && $_GET['action'] === 'update-selected' ) {
				add_action( 'admin_head', array( $this, 'plugin_upgrade_custom_errors' ) );
			} else {
				add_action( 'all_admin_notices', array( $this, 'plugin_upgrade_custom_errors' ) );
			}
		}

		if ( defined( 'DOING_AJAX' ) ) {
			add_action( 'wp_ajax_update-plugin', array(
				$this,
				'plugin_upgrade_custom_errors'
			), 0 );
		}

		$repositories_factory          = new \OTGS_Installer_Repositories_Factory();
		$this->recommendations_manager = new RecommendationsManager(
			$repositories_factory->create( $this ),
			new Storage()
		);
		$this->recommendations_manager->addHooks();

		include_once $this->plugin_path() . '/includes/class-installer-theme.php';

		$package_source_file = $this->plugin_path() . '/installer-source.json';
		if ( file_exists( $package_source_file ) ) {
			WP_Filesystem();
			global $wp_filesystem;
			$this->package_source = json_decode( $wp_filesystem->get_contents( $package_source_file ) );
		}

		add_action( 'rest_api_init', Push::class . '::register_routes' );

		do_action( 'otgs_installer_initialized' );
	}

	public function get_last_subscriptions_refresh() {
		if ( isset( $this->settings()['last_subscriptions_update'] ) ) {
			return $this->settings()['last_subscriptions_update'];
		}

		return 0;
	}

	private function prepare_products_manager() {
		return OTGS_Products_Manager_Factory::create(
			$this->products_config_xml,
			otgs_installer_get_logger_storage()
		);
	}

	private function prepare_products_config_xml() {
		return new OTGS_Products_Config_Xml( $this->get_xml_config_file() );
	}

	private function is_installer_running_on_otgs_plugin() {
		return ( defined( 'ICL_PLUGIN_PATH' ) && false !== strpos( $this->plugin_path(), (string) realpath( ICL_PLUGIN_PATH ) ) )
		       || ( defined( 'TYPES_ABSPATH' ) && false !== strpos( $this->plugin_path(), (string) realpath( TYPES_ABSPATH ) ) )
		       || ( defined( 'WCML_PLUGIN_PATH' ) && false !== strpos( $this->plugin_path(), (string) realpath( WCML_PLUGIN_PATH ) ) );
	}

	public static function is_products_screen() {
		global $pagenow;

		$is_commercial_tab  = 'plugin-install.php' === $pagenow && isset( $_GET['tab'] ) && 'commercial' === $_GET['tab'];
		$is_activate_update = isset( $_GET['page'] ) && 'wpml-activate-update' === $_GET['page'];

		return $is_commercial_tab || $is_activate_update;
	}

	private function is_commercial_page() {
		global $pagenow;

		return $pagenow === 'plugin-install.php' && isset( $_GET['tab'] ) && $_GET['tab'] === 'commercial';
	}

	private function is_wpml_activate_update_page() {
		return isset( $_GET['page'] ) && $_GET['page'] === 'wpml-activate-update';
	}

	public function log( $message ) {
		if ( file_exists( ABSPATH . 'wp-admin/includes/file.php' ) ) {
			require_once ABSPATH . 'wp-admin/includes/file.php';
			WP_Filesystem();
			global $wp_filesystem;
			if ( defined( 'WPML_INSTALLER_LOGGING' ) && WPML_INSTALLER_LOGGING ) {
				$wp_filesystem->put_contents( $this->plugin_path() . '/installer.log', current_time( 'mysql' ) . "\t" . $message . "\n" );
			}
		}
	}

	public function register_admin_message( $text, $type = 'updated' ) {
		$this->admin_messages[] = array( 'text' => $text, 'type' => $type );
		if ( defined( 'DOING_AJAX' ) ) {
			$this->ajax_messages[] = '<p>' . $text . '</p>';
		}
	}

	public function show_admin_messages() {
		if ( ! empty( $this->admin_messages ) ) {
			$types = array( 'error', 'updated', 'notice' );
			foreach ( $this->admin_messages as $message ) {
				$class = in_array( $message['type'], $types, true ) ? $message['type'] : 'updated';
				?>
                <div class="<?php
				echo $class ?>">
                    <p>
						<?php
						echo $message['text'] ?>
                    </p>
                </div>
				<?php
			}
		}
	}

	public function load_locale() {
		if ( function_exists( 'get_user_locale' ) ) {
			$locale = get_user_locale();
		} else {
			$locale = get_locale();
		}
		$locale  = apply_filters( 'plugin_locale', $locale, 'installer' );
		$mo_file = $this->plugin_path() . '/locale/installer-' . $locale . '.mo';

		load_textdomain( 'installer', $mo_file );
	}

	public function load_embedded_plugins() {
		if ( file_exists( $this->plugin_path() . '/embedded-plugins' ) ) {
			include_once $this->plugin_path() . '/embedded-plugins/embedded-plugins.class.php';
			if ( class_exists( 'Installer_Embedded_Plugins' ) ) {
				$this->installer_embedded_plugins = new Installer_Embedded_Plugins();
			}
		}
	}

	public function menu_setup() {
		global $pagenow;

		if ( is_multisite() && ! is_network_admin() ) {
			$this->menu_multisite_redirect();
			add_options_page(
				__( 'Installer', 'installer' ),
				__( 'Installer', 'installer' ),
				'install_plugins',
				'installer',
				array(
					$this,
					'show_products',
				)
			);
		} else {
			if ( $this->config['plugins_install_tab'] && is_admin() && $pagenow === 'plugin-install.php' ) {
				add_filter( 'install_plugins_tabs', array( $this, 'add_install_plugins_tab' ) );
				add_action( 'install_plugins_commercial', array( $this, 'show_products' ) );
			}
		}
	}

	public static function menu_url() {
		if ( is_multisite() ) {
			if ( is_network_admin() ) {
				$url = network_admin_url( 'plugin-install.php?tab=commercial' );
			} else {
				$url = admin_url( 'options-general.php?page=installer' );
			}
		} else {
			$url = admin_url( 'plugin-install.php?tab=commercial' );
		}

		return $url;
	}

	private function menu_multisite_redirect() {
		global $pagenow;

		if ( $pagenow === 'plugin-install.php' && isset( $_GET['tab'] ) && $_GET['tab'] === 'commercial' ) {
			wp_redirect( $this->menu_url() );
			exit;
		}
	}

	public function setup_plugins_action_links() {
		$plugins = get_plugins();

		$repositories_plugins = array();

		if ( ! empty( $this->settings()['repositories'] ) ) {
			foreach ( $this->settings()['repositories'] as $repository_id => $repository ) {
				foreach ( $repository['data']['packages'] as $package ) {
					if ( array_key_exists( 'products', $package ) ) {
						foreach ( $package['products'] as $product ) {
							if ( array_key_exists( 'plugins', $product ) ) {
								foreach ( $product['plugins'] as $plugin_slug ) {
									if ( ! $this->isPluginAvailableInRepositoryDownloads( $repository_id, $plugin_slug ) ) {
										continue;
									}

									$download = $this->settings()['repositories'][ $repository_id ]['data']['downloads']['plugins'][ $plugin_slug ];

									if ( ! isset( $repositories_plugins[ $repository_id ][ $download['slug'] ] ) ) {
										$repositories_plugins[ $repository_id ][ $download['slug'] ] = array(
											'name'       => $download['name'],
											'registered' => $this->plugin_is_registered( $repository_id, $download['slug'] ) ? 1 : 0
										);
									}
								}
							} else {
								$this->refresh_repositories_data();
							}
						}
					} else {
						$this->refresh_repositories_data();
					}
				}

				foreach ( $plugins as $plugin_id => $plugin ) {
					$wp_plugin_slug = dirname( $plugin_id );
					if ( empty( $wp_plugin_slug ) ) {
						$wp_plugin_slug = basename( $plugin_id, '.php' );
					}

					foreach ( $repositories_plugins as $repository_id => $r_plugins ) {
						foreach ( $r_plugins as $slug => $r_plugin ) {
							if ( $wp_plugin_slug === $slug || $r_plugin['name'] === $plugin['Name'] || $r_plugin['name'] === $plugin['Title'] ) {

								$plugin_finder = $this->get_plugin_finder();
								$plugin_obj    = $plugin_finder->get_plugin( $slug, (string) $repository_id );

								if ( $plugin_obj && $plugin_obj->get_external_repo() && $plugin_obj->is_lite() ) {
									continue;
								}

								if ( $r_plugin['registered'] ) {
									remove_filter( 'plugin_action_links_' . $plugin_id, array(
										$this,
										'plugins_action_links_not_registered'
									) );

									add_filter( 'plugin_action_links_' . $plugin_id, array(
										$this,
										'plugins_action_links_registered'
									) );
								} else {
									if ( $this->plugin_is_registered( $plugin_obj->get_external_repo(), $slug ) || $this->plugin_is_registered( 'wpml', $slug ) ) {
										continue;
									}

									remove_filter( 'plugin_action_links_' . $plugin_id, array(
										$this,
										'plugins_action_links_registered'
									) );

									add_filter( 'plugin_action_links_' . $plugin_id, array(
										$this,
										'plugins_action_links_not_registered'
									) );

									if ( $this->should_display_types_upgrade_link( $r_plugin['name'], $plugin['Version'] ) ) {
										add_filter( 'plugin_action_links_' . $plugin_id, array( $this, 'types_upgrade_link' ) );
									}
								}
							}
						}
					}
				}
			}
		}
	}

	private function should_display_types_upgrade_link( $name, $version ) {
		return $name === self::TOOLSET_TYPES && version_compare( $version, '3.0', '<' );
	}

	public function types_upgrade_link( $links ) {
		$links[] = '<a style="color: #55AA55" target="_blank" href="' . esc_url( 'https://toolset.com/buy/?utm_source=typesplugin&utm_campaign=moving-types-to-toolset&utm_medium=plugins-page&utm_term=upgrade-link' ) . '">' . __( 'Upgrade', 'installer' ) . '</a>';

		return $links;
	}

	public function plugins_action_links_registered( $links ) {
		$links[] = '<a href="' . $this->menu_url() . '">' . __( 'Registered', 'installer' ) . '</a>';

		return $links;
	}

	public function plugins_action_links_not_registered( $links ) {
		$links[] = '<a href="' . $this->menu_url() . '">' . __( 'Register', 'installer' ) . '</a>';

		return $links;
	}

	public function plugin_is_registered( $repository_id, $slug ) {
		$registered = false;

		if ( $this->repository_has_valid_subscription( $repository_id ) ) {
			$subscription_type = $this->get_subscription_type_for_repository( $repository_id );
			$r_plugins         = array();

			if ( $subscription_type === self::LEGACY_FREE_TYPES_SUBSCRIPTION_ID && $slug === 'types' ) {
				return true;
			}

			foreach ( $this->settings()['repositories'][ $repository_id ]['data']['packages'] as $package ) {
				foreach ( $package['products'] as $product ) {
					if ( ! array_key_exists( 'subscription_type_equivalent', $product ) ) {
						$product['subscription_type_equivalent'] = '';
					}

					if (
						$product['subscription_type'] === (int) $subscription_type || (int) $product['subscription_type_equivalent'] === (int) $subscription_type || $this->have_superior_subscription( $subscription_type, $product )
					) {
						foreach ( $product['plugins'] as $plugin_slug ) {
							if ( ! $this->isPluginAvailableInRepositoryDownloads( $repository_id, $plugin_slug ) ) {
								continue;
							}

							$download                       = $this->settings()['repositories'][ $repository_id ]['data']['downloads']['plugins'][ $plugin_slug ];
							$r_plugins[ $download['slug'] ] = $download['slug'];
						}
					}
				}
			}

			$registered = isset( $r_plugins[ $slug ] );
		}


		return $registered;
	}

	public function version() {
		return WP_INSTALLER_VERSION;
	}

	public function plugin_path() {
		return untrailingslashit( plugin_dir_path( dirname( __FILE__ ) ) );
	}

	private function theme_folder_url() {
		$theme_url = isset( $this->config['in_theme'] ) && 'stylesheet' === $this->config['in_theme'] ? get_stylesheet_directory_uri() : get_template_directory_uri();

		return untrailingslashit( $theme_url . '/' . $this->config['in_theme_folder'] );
	}

	public function plugin_url() {
		if ( isset( $this->config['in_theme_folder'] ) && ! empty( $this->config['in_theme_folder'] ) ) {
			$url = $this->theme_folder_url();
		} else {
			$url = untrailingslashit( plugins_url( '/', dirname( __FILE__ ) ) );
		}

		return $url;
	}

	public function vendor_url() {
		$url = null;

		$site_url    = get_site_url();
		$site_path   = ABSPATH;
		$vendor_path = dirname( dirname( $this->plugin_path() ) );

		return str_replace( '\\', '/', $site_url . '/' . substr( $vendor_path, strlen( $site_path ) ) );
	}

	public function get_embedded_at() {
		$embedded_at = str_replace( array( get_stylesheet_directory_uri(), get_template_directory_uri(), plugins_url() ), '', $this->plugin_url() );
		preg_match( '/\/(.*?)\//', $embedded_at, $matches );

		return isset( $matches[1] ) ? $matches[1] : '';
	}

	public function is_repositories_page() {
		global $pagenow;

		if ( $pagenow == 'plugin-install.php' && isset( $_GET['tab'] ) && $_GET['tab'] == 'commercial' ) {
			return true;
		}

		return is_multisite() && ! is_network_admin()
		       && $pagenow == 'options-general.php' && isset( $_GET['page'] ) && $_GET['page'] == 'installer';
	}

	public function res_url() {
		if ( isset( $this->config['in_theme_folder'] ) && ! empty( $this->config['in_theme_folder'] ) ) {
			$url = $this->theme_folder_url();
		} else {
			$url = $this->plugin_url();
		}

		return $url;
	}

	public function save_settings( $settings = null ) {
		if ( null !== $settings ) {
			$this->settings = $settings;
		}

		$this->keep_stored_registrations();
		Settings::save( $this->settings );
		$this->subscriptions_as_read = self::registrations( $this->settings );
	}

	private function keep_stored_registrations() {
		if ( null === $this->subscriptions_as_read || is_multisite() || empty( $this->settings['repositories'] ) ) {
			return;
		}

		$stored = Settings::read_stored();
		if ( empty( $stored['repositories'] ) ) {
			return;
		}

		$copy = self::registrations( $this->settings );
		$row  = self::registrations( $stored );
		foreach ( $row as $repository_id => $stored_registration ) {
			if ( empty( $stored['repositories'][ $repository_id ]['subscription']['key'] )
			     || ! isset( $this->settings['repositories'][ $repository_id ] ) ) {
				continue;
			}

			$as_read = isset( $this->subscriptions_as_read[ $repository_id ] ) ? $this->subscriptions_as_read[ $repository_id ] : serialize( array() );
			$in_copy = isset( $copy[ $repository_id ] ) ? $copy[ $repository_id ] : serialize( array() );
			if ( $in_copy !== $as_read || $stored_registration === $as_read ) {
				continue;
			}

			foreach ( self::SUBSCRIPTION_FIELDS as $field ) {
				if ( isset( $stored['repositories'][ $repository_id ][ $field ] ) ) {
					$this->settings['repositories'][ $repository_id ][ $field ] = $stored['repositories'][ $repository_id ][ $field ];
				} else {
					unset( $this->settings['repositories'][ $repository_id ][ $field ] );
				}
			}
		}
	}

	private static function registrations( $settings ) {
		$registrations = array();
		if ( ! isset( $settings['repositories'] ) || ! is_array( $settings['repositories'] ) ) {
			return $registrations;
		}

		foreach ( $settings['repositories'] as $repository_id => $repository ) {
			$fields = array();
			foreach ( self::SUBSCRIPTION_FIELDS as $field ) {
				if ( isset( $repository[ $field ] ) ) {
					$fields[ $field ] = $repository[ $field ];
				}
			}
			$registrations[ $repository_id ] = serialize( $fields );
		}

		return $registrations;
	}

	public function get_settings( $refresh = false ) {
		if ( $refresh || empty( $this->settings ) ) {
			$this->settings              = Settings::load();
			$this->subscriptions_as_read = self::registrations( $this->settings );

			if ( empty( $this->settings ) ) {
				$this->settings = array(
					'repositories' => array()
				);
			}

			$this->install_default_products_where_none_stored();
		}

		return $this->settings;
	}

	private function install_default_products_where_none_stored() {
		if ( ! $this->products_config_xml || empty( $this->settings['repositories'] ) ) {
			return;
		}

		if ( null === $this->default_products ) {
			$this->default_products = $this->products_config_xml->get_repository_products_default_data();
		}

		foreach ( $this->settings['repositories'] as $repository_id => $repository ) {
			if ( ! empty( $repository['data'] ) || empty( $this->default_products[ $repository_id ] ) ) {
				continue;
			}

			$this->settings['repositories'][ $repository_id ]['data'] = $this->default_products[ $repository_id ];
		}
	}

	public function load_hardcoded_site_keys() {
		$subscriptions = Settings::load_subscriptions();
		if ( ! isset( $subscriptions['repositories'] ) || empty( $subscriptions['repositories'] ) ) {
			return;
		}

		foreach ( $subscriptions['repositories'] as $repository_id => $repository ) {
			if ( $site_key = self::get_repository_hardcoded_site_key( $repository_id ) ) {
				$site_key         = self::sanitize_site_key( $site_key );
				$site_key_missing = empty( $this->settings()['repositories'][ $repository_id ]['subscription']['data'] );
				$site_key_changed = ! $site_key_missing && $this->settings()['repositories'][ $repository_id ]['subscription']['key'] != $site_key;

				$network_key = $this->other_network_site_key( $repository_id, $site_key );
				if ( $network_key ) {
					if ( current_user_can( 'manage_network_options' ) ) {
						$this->admin_messages[] = array(
							'type' => 'error',
							'text' => $this->hardcoded_site_key_differs_from_network_message( $repository_id, $network_key ),
						);
					}
					continue;
				}

				if ( $site_key_missing || $site_key_changed ) {
					$refusal = $this->hardcoded_site_key_refusal( $repository_id, $site_key );
					if ( $refusal ) {
						$this->admin_messages[] = array(
							'type' => 'error',
							'text' => $this->hardcoded_site_key_refusal_message( $repository_id, $refusal ),
						);
						continue;
					}

					if ( ! function_exists( 'get_plugins' ) ) {
						require_once ABSPATH . 'wp-admin/includes/plugin.php';
					}
					$this->load_repositories_list();
					$response = $this->save_site_key(
						array(
							'repository_id' => $repository_id,
							'site_key'      => $site_key,
							'return'        => true,
							'nonce'         => wp_create_nonce( 'save_site_key_' . $repository_id ),
							'automatic'     => true,
						)
					);

					if ( ! empty( $response['postponed'] ) ) {
						continue;
					}

					if ( ! empty( $response['key_not_checked'] ) ) {
						$this->admin_messages[] = array(
							'type' => 'notice',
							'text' => $this->hardcoded_site_key_not_checked_message( $repository_id, $response['reason'] ),
						);
						continue;
					}

					if ( ! empty( $response['error'] ) ) {
						// wpmldev-12310: only Refresh license removes a refused key. The stored copy stays, flagged.
						$this->mark_site_key_as_invalid( $repository_id );
						$this->save_settings();

						$refusal = $this->remember_hardcoded_site_key_refusal( $repository_id, $site_key, $response['error'] );

						$this->admin_messages[] = array(
							'type' => 'error',
							'text' => $this->hardcoded_site_key_refusal_message( $repository_id, $refusal ),
						);
					} else {
						$this->forget_hardcoded_site_key_refusal( $repository_id );

						do_action( 'otgs_installer_site_key_update', $repository_id );
					}
				}
			}
		}
	}

	private function hardcoded_site_key_refusal( $repository_id, $site_key ) {
		$refusal = get_transient( self::HARDCODED_SITE_KEY_REFUSAL . $repository_id );

		if ( ! is_array( $refusal ) || empty( $refusal['site_key'] ) ) {
			return false;
		}

		if ( (string) $refusal['site_key'] !== (string) $site_key
		     || (string) $refusal['site_url'] !== (string) $this->get_installer_site_url( $repository_id ) ) {
			$this->forget_hardcoded_site_key_refusal( $repository_id );

			return false;
		}

		return $refusal;
	}

	private function remember_hardcoded_site_key_refusal( $repository_id, $site_key, $message ) {
		$refusal = array(
			'site_key' => (string) $site_key,
			'site_url' => (string) $this->get_installer_site_url( $repository_id ),
			'message'  => (string) $message,
			'time'     => time(),
		);

		set_transient( self::HARDCODED_SITE_KEY_REFUSAL . $repository_id, $refusal, self::HARDCODED_SITE_KEY_BACKOFF );

		return $refusal;
	}

	public function forget_hardcoded_site_key_refusal( $repository_id ) {
		delete_transient( self::HARDCODED_SITE_KEY_REFUSAL . $repository_id );
	}

	private function hardcoded_site_key_refusal_message( $repository_id, array $refusal ) {
		$said = trim( wp_kses_post( (string) $refusal['message'] ) );
		if ( '' !== $said && ! in_array( substr( $said, -1 ), array( '.', '!', '?' ), true ) ) {
			$said .= '.';
		}

		return sprintf(
		// translators: %1$s the constant's name %2$s the site url %3$s what wpml.org answered
			__( 'The site key defined in %1$s (most likely in wp-config.php) could not be registered for %2$s: %3$s Correct the constant or remove it, then reload this page to try again.', 'installer' ),
			'<strong>OTGS_INSTALLER_SITE_KEY_' . esc_html( strtoupper( $repository_id ) ) . '</strong>',
			'<strong>' . esc_html( (string) $refusal['site_url'] ) . '</strong>',
			$said
		);
	}

	private function hardcoded_site_key_not_checked_message( $repository_id, $reason ) {
		return sprintf(
		// translators: %1$s the constant's name %2$s the connection sentence %3$s what went wrong
			__( 'The site key defined in %1$s (most likely in wp-config.php) was not checked, so this site keeps its registration. %2$s Reason: %3$s', 'installer' ),
			'<strong>OTGS_INSTALLER_SITE_KEY_' . esc_html( strtoupper( $repository_id ) ) . '</strong>',
			$this->connection_sentence( $repository_id, OTGS_Installer_Site_Key_Ajax::host_of( $this->repositories[ $repository_id ]['api-url'] ) ),
			esc_html( rtrim( (string) $reason, ' .' ) ) . '.'
		);
	}

	private function other_network_site_key( $repository_id, $site_key ) {
		if ( ! is_multisite() || is_main_site() ) {
			return false;
		}

		$settings    = $this->settings();
		$network_key = isset( $settings['repositories'][ $repository_id ]['subscription']['key'] )
			? (string) $settings['repositories'][ $repository_id ]['subscription']['key'] : '';

		return '' !== $network_key && $network_key !== (string) $site_key ? $network_key : false;
	}

	private function hardcoded_site_key_differs_from_network_message( $repository_id, $network_key ) {
		return sprintf(
		// translators: %1$s the site's URL %2$s the constant's name %3$s the network's key
			__( 'The site %1$s defines %2$s (most likely in its wp-config.php) with a key that differs from the one this network uses, %3$s. A network uses one key, so this constant was not registered. Remove it, or set it to the network\'s key.', 'installer' ),
			'<strong>' . esc_html( get_site_url() ) . '</strong>',
			'<strong>OTGS_INSTALLER_SITE_KEY_' . esc_html( strtoupper( $repository_id ) ) . '</strong>',
			'<strong>' . esc_html( $network_key ) . '</strong>'
		);
	}

	public static function get_repository_hardcoded_site_key( $repository_id ) {
		$site_key = false;

		$site_key_constant = 'OTGS_INSTALLER_SITE_KEY_' . strtoupper( $repository_id );
		if ( defined( $site_key_constant ) ) {
			$site_key = constant( $site_key_constant );
		}

		return $site_key;
	}

	public function get_installer_site_url( $repository_id = false ) {
		$site_url = new SiteUrl();

		return $site_url->get( $repository_id );
	}

	public function get_registered_site_url( $repository_id ) {
		if ( isset( $this->settings()['repositories'][ $repository_id ]['subscription']['site_url'] ) ) {
			return $this->settings()['repositories'][ $repository_id ]['subscription']['site_url'];
		}

		return null;
	}

	public function get_site_key_nags_config() {
		return isset( $this->config['site_key_nags'] ) ? $this->config['site_key_nags'] : [];
	}

	public function getRepositories() {
		$repositories = [];
		foreach ( $this->repositories as $repositoryId => $repository ) {
			$repositories[] = [ 'repository_id' => $repositoryId ];
		}

		return $repositories;
	}

	public function add_install_plugins_tab( $tabs ) {
		$tabs['commercial'] = __( 'Commercial', 'installer' );

		return $tabs;
	}

	public function load_repositories_list() {
		$config_file = $this->get_xml_config_file();

		if ( $config_file ) {
			$repos = simplexml_load_file( $config_file );

			if ( $repos ) {
				foreach ( $repos as $repo ) {
					$id = strval( $repo->id );

					if ( isset( $this->config['repositories_exclude'] ) && in_array( $id, $this->config['repositories_exclude'] ) ) {
						continue;
					}

					$data['api-url'] = WpmlOrgOrigin::map( strval( $repo->apiurl ) );

					$this->repositories[ $id ] = $data;
					$this->set_predefined_config( $id, 'api-url', 'API_URL' );
				}
			}
		}
	}

	public function get_xml_config_file() {
		$file_name = 'repositories.xml';

		if ( file_exists( $this->get_config_file_path( $file_name ) ) ) {
			return $this->get_config_file_path( $file_name );
		}

		return null;
	}

	private function get_config_file_path( $file_name ) {
		return __DIR__ . '/../' . $file_name;
	}

	private function set_predefined_config( $id, $setting_field, $constant_suffix ) {
		$repo_upper = strtoupper( $id );
		if ( defined( "OTGS_INSTALLER_{$repo_upper}_{$constant_suffix}" ) ) {
			$this->repositories[ $id ][ $setting_field ] = constant( "OTGS_INSTALLER_{$repo_upper}_{$constant_suffix}" );
		}
	}

	public function filter_repositories_list() {
		$this->get_settings();
		if ( ! empty( $this->settings['repositories'] ) ) {
			foreach ( $this->settings['repositories'] as $id => $repo_data ) {
				if ( isset( $this->config['repositories_exclude'] ) && in_array( $id, $this->config['repositories_exclude'] ) ) {
					unset( $this->settings['repositories'][ $id ] );
				}
			}
		}
	}

	public function refresh_subscriptions_data( $user_requested = false ) {
		$this->get_settings();
		$checked = false;

		foreach ( $this->repositories as $repository_id => $data ) {
			$site_key = $this->get_site_key( $repository_id );

			if ( ! $site_key ) {
				continue;
			}

			$checked = true;

			try {
				$subscriptionManagerFactory = $this->get_subscription_manager_factory();
				$subscriptionManager        = $subscriptionManagerFactory->create( $repository_id, $this->repositories[ $repository_id ]['api-url'] );
				list ( $subscription_data, $site_key_data ) = $subscriptionManager->fetch( $site_key, self::SITE_KEY_VALIDATION_SOURCE_REVALIDATION_DAILY, $user_requested );

				if ( ! $subscription_data ) {
					$this->log_subscription_update(
						sprintf( 'Installer could not fetch subscription data for %s: the answer held no subscription data.', $repository_id )
					);

					continue;
				}

				unset( $this->settings['repositories'][ $repository_id ]['subscription']['site_key_invalid'] );
				$this->settings['repositories'][ $repository_id ]['subscription']['data']     = $subscription_data;
				$this->settings['repositories'][ $repository_id ]['subscription']['key_type'] = isset( $site_key_data['type'] )
					? (int) $site_key_data['type'] : OTGS_Installer_Subscription::SITE_KEY_TYPE_PRODUCTION;

				$actualSiteUrl                                                    = $this->get_installer_site_url( $repository_id );
				$this->settings['repositories'][ $repository_id ]['site_key_url'] = $actualSiteUrl;

				$this->setLastSuccessSubscriptionFetch( $repository_id );
				$this->log_subscription_update( "Subscriptions updated successfully." );
			} catch ( OTGS_Installer_Fetch_Subscription_Postponed_Exception $e ) {
				if ( $user_requested ) {
					$this->register_site_key_not_checked_message( $repository_id, $e->getMessage() );
				}
			} catch ( Exception $e ) {
				if ( OTGS_Installer_Fetch_Subscription_Exception::is_site_key_refused( $e ) ) {
					$this->mark_site_key_as_invalid( $repository_id );
					$this->log_subscription_update(
						$repository_id . ': Invalid site key for the current site. (API: '
						. OTGS_Installer_Fetch_Subscription_Exception::api_error_text( $e ) . ')'
					);
					$this->register_site_key_refused_message( $repository_id );
				} else {
					$evidence = $e instanceof OTGS_Installer_Fetch_Subscription_Exception ? $e->get_evidence() : '';
					$this->log_subscription_update( $repository_id . ': ' . $e->getMessage() . ( $evidence ? ' (' . $evidence . ')' : '' ) );
					$this->register_site_key_not_checked_message( $repository_id, $e->getMessage() );
				}
			}
		}

		if ( $checked ) {
			$this->settings['last_subscriptions_update'] = time();
			$this->save_settings();
		}
	}

	protected function get_subscription_manager_factory() {
		return new SubscriptionManagerFactory();
	}

	protected function register_site_key_refused_message( $repository_id ) {
		$message = sprintf(
			__( 'The %s site key on this site was not accepted, so no updates are offered for it. Register the site again to get updates.', 'installer' ),
			strtoupper( $repository_id )
		);

		$message .= ' <a href="' . esc_url( self::menu_url() ) . '">'
		            . esc_html__( 'Go to the registration screen', 'installer' ) . '</a>';

		$this->register_admin_message( $message, 'error' );
	}

	protected function register_site_key_not_checked_message( $repository_id, $reason ) {
		$message = sprintf(
			__( 'Installer could not check the %1$s site key on this site. The updates below are the ones from the last check that worked. Reason: %2$s', 'installer' ),
			strtoupper( $repository_id ),
			esc_html( rtrim( $reason, ' .' ) ) . '.'
		);

		$message .= ' <a href="' . esc_url( admin_url( 'update-core.php?force-check=1' ) ) . '">'
		            . esc_html__( 'Check again', 'installer' ) . '</a>';

		$this->register_admin_message( $message, 'notice' );
	}

	/**
	 * The API refused this key: the subscription data and its update offers go, and the key is
	 * flagged, so the site reads as not validly registered and shows the "no longer valid" warning
	 * (wpmldev-6786).
	 *
	 * wpmldev-12310: the key itself stays unless $drop_key. A refusal answers for the URL the request
	 * sent, and a check can send the wrong one (wpmldev-11891: wp-cron on a secondary language's domain);
	 * the next check that succeeds clears the flag. Only WPML's Refresh license drops the key: it posts
	 * drop_refused_key=1 to update_site_key. A merely unreachable API never reaches here (the callers
	 * gate on is_site_key_refused()).
	 *
	 * wpmldev-8965: a dropped key takes the time of its last answer with it, as remove_site_key() does.
	 * Kept, that time aged with no key left to check, and a week later the site read "cannot connect".
	 * A key that stays has just had an answer, so the refusal sets that time.
	 *
	 * @param string $repository_id
	 * @param bool   $drop_key
	 */
	public function mark_site_key_as_invalid( $repository_id, $drop_key = false ) {
		$this->get_settings();

		if ( empty( $this->settings['repositories'][ $repository_id ]['subscription']['key'] ) ) {
			return;
		}

		if ( $drop_key ) {
			unset( $this->settings['repositories'][ $repository_id ]['subscription']['key'] );
			unset( $this->settings['repositories'][ $repository_id ]['last_successful_subscription_fetch'] );
		} else {
			$this->setLastSuccessSubscriptionFetch( $repository_id );
		}
		unset( $this->settings['repositories'][ $repository_id ]['subscription']['data'] );
		unset( $this->settings['repositories'][ $repository_id ]['subscription']['key_type'] );
		$this->settings['repositories'][ $repository_id ]['subscription']['site_key_invalid'] = true;

		$this->clean_plugins_update_cache();
	}

	/**
	 * wpmldev-12310: an update or download attempt that the API answers with a refusal keeps the key and flags
	 * it, as every other call except Refresh license does, so the registration screen says the key is no longer
	 * valid. Any other failure says nothing about the key and changes nothing.
	 *
	 * @param Exception $e             what the subscription fetch threw
	 * @param string    $repository_id
	 *
	 * @return bool true when the API refused the key
	 */
	private function flag_site_key_if_refused( Exception $e, $repository_id ) {
		if ( ! OTGS_Installer_Fetch_Subscription_Exception::is_site_key_refused( $e ) ) {
			return false;
		}

		$this->mark_site_key_as_invalid( $repository_id );
		$this->save_settings();

		return true;
	}

	public function shouldDisplayConnectionIssueMessage( $repositoryId ) {
		$has_key = $this->get_site_key( $repositoryId ) || self::get_repository_hardcoded_site_key( $repositoryId );

		return ( $has_key && time() - $this->getLastSuccessSubscriptionFetch( $repositoryId ) > WEEK_IN_SECONDS )
		       || $this->isUsingProductsFallback( $repositoryId );
	}

	private function getLastSuccessSubscriptionFetch( $repositoryId ) {
		if ( defined( 'OTGS_INSTALLER_OVERRIDE_LAST_SUCCESS_SUBSCRIPTION_FETCH' ) ) {
			return constant( 'OTGS_INSTALLER_OVERRIDE_LAST_SUCCESS_SUBSCRIPTION_FETCH' );
		}

		$subscriptions = Settings::load_subscriptions();
		if ( isset( $subscriptions['repositories'][ $repositoryId ]['last_successful_subscription_fetch'] ) ) {
			return (int) $subscriptions['repositories'][ $repositoryId ]['last_successful_subscription_fetch'];
		} else {
			return time();
		}
	}

	private function isUsingProductsFallback( $repositoryId ) {
		return Settings::is_using_products_fallback( $repositoryId );
	}

	private function setLastSuccessSubscriptionFetch( $repositoryId ) {
		$this->get_settings();
		$this->settings['repositories'][ $repositoryId ]['last_successful_subscription_fetch'] = time();
	}

	private function setUsingProductsFallback( $repositoryId, $value ) {
		$this->get_settings();
		$this->settings['repositories'][ $repositoryId ]['using_products_fallback'] = $value;
	}

	private function log_subscription_update( $message ) {
		$log = new OTGS_Installer_Log();
		$log->set_component( OTGS_Installer_Logger_Storage::COMPONENT_SUBSCRIPTION );
		$log->set_response( $message );
		otgs_installer_get_logger_storage()->add( $log );
	}

	public function get_recommendations( $repository_id ) {
		$subscription = $this->get_subscription( $repository_id );
		if ( $subscription->is_valid() ) {
			return $this->recommendations_manager->getRepositoryPluginsRecommendations();
		}

		return null;
	}

	public function refresh_repositories_data( $bypass_bucket = false ) {
		$skip_refresh = defined( 'OTGS_DISABLE_AUTO_UPDATES' ) && OTGS_DISABLE_AUTO_UPDATES && empty( $_GET['force-check'] ) || $this->repositories_already_refreshed;
		if ( $skip_refresh && ! $bypass_bucket ) {
			if ( empty( $this->settings()['repositories'] ) && $this->is_repositories_page() ) {
				foreach ( $this->repositories as $repository_id => $data ) {
					$repository_names[] = $repository_id;
				}

				$error = sprintf( __( "Installer cannot display the products information because the automatic updating for %s was explicitly disabled with the configuration below (usually in wp-config.php):", 'installer' ), strtoupper( join( ', ', $repository_names ) ) );
				$error .= '<br /><br /><code>define("OTGS_DISABLE_AUTO_UPDATES", true);</code><br /><br />';
				$error .= sprintf( __( "In order to see the products information, please run the %smanual updates check%s to initialize the products list or (temporarily) remove the above code.", 'installer' ), '<a href="' . admin_url( 'update-core.php' ) . '">', '</a>' );

				$this->register_admin_message( $error, 'error' );
			}

			return;
		}
		$this->repositories_already_refreshed = true;
		$this->feed_read_failed               = false;
		$this->get_settings();

		foreach ( $this->repositories as $repository_id => $data ) {
			$products_url = $this->products_manager->get_products_url(
				$repository_id,
				$this->get_repository_site_key( $repository_id ),
				$this->get_installer_site_url( $repository_id ),
				$bypass_bucket
			);

			$response = $this->read_feed_document( $products_url );

			$products_parser = new OTGS_Installer_Products_Parser(
				WP_Installer_Channels(),
				$this->products_config_xml,
				otgs_installer_get_logger_storage()
			);

			if ( is_wp_error( $response ) ) {
				$this->record_products_feed_problem(
					$repository_id,
					$products_url,
					$products_parser,
					OTGS_Installer_Products_Feed_Problem::connection( $response )
				);

				continue;
			}

			if ( $response && isset( $response['response']['code'] ) && $response['response']['code'] == 200 ) {
				$releases_url = $this->products_manager->get_releases_url(
					$repository_id,
					$this->get_repository_site_key( $repository_id ),
					$this->get_installer_site_url( $repository_id ),
					$bypass_bucket
				);
				$releases     = $releases_url ? $this->refresh_releases_data( $repository_id, $releases_url ) : null;

				try {
					$products = $products_parser->get_products_from_response( $products_url, $repository_id, $response, $releases, $releases_url );
					$this->handle_product_parsing_notices( $products_parser->get_product_notices() );
					$this->settings['repositories'][ $repository_id ]['data'] = $products;
					$this->clear_products_feed_problem( $repository_id );
				} catch ( OTGS_Installer_Products_Parsing_Exception $exception ) {
					$this->record_products_feed_problem(
						$repository_id,
						$products_url,
						$products_parser,
						OTGS_Installer_Products_Feed_Problem::body(
							$this->products_feed_body_defect( $response ),
							$exception->getMessage()
						)
					);
				}
			} else {
				$this->record_products_feed_problem(
					$repository_id,
					$products_url,
					$products_parser,
					OTGS_Installer_Products_Feed_Problem::http(
						isset( $response['response']['code'] ) ? $response['response']['code'] : null
					)
				);
			}
			$this->log( sprintf( "Checked for %s updates: %s", $repository_id, $products_url ) );
		}

		if ( empty( $this->settings['repositories'] ) ) {
			$this->settings['repositories'] = array();
		}
		foreach ( $this->settings['repositories'] as $repository_id => $data ) {
			if ( ! in_array( $repository_id, array_keys( $this->repositories ) ) ) {
				unset( $this->settings['repositories'][ $repository_id ] );
			}
		}

		delete_site_transient( 'update_plugins' );

		$read_the_master = $bypass_bucket && ! $this->feed_read_failed;
		if ( $read_the_master ) {
			$this->save_master_releases();
		}

		$this->save_settings();

		if ( $read_the_master ) {
			Settings::mark_updated();
		}

		return $this->ajax_messages;
	}

	private function save_master_releases() {
		foreach ( $this->settings['repositories'] as $repository_id => $repository ) {
			if ( ! isset( $repository['data']['downloads']['plugins'] ) ) {
				continue;
			}

			foreach ( $repository['data']['downloads']['plugins'] as $slug => $download ) {
				if ( isset( $download['channel'] ) && WP_Installer_Channels::CHANNEL_PRODUCTION === $download['channel'] ) {
					$this->settings['repositories'][ $repository_id ]['master_releases'][ $slug ] = [
						'version'       => $download['version'],
						'date'          => $download['date'],
						'release-notes' => $download['release-notes'],
					];
				}
			}
		}
	}

	public function get_master_release( $repository_id, $slug, $version ) {
		$settings = $this->settings();

		if ( ! isset( $settings['repositories'][ $repository_id ]['master_releases'][ $slug ] ) ) {
			return null;
		}

		$release = $settings['repositories'][ $repository_id ]['master_releases'][ $slug ];

		return $version === $release['version'] ? $release : null;
	}

	private function record_products_feed_problem( $repository_id, $products_url, $products_parser, OTGS_Installer_Products_Feed_Problem $problem ) {
		$this->feed_read_failed = true;
		$this->register_admin_message( $this->products_feed_problem_message( $repository_id, $problem ), 'error' );
		$this->store_log( $products_url, null, OTGS_Installer_Logger_Storage::COMPONENT_REPOSITORIES, $problem->describe() );

		$this->setUsingProductsFallback( $repository_id, true );
		$this->settings['repositories'][ $repository_id ]['products_feed_problem'] = array(
			'kind'    => $problem->kind(),
			'detail'  => $problem->detail(),
			'message' => $problem->message(),
			'code'    => $problem->code(),
		);

		$this->use_products_fallback_if_no_data( $repository_id, $products_parser );
	}

	private function clear_products_feed_problem( $repository_id ) {
		$this->setUsingProductsFallback( $repository_id, false );
		unset( $this->settings['repositories'][ $repository_id ]['products_feed_problem'] );
	}

	private function products_feed_problem_message( $repository_id, OTGS_Installer_Products_Feed_Problem $problem ) {
		if ( OTGS_Installer_Products_Feed_Problem::KIND_CONNECTION === $problem->kind() ) {
			$message = sprintf( __( "Installer cannot contact our updates server to get information about the available products and check for new versions. If you are seeing this message for the first time, you can ignore it, as it may be a temporary communication problem. If the problem persists and your WordPress admin is slowing down, you can disable automated version checks. Add the following line to your wp-config.php file:",
				'installer' ), strtoupper( $repository_id ) );
			$message .= '<br /><br /><code>define("OTGS_DISABLE_AUTO_UPDATES", true);</code>';

			return $message;
		}

		return __( "Information about new versions is invalid. It may be a temporary communication problem, please check for updates again.", 'installer' );
	}

	private function products_feed_body_defect( $response ) {
		$body = wp_remote_retrieve_body( $response );

		if ( '' === trim( (string) $body ) ) {
			return OTGS_Installer_Products_Feed_Problem::BODY_EMPTY;
		}

		if ( null === json_decode( $body, true ) ) {
			return OTGS_Installer_Products_Feed_Problem::BODY_NOT_JSON;
		}

		return OTGS_Installer_Products_Feed_Problem::BODY_INVALID_DOCUMENT;
	}

	private function use_products_fallback_if_no_data( $repository_id, $products_parser ) {
		if ( empty( $this->settings['repositories'][ $repository_id ]['data'] ) ) {
			$this->settings['repositories'][ $repository_id ]['data'] = $products_parser->get_default_products( $repository_id );
		}
	}

	private function read_feed_document( $url ) {
		$url = (string) $url;

		if ( preg_match( '#^https?://#i', $url ) ) {
			return wp_remote_get( $url );
		}

		if ( ! is_file( $url ) || ! is_readable( $url ) ) {
			return new WP_Error(
				'otgs_installer_feed_unreadable',
				sprintf( 'The feed document %s is not readable', $url )
			);
		}

		return array(
			'response' => array( 'code' => 200, 'message' => 'OK' ),
			'headers'  => array(),
			'body'     => file_get_contents( $url ),
		);
	}

	private function refresh_releases_data( $repository_id, $releases_url ) {
		$last_good = isset( $this->settings['repositories'][ $repository_id ]['releases'] )
			? $this->settings['repositories'][ $repository_id ]['releases']
			: null;

		$response = $this->read_feed_document( $releases_url );

		if ( is_wp_error( $response ) ) {
			$problem = $response->get_error_message();
		} elseif ( ! isset( $response['response']['code'] ) || 200 != $response['response']['code'] ) {
			$problem = isset( $response['response']['code'] )
				? sprintf( 'the server answered %s', $response['response']['code'] )
				: 'the server did not answer';
		} else {
			$body     = wp_remote_retrieve_body( $response );
			$releases = $body ? json_decode( $body, true ) : null;
			$problem  = isset( $releases['plugins'] ) && is_array( $releases['plugins'] )
				? null
				: 'the document has no plugins';
		}

		if ( $problem ) {
			$this->feed_read_failed = true;
			$this->store_log(
				$releases_url,
				null,
				OTGS_Installer_Logger_Storage::COMPONENT_REPOSITORIES,
				sprintf(
					'Installer could not read the release information for %s: %s. The versions from the last successful check are kept.',
					$repository_id,
					$problem
				)
			);

			return $last_good;
		}

		$this->settings['repositories'][ $repository_id ]['releases'] = $releases;

		return $this->keep_last_good_releases( $releases, $last_good );
	}

	private function keep_last_good_releases( $releases, $last_good ) {
		if ( ! isset( $last_good['plugins'] ) || ! is_array( $last_good['plugins'] ) ) {
			return $releases;
		}

		foreach ( $last_good['plugins'] as $slug => $release ) {
			if ( ! isset( $releases['plugins'][ $slug ] ) ) {
				$releases['plugins'][ $slug ] = $release;
			}
		}

		return $releases;
	}

	private function handle_product_parsing_notices( $products_notices ) {
		if ( $products_notices ) {
			if ( $products_notices ) {
				$this->register_admin_message( implode( '<br/>', $products_notices ), 'error' );
			}
		}
	}

	public function show_products( $args = array() ) {
		$is_compact = is_array( $args ) && isset( $args['template'] ) && 'compact' === $args['template'];

		if ( ! current_user_can( 'install_plugins' ) ) {
			$message = __( 'Sorry, you are not allowed to manage Installer for this site.', 'installer' );

			if ( $is_compact ) {
				echo '<p>' . esc_html( $message ) . '</p>';

				return;
			}

			wp_die( $message );

			return;
		}

		if ( $is_compact ) {
			$this->enqueue_admin_assets();
		}

		$screen = get_current_screen();

		if ( $screen->base === 'settings_page_installer' ) {
			echo '<div class="wrap">';
			echo '<h2>' . __( 'Installer', 'installer' ) . '</h2>';
		}

		if ( ! is_array( $args ) ) {
			$args = array();
		}
		if ( empty( $args['template'] ) ) {
			$args['template'] = 'default';
		}

		$this->filter_repositories_list();

		if ( ! empty( $this->settings()['repositories'] ) ) {
			$this->localize_strings();
			$this->set_filtered_prices( $args );
			$this->set_hierarchy_and_order();

			foreach ( $this->settings()['repositories'] as $repository_id => $repository ) {
				if ( $args['template'] === 'compact' ) {
					if ( isset( $args['repository'] ) && $args['repository'] === $repository_id ) {
						include $this->plugin_path() . '/templates/products-compact.php';
					}
				} else {
					include $this->plugin_path() . '/templates/repository-listing.php';
				}

				unset( $site_key, $subscription_type, $expired, $upgrade_options, $products_avaliable );
			}
		} else {
			echo '<p>' . __( 'No repositories defined.', 'installer' ) . '</p>';
		}

		if ( $screen->base === 'settings_page_installer' ) {
			echo '</div>';
		}
	}

	private function enqueue_admin_assets() {
		wp_enqueue_script( 'installer-admin' );
		wp_enqueue_script( 'otgs-installer-notification', $this->res_url() . '/dist/js/notification/app.js', [], $this->version() );
		wp_enqueue_style( 'installer-admin', $this->res_url() . '/res/css/admin.css', array( 'common' ), $this->version() );
		wp_enqueue_style( 'otgs-icons' );
		wp_enqueue_style( 'installer-admin-notices', $this->res_url() . '/res/css/admin-notices.css', array( 'common' ), $this->version() );
	}

	private function get_compact_box_product( $repository, $product_id ) {
		$packages = isset( $repository['data']['packages'] ) ? $repository['data']['packages'] : [];
		$ids      = [ $product_id ];

		if ( isset( self::COMPACT_BOX_RENAMED_PRODUCTS[ $product_id ] ) ) {
			$ids[] = self::COMPACT_BOX_RENAMED_PRODUCTS[ $product_id ];
		}

		foreach ( $ids as $id ) {
			foreach ( $packages as $package ) {
				if ( isset( $package['products'][ $id ] ) ) {
					return $package['products'][ $id ];
				}
			}
		}

		return false;
	}

	private function get_compact_box_downloads( $repository, $repository_id, $subscription_type ) {
		$downloads = [];
		$packages  = $this->_render_product_packages(
			$repository,
			$repository['data']['packages'],
			$subscription_type,
			false,
			$this->get_upgrade_options( $repository_id ),
			$repository_id
		);

		foreach ( $packages as $package ) {
			foreach ( $this->get_plugins_sections( $repository_id, $package['downloads'] ) as $section ) {
				foreach ( $section['downloads'] as $download_id => $download ) {
					if ( DownloadFilter::shouldDisplayRecord( $download_id ) ) {
						$downloads[ $download_id ] = $download;
					}
				}
			}
		}

		return $downloads;
	}

	public function get_plugins_sections( $repositoryId, $downloads ) {
		$sections_manager = new SectionsManager(
			$this->get_settings()['repositories']
		);

		$sections = $sections_manager->getPluginsSections( $repositoryId, $downloads );

		if ( count( $sections ) === 1 ) {
			reset( $sections );
		}

		return $sections;
	}

	public function get_product_price( $repository_id, $package_id, $product_id, $incl_discount = false ) {
		$price = false;

		foreach ( $this->settings()['repositories'][ $repository_id ]['data']['packages'] as $package ) {
			if ( $package['id'] == $package_id ) {
				if ( isset( $package['products'][ $product_id ] ) ) {
					if ( $incl_discount && isset( $package['products'][ $product_id ]['price_disc'] ) ) {
						$price = $package['products'][ $product_id ]['price_disc'];
					} elseif ( isset( $package['products'][ $product_id ]['price'] ) ) {
						$price = $package['products'][ $product_id ]['price'];
					}
				}
				break;
			}
		}

		return $price;
	}

	public function get_product_data( $repository_id, $data_type ) {
		return isset( $this->settings()['repositories'][ $repository_id ]['data'][ $data_type ] ) ?
			$this->settings()['repositories'][ $repository_id ]['data'][ $data_type ]
			: null;
	}

	private function getProductPriceCurrencySymbol( $products ) {
		return ( array_key_exists( 'shop_currency_symbol', $products ) ) ? $products['shop_currency_symbol'] : '&#36;';
	}

	private function getProductPriceCurrency( $products ) {
		return ( array_key_exists( 'shop_currency', $products ) ) ? $products['shop_currency'] : 'USD';
	}

	private function getProductPriceString( $products, $product ) {
		$currencySymbol = $this->getProductPriceCurrencySymbol( $products );
		$currency       = $this->getProductPriceCurrency( $products );

		return sprintf( '%s%d (%s)', $currencySymbol, $product['price'], $currency );
	}

	public function getProductPriceWithDiscountString( $products, $product ) {
		$currencySymbol = $this->getProductPriceCurrencySymbol( $products );
		$currency       = $this->getProductPriceCurrency( $products );

		return sprintf( '%s%s %s%s%d%s (%s)', $currencySymbol, $product['price_disc'], '&nbsp;&nbsp;<del>', $currencySymbol, $product['price'], '</del>', $currency );
	}

	public function getRenderProductPackagesData( $repository, $packages, $subscription_type, $expired, $upgrade_options, $repository_id ) {
		return $this->_render_product_packages( $repository, $packages, $subscription_type, $expired, $upgrade_options, $repository_id );
	}

	private function _render_product_packages( $repository, $packages, $subscription_type, $expired, $upgrade_options, $repository_id ) {
		$data                = array();
		$products            = ( array_key_exists( 'data', $repository ) ) ? $repository['data'] : $repository;
		$recommended_plugins = $this->get_recommendation_plugins_to_be_installed( $repository_id );

		if ( $this->get_subscription( $repository_id )->is_wpml_blog_subscription() ) {
			$packages = $this->syncWpmlDescriptionPackageWithBlog( $packages );
		}

		foreach ( $packages as $package ) {
			$row = [ 'products' => [], 'downloads' => [], 'plan_plugins' => [] ];
			foreach ( $package['products'] as $product ) {
				if ( empty( $product['price'] ) && ( empty( $subscription_type ) || $expired ) ) {
					continue;
				}

				if ( empty( $product['subscription_type_equivalent'] ) ) {
					$product['subscription_type_equivalent'] = '';
				}

				if ( empty( $subscription_type ) || $expired ) {
					$p['url']           = $this->append_parameters_to_buy_url( $product['url'], $repository_id );
					$p['shouldDisplay'] = ! ( isset( $product['deprecated'] ) ? (bool) $product['deprecated'] : false );

					if ( ! empty( $product['price_disc'] ) ) {
						$p['label'] = $product['call2action'] . ' - ' . $this->getProductPriceWithDiscountString( $products, $product );
					} else {
						$p['label'] = $product['call2action'] . ' - ' . $this->getProductPriceString( $products, $product );
					}
					$row['products'][] = $p;
				} elseif ( $product['subscription_type'] == $subscription_type || $product['subscription_type_equivalent'] == $subscription_type ) {
					if ( $product['renewals'] ) {
						foreach ( $product['renewals'] as $renewal ) {
							$p['url']   = $this->append_parameters_to_buy_url( $renewal['url'], $repository_id );
							$p['label'] = $renewal['call2action'] . ' - ' . $this->getProductPriceString( $products, $renewal );
						}

						$row['products'][] = $p;
					}
				}

				if ( ! empty( $upgrade_options[ $product['subscription_type'] ] ) ) {
					foreach ( $upgrade_options[ $product['subscription_type'] ] as $stype => $upgrade ) {
						if ( $stype != $subscription_type ) {
							continue;
						}

						$p['url'] = $this->append_parameters_to_buy_url( $upgrade['url'], $repository_id );
						if ( ! empty( $upgrade['price_disc'] ) ) {
							$p['label'] = $upgrade['call2action'] . ' - ' . $this->getProductPriceWithDiscountString( $products, $upgrade );
						} else {
							$p['label'] = $upgrade['call2action'] . ' - ' . $this->getProductPriceString( $products, $upgrade );
						}
						$row['products'][] = $p;
					}
				}

				if ( isset( $subscription_type ) && ! $expired && ( $product['subscription_type'] == $subscription_type || $product['subscription_type_equivalent'] == $subscription_type ) ) {
					foreach ( $product['plugins'] as $plugin_slug ) {
						$plugin_finder = $this->get_plugin_finder();
						$plugin        = $plugin_finder->get_plugin( $plugin_slug, $repository_id );

						if ( ! $plugin ) {
							continue;
						}

						$external_repo = $plugin->get_external_repo();

						if ( $external_repo && $this->repository_has_valid_subscription( $external_repo ) ) {
							continue;
						}

						$row['downloads'][ $plugin_slug ] = $this->settings()['repositories'][ $repository_id ]['data']['downloads']['plugins'][ $plugin_slug ];
					}

					$row['plan_plugins'] = $this->get_plan_plugins( $products, $subscription_type, $upgrade_options, $repository_id );
				}
				foreach ( $row['downloads'] as &$download ) {
					$download['is_preselected_plugin'] = self::is_plugin_recommended( $download, $recommended_plugins )
					                                     && $this->can_plugin_be_installed( $download, $repository_id );
				}
				unset( $download );

				if ( ! empty( $package['sub-packages'] ) ) {
					$row['sub-packages'] = $package['sub-packages'];
				}
			}

			$row['id']          = $package['id'];
			$row['image_url']   = $package['image_url'];
			$row['name']        = $package['name'];
			$row['description'] = $package['description'];

			$row['notification'] = '';
			if ( ! $this->get_subscription( $repository_id )->is_wpml_blog_subscription() && $this->has_any_preselected_plugins( $row['downloads'] ) ) {
				$row['notification'] = __( 'We have preselected the plugins your site needs to be fully multilingual.', 'installer' );
			}

			if ( ! empty( $row['products'] ) || ! empty( $row['downloads'] ) || ! empty( $row['sub-packages'] ) ) {
				$data[] = $row;
			}
		}

		return $data;
	}

	private function get_plan_plugins( $products, $subscription_type, $upgrade_options, $repository_id ) {
		if ( ! $this->is_wpml_repository( $repository_id ) ) {
			return [];
		}

		$plan_plugins = PlanPlugins::forSubscription( $products, $subscription_type, $upgrade_options );

		if ( $plan_plugins ) {
			$plan_plugins['url'] = $this->append_parameters_to_buy_url( $plan_plugins['url'], $repository_id );
		}

		return $plan_plugins;
	}

	public function can_plugin_be_installed( $plugin, $repository_id ) {
		if ( $this->dependencies->cant_download( $repository_id ) ) {
			return false;
		}

		$isPluginIsNotInstalled = $this->plugin_is_not_installed( $plugin['name'], $plugin['slug'], $plugin['version'] );
		$isEmbeddedVersion      = $this->plugin_is_embedded_version( $plugin['name'], $plugin['slug'] );

		return ( $isPluginIsNotInstalled || $isEmbeddedVersion );
	}

	public function can_plugin_go_back_to_production( $plugin, $repository_id ) {
		if ( $this->dependencies->cant_download( $repository_id ) ) {
			return false;
		}

		if ( ! isset( $plugin['channel'] ) || WP_Installer_Channels::CHANNEL_PRODUCTION !== $plugin['channel'] ) {
			return false;
		}

		$installed_version = $this->plugin_is_installed( $plugin['name'], $plugin['slug'] );

		return $installed_version
			&& WP_Installer_Channels::is_pre_release( $installed_version )
			&& version_compare( $installed_version, $plugin['version'], '>' );
	}

	public function get_recommendation_plugins_to_be_installed( $repository_id ): array {
		$recommendations = $this->get_recommendations( $repository_id );
		if ( $recommendations === null ) {
			return [];
		}
		$recommended_plugins = isset( $recommendations['plugins'] ) ? $recommendations['plugins'] : [];
		if ( empty( $recommended_plugins ) ) {
			return [];
		}

		$slugs = array_map( function ( $plugin ) {
			return $plugin['slug'];
		}, $recommended_plugins );

		return array_values( $slugs );
	}

	private static function is_plugin_recommended( $plugin, $recommended_plugins ): bool {
		return in_array( $plugin['slug'], $recommended_plugins );
	}

	public function get_end_user_renewal_url( $repository_id ) {
		$settings = $this->settings();

		if ( ! isset( $settings['repositories'][ $repository_id ]['data']['packages'] ) ) {
			return null;
		}

		return Collection::of( $settings['repositories'][ $repository_id ]['data']['packages'] )
		                 ->filter( function ( $package ) {
			                 return $package['id'] === 'wpml';
		                 } )
		                 ->head()
		                 ->get( 'products' )
		                 ->get( 'end-user-subscription' )
		                 ->getOrNull( 'url' );
	}

	public function get_extra_url_parameters() {
		$parameters = array();

		if ( ! empty( $this->package_source ) ) {
			foreach ( $this->package_source as $key => $val ) {
				$parameters[ $key ] = $val;
			}
		}

		$parameters['installer_version'] = WP_INSTALLER_VERSION;
		$parameters['theme']             = wp_get_theme()->get( 'Name' );
		$parameters['site_name']         = get_bloginfo( 'name' );

		return $parameters;
	}

	public function append_parameters_to_buy_url( $url, $repository_id, $args = array() ) {
		$url = add_query_arg( array( 'icl_site_url' => $this->get_installer_site_url( $repository_id ) ), $url );

		$affiliate_id  = false;
		$affiliate_key = false;

		if ( ! empty( $this->package_source ) ) {
			$extra = $this->get_extra_url_parameters();

			if ( ! empty( $extra['repository'] ) && $extra['repository'] == $repository_id ) {
				if ( ! empty( $extra['affiliate_key'] ) && ! empty( $extra['user_id'] ) ) {
					$this->config[ 'affiliate_id:' . $repository_id ]  = $extra['user_id'];
					$this->config[ 'affiliate_key:' . $repository_id ] = $extra['affiliate_key'];
					unset( $extra['affiliate_key'], $extra['user_id'], $extra['repository'] );
				}

				$url = add_query_arg( $extra, $url );
			}
		}

		if ( isset( $this->config[ 'affiliate_id:' . $repository_id ] ) && isset( $this->config[ 'affiliate_key:' . $repository_id ] ) ) {
			$affiliate_id  = $this->config[ 'affiliate_id:' . $repository_id ];
			$affiliate_key = $this->config[ 'affiliate_key:' . $repository_id ];
		} elseif ( isset( $args[ 'affiliate_id:' . $repository_id ] ) && isset( $args[ 'affiliate_key:' . $repository_id ] ) ) {
			$affiliate_id  = $args[ 'affiliate_id:' . $repository_id ];
			$affiliate_key = $args[ 'affiliate_key:' . $repository_id ];
		} elseif ( defined( 'ICL_AFFILIATE_ID' ) && defined( 'ICL_AFFILIATE_KEY' ) ) {

			$affiliate_id  = ICL_AFFILIATE_ID;
			$affiliate_key = ICL_AFFILIATE_KEY;
		} elseif ( isset( $this->config['affiliate_id'] ) && isset( $this->config['affiliate_key'] ) ) {
			$affiliate_id  = $this->config['affiliate_id'];
			$affiliate_key = $this->config['affiliate_key'];
		}

		if ( $affiliate_id && $affiliate_key ) {
			$url = add_query_arg( array( 'aid' => $affiliate_id, 'affiliate_key' => $affiliate_key ), $url );
		}

		if ( $repository_id == 'wpml' ) {
			$url = add_query_arg( array(
				'using_icl'    => $this->_using_icl,
				'wpml_version' => $this->_wpml_version
			), $url );
		}

		$url = apply_filters( 'wp_installer_buy_url', $url );

		$url = esc_url( $url );

		return $url;
	}

	private function syncWpmlDescriptionPackageWithBlog( array $packages ): array {
		$blogPackageIndex = $this->get_blog_package_index( $packages );
		$wpmlPackageIndex = $this->get_wpml_package_index( $packages );

		if ( $blogPackageIndex === - 1 || $wpmlPackageIndex === - 1 ) {
			return $packages;
		}

		$packages[ $wpmlPackageIndex ]['description'] = $packages[ $blogPackageIndex ]['description'];

		return $packages;
	}

	private function is_wpml_repository( string $repository_id ) {
		return $repository_id === 'wpml';
	}

	private function is_blog_package( string $id ) {
		return $id === 'multilingual-blog';
	}

	private function get_component_setting() {
		if ( ! $this->components_setting ) {
			$this->components_setting = new OTGS_Installer_WP_Share_Local_Components_Setting();
		}

		return $this->components_setting;
	}

	public function save_site_key( $args = array() ) {
		$error = '';

		if ( isset( $args['repository_id'] ) ) {
			$repository_id = $args['repository_id'];
		} elseif ( isset( $_POST['repository_id'] ) ) {
			$repository_id = sanitize_text_field( $_POST['repository_id'] );
		} else {
			$repository_id = false;
		}

		if ( isset( $args['nonce'] ) ) {
			$nonce = $args['nonce'];
		} elseif ( isset( $_POST['nonce'] ) ) {
			$nonce = sanitize_text_field( $_POST['nonce'] );
		} else {
			$nonce = '';
		}

		if ( isset( $args['site_key'] ) ) {
			$site_key = $args['site_key'];
		} else {
			$site_key = sanitize_text_field( $_POST[ 'site_key_' . $repository_id ] );
		}
		$site_key = self::sanitize_site_key( $site_key );

		$user_requested  = empty( $args['automatic'] );
		$postponed       = false;
		$key_not_checked = false;
		$reason          = '';

		if ( $repository_id && $nonce && wp_verify_nonce( $nonce, 'save_site_key_' . $repository_id ) ) {
			try {
				$subscriptionManagerFactory = new SubscriptionManagerFactory();
				$subscriptionManager        = $subscriptionManagerFactory->create( $repository_id, $this->repositories[ $repository_id ]['api-url'] );
				list ( $subscription_data, $site_key_data ) = $subscriptionManager->fetch( $site_key, self::SITE_KEY_VALIDATION_SOURCE_REGISTRATION, $user_requested );

				if ( $subscription_data ) {
					$this->get_settings();
					$this->settings['repositories'][ $repository_id ]['subscription'] = array(
						'key'           => $site_key,
						'key_type'      => isset( $site_key_data['type'] )
							? (int) $site_key_data['type'] : OTGS_Installer_Subscription::SITE_KEY_TYPE_PRODUCTION,
						'data'          => $subscription_data,
						'registered_by' => get_current_user_id(),
						'site_url'      => is_multisite() ? $this->get_installer_site_url( $repository_id ) : get_site_url(),
					);
					$this->save_settings();
					$this->clean_plugins_update_cache();

					do_action('check_posthog_should_record');
				} else {
					$error = __( 'Invalid site key for the current site.', 'installer' )
					         . '<br /> <div class="installer-footnote">' . __( 'Please note that the site key is case sensitive.', 'installer' ) . '</div>';
				}
			} catch ( OTGS_Installer_Fetch_Subscription_Postponed_Exception $e ) {
				$error     = OTGS_Installer_Fetch_Subscription_Postponed_Exception::user_message();
				$postponed = true;
			} catch ( Exception $e ) {
				$error = $e->getMessage();
				$key_not_checked = ! OTGS_Installer_Fetch_Subscription_Exception::is_site_key_refused( $e );
				$reason          = $e->getMessage();
				if ( OTGS_Installer_Fetch_Subscription_Exception::is_service_unavailable( $e ) ) {
					$matches = array( '', OTGS_Installer_Site_Key_Ajax::host_of( $this->repositories[ $repository_id ]['api-url'] ) );
				}
				if ( ! empty( $matches ) || preg_match( '#Could not resolve host: (.*)#', $error, $matches ) || preg_match( '#Couldn\'t resolve host \'(.*)\'#', $error, $matches ) ) {
					$error = $this->connection_sentence( $repository_id, $matches[1] );
				} elseif ( ! $key_not_checked && $user_requested && ! self::is_subsite_of_unregistered_network( $repository_id ) ) {
					$error = self::site_key_refused_message( $repository_id );
				}

				$this->log_subscription_update( $repository_id . ': ' . $e->getMessage() );
			}
		}

		$return = array( 'error' => $error );

		if ( $postponed ) {
			$return['postponed'] = true;
		}

		if ( $key_not_checked ) {
			$return['key_not_checked'] = true;
			$return['reason']          = $reason;
		}

		if ( $this->api_debug ) {
			$return['debug'] = $this->api_debug;
		}

		if ( ! empty( $args['return'] ) ) {
			return $return;
		} else {
			echo json_encode( $return );
			exit;
		}
	}

	private function connection_sentence( $repository_id, $host ) {
		return sprintf( __( "%s cannot access %s to register. Try again to see if it's a temporary problem. If the problem continues, make sure that this site has access to the Internet.", 'installer' ),
			'<strong><i>' . $this->get_generic_product_name( $repository_id ) . '</i></strong>',
			'<strong><i>' . $host . '</i></strong>'
		);
	}

	public function get_site_key( $repository_id ) {
		return WP_Installer::get_repository_site_key( $repository_id );
	}

	private static function sanitize_site_key( $site_key ) {
		return (string) preg_replace( '/[^A-Za-z0-9]/', '', (string) $site_key );
	}

	public static function site_key_belongs_to_network( $repository_id ) {
		if ( ! is_multisite() || is_main_site() ) {
			return false;
		}

		$subscriptions = Settings::load_subscriptions();

		return ! empty( $subscriptions['repositories'][ $repository_id ]['subscription']['key'] );
	}

	public static function network_site_key_notice() {
		$main_site_id = get_main_site_id();

		return sprintf(
		// translators: %1$s the main site's URL, %2$s and %3$s wrap the link to the main site's registration screen
			__( 'This site uses the network key registered on %1$s. To change or remove it, %2$sgo to the main site%3$s.', 'installer' ),
			esc_html( get_site_url( $main_site_id ) ),
			'<a href="' . esc_url( self::main_site_registration_url() ) . '">',
			'</a>'
		);
	}

	private static function main_site_registration_url() {
		return get_admin_url( get_main_site_id(), 'options-general.php?page=installer' );
	}

	public static function is_subsite_of_unregistered_network( $repository_id ) {
		if ( ! is_multisite() || is_main_site() ) {
			return false;
		}

		return ! self::site_key_belongs_to_network( $repository_id );
	}

	public static function checked_site_address( $repository_id ) {
		$site_url = new SiteUrl();

		return esc_html( $site_url->get( $repository_id ) );
	}

	public static function site_key_refused_message( $repository_id ) {
		return sprintf(
		// translators: %s the site address the key was checked for, e.g. https://www.example.com
			__( 'This site key does not match %s. Create a site key for this exact address and try again.', 'installer' ),
			self::checked_site_address( $repository_id )
		);
	}

	public static function network_one_key_notice( $repository_id, $product_name ) {
		$site_url = new SiteUrl();
		$address = preg_replace( '#^https?://#i', '', untrailingslashit( $site_url->get( $repository_id ) ) );

		return sprintf(
		// translators: %1$s the product name (WPML, Toolset), %2$s the network's address (domain and path, no scheme), %3$s and %4$s wrap the link to the main site's registration screen
			__( 'On a network, %1$s uses one site key for all sites, created for the network\'s address %2$s. Create a key for that address in your %1$s account, then register it here or on the %3$smain site%4$s.', 'installer' ),
			esc_html( $product_name ),
			'<strong>' . esc_html( $address ) . '</strong>',
			'<a href="' . esc_url( self::main_site_registration_url() ) . '">',
			'</a>'
		);
	}

	public function remove_site_key( $repository_id, $refresh_repositories_data = true ) {
		$this->get_settings();
		if ( isset( $this->settings['repositories'][ $repository_id ] ) ) {
			unset( $this->settings['repositories'][ $repository_id ]['subscription'] );
			unset( $this->settings['repositories'][ $repository_id ]['last_successful_subscription_fetch'] );

			$this->save_settings();
			$this->clean_plugins_update_cache();
			if ( $refresh_repositories_data ) {
				$this->refresh_repositories_data();
			}
		}
	}

	public static function check_order_status_url( $repository_id ) {
		$url = current_user_can( 'install_plugins' )
			? self::menu_url() . '&validate_repository=' . $repository_id
			: admin_url( 'admin-post.php?action=' . self::CHECK_ORDER_STATUS_ACTION . '&validate_repository=' . $repository_id );

		return wp_nonce_url( $url, 'validate_repository_' . $repository_id );
	}

	public static function current_user_can_check_order_status() {
		return current_user_can( 'install_plugins' )
		       || current_user_can( 'manage_options' )
		       || current_user_can( 'wpml_manage_support' );
	}

	public function check_order_status_from_notice() {
		$repository_id = isset( $_GET['validate_repository'] ) ? sanitize_text_field( $_GET['validate_repository'] ) : '';
		if ( ! $repository_id || ! self::current_user_can_check_order_status() ) {
			wp_die( esc_html__( 'Sorry, you are not allowed to access this page.', 'installer' ), 403 );
		}
		check_admin_referer( 'validate_repository_' . $repository_id );
		$this->check_order_status( $repository_id );

		$back = wp_get_referer();
		wp_safe_redirect( $back ? $back : admin_url() );
		exit;
	}

	public function validate_repository_subscription() {
		$repository_id = isset( $_GET['validate_repository'] ) ? sanitize_text_field( $_GET['validate_repository'] ) : false;
		if ( $repository_id ) {
			if ( ! current_user_can( 'install_plugins' ) ) {
				return;
			}
			check_admin_referer( 'validate_repository_' . $repository_id );
			$this->check_order_status( $repository_id );

			wp_redirect( $this->menu_url() . '#repository-' . $repository_id );
			exit;
		}
	}

	private function check_order_status( $repository_id ) {
		$this->forget_hardcoded_site_key_refusal( $repository_id );
		$site_key = $this->get_site_key( $repository_id );
		if ( $site_key ) {
			$key_was_checked = true;
			try {
				$subscriptionManagerFactory = $this->get_subscription_manager_factory();
				$subscriptionManager        = $subscriptionManagerFactory->create( $repository_id, $this->repositories[ $repository_id ]['api-url'] );
				list ( $subscription_data, $site_key_data ) = $subscriptionManager->fetch( $site_key, self::SITE_KEY_VALIDATION_SOURCE_REVALIDATION, true );
			} catch ( Exception $e ) {
				$subscription_data = false;
				$key_was_checked = OTGS_Installer_Fetch_Subscription_Exception::is_site_key_refused( $e );
				if ( ! $key_was_checked ) {
					$this->log_subscription_update( $repository_id . ': ' . $e->getMessage() );
				}
			}

			$this->get_settings();
			if ( empty( $subscription_data ) && $key_was_checked ) {
				// wpmldev-12310: only Refresh license removes a refused key. Here it stays, flagged.
				$this->mark_site_key_as_invalid( $repository_id );
				delete_site_transient( 'update_plugins' );
			} elseif ( ! empty( $subscription_data ) ) {
				unset( $this->settings['repositories'][ $repository_id ]['subscription']['site_key_invalid'] );
				$this->settings['repositories'][ $repository_id ]['subscription']['data']     = $subscription_data;
				$this->settings['repositories'][ $repository_id ]['subscription']['key_type'] = isset( $site_key_data['type'] )
					? (int) $site_key_data['type'] : OTGS_Installer_Subscription::SITE_KEY_TYPE_PRODUCTION;
			}
			$this->save_settings();
		}
	}

	public function api_debug_log( $text ) {
		if ( defined( 'WPML_DEBUG_INSTALLER' ) && WPML_DEBUG_INSTALLER ) {
			if ( ! is_scalar( $text ) ) {
				$text = print_r( $text, true );
			}

			$this->api_debug .= $text . "\n";
		}
	}

	public function get_repository_site_key( $repository_id ) {
		$site_key = false;
		$subscriptions = Settings::load_subscriptions();

		if ( isset( $subscriptions['repositories'][ $repository_id ]['subscription']['key'] ) ) {
			$site_key = $subscriptions['repositories'][ $repository_id ]['subscription']['key'];
		}

		return ! empty( $site_key ) ? $site_key : false;
	}

	public function get_subscription( $repository_id ) {
		$data = null;
		$subscriptions = Settings::load_subscriptions();
		if ( ! empty( $subscriptions['repositories'][ $repository_id ]['subscription'] ) ) {
			$data = $subscriptions['repositories'][ $repository_id ]['subscription'];
		}

		return new OTGS_Installer_Subscription( $data );
	}

	public function repository_has_valid_subscription( $repository_id, $expiredForPeriod = 0 ) {
		return $this->get_subscription( $repository_id )->is_valid( $expiredForPeriod );
	}

	public function repository_is_in_grace_period( $repository_id, $expiredForPeriod = 0 ) {
		return $this->get_subscription( $repository_id )->is_in_grace( $expiredForPeriod );
	}

	public function repository_has_refunded_subscription( $repository_id ) {
		return $this->get_subscription( $repository_id )->is_refunded();
	}

	public function repository_has_subscription( $repository_id ) {
		$key = false;
		$subscriptions = Settings::load_subscriptions();
		if (
			isset( $subscriptions['repositories'][ $repository_id ]['subscription']['key'] )
			&& ! empty( $subscriptions['repositories'][ $repository_id ]['subscription']['key'] )
		) {
			$key = $subscriptions['repositories'][ $repository_id ]['subscription']['key'];
		}

		return $key;
	}

	public function repository_has_development_site_key( $repository_id ) {
		$subscriptions = Settings::load_subscriptions();
		return ! $this->repository_has_invalid_site_key( $repository_id )
		       && isset( $subscriptions['repositories'][ $repository_id ]['subscription']['key_type'] )
		       && $subscriptions['repositories'][ $repository_id ]['subscription']['key_type'] === OTGS_Installer_Subscription::SITE_KEY_TYPE_DEVELOPMENT;
	}

	public function repository_has_invalid_site_key( $repository_id ) {
		$subscriptions = Settings::load_subscriptions();

		return ! empty( $subscriptions['repositories'][ $repository_id ]['subscription']['key'] )
		       && ! empty( $subscriptions['repositories'][ $repository_id ]['subscription']['site_key_invalid'] );
	}

	public function repository_has_legacy_free_subscription( $repository_id ) {
		return $this->repository_has_valid_subscription( $repository_id )
		       && $this->get_subscription_type_for_repository( $repository_id ) === self::LEGACY_FREE_TYPES_SUBSCRIPTION_ID;
	}

	public function repository_has_expired_subscription( $repository_id, $expiredForPeriod = 0 ) {
		return ! $this->repository_has_invalid_site_key( $repository_id )
		       && $this->repository_has_subscription( $repository_id )
		       &&
		       ! $this->repository_has_valid_subscription( $repository_id, $expiredForPeriod )
		       &&
		       ! $this->repository_has_refunded_subscription( $repository_id );
	}

	public function repository_has_in_grace_subscription( $repository_id, $expiredForPeriod = 0 ) {
		return $this->repository_has_expired_subscription( $repository_id ) && $this->repository_is_in_grace_period( $repository_id, $expiredForPeriod );
	}

	public function get_generic_product_name( $repository_id ) {
		return $this->settings()['repositories'][ $repository_id ]['data']['product-name'];
	}

	public function show_subscription_renew_warning( $repository_id, $subscription_id ) {
		$subscriptionWarningMessage = new Subscription_Warning_Message( $this );
		$warningMessage             = $subscriptionWarningMessage->get( $repository_id, $subscription_id );

		if ( ! empty( $warningMessage ) ) {
			echo '<div><p class="installer-warn-box notice notice-alt">' . $warningMessage . '</p></div>';
		}

		return ! empty( $warningMessage );
	}

	public function setup_plugins_renew_warnings() {
		$plugins                     = get_plugins();
		$subscriptions_with_warnings = [];

		foreach ( $this->settings()['repositories'] as $repositoryId => $repository ) {
			$subscriptionData = Obj::path( [
				'repositories',
				$repositoryId,
				'subscription',
				'data'
			], $this->settings );

			$subscriptionType = Obj::prop( 'subscription_type', $subscriptionData );

			$subscriptionWarningMessage = new Subscription_Warning_Message( $this );
			$warningMessage             = $subscriptionWarningMessage->get( $repositoryId, $subscriptionType );

			if ( ! empty( $warningMessage ) ) {
				$subscriptions_with_warnings[ $subscriptionType ] = $warningMessage;
			}
		}


		foreach ( $plugins as $plugin_id => $plugin ) {
			$slug = dirname( $plugin_id );
			if ( empty( $slug ) ) {
				continue;
			}

			foreach ( $this->settings()['repositories'] as $repository_id => $repository ) {
				if ( $this->repository_has_valid_subscription( $repository_id ) ) {
					foreach ( $repository['data']['packages'] as $package ) {
						foreach ( $package['products'] as $product ) {
							foreach ( $product['plugins'] as $plugin_slug ) {
								if ( ! $this->isPluginAvailableInRepositoryDownloads( $repository_id, $plugin_slug ) ) {
									continue;
								}

								$download = $this->settings()['repositories'][ $repository_id ]['data']['downloads']['plugins'][ $plugin_slug ];

								if ( $download['slug'] == $slug || $download['name'] == $plugin['Name'] || $download['name'] == $plugin['Title'] ) {

									if ( isset( $subscriptions_with_warnings[ $product['subscription_type'] ] ) ) {
										$this->_plugins_renew_warnings[ $plugin_id ] = $subscriptions_with_warnings[ $product['subscription_type'] ];
									}
								}
							}
						}
					}
				}
			}
		}
	}

	public function queue_plugins_renew_warnings() {
		if ( ! empty( $this->_plugins_renew_warnings ) ) {
			foreach ( $this->_plugins_renew_warnings as $plugin_id => $message ) {
				add_action( "after_plugin_row_" . $plugin_id, array( $this, 'plugins_renew_warning' ), 10, 3 );
			}
		}
	}

	public function plugins_renew_warning( $plugin_file, $plugin_data, $status ) {
		if ( empty( $this->_plugins_renew_warnings[ $plugin_file ] ) ) {
			return;
		}

		$wp_list_table = _get_list_table( 'WP_Plugins_List_Table' );
		if ( false === $wp_list_table ) {
			return;
		}
		?>

        <tr id="<?php
		echo $plugin_data['slug']; ?>-update" data-slug="<?php
		echo $plugin_data['slug']; ?>" data-plugin="<?php
		echo $plugin_file ?>">
            <td colspan="<?php
			echo $wp_list_table->get_column_count(); ?>" class="notice notice-warning notice-otgs">
                <p>
					<?php
					echo $this->_plugins_renew_warnings[ $plugin_file ];
					?>
                </p>
            </td>
        </tr>

		<?php
	}

	public function get_subscription_type_for_repository( $repository_id ) {
		$subscription_type = false;

		if ( ! empty( $this->settings()['repositories'][ $repository_id ]['subscription'] ) ) {
			$subscription_type = $this->settings()['repositories'][ $repository_id ]['subscription']['data']->subscription_type;
		}

		return $subscription_type;
	}

	public function have_superior_subscription( $subscription_type, $product ) {
		$have = false;

		if ( is_array( $product['upgrades'] ) ) {
			foreach ( $product['upgrades'] as $u ) {
				if ( $u['subscription_type'] == $subscription_type ) {
					$have = true;
					break;
				}
			}
		}

		return $have;
	}

	public function is_product_available_for_download( $product_name, $repository_id ) {
		$available = false;

		$subscription_type = $this->get_subscription_type_for_repository( $repository_id );
		$expired           = $this->repository_has_expired_subscription( $repository_id );

		if ( ! $expired && $this->repository_has_subscription( $repository_id ) ) {
			$this->set_hierarchy_and_order();

			foreach ( $this->settings()['repositories'][ $repository_id ]['data']['packages'] as $package_id => $package ) {
				$has_top_package = false;

				foreach ( $package['products'] as $product ) {
					if ( $subscription_type === (int) $product['subscription_type'] || $subscription_type === (int) $product['subscription_type_equivalent'] ) {
						$has_top_package = true;
						if ( $product['name'] == $product_name ) {
							return $available = true;
						}
					}
				}

				if ( ! empty( $package['sub-packages'] ) ) {
					foreach ( $package['sub-packages'] as $sub_package ) {
						foreach ( $sub_package['products'] as $product ) {
							if ( $product['name'] == $product_name && ( $subscription_type === (int) $product['subscription_type'] || $subscription_type === (int) $product['subscription_type_equivalent'] || $has_top_package ) ) {
								return $available = true;
							}
						}
					}
				}
			}
		}

		return $available;
	}

	public function get_upgrade_options( $repository_id ) {
		$all_upgrades = array();


		$all_products = array();
		foreach ( $this->settings()['repositories'][ $repository_id ]['data']['packages'] as $package ) {
			foreach ( $package['products'] as $product ) {
				$all_products[] = $product;
			}
			if ( ! empty( $package['sub-packages'] ) ) {
				foreach ( $package['sub-packages'] as $subpackage ) {
					foreach ( $subpackage['products'] as $product ) {
						$all_products[] = $product;
					}
				}
			}
		}

		foreach ( $all_products as $product ) {
			if ( $product['upgrades'] ) {
				foreach ( $product['upgrades'] as $upgrade ) {
					if ( $this->repository_has_valid_subscription( $repository_id ) || ( $this->repository_has_subscription( $repository_id ) && $upgrade['including_expired'] ) ) {
						$all_upgrades[ $upgrade['subscription_type'] ][ $product['subscription_type'] ] = $upgrade;
					}
				}
			}
		}

		return $all_upgrades;
	}

	public function append_site_key_to_download_url( $url, $key, $repository_id ) {
		$url_params['site_key'] = $key;
		$url_params['site_url'] = $this->get_installer_site_url( $repository_id );


		if ( ! empty( $this->package_source ) ) {
			$extra = $this->get_extra_url_parameters();
			if ( ! empty( $extra['repository'] ) && $extra['repository'] == $repository_id ) {
				unset( $extra['repository'] );
				foreach ( $extra as $key => $val ) {
					$url_params[ $key ] = $val;
				}
			}
		}

		$url = add_query_arg( $url_params, $url );

		if ( $repository_id == 'wpml' ) {
			$url = add_query_arg( array(
				'using_icl'    => $this->_using_icl,
				'wpml_version' => $this->_wpml_version
			), $url );
		}

		return $url;
	}

	public function plugin_is_not_installed( $name, $slug, $version = null ) {
		return ! $this->plugin_is_installed( $name, $slug, $version );
	}

	public function plugin_is_installed( $name, $slug, $version = null ) {
		$is = false;

		$plugins = get_plugins();

		foreach ( $plugins as $plugin_id => $plugin ) {
			$wp_plugin_slug = dirname( $plugin_id );

			if ( $wp_plugin_slug == $slug || $plugin['Name'] == $name || $plugin['Title'] == $name || ( $wp_plugin_slug == $slug . '-embedded' || $plugin['Name'] == $name . ' Embedded' ) ) {
				if ( $version ) {
					if ( version_compare( $plugin['Version'], $version, '>=' ) ) {
						$is = $plugin['Version'];
					}
				} else {
					$is = $plugin['Version'];
				}

				break;
			}
		}

		if ( ! $is && $name == 'Types' ) {
			return $this->plugin_is_installed( 'Types - Complete Solution for Custom Fields and Types', $slug, $version );
		}

		return $is;
	}

	public function plugin_is_embedded_version( $name, $slug ) {
		$is = false;

		$plugins = get_plugins();

		$is_full_installed = false;
		foreach ( $plugins as $plugin_id => $plugin ) {
			if ( ( $plugin['Name'] == $name && ! preg_match( "#-embedded$#", $slug ) ) ) {
				$is_full_installed = true;
				break;
			}
		}

		if ( $is_full_installed ) {
			return false;
		}

		foreach ( $plugins as $plugin_id => $plugin ) {
			$wp_plugin_slug = dirname( $plugin_id );
			if ( $wp_plugin_slug == $slug . '-embedded' && $plugin['Name'] == $name . ' Embedded' ) {
				$is = true;
				break;
			}
		}

		return $is;
	}

	public function get_plugin_installed_version( $name, $slug ) {
		return $this->plugin_is_installed( $name, $slug );
	}

	public function get_plugin_repository_version( $repository_id, $slug ) {
		$version = false;

		if ( ! empty( $this->settings()['repositories'][ $repository_id ]['data']['packages'] ) ) {
			foreach ( $this->settings()['repositories'][ $repository_id ]['data']['packages'] as $package ) {
				foreach ( $package['products'] as $product ) {
					foreach ( $product['plugins'] as $plugin_slug ) {
						if ( ! array_key_exists( $plugin_slug, $this->settings()['repositories'][ $repository_id ]['data']['downloads']['plugins'] ) ) {
							continue;
						}

						$download = $this->settings()['repositories'][ $repository_id ]['data']['downloads']['plugins'][ $plugin_slug ];

						if ( $download['slug'] == $slug ) {
							$version = $download['version'];
							break ( 3 );
						}
					}
				}
			}
		}

		return $version;
	}

	public function is_uploading_allowed() {
		return $this->dependencies->is_uploading_allowed();
	}

	public function download_plugin_ajax_handler() {
		if ( ! current_user_can( 'install_plugins' ) ) {
			wp_send_json_error( __( 'You are not allowed to install plugins.', 'installer' ), 403 );
		}

		$data = json_decode( base64_decode( sanitize_text_field( $_POST['data'] ) ), true );

		if ( ! isset( $data['nonce'], $data['url'] ) || ! wp_verify_nonce( $data['nonce'], 'install_plugin_' . $data['url'] ) ) {
			wp_send_json(
				array(
					'version'    => 0,
					'non_stable' => '',
					'plugin_id'  => false,
					'success'    => false,
					'message'    => __( 'This download link has expired. Reload the page and try again.', 'installer' ),
				)
			);
		}

		require_once ABSPATH . 'wp-admin/includes/class-wp-upgrader.php';
		require_once $this->plugin_path() . '/includes/class-installer-upgrader-skins.php';

		$data_url_parsed = wp_parse_url( $data['url'] );
		$data_url_args   = $data_url_parsed['query'];
		unset( $data_url_parsed['query'] );
		$data_url = OTGS_Installer_Url::build( $data_url_parsed );

		$ret              = false;
		$plugin_id        = false;
		$message          = '';
		$connection_error = false;

		$site_key = $this->get_repository_site_key( $data['repository_id'] );
		try {
			$subscriptionManagerFactory = new SubscriptionManagerFactory( $this->get_settings() );
			$subscriptionManager        = $subscriptionManagerFactory->create( $data['repository_id'], $this->repositories[ $data['repository_id'] ]['api-url'] );
			list ( $subscription_data, $site_key_data ) = $subscriptionManager->fetch( $site_key, self::SITE_KEY_VALIDATION_SOURCE_DOWNLOAD_REPORT, true );
		} catch ( Exception $e ) {
			$subscription_data = false;

			$this->store_log( $data_url, $data_url_args, OTGS_Installer_Logger_Storage::COMPONENT_SUBSCRIPTION, $e->getMessage() );

			if ( ! $this->flag_site_key_if_refused( $e, $data['repository_id'] ) ) {
				$connection_error = $e->getMessage();
			}
		}

		if ( $subscription_data && ! is_wp_error( $subscription_data ) && $this->repository_has_valid_subscription( $data['repository_id'] ) ) {
			$upgrader_skins = new Installer_Upgrader_Skins();
			$upgrader       = new Plugin_Upgrader( $upgrader_skins );

			remove_action( 'upgrader_process_complete', array( 'Language_Pack_Upgrader', 'async_upgrade' ), 20 );

			$plugins = get_plugins();

			$is_embedded = false;
			foreach ( $plugins as $id => $plugin ) {
				$wp_plugin_slug = dirname( $id );
				$is_embedded    = $this->plugin_is_embedded_version( preg_replace( '/ Embedded$/', '', $plugin['Name'] ), preg_replace( '/-embedded$/', '', $wp_plugin_slug ) );

				if ( $wp_plugin_slug == $data['slug'] || $is_embedded && preg_replace( '/-embedded$/', '', $wp_plugin_slug ) == $data['slug'] ) {
					$plugin_id = $id;
					break;
				}
			}

			if ( $plugin_id && ! $is_embedded && $this->is_plugin_out_of_date( $plugin_id ) ) {
				$response['upgrade'] = 1;

				$plugin_is_active = is_plugin_active( $plugin_id );

				$download_http_logger = $this->get_download_http_logger();
				$download_http_logger->start( $data['url'], array(
					'operation'     => 'upgrade',
					'repository_id' => isset( $data['repository_id'] ) ? $data['repository_id'] : null,
					'slug'          => isset( $data['slug'] ) ? $data['slug'] : null,
					'plugin_id'     => $plugin_id,
				) );
				$ret = $upgrader->upgrade( $plugin_id, [ 'clear_update_cache' => false ] );
				$download_http_logger->stop();

				if ( ! $ret && ! empty( $upgrader->skin->installer_error ) ) {
					if ( is_wp_error( $upgrader->skin->installer_error ) ) {
						$message = $upgrader->skin->installer_error->get_error_message() .
						           ' (' . $upgrader->skin->installer_error->get_error_data() . ')';
					}
					$plugin_version = 0;
				} else {
					if ( $plugin_is_active ) {
						add_filter( 'wp_redirect', '__return_false' );
						activate_plugin( $plugin_id );
					}
					$plugin_version = $this->get_plugin_repository_version( $data['repository_id'], $data['slug'] );
				}
			} elseif ( $plugin_id && ! $is_embedded ) {
				$answer  = $this->get_already_installed_answer( $plugins[ $plugin_id ] );
				$ret     = $answer['success'];
				$message = $answer['message'];
			} else {

				if ( $is_embedded && $plugin_id ) {
					delete_plugins( array( $plugin_id ) );
				}

				$response['install'] = 1;
				$download_http_logger = $this->get_download_http_logger();
				$download_http_logger->start( $data['url'], array(
					'operation'     => 'install',
					'repository_id' => isset( $data['repository_id'] ) ? $data['repository_id'] : null,
					'slug'          => isset( $data['slug'] ) ? $data['slug'] : null,
				) );
				$ret                 = $upgrader->install( $data['url'] );
				$download_http_logger->stop();
				if ( ! $ret && ! empty( $upgrader->skin->installer_error ) ) {
					if ( is_wp_error( $upgrader->skin->installer_error ) ) {
						$message = $upgrader->skin->installer_error->get_error_message() .
						           ' (' . $upgrader->skin->installer_error->get_error_data() . ')';
					}
				}
			}

			$plugins = get_plugins();

			if ( $ret ) {
				foreach ( $plugins as $id => $plugin ) {
					$wp_plugin_slug = dirname( $id );
					if ( $wp_plugin_slug == $data['slug'] ) {
						$plugin_version = $plugin['Version'];
						$plugin_id      = $id;

						$include_auto_upgrade = new IncludeAutoUpgrade( $this->settings, $data['repository_id'] );
						$include_auto_upgrade->includeDuringInstall( $plugin_id );
						break;
					}
				}
			}

			if ( WP_Installer_Channels()->get_channel( $data['repository_id'] ) !== WP_Installer_Channels::CHANNEL_PRODUCTION ) {
				$download   = $this->settings()['repositories'][ $data['repository_id'] ]['data']['downloads']['plugins'][ $data['slug'] ];
				$non_stable = WP_Installer_Channels()->get_download_source_channel( $plugin_version, $data['repository_id'], $download['slug'], 'plugins' );
			}
		} elseif ( $connection_error ) {
			$ret     = false;
			$message = sprintf( __( 'Connection failed! Please refresh the page and try again. (%s)', 'installer' ), '<i>' . $connection_error . '</i>' );
		} else {
			$ret     = false;
			$message = __( 'Your subscription appears to no longer be valid. Please try to register again using a valid site key.', 'installer' );
		}

		if ( $message ) {
			$this->store_log( $data_url, $data_url_args, OTGS_Installer_Logger_Storage::COMPONENT_DOWNLOAD, $message );
		}

		$response['version']    = isset( $plugin_version ) ? $plugin_version : 0;
		$response['non_stable'] = isset( $non_stable ) ? $non_stable : '';
		$response['plugin_id']  = $plugin_id;
		$response['nonce']      = wp_create_nonce( 'activate_' . $plugin_id );
		$response['success']    = $ret;
		$response['message']    = $message;

		wp_send_json( $response );
	}

	public function get_already_installed_answer( array $plugin ) {
		return [
			'success' => false,
			/* translators: 1: plugin name, 2: installed version */
			'message' => sprintf( __( '%1$s %2$s is already installed.', 'installer' ), $plugin['Name'], $plugin['Version'] ),
		];
	}

	private function is_plugin_out_of_date( $plugin ) {
		$current = get_site_transient( 'update_plugins' );

		return isset( $current->response[ $plugin ] );
	}

	public function download_plugin( $slug, $url ) {
		require_once ABSPATH . 'wp-admin/includes/class-wp-upgrader.php';
		require_once $this->plugin_path() . '/includes/class-installer-upgrader-skins.php';

		$upgrader_skins = new Installer_Upgrader_Skins();
		$upgrader       = new Plugin_Upgrader( $upgrader_skins );

		remove_action( 'upgrader_process_complete', array( 'Language_Pack_Upgrader', 'async_upgrade' ), 20 );

		$plugins = get_plugins();

		$plugin_id = false;

		foreach ( $plugins as $id => $plugin ) {
			$wp_plugin_slug = dirname( $id );
			if ( $wp_plugin_slug == $slug ) {
				$plugin_id = $id;
				break;
			}
		}

		if ( $plugin_id ) {

			$plugin_is_active = is_plugin_active( $plugin_id );

			$ret = $upgrader->upgrade( $plugin_id );

			if ( $plugin_is_active ) {
				activate_plugin( $plugin_id );
			}
		} else {
			$ret = $upgrader->install( $url );
		}

		return $ret;
	}

	public function activate_plugin() {
		if ( ! current_user_can( 'activate_plugins' ) ) {
			wp_send_json_error( __( 'You are not allowed to activate plugins.', 'installer' ), 403 );
		}

		$error = '';

		$plugin_id = sanitize_text_field( $_POST['plugin_id'] );

		if ( isset( $_POST['nonce'] ) && $plugin_id && wp_verify_nonce( $_POST['nonce'], 'activate_' . $plugin_id ) ) {
			$plugin_slug    = dirname( $plugin_id );
			$active_plugins = get_option( 'active_plugins' );
			foreach ( $active_plugins as $plugin ) {
				$wp_plugin_slug = dirname( $plugin );
				if ( $wp_plugin_slug == $plugin_slug . '-embedded' ) {
					deactivate_plugins( array( $plugin ) );
					break;
				}
			}

			add_filter( 'wp_redirect', '__return_false', 10000 );

			$return = activate_plugin( $plugin_id );

			if ( is_wp_error( $return ) ) {
				$error = $return->get_error_message();
			}
		} else {
			$error = 'error';
		}

		$ret = array( 'error' => $error );

		echo json_encode( $ret );
		exit;
	}

	public function custom_plugins_api_call( $result, $action, $args ) {
		if ( $action == 'plugin_information' ) {
			if ( ! function_exists( 'get_plugins' ) ) {
				require_once ABSPATH . 'wp-admin/includes/plugin.php';
			}

			$plugins           = get_plugins();
			$installed_plugins = array();
			foreach ( $plugins as $plugin_id => $plugin ) {
				$installed_plugins[ dirname( $plugin_id ) ] = array(
					'name'    => $plugin['Name'],
					'title'   => $plugin['Title'],
					'is_lite' => false !== stripos( $plugin['Version'], '-lite' ),
				);
			}

			$slug          = $args->slug;
			$custom_plugin = false;

			foreach ( $this->settings()['repositories'] as $repository_id => $repository ) {
				if ( ! $this->repository_has_valid_subscription( $repository_id ) ) {
					$site_key = false;
				} else {
					$site_key = $repository['subscription']['key'];
				}

				foreach ( $repository['data']['packages'] as $package ) {
					foreach ( $package['products'] as $product ) {
						foreach ( $product['plugins'] as $plugin_slug ) {
							if ( ! $this->isPluginAvailableInRepositoryDownloads( $repository_id, $plugin_slug ) ) {
								continue;
							}

							$download = $this->settings()['repositories'][ $repository_id ]['data']['downloads']['plugins'][ $plugin_slug ];

							if ( $download['slug'] == $slug
							     || isset( $installed_plugins[ $slug ] )
							        && (
								        $installed_plugins[ $slug ]['name'] == $download['name'] || $installed_plugins[ $slug ]['title'] == $download['name']
							        )
							) {
								$this_plugin           = new stdClass();
								$this_plugin->external = true;

								$this_plugin->is_free               = $this->should_fallback_under_wp_org_repo( $download,
									$site_key );
								$this_plugin->is_lite               = ! empty( $download['is-lite'] );
								$this_plugin->is_download_available = $this->is_product_available_for_download( $product['name'],
									$repository_id );

								if ( $custom_plugin ) {
									if ( ( ! $custom_plugin->is_free && $this_plugin->is_free )
									     || ( ! $custom_plugin->is_lite && $custom_plugin->is_download_available )
									     || ( $custom_plugin->is_lite && ! $this_plugin->is_download_available && $installed_plugins[ $slug ]['is_lite'] )
									) {
										continue;
									}
								}
								$custom_plugin = $this_plugin;

								$custom_plugin->name           = $download['name'];
								$custom_plugin->slug           = $slug;
								$custom_plugin->version        = $download['version'];
								$custom_plugin->author         = '';
								$custom_plugin->author_profile = '';
								$custom_plugin->last_updated   = $download['date'];
								$custom_plugin->tested         = OTGS_Installer_Tested_Version::for_site( isset( $download['tested'] ) ? $download['tested'] : '' );

								if ( $site_key ) {
									$custom_plugin->download_link = $this->append_site_key_to_download_url( $download['url'],
										$site_key, $repository_id );
								}

								$custom_plugin->homepage = $repository['data']['url'];
								$custom_plugin->sections = array(
									'Description' => $download['description'],
								);

								$changelog = Settings::get_changelog_for_plugin( $slug );
								if ( $changelog ) {
									$custom_plugin->sections['Changelog'] = $changelog;
								}
							}
						}
					}
				}
			}

			if ( $custom_plugin ) {
				if ( $custom_plugin->is_free ) {
					$result = false;
				} else {
					$result = $custom_plugin;
				}
			}
		}

		return $result;
	}

	private function should_fallback_under_wp_org_repo( $download, $site_key ) {
		return ( ! empty( $download['free-on-wporg'] ) || isset( $download['fallback-free-on-wporg'] ) && $download['fallback-free-on-wporg'] && ! $site_key ) && $download['channel'] == WP_Installer_Channels::CHANNEL_PRODUCTION;
	}

	public function setup_plugins_page_notices() {
		$plugins = get_plugins();

		$template_service = OTGS_Template_Service_Factory::create(
			$this->plugin_path() . '/templates/php/components-setting/'
		);


		$plugin_page_notice = new OTGS_Installer_Plugins_Page_Notice( $template_service );

		foreach ( $plugins as $plugin_id => $plugin ) {
			$slug = dirname( $plugin_id );
			if ( empty( $slug ) ) {
				continue;
			}

			$name = $plugin['Name'];

			foreach ( $this->settings()['repositories'] as $repository_id => $repository ) {
				if ( ! $this->repository_has_valid_subscription( $repository_id ) ) {
					$site_key = false;
				} else {
					$site_key = $repository['subscription']['key'];
				}

				foreach ( $repository['data']['packages'] as $package ) {
					foreach ( $package['products'] as $product ) {
						foreach ( $product['plugins'] as $plugin_slug ) {
							$plugin_finder = $this->get_plugin_finder();
							$plugin_found  = $plugin_finder->get_plugin( $plugin_slug, $repository_id );

							if ( ! $plugin_found ) {
								continue;
							}

							$external_repo = $plugin_found->get_external_repo();

							if ( $external_repo && $this->plugin_is_registered( $external_repo, $plugin_slug ) ) {
								continue;
							}

							$download                    = $this->settings()['repositories'][ $repository_id ]['data']['downloads']['plugins'][ $plugin_slug ];
							$display_subscription_notice = false;
							$display_setting_notice      = false;

							if ( $download['slug'] == $slug || $download['name'] == $name ) {
								if ( in_array( $name, array( 'Toolset Types', 'WPML Multilingual CMS' ), true ) ) {
									$display_setting_notice = true;
								}

								if ( ! $site_key || ! $this->plugin_is_registered( $repository_id, $download['slug'] ) ) {
									$display_setting_notice = false;

									if (
										self::isFreeToolsetTypes( $name, $plugin ) || $this->isComplementaryWithWPMLSubscription( $name, $slug ) || $download['fallback-free-on-wporg']
									) {
										$display_subscription_notice = false;
									} else {
										if ( $this->repository_has_in_grace_subscription( $repository_id, self::GRACE_TIME ) ) {
											$display_subscription_notice = [
												'type'    => 'in_grace',
												'repo'    => $repository_id,
												'product' => $repository['data']['product-name']
											];
										} elseif ( $this->repository_has_expired_subscription( $repository_id ) ) {
											$display_subscription_notice = [
												'type'    => 'expired',
												'repo'    => $repository_id,
												'product' => $repository['data']['product-name']
											];
										} elseif ( $this->repository_has_refunded_subscription( $repository_id ) ) {
											$display_subscription_notice = [
												'type'    => 'refunded',
												'repo'    => $repository_id,
												'product' => $repository['data']['product-name']
											];
										} elseif ( ! $this->repository_has_subscription( $repository_id ) ) {
											$display_subscription_notice = apply_filters(
												'otgs_installer_display_subscription_notice',
												[
													'type'    => 'register',
													'repo'    => $repository_id,
													'product' => $repository['data']['product-name']
												]
											);
										}
									}
								}
								if ( $this->plugin_is_registered( $repository_id, $download['slug'] ) && $this->repository_has_legacy_free_subscription( $repository_id ) ) {
									$display_subscription_notice = [
										'type'    => 'legacy_free',
										'repo'    => $repository_id,
										'product' => $repository['data']['product-name']
									];
								}
							}

							if ( $display_setting_notice || $display_subscription_notice ) {
								$plugin_page_notice->add_plugin(
									$plugin_id,
									array(
										OTGS_Installer_Plugins_Page_Notice::DISPLAY_SUBSCRIPTION_NOTICE_KEY => $display_subscription_notice,
										OTGS_Installer_Plugins_Page_Notice::DISPLAY_SETTING_NOTICE_KEY      => $display_setting_notice,
									)
								);
							}
						}
					}
				}
			}
		}

		$plugin_page_notice->add_hooks();
	}

	public function localize_strings() {
		if ( ! empty( $this->settings()['repositories'] ) ) {
			foreach ( $this->settings['repositories'] as $repository_id => $repository ) {
				if ( ! isset( $repository['data']['packages'] ) ) {
					continue;
				}

				foreach ( $repository['data']['packages'] as $package_id => $package ) {
					foreach ( $package['products'] as $product_id => $product ) {
						if ( empty( $product['call2action'] ) ) {
							$this->settings['repositories'][ $repository_id ]['data']['packages'][ $package_id ]['products'][ $product_id ]['call2action'] = $product['name'];
						}

						foreach ( $product['upgrades'] as $idx => $upg ) {
							if ( empty( $upg['call2action'] ) ) {
								$this->settings['repositories'][ $repository_id ]['data']['packages'][ $package_id ]['products'][ $product_id ]['upgrades'][ $idx ]['call2action'] = $upg['name'];
							}
						}

						foreach ( $product['renewals'] as $idx => $rnw ) {
							if ( empty( $rnw['call2action'] ) ) {
								$this->settings['repositories'][ $repository_id ]['data']['packages'][ $package_id ]['products'][ $product_id ]['renewals'][ $idx ]['call2action'] = $rnw['name'];
							}
						}
					}
				}
			}
		}

		global $sitepress;
		if ( is_null( $sitepress ) ) {
			return;
		}

		$user_admin_language = $sitepress->get_admin_language();

		if ( $user_admin_language != 'en' ) {
			foreach ( $this->settings['repositories'] as $repository_id => $repository ) {
				$localization = $repository['data']['localization'];

				foreach ( $repository['data']['packages'] as $package_id => $package ) {
					if ( isset( $localization['packages'][ $package_id ]['name'][ $user_admin_language ] ) ) {
						$this->settings['repositories'][ $repository_id ]['data']['packages'][ $package_id ]['name'] = $localization['packages'][ $package_id ]['name'][ $user_admin_language ];
					}
					if ( isset( $localization['packages'][ $package_id ]['description'][ $user_admin_language ] ) ) {
						$this->settings['repositories'][ $repository_id ]['data']['packages'][ $package_id ]['description'] = $localization['packages'][ $package_id ]['description'][ $user_admin_language ];
					}
				}

				foreach ( $repository['data']['packages'] as $package_id => $package ) {
					foreach ( $package['products'] as $product_id => $product ) {
						if ( isset( $localization['products'][ $product_id ]['name'][ $user_admin_language ] ) ) {
							$this->settings['repositories'][ $repository_id ]['data']['packages'][ $package_id ]['products'][ $product_id ]['name']
								= $localization['products'][ $product_id ]['name'][ $user_admin_language ];
						}
						if ( isset( $localization['products'][ $product_id ]['description'][ $user_admin_language ] ) ) {
							$this->settings['repositories'][ $repository_id ]['data']['packages'][ $package_id ]['products'][ $product_id ]['description']
								= $localization['products'][ $product_id ]['description'][ $user_admin_language ];
						}
						if ( isset( $localization['products'][ $product_id ]['call2action'][ $user_admin_language ] ) ) {
							$this->settings['repositories'][ $repository_id ]['data']['packages'][ $package_id ]['products'][ $product_id ]['name']
								= $localization['products'][ $product_id ]['call2action'][ $user_admin_language ];
						}
					}
				}

				if ( isset( $repository['data']['subscriptions_meta']['expiration'] ) ) {
					foreach ( $repository['data']['subscriptions_meta']['expiration'] as $subscription_id => $note ) {
						if ( isset( $localization['subscriptions-notes'][ $subscription_id ]['expiration-warning'][ $user_admin_language ] ) ) {
							$this->settings['repositories'][ $repository_id ]['data']['subscriptions_meta']['expiration'][ $subscription_id ]['warning_message']
								= $localization['subscriptions-notes'][ $subscription_id ]['expiration-warning'][ $user_admin_language ];
						}
					}
				}
			}
		}
	}

	public function get_matching_cp( $repository, $args = array() ) {
		$match = false;


		$cp_name = $cp_author = false;

		if ( isset( $this->config['src_name'] ) && isset( $this->config['src_author'] ) ) {
			$cp_name   = $this->config['src_name'];
			$cp_author = $this->config['src_author'];
		} elseif ( isset( $args['src_name'] ) && isset( $args['src_author'] ) ) {
			$cp_name   = $args['src_name'];
			$cp_author = $args['src_author'];
		}

		if ( isset( $repository['data']['marketing_cp'] ) ) {
			foreach ( $repository['data']['marketing_cp'] as $cp ) {
				if ( ! empty( $cp['exp'] ) && time() > $cp['exp'] ) {
					continue;
				}

				if ( ! empty( $cp['theme_name'] ) ) {
					if ( $cp['author_name'] == $cp_author && $cp['theme_name'] == $cp_name ) {
						$match = $cp;
						continue;
					}
				} else {
					if ( $cp['author_name'] == $cp_author ) {
						$match = $cp;
						continue;
					}
				}
			}
		}

		return $match;
	}

	public function set_filtered_prices( $args = array() ) {
		foreach ( $this->settings()['repositories'] as $repository_id => $repository ) {
			$match = $this->get_matching_cp( $repository, $args );

			if ( empty( $match ) ) {
				continue;
			}

			foreach ( $repository['data']['packages'] as $package_id => $package ) {
				foreach ( $package['products'] as $product_id => $product ) {
					if ( $match['dtp'] == '%' ) {
						$fprice = round( $product['price'] * ( 1 - $match['amt'] / 100 ), 2 );
						$fprice = $fprice != round( $fprice ) ? sprintf( '%.2f', $fprice ) : round( $fprice, 0 );
					} elseif ( $match['dtp'] == '-' ) {
						$fprice = $product['price'] - $match['amt'];
					} else {
						$fprice = $product['price'];
					}

					if ( $fprice ) {
						$this->settings['repositories'][ $repository_id ]['data']['packages'][ $package_id ]['products'][ $product_id ]['price_disc'] = $fprice;

						$url_glue                                                                                                              = false !== strpos( $this->settings()['repositories'][ $repository_id ]['data']['packages'][ $package_id ]['products'][ $product_id ]['url'], '?' ) ? '&' : '?';
						$cpndata                                                                                                               = base64_encode( (string) json_encode( array(
							'theme_author' => $match['author_name'],
							'theme_name'   => $match['theme_name'],
							'vlc'          => $match['vlc']
						) ) );
						$this->settings['repositories'][ $repository_id ]['data']['packages'][ $package_id ]['products'][ $product_id ]['url'] .= $url_glue . 'cpn=' . $cpndata;

						foreach ( $product['upgrades'] as $upgrade_id => $upgrade ) {
							$fprice = false;
							if ( $match['dtp'] == '%' ) {
								$fprice = round( $upgrade['price'] * ( 1 - $match['amt'] / 100 ), 2 );
								$fprice = $fprice != round( $fprice ) ? sprintf( '%.2f', $fprice ) : round( $fprice, 0 );
							} elseif ( $match['dtp'] == '-' ) {
								$fprice = $upgrade['price'] - $match['amt'];
							}
							if ( $fprice ) {
								$this->settings['repositories'][ $repository_id ]['data']['packages'][ $package_id ]['products'][ $product_id ]['upgrades'][ $upgrade_id ]['price_disc'] = $fprice;
								$this->settings['repositories'][ $repository_id ]['data']['packages'][ $package_id ]['products'][ $product_id ]['upgrades'][ $upgrade_id ]['url']        .= $url_glue . 'cpn=' . $cpndata;
							}
						}
					}
				}
			}
		}
	}

	public function set_hierarchy_and_order() {
		if ( ! empty( $this->settings()['repositories'] ) ) {
			foreach ( $this->settings()['repositories'] as $repository_id => $repository ) {
				if ( empty( $repository['data']['packages'] ) ) {
					continue;
				}

				$all_packages     = $repository['data']['packages'];
				$ordered_packages = array();

				foreach ( $all_packages as $k => $v ) {
					if ( ! isset( $v['order'] ) ) {
						$all_packages[ $k ]['order'] = 0;
					}
				}

				foreach ( $all_packages as $package_id => $package ) {
					if ( empty( $package['parent'] ) ) {
						$ordered_packages[ $package_id ] = $package;
					}
				}

				foreach ( $all_packages as $package_id => $package ) {
					if ( ! empty( $package['parent'] ) ) {
						if ( isset( $ordered_packages[ $package['parent'] ] ) ) {
							$ordered_packages[ $package['parent'] ]['sub-packages'][ $package_id ] = $package;
						}
					}
				}

				usort( $ordered_packages, array( $this, 'compare_package_order' ) );
				foreach ( $ordered_packages as $package_id => $package ) {
					if ( ! empty( $package['sub-packages'] ) ) {
						usort( $ordered_packages[ $package_id ]['sub-packages'], array( $this, 'compare_package_order' ) );
					}
				}

				$this->settings['repositories'][ $repository_id ]['data']['packages'] = $ordered_packages;
			}
		}
	}

	public function compare_package_order( $a, $b ) {
		return $a['order'] - $b['order'];
	}

	public function get_support_tag_by_name( $name, $repository ) {
		if ( is_array( $this->settings()['repositories'][ $repository ]['data']['support_tags'] ) ) {
			foreach ( $this->settings()['repositories'][ $repository ]['data']['support_tags'] as $support_tag ) {
				if ( $support_tag['name'] == $name ) {
					return $support_tag['url'];
				}
			}
		}

		return false;
	}

	private function get_plugin_finder() {
		if ( ! $this->plugin_finder ) {
			$this->plugin_finder = new OTGS_Installer_Plugin_Finder( new OTGS_Installer_Plugin_Factory(), $this->settings()['repositories'] );
		}

		return $this->plugin_finder;
	}

	private function clean_plugins_update_cache() {
		do_action( 'otgs_installer_clean_plugins_update_cache' );
	}

	private function register_again_url( $repository_id ) {
		return wp_nonce_url( $this->menu_url() . '&validate_repository=' . $repository_id, 'validate_repository_' . $repository_id )
		       . '#repository-' . $repository_id;
	}

	public function plugin_upgrade_custom_errors() {
		if ( isset( $_REQUEST['action'] ) ) {
			$action = isset( $_REQUEST['action'] ) ? sanitize_text_field( $_REQUEST['action'] ) : '';

			if ( 'update-selected' == $action ) {
				global $plugins;

				if ( isset( $plugins ) && is_array( $plugins ) ) {
					foreach ( $plugins as $k => $plugin ) {
						$plugin_repository = false;

						$wp_plugin_slug = dirname( $plugin );

						foreach ( $this->settings()['repositories'] as $repository_id => $repository ) {
							foreach ( $repository['data']['packages'] as $package ) {
								foreach ( $package['products'] as $product ) {
									foreach ( $product['plugins'] as $plugin_slug ) {
										if ( ! $this->isPluginAvailableInRepositoryDownloads( $repository_id, $plugin_slug ) ) {
											continue;
										}

										if ( $this->settings()['repositories'][ $repository_id ]['data']['downloads']['plugins'][ $plugin_slug ]['slug'] == $wp_plugin_slug ) {
											$download          = $this->settings()['repositories'][ $repository_id ]['data']['downloads']['plugins'][ $plugin_slug ];
											$plugin_repository = $repository_id;
											$product_name      = $repository['data']['product-name'];
											$plugin_name       = $download['name'];
											$free_on_wporg     = ! empty( $download['free-on-wporg'] ) && $download['channel'] == WP_Installer_Channels::CHANNEL_PRODUCTION;
											break;
										}
									}
								}
							}
						}

						if ( $plugin_repository ) {
							$subscription_data = false;
							$site_key          = $this->get_repository_site_key( $plugin_repository );

							if ( ! $site_key ) {
								list( $plugin_repository, $site_key ) = $this->match_product_in_external_repository( $plugin_repository, $wp_plugin_slug );
							}

							if ( $site_key ) {
								try {
									$subscriptionManagerFactory = new SubscriptionManagerFactory( $this->get_settings() );
									$subscriptionManager        = $subscriptionManagerFactory->create( $plugin_repository, $this->repositories[ $plugin_repository ]['api-url'] );
									list ( $subscription_data, $site_key_data ) = $subscriptionManager->fetch( $site_key, self::SITE_KEY_VALIDATION_SOURCE_REVALIDATION, true );
								} catch ( Exception $e ) {
									$this->flag_site_key_if_refused( $e, $plugin_repository );
								}
							}

							if ( ! $site_key && ( ! empty( $free_on_wporg ) || $this->should_fallback_under_wp_org_repo( $download, $site_key ) ) ) {
								continue;
							}

							if ( empty( $site_key ) || empty( $subscription_data ) ) {
								$error_message = sprintf( __( "%s cannot update because your site's registration is not valid. Please %sregister %s%s again for this site first.", 'installer' ),
									'<strong>' . $plugin_name . '</strong>', '<a target="_top" href="' . $this->register_again_url( $plugin_repository ) . '">', $product_name, '</a>' );

								echo '<div class="updated error"><p>' . $error_message . '</p></div>';

								unset( $plugins[ $k ] );
							}
						}
					}
				}
			}


			if ( 'upgrade-plugin' == $action || 'update-plugin' == $action ) {
				$plugin = isset( $_REQUEST['plugin'] ) ? trim( sanitize_text_field( $_REQUEST['plugin'] ) ) : '';

				$wp_plugin_slug = dirname( $plugin );

				$plugin_repository = false;

				foreach ( $this->settings()['repositories'] as $repository_id => $repository ) {
					foreach ( $repository['data']['packages'] as $package ) {
						foreach ( $package['products'] as $product ) {
							foreach ( $product['plugins'] as $plugin_slug ) {
								if ( ! $this->isPluginAvailableInRepositoryDownloads( $repository_id, $plugin_slug ) ) {
									continue;
								}

								$download = $this->settings()['repositories'][ $repository_id ]['data']['downloads']['plugins'][ $plugin_slug ];

								if ( $download['slug'] == $wp_plugin_slug ) {
									$plugin_repository = $repository_id;
									$product_name      = $repository['data']['product-name'];
									$plugin_name       = $download['name'];
									$free_on_wporg     = ! empty( $download['free-on-wporg'] ) && $download['channel'] == WP_Installer_Channels::CHANNEL_PRODUCTION;
									break;
								}
							}
						}
					}
				}

				if ( $plugin_repository ) {
					$site_key = $this->get_repository_site_key( $plugin_repository );

					if ( ! $site_key ) {
						list( $plugin_repository, $site_key ) = $this->match_product_in_external_repository( $plugin_repository, $wp_plugin_slug );
					}

					if ( $site_key ) {
						try {
							$subscriptionManagerFactory = new SubscriptionManagerFactory( $this->get_settings() );
							$subscriptionManager        = $subscriptionManagerFactory->create( $plugin_repository, $this->repositories[ $plugin_repository ]['api-url'] );
							list ( $subscription_data, $site_key_data ) = $subscriptionManager->fetch( $site_key, self::SITE_KEY_VALIDATION_SOURCE_REVALIDATION, true );
						} catch ( Exception $e ) {
							$subscription_data = false;
							$this->flag_site_key_if_refused( $e, $plugin_repository );
						}
					}

					$no_subscription = empty( $site_key ) || empty( $subscription_data );
					$not_on_wporg    = empty( $free_on_wporg ) && ! $this->should_fallback_under_wp_org_repo( $download, $site_key );

					if ( $no_subscription && $not_on_wporg ) {
						$error_message = sprintf( __( "%s cannot update because your site's registration is not valid. Please %sregister %s%s again for this site first.", 'installer' ),
							'<strong>' . $plugin_name . '</strong>', '<a href="' . $this->register_again_url( $plugin_repository ) . '">', $product_name, '</a>' );

						if ( defined( 'DOING_AJAX' ) ) {

							$status = array(
								'update'     => 'plugin',
								'plugin'     => $plugin,
								'slug'       => sanitize_key( $_POST['slug'] ),
								'oldVersion' => '',
								'newVersion' => '',
							);

							$status['errorCode']    = 'wp_installer_invalid_subscription';
							$status['errorMessage'] = $error_message;

							wp_send_json_error( $status );
						} else {
							echo '<div class="updated error"><p>' . $error_message . '</p></div>';


							echo '<div class="wrap">';
							echo '<h2>' . __( 'Update Plugin', 'installer' ) . '</h2>';
							echo '<a href="' . admin_url( 'plugins.php' ) . '">' . __( 'Return to the plugins page', 'installer' ) . '</a>';
							echo '</div>';
							require_once( ABSPATH . 'wp-admin/admin-footer.php' );
							exit;
						}
					}
				}
			}
		}
	}

	private function store_log( $url, $url_args, $component, $response ) {
		$log = new OTGS_Installer_Log();
		$log->set_request_url( $url )
		    ->set_component( $component )
		    ->set_response( $response )
		    ->set_request_args( $url_args );

		otgs_installer_get_logger_storage()->add( $log );
	}

	private function get_download_http_logger() {
		if ( ! $this->download_http_logger ) {
			$this->download_http_logger = new OTGS_Installer_Download_HTTP_Logger();
		}

		return $this->download_http_logger;
	}

	public function get_api_debug() {
		return $this->api_debug;
	}

	private function match_product_in_external_repository( $current_repository, $plugin_slug ) {
		foreach ( $this->get_repositories() as $repo => $repo_data ) {
			if ( $repo !== $current_repository ) {
				$plugin_finder = $this->get_plugin_finder();
				$plugin_obj    = $plugin_finder->get_plugin( $plugin_slug, $repo );

				if ( $plugin_obj ) {
					$site_key = $this->get_repository_site_key( $repo );

					if ( $site_key ) {
						return array( $repo, $site_key );
					}
				}
			}
		}

		return array( $current_repository, '' );
	}

	private function isComplementaryWithWPMLSubscription( $name, $slug ) {
		return Collection::of( [ 'Toolset Types', 'Toolset Module Manager' ] )->contains( $name ) && $this->plugin_is_registered( 'wpml', $slug );
	}

	public function toolset_is_covered_by_wpml() {
		$installed = $this->get_plugin_finder()->get_otgs_installed_plugins_by_repository();

		if ( empty( $installed['toolset'] ) ) {
			return false;
		}

		foreach ( $installed['toolset'] as $plugin ) {
			if ( ! $this->isComplementaryWithWPMLSubscription( $plugin['name'], $plugin['slug'] ) ) {
				return false;
			}
		}

		return true;
	}

	private function isPluginAvailableInRepositoryDownloads( $repository_id, $plugin_slug ) {
		return $plugin_slug && isset( $this->settings()['repositories'][ $repository_id ]['data']['downloads']['plugins'][ $plugin_slug ] );
	}

	private function has_any_preselected_plugins( array $downloads ) {
		return Collection::of( $downloads )->any( function ( $download ) {
			return $download['is_preselected_plugin'] === true;
		} );
	}

	private function get_blog_package_index( $packages ) {
		return Collection::of( $packages )->firstIndex( function ( $package ) {
			return $package['id'] === 'multilingual-blog';
		} );
	}

	private function get_wpml_package_index( $packages ) {
		return Collection::of( $packages )->firstIndex( function ( $package ) {
			return $package['id'] === 'wpml';
		} );
	}
}
