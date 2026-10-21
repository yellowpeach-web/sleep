<?php

use WPML\Core\Component\CustomFieldPreferences\Domain\ElementType;
use WPML\TM\Settings\PreferenceResolver;
use WPML\TM\Settings\PreferenceSourceIndex;
use WPML\Utils\XmlTranslatableIds;

class WPML_Config {

	const PATH_TO_XSD = WPML_PLUGIN_PATH . '/res/xsd/wpml-config.xsd';

	const RETIRED_TAXONOMIES = [ 'translation_priority' ];

	static $has_run = false;

	static $wpml_config_files = array();
	static $active_plugins    = array();

	private static $readonly_configs_before_import;

	private static $imported_index_signature;

	static function load_config() {
		global $pagenow, $sitepress;

		if ( ! is_admin() || wpml_is_ajax() || ( isset( $_POST['action'] ) && $_POST['action'] === 'heartbeat' ) || ! $sitepress || ! $sitepress->get_default_language() ) {
			return;
		}

		$white_list_pages = array(
			'theme_options',
			'plugins.php',
			'themes.php',
			WPML_PLUGIN_FOLDER . '/menu/languages.php',
			WPML_PLUGIN_FOLDER . '/menu/theme-localization.php',
			WPML_PLUGIN_FOLDER . '/menu/translation-options.php',
		);
		if ( defined( 'WPML_ST_FOLDER' ) ) {
			$white_list_pages[] = WPML_ST_FOLDER . '/menu/string-translation.php';
			$white_list_pages[] = 'wpml-admin-texts-translation';
		}
		$white_list_pages = apply_filters( 'wpml_config_white_list_pages', $white_list_pages );

		$current_page = \WPML\SuperGlobals\Request::page();
		if ( ( '' !== $current_page && in_array( $current_page, $white_list_pages ) ) || ( isset( $pagenow ) && in_array( $pagenow, $white_list_pages ) ) ) {
			self::load_config_run();
		}
	}

	public static function config_files_signature() {
		return md5( (string) maybe_serialize( get_option( 'wpml_config_files_arr' ) ) );
	}

	public static function local_config_files_signature( array $deactivated_plugins = [] ) {
		$files = [];

		$plugins = (array) get_option( 'active_plugins', [] );
		if ( is_multisite() ) {
			$plugins = array_merge( $plugins, array_keys( (array) get_site_option( 'active_sitewide_plugins', [] ) ) );
		}
		$plugins = array_diff( $plugins, $deactivated_plugins );
		foreach ( $plugins as $plugin ) {
			$files[] = self::get_plugin_wpml_config_file( $plugin );
		}

		foreach ( wp_get_mu_plugins() as $mu_plugin ) {
			$files[] = self::get_mu_plugin_wpml_config_file( $mu_plugin );
		}

		$files[] = get_stylesheet_directory() . '/wpml-config.xml';
		$files[] = get_template_directory() . '/wpml-config.xml';

		$hashes = [];
		foreach ( array_unique( array_filter( $files ) ) as $file ) {
			if ( file_exists( $file ) ) {
				$hashes[ $file ] = md5_file( $file );
			}
		}
		ksort( $hashes );

		return md5( (string) wp_json_encode( $hashes ) );
	}

	public static function can_import() {
		global $sitepress, $iclTranslationManagement;

		return $sitepress && $sitepress->get_default_language() && $iclTranslationManagement;
	}

	static function import_now() {
		if ( ! self::can_import() ) {
			return;
		}

		$index_signature = self::config_files_signature();
		if ( null !== self::$imported_index_signature && $index_signature === self::$imported_index_signature ) {
			return;
		}
		self::$imported_index_signature = $index_signature;

		if ( ! function_exists( 'get_plugins' ) ) {
			require_once ABSPATH . 'wp-admin/includes/plugin.php';
		}

		self::$has_run           = false;
		self::$wpml_config_files = array();
		self::$active_plugins    = array();

		self::load_config_run();
	}

