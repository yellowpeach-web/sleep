<?php

namespace OTGS\Installer;

class Settings {

	const NETWORK_OPTION = 'wp_installer_network';

	private static $settings;

	private static $settings_common;

	private static $network;

	private static $common_keys = [
		'subscription',
		'channel',
		'ts_info',
		'last_successful_subscription_fetch',
		'using_products_fallback',
		'products_feed_problem',
	];

	public static function load() {
		return self::with_network_subscriptions( self::load_own() );
	}

	private static function load_own() {
		if ( null !== self::$settings ) {
			return self::$settings;
		}

		$settings = \get_option( 'wp_installer_settings' );

		if ( is_array( $settings ) || empty( $settings ) ) {
			return $settings;
		}

		$settings = self::uncompress( $settings );
		if ( ! is_array( $settings ) ) {
			return false;
		}

		$settings = self::pre_1_8_clean_up( $settings );

		self::$settings = $settings;
		return self::$settings;
	}

	private static function load_common() {
		if ( null === self::$settings_common ) {
			self::$settings_common = self::with_network_subscriptions( self::read_common() );
		}

		return self::$settings_common;
	}

	private static function read_common() {
		$common = \get_option( 'wp_installer_settings_common' );

		if ( is_array( $common ) ) {
			return $common;
		}

		if ( ! $common ) {
			if ( ! self::load_own() ) {
				return null;
			}

			$settings = self::load();
			self::save( $settings );
			return $settings;
		}

		return self::uncompress( $common );
	}

	private static function with_network_subscriptions( $settings ) {
		if ( ! \is_multisite() ) {
			return $settings;
		}

		$network  = self::adopt_main_site_subscriptions( $settings );
		$settings = self::without_subscriptions( $settings );

		if ( ! $network ) {
			return $settings;
		}

		if ( ! is_array( $settings ) ) {
			$settings = array();
		}

		foreach ( $network as $repository_id => $subscription ) {
			$settings['repositories'][ $repository_id ]['subscription'] = $subscription;
		}

		return $settings;
	}

	private static function without_subscriptions( $settings ) {
		if ( isset( $settings['repositories'] ) && is_array( $settings['repositories'] ) ) {
			foreach ( array_keys( $settings['repositories'] ) as $repository_id ) {
				unset( $settings['repositories'][ $repository_id ]['subscription'] );
			}
		}

		return $settings;
	}

	private static function adopt_main_site_subscriptions( $settings ) {
		$network = self::network_subscriptions();
		if ( ! isset( $settings['repositories'] ) || ! is_array( $settings['repositories'] ) || ! \is_main_site() ) {
			return $network;
		}

		$adopted = false;
		foreach ( $settings['repositories'] as $repository_id => $repository ) {
			if ( empty( $network[ $repository_id ] ) && ! empty( $repository['subscription']['key'] ) ) {
				$network[ $repository_id ] = $repository['subscription'];
				if ( empty( $network[ $repository_id ]['site_url'] ) ) {
					$network[ $repository_id ]['site_url'] = \get_site_url();
				}
				$adopted = true;
			}
		}

		if ( $adopted ) {
			self::write_network_subscriptions( $network );
		}

		return $network;
	}

	private static function network_subscriptions() {
		if ( null === self::$network ) {
			self::$network = self::read_network_subscriptions();
		}

		return self::$network;
	}

	private static function read_network_subscriptions() {
		$network = \maybe_unserialize( \get_site_option( self::NETWORK_OPTION ) );

		return is_array( $network ) ? array_filter( $network ) : array();
	}

	private static function write_network_subscriptions( array $network ) {
		\update_site_option( self::NETWORK_OPTION, $network );
		self::$network = $network;
	}

