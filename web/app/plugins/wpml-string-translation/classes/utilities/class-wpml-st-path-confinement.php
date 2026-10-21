<?php

class WPML_ST_Path_Confinement {

	private static $registered_plugin_roots = null;

	public static function resolve_contained( $path, $root, $allow_root = false ) {
		$canonical_root = realpath( $root );
		if ( false === $canonical_root ) {
			return false;
		}

		$canonical_path = realpath( $path );
		if ( false === $canonical_path ) {
			return false;
		}

		return self::is_canonical_path_within( $canonical_path, $canonical_root, $allow_root ) ? $canonical_path : false;
	}

	private static function is_canonical_path_within( $canonical_path, $canonical_root, $allow_root ) {
		if ( $allow_root && $canonical_path === $canonical_root ) {
			return true;
		}

		return strpos( $canonical_path, $canonical_root . DIRECTORY_SEPARATOR ) === 0;
	}

	public static function resolve_registered_plugin_file( $plugin_file ) {
		if ( ! self::is_safe_plugin_file_identifier( $plugin_file ) ) {
			return false;
		}

		if ( ! function_exists( 'get_plugins' ) ) {
			require_once ABSPATH . 'wp-admin/includes/plugin.php';
		}

		$plugins = (array) get_plugins();
		if ( ! array_key_exists( $plugin_file, $plugins ) ) {
			return self::resolve_registered_mu_plugin_file( $plugin_file );
		}

		$logical_file   = WP_PLUGIN_DIR . '/' . $plugin_file;
		$canonical_file = realpath( $logical_file );

		if ( false === $canonical_file || ! is_file( $canonical_file ) || ! is_readable( $canonical_file ) ) {
			return false;
		}

		$plugin_dir = dirname( $plugin_file );
		if ( '.' !== $plugin_dir ) {
			$canonical_root = realpath( WP_PLUGIN_DIR . '/' . $plugin_dir );
			if ( false === $canonical_root || false === self::resolve_contained( $canonical_file, $canonical_root ) ) {
				return false;
			}
		}

		return $canonical_file;
	}

	private static function resolve_registered_mu_plugin_file( $plugin_file ) {
		if ( false !== strpos( $plugin_file, '/' )
			|| ! defined( 'WPMU_PLUGIN_DIR' )
			|| ! function_exists( 'wp_get_mu_plugins' )
		) {
			return false;
		}

		$mu_plugin_files = array_map( 'basename', (array) wp_get_mu_plugins() );
		if ( ! in_array( $plugin_file, $mu_plugin_files, true ) ) {
			return false;
		}

		$canonical_file = self::resolve_contained( WPMU_PLUGIN_DIR . '/' . $plugin_file, WPMU_PLUGIN_DIR );

		if ( false === $canonical_file || ! is_file( $canonical_file ) || ! is_readable( $canonical_file ) ) {
			return false;
		}

		return $canonical_file;
	}

	public static function resolve_mu_plugin_scan_paths( $plugin_file ) {
		if ( ! self::is_safe_plugin_file_identifier( $plugin_file ) ) {
			return false;
		}

		$loader = self::resolve_registered_mu_plugin_file( $plugin_file );
		if ( false === $loader ) {
			return false;
		}

		$root = realpath( WPMU_PLUGIN_DIR );
		if ( false === $root ) {
			return false;
		}

		$paths = array(
			'file'        => $loader,
			'files'       => array( $loader ),
			'directories' => array(),
			'refused'     => array(),
		);

		$same_name_folder = WPMU_PLUGIN_DIR . '/' . basename( $plugin_file, '.php' );
		if ( is_dir( $same_name_folder ) ) {
			$canonical_folder = self::resolve_contained( $same_name_folder, WPMU_PLUGIN_DIR );
			if ( false !== $canonical_folder && is_dir( $canonical_folder ) ) {
				$paths['directories'][] = $canonical_folder;
			} else {
				$paths['refused'][] = array(
					'include' => $same_name_folder,
					'reason'  => 'outside',
				);
			}
		}

		foreach ( self::read_loader_includes( $loader ) as $include ) {
			if ( null === $include['path'] ) {
				$paths['refused'][] = array(
					'include' => $include['code'],
					'reason'  => 'unresolved',
				);
				continue;
			}

			$target = $include['path'];
			if ( ! self::is_absolute_path( $target ) ) {
				$target = dirname( $loader ) . '/' . $target;
			}

			$canonical_target = self::resolve_contained( $target, WPMU_PLUGIN_DIR );
			if ( false === $canonical_target || ! is_file( $canonical_target ) ) {
				$paths['refused'][] = array(
					'include' => $target,
					'reason'  => false === realpath( $target ) ? 'missing' : 'outside',
				);
				continue;
			}

			if ( $canonical_target === $loader ) {
				continue;
			}

			$relative = substr( $canonical_target, strlen( $root ) + 1 );
			$slash    = strpos( $relative, DIRECTORY_SEPARATOR );
			if ( false === $slash ) {
				$paths['files'][] = $canonical_target;
			} else {
				$paths['directories'][] = $root . DIRECTORY_SEPARATOR . substr( $relative, 0, $slash );
			}
		}

		$paths['files']       = array_values( array_unique( $paths['files'] ) );
		$paths['directories'] = array_values( array_unique( $paths['directories'] ) );

		return $paths;
	}

