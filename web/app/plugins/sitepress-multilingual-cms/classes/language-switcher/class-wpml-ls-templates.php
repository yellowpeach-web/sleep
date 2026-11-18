<?php

use WPML\LanguageSwitcher\FolderIdentity;
use WPML\LanguageSwitcher\UploadsFolder;

class WPML_LS_Templates {

	const CONFIG_FILE = 'config.json';
	const OPTION_NAME = 'wpml_language_switcher_template_objects';

	const OPTION_UPLOADS_SLUGS = 'wpml_ls_uploads_template_slugs';

	private $uploads_path;

	private $wpml_file;

	private $templates = false;

	private $uploads_templates = array();

	private $uploads_slugs;

	private $ds = DIRECTORY_SEPARATOR;

	private $php_roots = array();

	private $are_templates_loaded_from_cache = false;

	public function __construct( ?WPML_File $wpml_file = null ) {
		if ( ! $wpml_file ) {
			$wpml_file = new WPML_File();
		}

		$this->wpml_file = $wpml_file;
	}

	public function init_hooks() {
		add_action( 'after_setup_theme', array( $this, 'action_after_setup_theme_action' ) );
		add_action( 'activated_plugin', array( $this, 'activated_plugin_action' ) );
		add_action( 'deactivated_plugin', array( $this, 'activated_plugin_action' ) );
		add_action( 'switch_theme', array( $this, 'activated_plugin_action' ) );
	}

	public function action_after_setup_theme_action() {
		$this->after_setup_theme_action();
	}

	public function are_templates_loaded_from_cache() {
		return $this->are_templates_loaded_from_cache;
	}

	public function after_setup_theme_action() {
		return $this->init_available_templates();
	}

	public function activated_plugin_action() {
		$this->remember_cached_uploads_slugs();
		delete_option( self::OPTION_NAME );
	}

	public function get_templates( $in_array = null ) {
		if ( null === $in_array ) {
			$ret = $this->templates;
		} else {
			$in_array = $in_array ? array_combine( $in_array, $in_array ) : $in_array;
			$ret      = array_intersect_key( $this->templates, $in_array );
		}
		return $ret;
	}

	public function get_template( $template_slug ) {
		$ret = new WPML_LS_Template( array() );
		if ( $this->templates && array_key_exists( $template_slug, $this->templates ) ) {
			$ret = $this->templates[ $template_slug ];
		}

		return $ret;
	}

	public function get_uploads_templates() {
		return $this->uploads_templates;
	}

	public function get_moved_from_uploads( $slug ) {
		$slug = (string) $slug;

		if ( '' === $slug || ! is_array( $this->templates ) ) {
			return null;
		}

		$folder_names = array();
		$name_parts   = array();

		if ( isset( $this->uploads_templates[ $slug ] ) ) {
			$data           = $this->uploads_templates[ $slug ]->get_template_data();
			$folder_names[] = basename( (string) end( $data['path'] ) );
		}

		$remembered = $this->get_uploads_slugs();

		if ( isset( $remembered[ $slug ] ) ) {
			if ( '' !== $remembered[ $slug ]['folder'] ) {
				$folder_names[] = $remembered[ $slug ]['folder'];
			}

			if ( null !== $remembered[ $slug ]['name'] ) {
				$name_parts[] = sanitize_title_with_dashes( wp_get_theme() . ' - ' . $remembered[ $slug ]['name'] );
				$name_parts[] = sanitize_title_with_dashes( $remembered[ $slug ]['name'] );
			}
		}

		foreach ( $this->get_uploads_slug_prefixes() as $prefix ) {
			if ( 0 === strpos( $slug, $prefix . '-' ) ) {
				$name         = substr( $slug, strlen( $prefix ) + 1 );
				$name_parts[] = sanitize_title_with_dashes( wp_get_theme() . ' - ' . $name );
				$name_parts[] = $name;
			}
		}

		if ( ! $folder_names && ! $name_parts ) {
			return null;
		}

		foreach ( $this->templates as $candidate_slug => $template ) {
			if ( ! $template instanceof WPML_LS_Template || $template->is_core() || $template->is_missing() || $template->is_in_uploads() ) {
				continue;
			}

			$data = $template->get_template_data();

			if (
				in_array( (string) $candidate_slug, $name_parts, true )
				|| ( $folder_names && in_array( basename( (string) end( $data['path'] ) ), $folder_names, true ) )
			) {
				return (string) $candidate_slug;
			}
		}

		return null;
	}

