<?php

use WPML\LanguageSwitcher\AllowedTemplateFolders;
use WPML\LanguageSwitcher\UploadsFolder;

class WPML_LS_Template extends WPML_Templates_Factory {

	const FILENAME     = 'template.twig';
	const PHP_FILENAME = 'template.php';

	const ENGINE_TWIG = 'twig';
	const ENGINE_PHP  = 'php';

	const SOURCE_PHP  = 'php';
	const SOURCE_TWIG = 'twig';

	private $template;

	private $model;

	private $prefix = 'wpml-ls-';

	private $own_folder_in_uploads = false;

	public function __construct( $template_data, $template_model = array() ) {
		$this->template        = $this->format_data($template_data);
		$this->template['js']  = self::remove_non_minified_duplicates( $this->template['js'] );
		$this->template['css'] = self::remove_non_minified_duplicates( $this->template['css'] );

		if ( array_key_exists( 'template_string', $this->template ) ) {
			$this->template_string = $this->template['template_string'];
		}

		$this->model = $template_model;
		parent::__construct();
	}

	private function format_data( $template_data ) {
		foreach ( array( 'path', 'js', 'css' ) as $k ) {
			$template_data[ $k ] = isset( $template_data[ $k ] ) ? $template_data[ $k ] : array();
			$template_data[ $k ] = is_array( $template_data[ $k ] ) ? $template_data[ $k ] : array( $template_data[ $k ] );
		}

		return $template_data;
	}

	public function set_model( $model ) {
		$this->model = is_array( $model ) ? $model : array( $model );
	}

	public function get_engine() {
		return isset( $this->template['engine'] ) && self::ENGINE_PHP === $this->template['engine']
			? self::ENGINE_PHP
			: self::ENGINE_TWIG;
	}

	public function is_missing() {
		return empty( $this->template['path'] ) && ! $this->is_string_template();
	}

	public function is_in_uploads() {
		if ( $this->is_core() || $this->is_string_template() || empty( $this->template['path'] ) ) {
			return false;
		}

		if ( $this->own_folder_in_uploads ) {
			return true;
		}

		$paths = (array) $this->template_paths;

		return ( ! empty( $paths ) && UploadsFolder::contains( (string) end( $paths ) ) )
			|| UploadsFolder::contains( (string) end( $this->template['path'] ) );
	}

	private function without_uploads_folders( array $paths ) {
		$usable = array();

		foreach ( $paths as $path ) {
			if ( ! UploadsFolder::contains( (string) $path ) ) {
				$usable[] = $path;
			}
		}

		if ( empty( $usable ) && $this->is_core() ) {
			return array_values( (array) $this->template['path'] );
		}

		return $usable;
	}

	public function get_html( $sandbox = false ) {
		$this->skip_uploads_folders();

		if ( self::ENGINE_PHP === $this->get_engine() && ! $this->is_string_template() ) {
			$source = $this->get_source();

			if ( self::SOURCE_TWIG !== $source['kind'] ) {
				if ( ! $this->is_core() ) {
					return $this->get_php_html( $source['file'] );
				}

				try {
					return $this->get_php_html( $source['file'] );
				} catch ( \Throwable $e ) {
					$this->log_core_php_failure( $e );
				}
			}
		}

		$ret = '';
		if ( $this->template_paths || $this->template_string ) {
			if ( $sandbox ) {
				$ret = parent::get_sandbox_view( null, null );
			} else {
				$ret = parent::get_view( null, null );
			}
		}
		return $ret;
	}

	private function skip_uploads_folders() {
		$paths = (array) $this->template_paths;

		if ( empty( $paths ) ) {
			return;
		}

		$usable = $this->without_uploads_folders( $paths );

		if ( count( $usable ) !== count( $paths ) ) {
			$this->own_folder_in_uploads = $this->own_folder_in_uploads || UploadsFolder::contains( (string) end( $paths ) );
			$this->template_paths        = $usable;

			foreach ( array_diff( $paths, $usable ) as $skipped ) {
				$this->log_skipped_uploads_folder( (string) $skipped );
			}
		}
	}

	private function log_skipped_uploads_folder( $folder ) {
		static $logged = array();

		$folder = rtrim( $folder, '/\\' );

		if ( isset( $logged[ $folder ] ) ) {
			return;
		}

		foreach ( array( self::PHP_FILENAME, self::FILENAME, 'flag.twig' ) as $name ) {
			if ( is_file( $folder . DIRECTORY_SEPARATOR . $name ) ) {
				$logged[ $folder ] = true;

				( new WPML_WP_API() )->error_log(
					sprintf( 'WPML: the language switcher template folder %s is in the uploads folder and is not used. Move the template into your theme.', $folder )
				);

				return;
			}
		}
	}