	private static function save_network_subscriptions( array $settings ) {
		if ( empty( $settings['repositories'] ) ) {
			return;
		}

		$read    = self::network_subscriptions();
		$network = self::read_network_subscriptions();
		$changed = false;

		foreach ( $settings['repositories'] as $repository_id => $repository ) {
			$subscription = empty( $repository['subscription'] ) ? null : $repository['subscription'];
			$known        = isset( $read[ $repository_id ] ) ? $read[ $repository_id ] : null;
			if ( serialize( $subscription ) === serialize( $known ) ) {
				continue;
			}

			if ( $subscription ) {
				$network[ $repository_id ] = $subscription;
			} else {
				unset( $network[ $repository_id ] );
			}
			$changed = true;
		}

		if ( $changed ) {
			self::write_network_subscriptions( $network );
		}
	}

	public static function load_subscriptions() {
		return self::load_common();
	}

	public static function load_channels() {
		return self::load_common();
	}

	public static function load_ts_info() {
		return self::load_common();
	}

	public static function is_using_products_fallback( $repository_id ) {
		$common = self::load_common();

		if ( isset( $common['repositories'][ $repository_id ]['using_products_fallback'] ) ) {
			return (bool) $common['repositories'][ $repository_id ]['using_products_fallback'];
		}

		return false;
	}

	public static function products_feed_problem( $repository_id ) {
		$common  = self::load_common();
		$problem = isset( $common['repositories'][ $repository_id ]['products_feed_problem'] )
			? $common['repositories'][ $repository_id ]['products_feed_problem'] : null;

		return is_array( $problem ) && ! empty( $problem['kind'] ) ? $problem : null;
	}

	public static function requires_update() {
		$last_update = \get_option( 'wp_installer_last_update', false );

		if ( ! $last_update || ( time() - $last_update ) > 86400 ) {
			\update_option( 'wp_installer_last_update', time(), false );
			return true;
		}

		return false;
	}


	public static function save( &$settings ) {
		$settings  = self::pre_1_8_clean_up( $settings );
		$changelog = self::extract_changelog( $settings );
		$own       = $settings;

		if ( \is_multisite() ) {
			self::save_network_subscriptions( $settings );
			$own = self::without_subscriptions( $settings );
		}

		$common = self::get_common( $own );

		self::$settings        = $own;
		self::$settings_common = null;

		\update_option( 'wp_installer_settings', self::compress( $own ), false );
		\update_option( 'wp_installer_settings_common', self::compress( $common ), true );

		self::save_changelog( $changelog );
	}

	public static function is_gz_on() {
		return function_exists( 'gzuncompress' ) && function_exists( 'gzcompress' );
	}

	private static function compress( $content ) {
		$content = serialize( $content );
		if ( self::is_gz_on() ) {
			$content = gzcompress( $content );
		}
		return base64_encode( (string) $content );
	}

	private static function uncompress( $content ) {
		$content = base64_decode( $content );
		if ( self::is_zlib_stream( $content ) ) {
			$content = self::inflate( $content );
			if ( false === $content ) {
				return false;
			}
		}
		return unserialize( (string) $content );
	}

	private static function inflate( $bytes ) {
		switch ( self::inflater() ) {
			case 'gzuncompress':
				return gzuncompress( $bytes );
			case 'zlib_decode':
				return zlib_decode( $bytes );
			case 'gzinflate':
				return gzinflate( substr( $bytes, 2 ) );
		}

		return false;
	}

	public static function inflater() {
		foreach ( [ 'gzuncompress', 'zlib_decode', 'gzinflate' ] as $function ) {
			if ( function_exists( $function ) ) {
				return $function;
			}
		}

		return null;
	}

	private static function is_zlib_stream( $bytes ) {
		if ( ! is_string( $bytes ) || strlen( $bytes ) < 2 || "\x78" !== $bytes[0] ) {
			return false;
		}

		return 0 === ( ( ord( $bytes[0] ) << 8 ) | ord( $bytes[1] ) ) % 31;
	}