	public function get_choice_for_settings_screen( $slug ) {
		$slug   = (string) $slug;
		$choice = array(
			'value'    => $slug,
			'stand_in' => null,
		);

		if ( '' === $slug || ! is_array( $this->get_templates() ) || isset( $this->templates[ $slug ] ) ) {
			return $choice;
		}

		$moved = $this->get_moved_from_uploads( $slug );

		if ( null !== $moved ) {
			$choice['value'] = $moved;
		} else {
			$in_uploads = $this->get_uploads_template_for( $slug );

			if ( $in_uploads ) {
				$choice['stand_in'] = $this->make_stand_in( $in_uploads, $slug );
			}
		}

		return $choice;
	}

	private function get_uploads_template_for( $slug ) {
		if ( isset( $this->uploads_templates[ $slug ] ) ) {
			return $this->uploads_templates[ $slug ];
		}

		$remembered = $this->get_uploads_slugs();

		if ( isset( $remembered[ $slug ] ) && '' !== $remembered[ $slug ]['folder'] ) {
			foreach ( $this->uploads_templates as $template ) {
				$data = $template->get_template_data();

				if ( basename( (string) end( $data['path'] ) ) === $remembered[ $slug ]['folder'] ) {
					return $template;
				}
			}
		}

		return null;
	}

	public function get_settings_screen_choices( array $settings ) {
		$stand_ins = array();

		foreach ( array( 'menus', 'sidebars', 'statics' ) as $group ) {
			if ( empty( $settings[ $group ] ) || ! is_array( $settings[ $group ] ) ) {
				continue;
			}

			foreach ( $settings[ $group ] as $slot_slug => $slot ) {
				if ( ! is_array( $slot ) || empty( $slot['template'] ) ) {
					continue;
				}

				$choice = $this->get_choice_for_settings_screen( $slot['template'] );

				$settings[ $group ][ $slot_slug ]['template'] = $choice['value'];

				if ( $choice['stand_in'] ) {
					$stand_ins[ $choice['value'] ] = $choice['stand_in'];
				}
			}
		}

		return array(
			'settings'  => $settings,
			'stand_ins' => $stand_ins,
		);
	}

	public function get_uploads_stand_ins() {
		$stand_ins = array_map( array( $this, 'make_stand_in' ), $this->uploads_templates );

		foreach ( array_keys( $this->get_uploads_slugs() ) as $slug ) {
			if ( ! isset( $stand_ins[ $slug ] ) && ! isset( $this->templates[ $slug ] ) ) {
				$in_uploads = $this->get_uploads_template_for( $slug );

				if ( $in_uploads ) {
					$stand_ins[ $slug ] = $this->make_stand_in( $in_uploads, $slug );
				}
			}
		}

		return $stand_ins;
	}

	private function make_stand_in( WPML_LS_Template $template, $slug = null ) {
		$data = $template->get_template_data();

		if ( null !== $slug ) {
			$data['slug'] = (string) $slug;
		}

		/* translators: Added to the name or the folder of a custom language switcher template that is in the uploads folder of the site, which WPML does not use, in the language switcher settings. */
		$data['name']          = $data['name'] . ' (' . __( 'not used any more: move it into your theme', 'sitepress' ) . ')';
		$data['detected_only'] = false;

		return new WPML_LS_Template( $data );
	}

	private function get_uploads_slugs() {
		if ( null === $this->uploads_slugs ) {
			$this->uploads_slugs = array();
			$saved               = get_option( self::OPTION_UPLOADS_SLUGS );

			if ( is_array( $saved ) ) {
				foreach ( $saved as $slug => $entry ) {
					if ( is_array( $entry ) && isset( $entry['folder'] ) && is_string( $entry['folder'] ) ) {
						$this->uploads_slugs[ (string) $slug ] = array(
							'folder' => $entry['folder'],
							'name'   => isset( $entry['name'] ) && is_string( $entry['name'] ) ? $entry['name'] : null,
						);
					}
				}
			}
		}

		return $this->uploads_slugs;
	}

