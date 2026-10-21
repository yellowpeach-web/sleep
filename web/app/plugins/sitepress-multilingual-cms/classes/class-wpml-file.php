<?php

class WPML_File {
	private $wp_api;

	private $filesystem;

	public function __construct( ?WPML_WP_API $wp_api = null, ?WP_Filesystem_Base $filesystem = null ) {
		if ( ! $wp_api ) {
			$wp_api = new WPML_WP_API();
		}

		$this->wp_api = $wp_api;

		if ( ! $filesystem ) {
			$filesystem = $this->wp_api->get_wp_filesystem();
		}

		$this->filesystem = $filesystem;
	}

	public function fix_dir_separator( $path ) {
		$directory_separator = $this->wp_api->constant( 'DIRECTORY_SEPARATOR' );

		return ( '\\' === $directory_separator ) ? str_replace( '/', '\\', $path ) : str_replace( '\\', '/', $path );
	}

	public function get_uri_from_path( $path, $trimProtocol = true ) {
		$base = null;

		if ( $this->wp_api->defined( 'WP_CONTENT_DIR' ) && $this->wp_api->defined( 'WP_CONTENT_URL' ) ) {
			$base_path = $this->fix_dir_separator( (string) $this->wp_api->constant( 'WP_CONTENT_DIR' ) );

			$content_base = array(
				'path' => $base_path,
				'uri'  => (string) $this->wp_api->constant( 'WP_CONTENT_URL' ),
			);

			if ( 0 === strpos( $path, $base_path ) ) {
				$base = $content_base;
			}

			if ( false === strpos( $path, $base_path ) ) {
				$base = $content_base;

				$wpml_plugin_folder_parts = explode(
					(string) $this->wp_api->constant( 'DIRECTORY_SEPARATOR' ),
					(string) $this->wp_api->constant( 'WPML_PLUGIN_PATH' )
				);

				if ( $wpml_plugin_folder_parts ) {
					$wpml_plugin_folder_parts = $this->pop_folder_array( 1, $wpml_plugin_folder_parts );

					$wpml_plugins_folder = implode(
						(string) $this->wp_api->constant( 'DIRECTORY_SEPARATOR' ),
						$wpml_plugin_folder_parts
					);
					$path                = str_replace(
						$wpml_plugins_folder,
						(string) $this->wp_api->constant( 'WP_PLUGIN_DIR' ),
						$path
					);
				}

				$base = $this->get_base_containing_path( $path, $content_base );
			}
		}

		if ( ! $base ) {
			$base = array(
				'path' => (string) $this->wp_api->constant( 'ABSPATH' ),
				'uri'  => (string) site_url(),
			);
		}

		if ( $trimProtocol ) {
			$base['uri'] = preg_replace( '/(^https?:)/', '', (string) $base['uri'] );
		}

		$relative_path = substr( $path, strlen( $base['path'] ) );
		$relative_path = str_replace( array( '/', '\\' ), '/', $relative_path );
		$relative_path = ltrim( $relative_path, '/' );

		return trailingslashit( (string) $base['uri'] ) . $relative_path;
	}

	private function get_base_containing_path( $path, array $default_base ) {
		$candidates = array(
			array( 'WP_CONTENT_DIR', 'WP_CONTENT_URL' ),
			array( 'WP_PLUGIN_DIR', 'WP_PLUGIN_URL' ),
		);

		foreach ( $candidates as $candidate ) {
			list( $dir_constant, $uri_constant ) = $candidate;

			if ( ! $this->wp_api->defined( $dir_constant ) || ! $this->wp_api->defined( $uri_constant ) ) {
				continue;
			}

			$dir = $this->fix_dir_separator( (string) $this->wp_api->constant( $dir_constant ) );

			if ( '' !== $dir && 0 === strpos( $path, $dir ) ) {
				return array(
					'path' => $dir,
					'uri'  => (string) $this->wp_api->constant( $uri_constant ),
				);
			}
		}

		return $default_base;
	}

	private function pop_folder_array( $times, $folder_array ) {
		$latest_folder = array_pop( $folder_array );
		if ( '..' === $latest_folder ) {
			return $this->pop_folder_array( $times + 1, $folder_array );
		}
		if ( '.' === $latest_folder ) {
			return $this->pop_folder_array( $times, $folder_array );
		}
		if ( $times > 1 ) {
			return $this->pop_folder_array( $times - 1, $folder_array );
		}
		return $folder_array;
	}

	public function get_relative_path( $path ) {
		$path    = $this->fix_dir_separator( $path );
		$abspath = $this->fix_dir_separator( (string) $this->wp_api->constant( 'ABSPATH' ) );

		if ( '' !== $abspath && 0 === strpos( $path, $abspath ) ) {
			return (string) substr( $path, strlen( $abspath ) );
		}

		return $path;
	}

	public function get_full_path( $path ) {
		$relative_path = $this->get_relative_path( $path );
		if ( 0 === strpos( $relative_path, '/' ) || 0 === strpos( $relative_path, '\\' ) || 1 === preg_match( '#^[A-Za-z]:[\\\\/]#', $relative_path ) ) {
			return $relative_path;
		}

		return (string) $this->wp_api->constant( 'ABSPATH' ) . $relative_path;
	}

	public function file_exists( $path ) {
		return $this->filesystem->is_readable( $this->get_full_path( $path ) );
	}

	public function get_file_modified_timestamp( $path ) {
		return $this->filesystem->mtime( $this->get_full_path( $path ) );
	}
}