	public static function resolve_mu_plugin_scan_file( $path, array $scan_paths ) {
		$canonical_path = realpath( $path );
		if ( false === $canonical_path ) {
			return false;
		}

		if ( in_array( $canonical_path, $scan_paths['files'], true ) ) {
			return $canonical_path;
		}

		foreach ( $scan_paths['directories'] as $directory ) {
			if ( self::is_canonical_path_within( $canonical_path, $directory, false ) ) {
				return $canonical_path;
			}
		}

		return false;
	}

	public static function read_includes_outside_theme( array $files, $theme_dir ) {
		$root = realpath( $theme_dir );
		if ( false === $root || ! is_dir( $root ) ) {
			return array();
		}

		$outside = array();
		foreach ( $files as $file ) {
			$canonical_file = self::resolve_contained( $file, $root );
			if ( false === $canonical_file || ! is_file( $canonical_file ) ) {
				continue;
			}

			$code = file_get_contents( $canonical_file );
			if ( false === $code || ( false === stripos( $code, 'require' ) && false === stripos( $code, 'include' ) ) ) {
				continue;
			}

			foreach ( self::read_loader_includes( $canonical_file ) as $include ) {
				if ( null === $include['path'] ) {
					continue;
				}

				$target = $include['path'];
				if ( ! self::is_absolute_path( $target ) ) {
					$target = dirname( $canonical_file ) . '/' . $target;
				}

				$canonical_target = realpath( $target );
				if (
					false === $canonical_target
					|| ! is_file( $canonical_target )
					|| self::is_canonical_path_within( $canonical_target, $root, false )
					|| isset( $outside[ $canonical_target ] )
				) {
					continue;
				}

				$outside[ $canonical_target ] = array(
					'file'    => $canonical_file,
					'include' => $canonical_target,
				);
			}
		}

		return array_values( $outside );
	}

	private static function read_loader_includes( $loader ) {
		$code = file_get_contents( $loader );
		if ( false === $code ) {
			return array();
		}

		$tokens   = token_get_all( $code );
		$keywords = array( T_REQUIRE, T_REQUIRE_ONCE, T_INCLUDE, T_INCLUDE_ONCE );
		$count    = count( $tokens );
		$includes = array();

		for ( $i = 0; $i < $count; $i++ ) {
			if ( ! is_array( $tokens[ $i ] ) || ! in_array( $tokens[ $i ][0], $keywords, true ) ) {
				continue;
			}

			$expression = array();
			$depth      = 0;
			for ( $j = $i + 1; $j < $count; $j++ ) {
				$token = $tokens[ $j ];
				if ( is_array( $token ) ) {
					if ( T_CLOSE_TAG === $token[0] ) {
						break;
					}
				} elseif ( '(' === $token ) {
					$depth++;
				} elseif ( ')' === $token ) {
					if ( 0 === $depth ) {
						break;
					}
					$depth--;
				} elseif ( 0 === $depth && ( ';' === $token || ',' === $token ) ) {
					break;
				}
				$expression[] = $token;
			}

			$written = '';
			foreach ( $expression as $token ) {
				$written .= is_array( $token ) ? $token[1] : $token;
			}

			$significant = array_values(
				array_filter(
					$expression,
					function ( $token ) {
						return ! is_array( $token ) || ! in_array( $token[0], array( T_WHITESPACE, T_COMMENT, T_DOC_COMMENT ), true );
					}
				)
			);
			$position    = 0;
			$path        = self::read_include_expression( $significant, $position, $loader );

			$includes[] = array(
				'code' => trim( $written ),
				'path' => null !== $path && count( $significant ) === $position ? $path : null,
			);
		}

		return $includes;
	}

	private static function read_include_expression( array $tokens, &$position, $loader ) {
		$value = self::read_include_term( $tokens, $position, $loader );

		while ( null !== $value && isset( $tokens[ $position ] ) && '.' === $tokens[ $position ] ) {
			$position++;
			$next  = self::read_include_term( $tokens, $position, $loader );
			$value = null === $next ? null : $value . $next;
		}

		return $value;
	}