	private function remember_uploads_slugs( array $found ) {
		$known = $this->get_uploads_slugs();

		foreach ( $found as $slug => $entry ) {
			if ( null === $entry['name'] && isset( $known[ $slug ] ) && null !== $known[ $slug ]['name'] ) {
				$found[ $slug ]['name'] = $known[ $slug ]['name'];
			}
		}

		$merged = array_merge( $known, $found );

		if ( $merged !== $known ) {
			$this->uploads_slugs = $merged;
			update_option( self::OPTION_UPLOADS_SLUGS, $merged, false );
		}
	}

	private function get_uploads_slug_prefixes() {
		return array_values(
			array_unique(
				array_filter(
					array( 'uploads', sanitize_title_with_dashes( __( 'Uploads', 'sitepress' ) ) )
				)
			)
		);
	}

	public function get_all_templates_data() {
		$ret = array();

		foreach ( $this->get_templates() as $slug => $template ) {
			$ret[ $slug ] = $template->get_template_data();
		}

		return $ret;
	}

	private function init_available_templates() {
		$is_admin_ui_page = isset( $_GET['page'] ) && WPML_LS_Admin_UI::get_page_hook() === $_GET['page'];

		if ( ! $is_admin_ui_page ) {
			$this->templates = $this->get_templates_from_transient();
		}

		if ( $this->templates === false ) {

			$this->templates         = array();
			$this->uploads_templates = array();
			$dirs_to_scan            = array();

			$dirs_to_scan = apply_filters( 'wpml_ls_directories_to_scan', $dirs_to_scan );

			$sub_dir          = $this->ds . 'templates' . $this->ds . 'language-switchers';
			$wpml_core_path   = WPML_PLUGIN_PATH . $sub_dir;
			$theme_path       = get_template_directory() . $this->ds . 'wpml' . $sub_dir;
			$child_theme_path = get_stylesheet_directory() . $this->ds . 'wpml' . $sub_dir;
			$uploads_path     = $this->get_uploads_path() . $this->ds . 'wpml' . $sub_dir;

			array_unshift( $dirs_to_scan, $wpml_core_path, $theme_path, $child_theme_path, $uploads_path );

			$this->php_roots = array_values( array_filter( $dirs_to_scan, 'is_string' ) );

			$templates_array_data = [];
			$uploads_seen         = array();
			$templates_paths      = $this->scan_template_paths( $dirs_to_scan );

			foreach ( $templates_paths as $template_path => $engine ) {
				$template_path = $this->wpml_file->fix_dir_separator( $template_path );
				if ( $engine ) {
					$tpl    = array();
					$config = $this->parse_template_config( $template_path );

					$is_core = $this->is_core_template( $template_path );

					$tpl['path']        = [ $wpml_core_path, $template_path ];
					$tpl['engine']      = $engine;
					$tpl['php_allowed'] = $this->may_have_php_template( $template_path );
					$tpl['in_uploads']  = $this->is_in_uploads( $template_path );
					$tpl['version']  = $this->get_template_version( $config, $is_core );
					$tpl['name']     = isset( $config['name'] ) ? $config['name'] : null;
					$tpl['name']     = $this->get_unique_name( $tpl['name'], $template_path );
					$tpl['slug']     = sanitize_title_with_dashes( $tpl['name'] );
					$tpl['base_uri'] = trailingslashit( $this->wpml_file->get_uri_from_path( $template_path, false ) );
					$tpl['css']      = $this->get_files( 'css', $template_path, $config );
					$tpl['js']       = $this->get_files( 'js', $template_path, $config );

					$tpl['flags_base_uri'] = isset( $config['flags_dir'] )
						? $this->wpml_file->get_uri_from_path( $template_path . $this->ds . $config['flags_dir'], false ) : null;
					$tpl['flags_base_uri'] = ! isset( $tpl['flags_base_uri'] ) && file_exists( $template_path . $this->ds . 'flags' )
						? $this->wpml_file->get_uri_from_path( $template_path . $this->ds . 'flags', false ) : $tpl['flags_base_uri'];

					$tpl['flag_extension'] = isset( $config['flag_extension'] )
						? $config['flag_extension'] : null;

					if ( $is_core ) {
						$tpl['is_core'] = true;
						$tpl['slug']    = isset( $config['slug'] ) ? $config['slug'] : $tpl['slug'];
					}

					$tpl['for']            = isset( $config['for'] )
						? $config['for'] : array( 'menus', 'sidebars', 'footer', 'post_translations', 'shortcode_actions' );
					$tpl['force_settings'] = isset( $config['settings'] )
						? $config['settings'] : array();

					if ( $tpl['in_uploads'] ) {
						$tpl['detected_only']                    = true;
						$templates_array_data[ $tpl['slug'] ]    = $tpl;
						$this->uploads_templates[ $tpl['slug'] ] = new WPML_LS_Template( $tpl );
						$uploads_seen[ (string) $tpl['slug'] ]   = array(
							'folder' => basename( $template_path ),
							'name'   => isset( $config['name'] ) && is_string( $config['name'] ) ? $config['name'] : basename( $template_path ),
						);
						continue;
					}

					$templates_array_data[ $tpl['slug'] ] = $tpl;
					$this->templates[ $tpl['slug'] ]      = new WPML_LS_Template( $tpl );
				}
			}
			$this->save_templates( $templates_array_data );
			$this->remember_uploads_slugs( $uploads_seen );
		}

		return $this->templates;
	}