	static function load_config_run() {
		global $sitepress;

		if ( self::$has_run ) {
			return;
		}

		self::load_config_pre_process();
		self::load_plugins_wpml_config();
		self::load_theme_wpml_config();
		self::load_global_wpml_config();
		self::parse_wpml_config_files();
		self::load_config_post_process();
		$sitepress->save_settings();

		self::$has_run = true;
	}

	static function get_custom_fields_translation_settings( $translation_actions = array( 0 ) ) {
		$result = array();
		foreach ( $translation_actions as $mode ) {
			$result = array_merge( $result, PreferenceResolver::namesByMode( ElementType::POST, (int) $mode ) );
		}

		return $result;
	}

	static function parse_wpml_config_post_process( $config ) {
		self::parse_custom_fields( $config );
		self::parseTaxonomies( $config );
		self::parsePostTypes( $config );

		do_action( 'wpml_reset_ls_settings', $config['wpml-config']['language-switcher-settings'] );

		return $config;
	}

	static function parseTaxonomies( $config ) {
		self::parseTMSetting( 'taxonomy', 'taxonomies', $config );
	}

	static function parsePostTypes( $config ) {
		self::parseTMSetting( 'custom-type', 'custom-types', $config );
	}

	static function parseTMSetting( $singular, $plural, $config ) {
		global $sitepress, $iclTranslationManagement;
		$tm_settings = new WPML_TM_Settings_Update(
			$singular,
			$plural,
			$iclTranslationManagement,
			$sitepress,
			wpml_load_settings_helper()
		);
		$tm_settings->update_from_config( $config['wpml-config'] );
	}

	static function load_config_post_process() {
		global $iclTranslationManagement;

		if ( null === self::$readonly_configs_before_import ) {
			return;
		}
		$post_process = new WPML_TM_Settings_Post_Process( $iclTranslationManagement );
		$post_process->run( self::$readonly_configs_before_import );
		self::$readonly_configs_before_import = null;
	}

	static function load_config_pre_process() {
		global $iclTranslationManagement;
		$tm_settings = $iclTranslationManagement->settings;

		PreferenceSourceIndex::reset();

		self::$readonly_configs_before_import = [];
		foreach (
			[
				WPML_POST_TYPE_READONLY_SETTING_INDEX,
				WPML_POST_META_READONLY_SETTING_INDEX,
				WPML_TERM_META_READONLY_SETTING_INDEX,
			] as $index
		) {
			self::$readonly_configs_before_import[ $index ] =
				isset( $tm_settings[ $index ] ) && is_array( $tm_settings[ $index ] ) ? $tm_settings[ $index ] : [];
			$iclTranslationManagement->settings[ $index ] = [];
			unset( $iclTranslationManagement->settings[ '__' . $index . '_prev' ] );
		}
	}

	static function load_plugins_wpml_config() {
		if ( is_multisite() ) {
			$plugins = get_site_option( 'active_sitewide_plugins' );
			if ( ! empty( $plugins ) ) {
				foreach ( $plugins as $p => $dummy ) {
					if ( ! self::check_on_config_file( $p ) ) {
						continue;
					}
					$config_file = self::get_plugin_wpml_config_file( $p );
					if ( $config_file && file_exists( $config_file ) ) {
						self::$wpml_config_files[] = $config_file;
					}
				}
			}
		}

		$plugins = get_option( 'active_plugins' );
		if ( ! empty( $plugins ) ) {
			foreach ( $plugins as $p ) {
				if ( ! self::check_on_config_file( $p ) ) {
					continue;
				}

				$config_file = self::get_plugin_wpml_config_file( $p );
				if ( $config_file && file_exists( $config_file ) ) {
					self::$wpml_config_files[] = $config_file;
				}
			}
		}

		$mu_plugins = wp_get_mu_plugins();

		if ( ! empty( $mu_plugins ) ) {
			foreach ( $mu_plugins as $mup ) {
				if ( ! self::check_on_config_file( $mup ) ) {
					continue;
				}

				$config_file = self::get_mu_plugin_wpml_config_file( $mup );
				if ( file_exists( $config_file ) ) {
					self::$wpml_config_files[] = $config_file;
				}
			}
		}

		return self::$wpml_config_files;
	}