	private static function get_common( $settings ) {
		$common = [ 'repositories' => [] ];
		foreach ( $settings['repositories'] as $repository_id => $repository ) {
			foreach ( self::$common_keys as $key ) {
				if ( isset( $repository[ $key ] ) ) {
					if ( ! isset( $common['repositories'][ $repository_id ] ) ) {
						$common['repositories'][ $repository_id ] = [];
					}

					$common['repositories'][ $repository_id ][ $key ] = $repository[ $key ];
				}
			}
		}
		return $common;
	}

	private static function extract_changelog( &$settings ) {
		$changelog = array();
		foreach ( $settings['repositories'] as $repository_id => $repository ) {
			if ( ! isset( $repository['data']['downloads']['plugins'] ) ) {
				continue;
			}

			foreach ( $repository['data']['downloads']['plugins'] as $slug => $download ) {
				if ( isset( $download['changelog'] ) && ! empty( $download['changelog'] ) ) {
					$changelog[ $slug ] = $download['changelog'];

					$settings['repositories'][ $repository_id ]['data']['downloads']['plugins'][ $slug ]['changelog'] = '';
				}
			}
		}
		return $changelog;
	}

	private static function save_changelog( $changelog ) {
		if ( empty( $changelog ) ) {
			return;
		}
		if ( ! file_exists( ABSPATH . 'wp-admin/includes/file.php' ) ) {
			return;
		}

		require_once ABSPATH . 'wp-admin/includes/file.php';

		$creds_ok = WP_Filesystem();
		if ( ! $creds_ok ) {
			return;
		}

		global $wp_filesystem;

		$uploads = wp_upload_dir();
		$dir     = trailingslashit( $uploads['basedir'] ) . 'wpml';
		$file    = trailingslashit( $dir ) . 'changelog.txt';

		if ( ! $wp_filesystem->is_dir( $dir ) ) {
			if ( ! $wp_filesystem->mkdir( $dir, defined( 'FS_CHMOD_DIR' ) ? FS_CHMOD_DIR : 0755 ) ) {
				return;
			}
		}

		$wp_filesystem->put_contents(
			$file,
			self::compress( $changelog ),
			defined( 'FS_CHMOD_FILE' ) ? FS_CHMOD_FILE : 0644
		);
	}

	public static function read_changelog() {
		if ( ! file_exists( ABSPATH . 'wp-admin/includes/file.php' ) ) {
			return;
		}

		require_once ABSPATH . 'wp-admin/includes/file.php';

		$creds_ok = WP_Filesystem();
		if ( ! $creds_ok ) {
			return '';
		}

		global $wp_filesystem;

		$uploads = wp_upload_dir();
		$file    = trailingslashit( $uploads['basedir'] ) . 'wpml/changelog.txt';

		if ( ! $wp_filesystem->exists( $file ) ) {
			return '';
		}

		$changelog = $wp_filesystem->get_contents( $file );
		if ( false === $changelog ) {
			return '';
		}

		return self::uncompress( $changelog );
	}


	public static function get_changelog_for_plugin( $slug ) {
		$changelogs = self::read_changelog();
		if ( is_array( $changelogs ) && isset( $changelogs[ $slug ] ) ) {
			return $changelogs[ $slug ];
		}
		return '';
	}


	private static function pre_1_8_clean_up( $settings ) {
		if ( empty( $settings['_pre_1_8_clean_up'] ) ) {
			$settings['_pre_1_8_clean_up'] = true;
			foreach ( $settings['repositories'] as $repository_id => $repository ) {
				if ( ! isset( $repository['data']['downloads']['plugins'] ) ) {
					continue;
				}

				foreach ( $repository['data']['downloads']['plugins'] as $slug => $download ) {
					if ( ! isset( $download['channel'] ) ) {
						$settings['repositories'][ $repository_id ]['data']['downloads']['plugins'][ $slug ]['channel'] = '';
					}
				}
			}
		}

		return $settings;
	}
}