	private function scan_template_paths( $dirs_to_scan ) {
		$templates_paths = array();

		foreach ( $dirs_to_scan as $dir ) {
			if ( ! is_dir( $dir ) ) {
				continue;
			}
			$files = scandir( $dir );
			$files = $files ? array_diff( $files, array( '..', '.' ) ) : [];
			if ( count( $files ) > 0 ) {
				foreach ( $files as $file ) {
					$template_path = $dir . '/' . $file;
					if ( is_dir( $template_path ) && file_exists( $template_path . $this->ds . self::CONFIG_FILE ) ) {
						$engine = $this->get_template_engine( $template_path );

						if ( $engine ) {
							$templates_paths[ $template_path ] = $engine;
						}
					}
				}
			}
		}

		return $templates_paths;
	}

	private function get_template_engine( $template_path ) {
		if ( $this->is_in_uploads( $template_path ) ) {
			if ( file_exists( $template_path . $this->ds . WPML_LS_Template::PHP_FILENAME ) ) {
				return WPML_LS_Template::ENGINE_PHP;
			}

			return file_exists( $template_path . $this->ds . WPML_LS_Template::FILENAME ) ? WPML_LS_Template::ENGINE_TWIG : null;
		}

		if ( $this->may_have_php_template( $template_path )
			 && file_exists( $template_path . $this->ds . WPML_LS_Template::PHP_FILENAME )
		) {
			return WPML_LS_Template::ENGINE_PHP;
		}

		if ( file_exists( $template_path . $this->ds . WPML_LS_Template::FILENAME ) ) {
			return WPML_LS_Template::ENGINE_TWIG;
		}

		return null;
	}

	private function may_have_php_template( $template_path ) {
		return ! $this->is_in_uploads( $template_path )
			   && FolderIdentity::inside( $template_path, array_diff( $this->php_roots, array( (string) $this->get_uploads_path() ) ), true );
	}

	private function is_in_uploads( $template_path ) {
		return UploadsFolder::contains( $template_path, (string) $this->get_uploads_path() );
	}

	private function parse_template_config( $template_path ) {
		$config             = array();
		$configuration_file = $template_path . $this->ds . self::CONFIG_FILE;
		if ( file_exists( $configuration_file ) ) {
			$json_content = file_get_contents( $configuration_file );
			$config       = $json_content ? json_decode( $json_content, true ) : [];
		}

		return $config;
	}

	private function get_files( $ext, $template_path, $config ) {
		$resources    = array();
		$trimProtocol = false;

		if ( isset( $config[ $ext ] ) ) {
			$config[ $ext ] = is_array( $config[ $ext ] ) ? $config[ $ext ] : array( $config[ $ext ] );
			foreach ( $config[ $ext ] as $file ) {
				$file        = untrailingslashit( $template_path ) . $this->ds . $file;
				$resources[] = $this->wpml_file->get_uri_from_path( $file, $trimProtocol );
			}
		} else {
			$search_path = $template_path . $this->ds . '*.' . $ext;
			if ( glob( $search_path ) ) {
				foreach ( glob( $search_path ) as $file ) {
					$resources[] = $this->wpml_file->get_uri_from_path( $file, $trimProtocol );
				}
			}
		}

		return $resources;
	}