	protected function uses_php_twins() {
		return false;
	}

	private function get_php_html( $file ) {
		if ( ! $file ) {
			throw new \RuntimeException( 'The language switcher template has no template.php file.' );
		}

		return self::include_php_template( $file, is_array( $this->model ) ? $this->model : array() );
	}

	private function get_source() {
		$paths = array_values( (array) $this->template_paths );

		foreach ( $paths as $index => $path ) {
			$folder = rtrim( $path, '/\\' );
			$file   = $folder . DIRECTORY_SEPARATOR . self::PHP_FILENAME;

			if ( is_file( $file ) ) {
				$real   = realpath( $file );
				$reason = UploadsFolder::contains( $file )
					? AllowedTemplateFolders::REASON_UPLOADS
					: ( false === $real ? AllowedTemplateFolders::REASON_OUTSIDE : AllowedTemplateFolders::refusal( $real ) );

				if ( null !== $reason ) {
					$this->log_refused_php_file( $file, $reason );

					return array( 'kind' => self::SOURCE_PHP, 'file' => null );
				}

				if ( $this->is_core() && $this->has_flag_override( $paths ) ) {
					return array( 'kind' => self::SOURCE_TWIG, 'file' => null );
				}

				return array( 'kind' => self::SOURCE_PHP, 'file' => $real );
			}

			if ( is_file( $folder . DIRECTORY_SEPARATOR . self::FILENAME ) ) {
				return array( 'kind' => self::SOURCE_TWIG, 'file' => null );
			}
		}

		return array( 'kind' => self::SOURCE_PHP, 'file' => null );
	}

	private function has_flag_override( array $paths ) {
		$own = realpath( WPML_PLUGIN_PATH . '/templates/language-switchers' );

		foreach ( $paths as $path ) {
			$folder = rtrim( $path, '/\\' );

			if ( is_file( $folder . DIRECTORY_SEPARATOR . 'flag.twig' ) ) {
				return false === $own || realpath( $folder ) !== $own;
			}
		}

		return false;
	}

	private function log_core_php_failure( \Throwable $e ) {
		static $logged = array();

		$slug = isset( $this->template['slug'] ) ? $this->template['slug'] : '';

		if ( isset( $logged[ $slug ] ) ) {
			return;
		}

		$logged[ $slug ] = true;

		( new WPML_WP_API() )->error_log(
			sprintf( 'WPML: the PHP version of the language switcher template %s failed (%s); its Twig version is used.', $slug, $e->getMessage() )
		);
	}

	private function log_refused_php_file( $file, $reason ) {
		static $logged = array();

		if ( isset( $logged[ $file ] ) ) {
			return;
		}

		$logged[ $file ] = true;

		$why = AllowedTemplateFolders::REASON_UPLOADS === $reason
			? 'PHP templates are not loaded from the uploads folder. Move the template into your theme.'
			: 'it is not in WPML, the theme, the child theme or a folder added with wpml_ls_directories_to_scan.';

		( new WPML_WP_API() )->error_log( sprintf( 'WPML: the language switcher template file %s is not used: %s', $file, $why ) );
	}

	private static function include_php_template( $wpml_ls_template_file, array $model ) {
		$wpml_ls_ob_level = ob_get_level();

		ob_start();

		try {
			extract( $model, EXTR_SKIP );
			include $wpml_ls_template_file;
		} catch ( \Throwable $e ) {
			while ( ob_get_level() > $wpml_ls_ob_level ) {
				ob_end_clean();
			}
			throw $e;
		}

		return (string) ob_get_clean();
	}

	public function get_styles( $with_version = false ) {
		$styles = $with_version
			? array_map( array( $this, 'add_resource_version' ), $this->template['css'] )
			: $this->template['css'];

		return array_values( $styles );
	}

	public function has_styles() {
		return ! empty( $this->template['css'] );
	}

	public function get_scripts( $with_version = false ) {
		$scripts = $with_version
			? array_map( array( $this, 'add_resource_version' ), $this->template['js'] )
			: $this->template['js'];

		return array_values( $scripts );
	}

	private function add_resource_version( $url ) {
		return $url . '?ver=' . $this->get_version();
	}

	public function get_resource_handler( $index ) {
		$slug   = isset( $this->template['slug'] ) ? $this->template['slug'] : '';
		$prefix = $this->is_core() ? '' : $this->prefix;
		return $prefix . $slug . '-' . $index;
	}

