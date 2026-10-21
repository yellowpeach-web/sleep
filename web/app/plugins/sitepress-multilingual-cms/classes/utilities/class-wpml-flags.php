<?php
use WPML\FP\Obj;
use WPML\LanguageEditor\Flags\FlagFile;
use WPML\LanguageEditor\LanguageCodeResolution;
use WPML\TM\Settings\Flags\Options;

class WPML_Flags {

	const CODE_GLYPH = '@code';

	const CACHE_NAME = 'flags';

	private $cache;

	private $wpdb;

	private $filesystem;

	public function __construct( $wpdb, icl_cache $cache, ?WP_Filesystem_Base $filesystem = null ) {
		$this->wpdb       = $wpdb;
		$this->cache      = $cache;
		$this->filesystem = $filesystem;
	}

	private function get_filesystem() {
		if ( ! $this->filesystem ) {
			$wp_api           = new WPML_WP_API();
			$this->filesystem = $wp_api->get_wp_filesystem();
		}

		return $this->filesystem;
	}

	public function get_flag( $lang_code ) {
		$wpdb = $this->wpdb;

		$flag = $this->cache->get( $lang_code );

		if ( ! $flag ) {
			$flag = $wpdb->get_row(
				$wpdb->prepare(
					"SELECT flag, from_template
                                                    FROM {$wpdb->prefix}icl_flags
                                                    WHERE lang_code=%s",
					$lang_code
				)
			);

			$this->cache->set( $lang_code, $flag );
		}

		return $flag;
	}

	public function get_flag_url( $lang_code ) {
		$flag = $this->get_flag( $lang_code );
		if ( ! $flag ) {
			return $this->append_path_to_url( self::get_wpml_flags_url(), $this->catalogue_flag_file( $lang_code ) );
		}

		$name = isset( $flag->flag ) ? trim( (string) $flag->flag ) : '';
		if ( self::CODE_GLYPH === $name ) {
			return '';
		}

		if ( $flag->from_template ) {
			$wp_upload_dir = wp_upload_dir();
			$path          = 'flags/' . $name;

			if ( '' !== $name && $this->flag_file_exists( $wp_upload_dir['basedir'] . '/' . $path ) ) {
				return $this->append_path_to_url( $wp_upload_dir['baseurl'], $path );
			}

			return $this->append_path_to_url( self::get_wpml_flags_url(), FlagFile::resolve( $name ) );
		}

		$base_url = self::get_wpml_flags_url();
		if ( ! $this->names_a_directory( $name ) && $this->flag_file_exists( self::get_wpml_flags_directory() . $name ) ) {
			return $this->append_path_to_url( $base_url, $name );
		}

		return $this->append_path_to_url( $base_url, FlagFile::resolve( $name ) );
	}

	private function catalogue_flag_file( $lang_code ) {
		$resolved = LanguageCodeResolution::resolve( $lang_code );
		$pair     = null !== $resolved && ! empty( $resolved['pair_flag'] ) ? (string) $resolved['pair_flag'] : '';

		return '' === $pair ? FlagFile::NEUTRAL_GLOBE : FlagFile::resolve( $pair );
	}

	private function names_a_directory( $name ) {
		return '' === $name || '/' === substr( $name, -1 );
	}

	public function get_flag_image( $lang_code, $size = [], $fallback_text = '', $css_classes = [] ) {
		$url = $this->get_flag_url( $lang_code );

		if ( ! $url ) {
			return $fallback_text;
		}

		$class_attribute = is_array( $css_classes ) && ! empty( $css_classes )
			? ' class="' . implode( ' ',  $css_classes ) . '"'
			: '';

		return '<img' . $class_attribute . '
					width="' . Obj::propOr( 18, 0, $size ) . '"
					height="' . Obj::propOr( 12, 1, $size ) . '"
					src="' . esc_url( $url ) . '"
					alt="' . esc_attr( sprintf( /* translators: Screen reader name of a flag image. %s: the name of the country or language it stands for, as in "Flag for Spain". */ __( 'Flag for %s', 'sitepress' ), $lang_code ) ) . '"
				/>';
	}

	public function clear() {
		$this->cache->clear();
	}

	public static function invalidate() {
		global $wpml_term_translations, $wpml_post_translations;

		if ( ! is_object( $wpml_term_translations ) || ! is_object( $wpml_post_translations ) ) {
			return;
		}

		$cache = new icl_cache( self::CACHE_NAME, true );
		$cache->clear();
	}

	public function get_wpml_flags( $allowed_file_types = null ) {
		if ( null === $allowed_file_types ) {
			$allowed_file_types = array( 'gif', 'jpeg', 'png', 'svg' );
		}

		$files = $this->get_filesystem()->dirlist( $this->get_wpml_flags_directory(), false );

		if ( ! $files ) {
			return [];
		}

		$files = array_keys( $files );

		$result = $this->filter_flag_files( $allowed_file_types, $files );
		sort( $result );

		return $result;
	}

	final public function get_wpml_flags_directory() {
		return WPML_PLUGIN_PATH . '/res/flags/';
	}

	final public static function get_wpml_flags_url() {
		return ICL_PLUGIN_URL . '/res/flags/';
	}

	final public static function get_wpml_flags_by_locales_url() {
		return ICL_PLUGIN_URL . '/res/flags_by_locales.json';
	}

	final public static function get_wpml_flag_image_ext() {
		return Options::getFormat();
	}

	private function flag_file_exists( $path ) {
		return $this->get_filesystem()->exists( $path );
	}

	private function filter_flag_files( $allowed_file_types, $files ) {
		$result = array();
		foreach ( $files as $file ) {
			$path = $this->get_wpml_flags_directory() . $file;
			if ( $this->flag_file_exists( $path ) ) {
				$ext = pathinfo( $path, PATHINFO_EXTENSION );
				if ( in_array( $ext, $allowed_file_types, true ) ) {
					$result[] = $file;
				}
			}
		}

		return $result;
	}

	private function append_path_to_url( $base_url, $path ) {
		$base_url_parts = wp_parse_url( $base_url );

		$base_url_path_components = array();
		if ( $base_url_parts && array_key_exists( 'path', $base_url_parts ) ) {
			$base_url_path_components = explode( '/', untrailingslashit( $base_url_parts['path'] ) );
		}

		$sub_dir_path_components = explode( '/', trim( $path, '/' ) );
		foreach ( $sub_dir_path_components as $sub_dir_path_part ) {
			$base_url_path_components[] = $sub_dir_path_part;
		}

		$base_url_parts['path'] = implode( '/', $base_url_path_components );

		return http_build_url( $base_url_parts );
	}
}