	private function get_unique_name( $name, $path ) {
		if ( is_null( $name ) ) {
			$name = basename( $path );
		}

		if ( strpos( $path, $this->wpml_file->fix_dir_separator( get_template_directory() ) ) === 0 ) {
			$theme = wp_get_theme();
			$name  = $theme . ' - ' . $name;
		} elseif ( strpos( $path, $this->wpml_file->fix_dir_separator( $this->get_uploads_path() ) ) === 0 ) {
			/* translators: Group heading in the language switcher style dropdown, for styles the user uploaded to the site. */
			$name = __( 'Uploads', 'sitepress' ) . ' - ' . $name;
		} elseif (
			strpos( $path, $this->wpml_file->fix_dir_separator( WPML_PLUGINS_DIR ) ) === 0
			&& ! $this->is_core_template( $path )
		) {
			$plugin_dir = $this->wpml_file->fix_dir_separator( WPML_PLUGINS_DIR );
			$plugin_dir = preg_replace( '#' . preg_quote( $plugin_dir ) . '#', '', $path, 1 );
			$plugin_dir = ltrim( $plugin_dir, $this->ds );
			$plugin_dir = explode( $this->ds, $plugin_dir );

			if ( isset( $plugin_dir[0] ) ) {
				$require = ABSPATH . 'wp-admin' . $this->ds . 'includes' . $this->ds . 'plugin.php';
				require_once $require;
				foreach ( get_plugins() as $slug => $plugin ) {
					if ( strpos( $slug, $plugin_dir[0] ) === 0 ) {
						$name = $plugin['Name'] . ' - ' . $name;
						break;
					}
				}
			} else {
				$name = substr( md5( $path ), 0, 8 ) . ' - ' . $name;
			}
		}

		return $name;
	}

	private function get_template_version( array $config, $is_core ) {
		if ( isset( $config['version'] ) ) {
			return (string) $config['version'];
		}

		return $is_core ? ICL_SITEPRESS_SCRIPT_VERSION : '1';
	}

	private function is_core_template( $path ) {
		return strpos( $path, $this->wpml_file->fix_dir_separator( WPML_PLUGIN_PATH ) ) === 0;
	}

	private function get_templates_from_transient() {
		$this->are_templates_loaded_from_cache = false;
		$templates                             = get_option( self::OPTION_NAME );

		if ( $templates ) {
			$uploads = array();

			foreach ( $templates as $slug => $data ) {
				if ( is_array( $data ) ) {
					if ( ! isset( $data['engine'] ) ) {
						return false;
					}

					if ( ! empty( $data['in_uploads'] ) && empty( $data['detected_only'] ) ) {
						return false;
					}

					$templates[ $slug ] = new WPML_LS_Template( $data );

					if ( ! empty( $data['detected_only'] ) ) {
						$uploads[ $slug ] = $templates[ $slug ];
					}
				}
			}

			if ( $templates && ! $this->are_template_paths_valid( $templates ) ) {
				return false;
			}

			$this->uploads_templates               = $uploads;
			$templates                             = array_diff_key( $templates, $uploads );
			$this->are_templates_loaded_from_cache = true;
		}

		return $templates;
	}

	private function remember_cached_uploads_slugs() {
		$cached = get_option( self::OPTION_NAME );

		if ( is_array( $cached ) ) {
			$this->remember_registered_uploads_templates( $cached );
		}
	}

	private function remember_registered_uploads_templates( array $cached ) {
		$found = array();

		foreach ( $cached as $slug => $data ) {
			if ( is_array( $data ) && ! empty( $data['in_uploads'] ) && empty( $data['detected_only'] ) && ! empty( $data['path'] ) ) {
				$found[ (string) $slug ] = array(
					'folder' => basename( (string) end( $data['path'] ) ),
					'name'   => null,
				);
			}
		}

		if ( $found ) {
			$this->remember_uploads_slugs( $found );
		}
	}

	private function save_templates( $data ) {
		$this->remember_cached_uploads_slugs();
		update_option( self::OPTION_NAME, $data );
	}

	private function are_template_paths_valid( $templates ) {
		$paths_are_valid = true;
		foreach ( $templates as $template ) {
			if ( ! method_exists( $template, 'is_path_valid' ) || ! $template->is_path_valid() ) {
				$paths_are_valid = false;
				break;
			}
		}
		return $paths_are_valid;
	}

	private function get_uploads_path() {
		if ( ! $this->uploads_path ) {
			$uploads = wp_upload_dir( null, false );

			if ( isset( $uploads['basedir'] ) ) {
				$this->uploads_path = $uploads['basedir'];
			}
		}

		return $this->uploads_path;
	}
}