	private static function get_plugin_wpml_config_file( $plugin_file ) {
		if ( ! is_string( $plugin_file ) || '' === $plugin_file || 0 !== validate_file( $plugin_file ) ) {
			return false;
		}

		$plugin_slug = dirname( $plugin_file );
		if ( '.' === $plugin_slug || '' === trim( $plugin_slug, '\/.' ) ) {
			return false;
		}

		return WP_PLUGIN_DIR . '/' . $plugin_slug . '/wpml-config.xml';
	}

	private static function get_mu_plugin_wpml_config_file( $mu_plugin ) {
		return dirname( $mu_plugin ) . '/' . basename( $mu_plugin, '.php' ) . '/wpml-config.xml';
	}

	static function check_on_config_file( $name ) {

		if ( empty( self::$active_plugins ) ) {
			if ( ! function_exists( 'get_plugins' ) ) {
				require_once ABSPATH . 'wp-admin/includes/plugin.php';
			}
			self::$active_plugins = get_plugins();
		}
		$config_index_file_data = get_option( 'wpml_config_index' );
		$config_files_arr       = get_option( 'wpml_config_files_arr' );

		if ( ! $config_index_file_data || ! $config_files_arr ) {
			return true;
		}

		if ( isset( self::$active_plugins[ $name ] ) ) {
			$plugin_info      = self::$active_plugins[ $name ];
			$config_file      = self::get_plugin_wpml_config_file( $name );
			if ( false === $config_file ) {
				return true;
			}
			$name             = $plugin_info['Name'];
			$config_data      = $config_index_file_data->plugins;
			$config_files_arr = $config_files_arr->plugins;
			$type             = 'plugin';

		} else {
			$config_data      = $config_index_file_data->themes;
			$config_files_arr = $config_files_arr->themes;
			$config_file      = get_template_directory() . '/wpml-config.xml';
			$type             = 'theme';
		}

		foreach ( $config_data as $item ) {
			if ( $name == $item->name && isset( $config_files_arr[ $item->name ] ) ) {
				if ( $item->override_local || ! file_exists( $config_file ) ) {
					end( self::$wpml_config_files );
					$key                                                 = key( self::$wpml_config_files ) + 1;
					self::$wpml_config_files[ $key ]                     = new stdClass();
					self::$wpml_config_files[ $key ]->config             = icl_xml2array( $config_files_arr[ $item->name ] );
					self::$wpml_config_files[ $key ]->type               = $type;
					self::$wpml_config_files[ $key ]->admin_text_context = basename( dirname( $config_file ) );

					return false;
				} else {
					return true;
				}
			}
		}

		return true;

	}

	static function load_theme_wpml_config() {
		$theme_data = wp_get_theme();
		if ( ! self::check_on_config_file( $theme_data->get( 'Name' ) ) ) {
			return self::$wpml_config_files;
		}

		$parent_theme = $theme_data->parent_theme;
		if ( $parent_theme && ! self::check_on_config_file( $parent_theme ) ) {
			return self::$wpml_config_files;
		}

		if ( get_template_directory() != get_stylesheet_directory() ) {
			$config_file = get_stylesheet_directory() . '/wpml-config.xml';
			if ( file_exists( $config_file ) ) {
				self::$wpml_config_files[] = $config_file;
			}
		}

		$config_file = get_template_directory() . '/wpml-config.xml';
		if ( file_exists( $config_file ) ) {
			self::$wpml_config_files[] = $config_file;
		}

		return self::$wpml_config_files;
	}

	static function get_theme_wpml_config_file() {
		if ( get_template_directory() != get_stylesheet_directory() ) {
			$config_file = get_stylesheet_directory() . '/wpml-config.xml';
			if ( file_exists( $config_file ) ) {
				return $config_file;
			}
		}

		$config_file = get_template_directory() . '/wpml-config.xml';
		if ( file_exists( $config_file ) ) {
			return $config_file;
		}

		return false;

	}