	private static function read_include_term( array $tokens, &$position, $loader ) {
		if ( ! isset( $tokens[ $position ] ) ) {
			return null;
		}

		$token = $tokens[ $position ];

		if ( '(' === $token ) {
			$position++;
			$value = self::read_include_expression( $tokens, $position, $loader );
			if ( null === $value || ! isset( $tokens[ $position ] ) || ')' !== $tokens[ $position ] ) {
				return null;
			}
			$position++;

			return $value;
		}

		if ( ! is_array( $token ) ) {
			return null;
		}

		$position++;

		switch ( $token[0] ) {
			case T_CONSTANT_ENCAPSED_STRING:
				$body = substr( $token[1], 1, -1 );

				return "'" === $token[1][0] ? strtr( $body, array( '\\\\' => '\\', "\\'" => "'" ) ) : stripcslashes( $body );
			case T_DIR:
				return dirname( $loader );
			case T_FILE:
				return $loader;
			case T_NS_SEPARATOR:
				return self::read_include_term( $tokens, $position, $loader );
		}

		$name = ltrim( $token[1], '\\' );

		if ( 'WPMU_PLUGIN_DIR' === $name ) {
			return defined( 'WPMU_PLUGIN_DIR' ) ? (string) WPMU_PLUGIN_DIR : null;
		}

		if ( ( 'dirname' === $name || 'plugin_dir_path' === $name ) && isset( $tokens[ $position ] ) && '(' === $tokens[ $position ] ) {
			$position++;
			$argument = self::read_include_expression( $tokens, $position, $loader );
			if ( null === $argument || ! isset( $tokens[ $position ] ) || ')' !== $tokens[ $position ] ) {
				return null;
			}
			$position++;

			return 'dirname' === $name ? dirname( $argument ) : rtrim( dirname( $argument ), '/\\' ) . '/';
		}

		return null;
	}

	private static function is_absolute_path( $path ) {
		return '' !== $path && ( '/' === $path[0] || '\\' === $path[0] || (bool) preg_match( '#^[A-Za-z]:[\\\\/]#', $path ) );
	}

	public static function resolve_registered_plugin_root( $plugin_file ) {
		if ( ! self::is_safe_plugin_file_identifier( $plugin_file ) ) {
			return false;
		}

		$roots = self::get_registered_plugin_roots();

		return array_key_exists( $plugin_file, $roots ) ? $roots[ $plugin_file ] : false;
	}

	private static function get_registered_plugin_roots() {
		if ( ! function_exists( 'get_plugins' ) ) {
			require_once ABSPATH . 'wp-admin/includes/plugin.php';
		}

		$plugin_files = array_keys( (array) get_plugins() );

		if ( null === self::$registered_plugin_roots || self::$registered_plugin_roots['plugins'] !== $plugin_files ) {
			$roots = array();
			foreach ( $plugin_files as $plugin_file ) {
				$roots[ $plugin_file ] = self::find_registered_plugin_root( $plugin_file );
			}

			self::$registered_plugin_roots = array(
				'plugins' => $plugin_files,
				'roots'   => $roots,
			);
		}

		return self::$registered_plugin_roots['roots'];
	}

	public static function clear_registered_plugin_roots() {
		self::$registered_plugin_roots = null;
	}

	private static function find_registered_plugin_root( $plugin_file ) {
		if ( ! self::is_safe_plugin_file_identifier( $plugin_file ) ) {
			return false;
		}

		$plugin_dir = dirname( $plugin_file );
		if ( '.' === $plugin_dir ) {
			return self::resolve_registered_plugin_file( $plugin_file );
		}

		$canonical_root = realpath( WP_PLUGIN_DIR . '/' . $plugin_dir );

		return false !== $canonical_root && is_dir( $canonical_root ) && is_readable( $canonical_root )
			? $canonical_root
			: false;
	}

	public static function resolve_registered_plugin_id_for_path( $path ) {
		if ( ! is_string( $path ) || '' === $path ) {
			return false;
		}

		$canonical_path = realpath( $path );
		if ( false === $canonical_path ) {
			return false;
		}

		foreach ( self::get_registered_plugin_roots() as $plugin_file => $canonical_root ) {
			if ( false !== $canonical_root && self::is_canonical_path_within( $canonical_path, $canonical_root, true ) ) {
				return $plugin_file;
			}
		}

		return false;
	}