	public function get_inline_style_handler() {
		$count = count( $this->template['css'] );

		return $count > 0 ? $this->get_resource_handler( $count - 1 ) : null;
	}

	public function get_version() {
		return $this->template['version'];
	}

	protected function init_template_base_dir() {
		$this->template_paths = $this->template['path'];
	}

	public function get_template() {
		$template = self::FILENAME;

		if ( isset( $this->template_string ) ) {
			$template = $this->template_string;
		} elseif ( array_key_exists( 'filename', $this->template ) ) {
			$template = $this->template['filename'];
		}

		return $template;
	}

	public function get_model() {
		return $this->model;
	}

	public function get_template_data() {
		$template = $this->template;

		if ( $this->is_core() && isset( $template['name'] ) ) {
			$template['name'] = self::core_template_display_name( $template['name'] );
		}

		return $template;
	}

	public static function core_template_display_name( $name ) {
		switch ( $name ) {
			case 'Dropdown':
				/* translators: Name of the language switcher style where the languages open as a dropdown from the current language, on WPML → Languages; in the Elementor language switcher widget, the heading of the color settings for that list of languages. */
				return __( 'Dropdown', 'sitepress' );
			case 'Dropdown click':
				/* translators: Name of a built-in language switcher style, in the style dropdown on WPML → Languages: the languages open as a dropdown when it is clicked. */
				return __( 'Dropdown click', 'sitepress' );
			case 'Horizontal List':
				/* translators: Name of a built-in language switcher style, in the style dropdown on WPML → Languages: the languages are listed side by side. */
				return __( 'Horizontal List', 'sitepress' );
			case 'Vertical List':
				/* translators: Name of a built-in language switcher style, in the style dropdown on WPML → Languages: the languages are listed one under the other. */
				return __( 'Vertical List', 'sitepress' );
			case 'Post translations':
				/* translators: Name of a built-in language switcher style, in the style dropdown on WPML → Languages: the links to a post's translations shown above or below the post. */
				return __( 'Post translations', 'sitepress' );
			case 'Menu Item':
				/* translators: Name of a built-in language switcher style, in the style dropdown on WPML → Languages: the switcher placed in a menu. */
				return __( 'Menu Item', 'sitepress' );
		}

		return $name;
	}

	public function set_template_data( $template ) {
		$this->template = $template;
	}

	public function is_core() {
		return isset( $this->template['is_core'] ) ? (bool) $this->template['is_core'] : false;
	}

	public function supported_slot_types() {
		return isset( $this->template['for'] ) ? $this->template['for'] : array();
	}

	public function force_settings() {
		return isset( $this->template['force_settings'] ) ? $this->template['force_settings'] : array();
	}

	public function is_path_valid() {
		$valid                = true;
		$this->template_paths = apply_filters( 'wpml_ls_template_paths', $this->template_paths );

		foreach ( $this->template_paths as $path ) {
			if ( ! file_exists( $path ) ) {
				$valid = false;
				break;
			}
		}

		return $valid && $this->is_engine_still_right();
	}

	private function is_engine_still_right() {
		$paths = (array) $this->template_paths;

		if ( empty( $paths ) ) {
			return true;
		}

		$has_php_file = is_file( rtrim( (string) end( $paths ), '/\\' ) . DIRECTORY_SEPARATOR . self::PHP_FILENAME );

		if ( self::ENGINE_PHP === $this->get_engine() ) {
			return $this->is_core() || $has_php_file;
		}

		return ! ( $has_php_file && ! empty( $this->template['php_allowed'] ) );
	}

	public function set_template_string( $template_string ) {
		if ( method_exists( $this, 'is_string_template' ) ) {
			$this->template_string = $template_string;
		}
	}



    public static function remove_non_minified_duplicates( array $assets ) {
        $hasMinifiedVersion = function( $url ) use ( $assets ) {
            $extension = pathinfo( $url, PATHINFO_EXTENSION );
            $basename  = pathinfo( $url, PATHINFO_BASENAME );
            $filename  = pathinfo( $url, PATHINFO_FILENAME );

            $url_start     = substr( $url, 0, strlen( $url ) - strlen( $basename ) );
            $minified_file = $filename . '.min.' . $extension;
            if ( in_array( $url_start . $minified_file, $assets, true ) ) {
                return true;
            }

            return false;
        };

        return wpml_collect( $assets )
            ->reject( $hasMinifiedVersion )
            ->toArray();
    }
}