	private static function load_global_wpml_config() {
		$notices_config = (string) get_option( WPML_Config_Update::OPTION_KEY_GLOBAL_NOTICES_CONFIG );

		if ( $notices_config ) {
			self::$wpml_config_files[] = (object) [
				'type'               => 'global',
				'config'             => icl_xml2array( $notices_config ),
				'admin_text_context' => WPML_Config_Update::CONFIG_KEY_GLOBAL_NOTICES,
			];
		}
	}

	static function parse_wpml_config_files() {
		do_action( 'wpml_config_parse_started' );

		$config_all['wpml-config'] = array(
			'custom-fields'                 => array(),
			'custom-fields-texts'           => array(),
			'custom-term-fields'            => array(),
			'custom-types'                  => array(),
			'taxonomies'                    => array(),
			'admin-texts'                   => array(),
			'language-switcher-settings'    => array(),
			'shortcodes'                    => array(),
			'shortcode-list'                => array(),
			'gutenberg-blocks'              => array(),
			'built-with-page-builder'       => array(),
			'allow-translatable-job-fields' => array(),
			'notices'                       => array(),
		);

		$config_all_updated = false;

		$validate  = new WPML_XML_Config_Validate();
		$transform = new WPML_XML2Array();

		if ( ! empty( self::$wpml_config_files ) ) {
			foreach ( self::$wpml_config_files as $file ) {
				if ( is_object( $file ) ) {
					$config = $file->config;
				} else {
					$xml_config_file = new WPML_XML_Config_Read_File( $file, $validate, $transform );
					$config          = $xml_config_file->get();
				}
				do_action( 'wpml_parse_config_file', $file );
				PreferenceSourceIndex::captureFile( $file, $config );
				$config_all         = self::merge_with( $config_all, $config );
				$config_all_updated = true;
			}
		}

		$config_all = self::append_custom_xml_config( $config_all, $config_all_updated );
		$config_all = self::drop_retired_taxonomies( $config_all );

		if ( $config_all_updated ) {
			$config_all = apply_filters( 'icl_wpml_config_array', $config_all );
			$config_all = apply_filters( 'wpml_config_array', $config_all );
		}

		$config_all = WPML_Config_Display_As_Translated::merge_to_translate_mode( $config_all );
		self::parse_wpml_config_post_process( $config_all );

		PreferenceSourceIndex::persist( $GLOBALS['iclTranslationManagement'] ?? null );

		do_action( 'wpml_config_parse_finished' );
	}

	public static function drop_retired_taxonomies( $config_all ) {
		if ( empty( $config_all['wpml-config']['taxonomies']['taxonomy'] ) || ! is_array( $config_all['wpml-config']['taxonomies']['taxonomy'] ) ) {
			return $config_all;
		}

		$config_all['wpml-config']['taxonomies']['taxonomy'] = array_values(
			array_filter(
				$config_all['wpml-config']['taxonomies']['taxonomy'],
				function ( $taxonomy ) {
					return ! ( is_array( $taxonomy ) && isset( $taxonomy['value'] ) && in_array( $taxonomy['value'], self::RETIRED_TAXONOMIES, true ) );
				}
			)
		);

		return $config_all;
	}

	private static function append_custom_xml_config( $config_files, &$updated = null ) {
		$validate      = new WPML_XML_Config_Validate( self::PATH_TO_XSD );
		$transform     = new WPML_XML2Array();
		$custom_config = self::get_custom_xml_config( $validate, $transform );
		if ( $custom_config ) {
			PreferenceSourceIndex::captureCustomXml( $custom_config );
			$config_files = self::merge_with( $config_files, $custom_config );
			$updated      = true;
		}

		return $config_files;
	}

	private static function get_custom_xml_config( $validate, $transform ) {
		if ( class_exists( 'WPML_Custom_XML' ) ) {
			$custom_xml_option = new WPML_Custom_XML();
			$custom_xml_config = new WPML_XML_Config_Read_Option( $custom_xml_option, $validate, $transform );
			$custom_config     = $custom_xml_config->get();
			if ( $custom_config ) {
				$config_object = (object) array(
					'config'             => $custom_config,
					'type'               => 'wpml-custom-xml',
					'admin_text_context' => 'wpml-custom-xml',
				);

				do_action( 'wpml_parse_custom_config', $config_object );

				return $custom_config;
			}
		}

		return null;
	}