	public static function resolve_registered_plugin_logical_path( $path, $plugin_file ) {
		$canonical_root = self::resolve_registered_plugin_root( $plugin_file );
		if ( false === $canonical_root ) {
			return false;
		}

		$canonical_path = realpath( $path );
		if ( false === $canonical_path || ! self::is_canonical_path_within( $canonical_path, $canonical_root, true ) ) {
			return false;
		}

		$plugin_dir = dirname( $plugin_file );
		if ( '.' === $plugin_dir ) {
			$logical_path = WP_PLUGIN_DIR . DIRECTORY_SEPARATOR . $plugin_file;
		} else {
			$logical_root = WP_PLUGIN_DIR . DIRECTORY_SEPARATOR . str_replace( '/', DIRECTORY_SEPARATOR, $plugin_dir );
			$logical_path = $logical_root . substr( $canonical_path, strlen( $canonical_root ) );
		}

		return realpath( $logical_path ) === $canonical_path ? $logical_path : false;
	}

	public static function is_theme_identifier( $theme_id ) {
		if ( ! is_string( $theme_id ) || '' === $theme_id
			|| false !== strpos( $theme_id, '\\' )
			|| false !== strpos( $theme_id, "\0" )
		) {
			return false;
		}

		$segments = explode( '/', $theme_id );
		if ( count( $segments ) > 2 ) {
			return false;
		}

		foreach ( $segments as $segment ) {
			if ( '' === $segment || '.' === $segment || '..' === $segment ) {
				return false;
			}
		}

		return true;
	}

	public static function is_active_theme_directory( $theme_directory ) {
		$theme_directory = rtrim( str_replace( '\\', '/', (string) $theme_directory ), '/' );
		if ( '' === $theme_directory ) {
			return false;
		}

		foreach ( array( get_stylesheet_directory(), get_template_directory() ) as $active_directory ) {
			$active_directory = rtrim( str_replace( '\\', '/', (string) $active_directory ), '/' );
			if ( $active_directory === $theme_directory || 0 === strpos( $active_directory, $theme_directory . '/' ) ) {
				return true;
			}
		}

		return false;
	}

	private static function is_safe_plugin_file_identifier( $plugin_file ) {
		return is_string( $plugin_file )
			&& '' !== $plugin_file
			&& false === strpos( $plugin_file, "\0" )
			&& '/' !== $plugin_file[0]
			&& false === strpos( $plugin_file, '\\' )
			&& ! preg_match( '#(?:^|/)\.{1,2}(?:/|$)#', $plugin_file );
	}

	public static function resolve_source_path_for_domain( $path, $domain ) {
		$components = self::get_components_for_domain( $domain );

		if ( 1 !== count( $components ) ) {
			return false;
		}

		$component = reset( $components );
		if ( is_array( $component ) ) {
			return self::resolve_mu_plugin_scan_file( $path, $component );
		}

		return self::resolve_contained( $path, $component, true );
	}

	private static function get_components_for_domain( $domain ) {
		$map = self::get_domain_component_map();

		if ( ! isset( $map[ $domain ] ) ) {
			return [];
		}

		$components = [];
		foreach ( $map[ $domain ] as $root ) {
			if ( is_array( $root ) ) {
				$scan_paths = self::resolve_mu_plugin_scan_paths( $root['mu_plugin'] );
				if ( false !== $scan_paths ) {
					$components[ $scan_paths['file'] ] = $scan_paths;
				}
				continue;
			}

			$canonical_root = realpath( $root );
			if ( false !== $canonical_root ) {
				$components[ $canonical_root ] = $canonical_root;
			}
		}

		return $components;
	}

	private static function get_domain_component_map() {
		$map = [];

		if ( ! function_exists( 'get_plugins' ) ) {
			require_once ABSPATH . 'wp-admin/includes/plugin.php';
		}

		foreach ( (array) get_plugins() as $plugin_file => $plugin_data ) {
			if ( empty( $plugin_data['TextDomain'] ) ) {
				continue;
			}

			$root = self::resolve_registered_plugin_root( $plugin_file );
			if ( false !== $root ) {
				$map[ $plugin_data['TextDomain'] ][] = $root;
			}
		}

		foreach ( (array) wp_get_mu_plugins() as $mu_plugin_file ) {
			if ( ! is_file( $mu_plugin_file ) || ! is_readable( $mu_plugin_file ) ) {
				continue;
			}

			$mu_plugin_data = get_plugin_data( $mu_plugin_file, false, false );
			if ( ! empty( $mu_plugin_data['TextDomain'] )
				&& false !== self::resolve_contained( $mu_plugin_file, WPMU_PLUGIN_DIR, true )
			) {
				$map[ $mu_plugin_data['TextDomain'] ][] = [ 'mu_plugin' => basename( $mu_plugin_file ) ];
			}
		}

		foreach ( (array) wp_get_themes() as $theme ) {
			$text_domain = $theme->get( 'TextDomain' );
			if ( ! $text_domain ) {
				continue;
			}

			if ( false !== self::resolve_contained( $theme->get_stylesheet_directory(), $theme->get_theme_root(), true ) ) {
				$map[ $text_domain ][] = $theme->get_stylesheet_directory();
			}
		}

		return $map;
	}
}