	private static function merge_with( $all_configs, $config ) {
		if ( isset( $config['wpml-config'] ) ) {
			$wpml_config     = $config['wpml-config'];
			$wpml_config_all = $all_configs['wpml-config'];
			$wpml_config_all = self::parse_config_index( $wpml_config_all, $wpml_config, 'custom-field', 'custom-fields' );
			$wpml_config_all = self::parse_config_index( $wpml_config_all, $wpml_config, 'custom-term-field', 'custom-term-fields' );
			$wpml_config_all = self::parse_config_index( $wpml_config_all, $wpml_config, 'custom-type', 'custom-types' );
			$wpml_config_all = self::parse_config_index( $wpml_config_all, $wpml_config, 'taxonomy', 'taxonomies' );
			$wpml_config_all = self::parse_config_index( $wpml_config_all, $wpml_config, 'shortcode', 'shortcodes' );
			$wpml_config_all = self::parse_config_index( $wpml_config_all, $wpml_config, 'gutenberg-block', 'gutenberg-blocks' );
			$wpml_config_all = self::parse_config_index( $wpml_config_all, $wpml_config, 'key', 'custom-fields-texts' );
			$wpml_config_all = self::parse_config_index( $wpml_config_all, $wpml_config, 'widget', 'elementor-widgets' );
			$wpml_config_all = self::parse_config_index( $wpml_config_all, $wpml_config, 'widget', 'beaver-builder-widgets' );
			$wpml_config_all = self::parse_config_index( $wpml_config_all, $wpml_config, 'widget', 'cornerstone-widgets' );
			$wpml_config_all = self::parse_config_index( $wpml_config_all, $wpml_config, 'widget', 'siteorigin-widgets' );
			$wpml_config_all = self::parse_config_index( $wpml_config_all, $wpml_config, 'allow-translatable-job-field', 'allow-translatable-job-fields' );
			$wpml_config_all = self::parse_config_index( $wpml_config_all, $wpml_config, 'notice', 'notices' );

			if ( isset( $wpml_config['language-switcher-settings']['key'] ) ) {
				if ( ! is_numeric( key( $wpml_config['language-switcher-settings']['key'] ) ) ) {
					$wpml_config_all['language-switcher-settings']['key'][] = $wpml_config['language-switcher-settings']['key'];
				} else {
					foreach ( $wpml_config['language-switcher-settings']['key'] as $cf ) {
						$wpml_config_all['language-switcher-settings']['key'][] = $cf;
					}
				}
			}

			if ( isset( $wpml_config['shortcode-list']['value'] ) ) {
				$wpml_config_all['shortcode-list'] = array_merge( $wpml_config_all['shortcode-list'], explode( ',', $wpml_config['shortcode-list']['value'] ) );
			}

			if ( isset( $wpml_config['built-with-page-builder']['value'] ) ) {
				$wpml_config_all['built-with-page-builder'] = $wpml_config['built-with-page-builder']['value'];
			}

			$all_configs['wpml-config'] = $wpml_config_all;
		}

		return $all_configs;
	}

	protected static function parse_custom_fields( $config ) {
		global $iclTranslationManagement;

		$setting_factory = $iclTranslationManagement->settings_factory();
		$xml_object_ids  = new XmlTranslatableIds();
		$import          = new WPML_Custom_Field_XML_Settings_Import( $setting_factory, $xml_object_ids, $config['wpml-config'] );
		$import->run();
	}

	private static function parse_config_index( $config_all, $wpml_config, $index_sing, $index_plur ) {
		if ( isset( $wpml_config[ $index_plur ][ $index_sing ] ) ) {
			if ( isset( $wpml_config[ $index_plur ][ $index_sing ]['value'] ) ) {
				$config_all[ $index_plur ][ $index_sing ][] = $wpml_config[ $index_plur ][ $index_sing ];
			} else {
				foreach ( (array) $wpml_config[ $index_plur ][ $index_sing ] as $cf ) {
					$config_all[ $index_plur ][ $index_sing ][] = $cf;
				}
			}
		}

		return $config_all;
	}
}
