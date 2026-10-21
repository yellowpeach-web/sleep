<?php

namespace WPML\UrlHandling\RootPage;

class HtmlFile {

	const ALLOWED_EXTENSIONS = [ 'html', 'htm', 'php' ];

	const RESERVED_FILES = [
		'index.php',
		'wp-config.php',
		'wp-load.php',
		'wp-settings.php',
		'wp-blog-header.php',
		'wp-cron.php',
		'wp-login.php',
		'wp-signup.php',
		'wp-activate.php',
		'wp-mail.php',
		'wp-links-opml.php',
		'wp-trackback.php',
		'xmlrpc.php',
	];

	const RESERVED_DIRS = [ 'wp-admin/', 'wp-includes/' ];

	public static function resolve( $raw_path, $base_path = null ) {
		$value     = trim( $raw_path );
		$base_path = ( null === $base_path ) ? ABSPATH : $base_path;

		if ( '' === $value ) {
			return [
				'valid'          => false,
				'resolved_path'  => null,
				'error_code'     => 'empty',
				'error_message'  => __( 'Enter the path to an HTML or PHP file.', 'sitepress' ),
			];
		}

		if ( '.' === $value[0] ) {
			return [
				'valid'          => false,
				'resolved_path'  => null,
				'error_code'     => 'looks_like_url_path',
				'error_message'  => __( 'This looks like a URL path, not a file. Enter a path to an HTML or PHP file on your server.', 'sitepress' ),
			];
		}

		$ext_so_far       = strtolower( pathinfo( $value, PATHINFO_EXTENSION ) );
		$active_languages = apply_filters( 'wpml_active_languages', null );
		if ( ! in_array( $ext_so_far, self::ALLOWED_EXTENSIONS, true ) && is_array( $active_languages ) ) {
			$relative = ltrim( $value, '/' );
			foreach ( array_keys( $active_languages ) as $lang_code ) {
				if ( $relative === $lang_code || 0 === strpos( $relative, $lang_code . '/' ) ) {
					return [
						'valid'          => false,
						'resolved_path'  => null,
						'error_code'     => 'looks_like_url_path',
						'error_message'  => __( 'This looks like a URL path, not a file. Enter a path to an HTML or PHP file on your server.', 'sitepress' ),
					];
				}
			}
		}

		if ( '' !== $ext_so_far && ! in_array( $ext_so_far, self::ALLOWED_EXTENSIONS, true ) ) {
			return [
				'valid'          => false,
				'resolved_path'  => null,
				'error_code'     => 'bad_extension',
				'error_message'  => __( 'Only .html, .htm and .php files are allowed as a root page.', 'sitepress' ),
			];
		}

		if ( false === strpos( $value, '/' ) ) {
			$candidate = rtrim( $base_path, '/\\' ) . '/' . $value;
		} elseif ( '/' === $value[0] ) {
			$candidate = $value;
		} else {
			$candidate = rtrim( $base_path, '/\\' ) . '/' . $value;
		}

		$resolved = realpath( $candidate );
		if ( false === $resolved ) {
			return [
				'valid'          => false,
				'resolved_path'  => null,
				'error_code'     => 'not_a_file',
				'error_message'  => __( 'The file you entered does not exist on the server.', 'sitepress' ),
			];
		}

		$base_real = realpath( $base_path );
		if ( false === $base_real || 0 !== strpos( $resolved, rtrim( $base_real, '/\\' ) . '/' ) ) {
			return [
				'valid'          => false,
				'resolved_path'  => null,
				'error_code'     => 'outside_abspath',
				'error_message'  => __( 'The file must be inside your WordPress installation folder.', 'sitepress' ),
			];
		}

		$relative = ltrim( substr( $resolved, strlen( rtrim( $base_real, '/\\' ) ) ), '/\\' );
		$relative = str_replace( '\\', '/', $relative );

		$in_reserved_dir = false;
		foreach ( self::RESERVED_DIRS as $reserved_dir ) {
			if ( 0 === strpos( $relative, $reserved_dir ) ) {
				$in_reserved_dir = true;
				break;
			}
		}

		if ( in_array( $relative, self::RESERVED_FILES, true ) || $in_reserved_dir ) {
			return [
				'valid'          => false,
				'resolved_path'  => null,
				'error_code'     => 'reserved_file',
				'error_message'  => __( 'This is a WordPress system file. Choose a different file for your root page.', 'sitepress' ),
			];
		}

		if ( ! is_file( $resolved ) ) {
			return [
				'valid'          => false,
				'resolved_path'  => null,
				'error_code'     => 'is_directory',
				'error_message'  => __( 'This is a folder, not a file. Enter the path to an HTML or PHP file.', 'sitepress' ),
			];
		}

		if ( ! is_readable( $resolved ) ) {
			return [
				'valid'          => false,
				'resolved_path'  => null,
				'error_code'     => 'not_readable',
				'error_message'  => __( 'The file exists but is not readable. Check file permissions.', 'sitepress' ),
			];
		}

		$ext = strtolower( pathinfo( $resolved, PATHINFO_EXTENSION ) );
		if ( ! in_array( $ext, self::ALLOWED_EXTENSIONS, true ) ) {
			return [
				'valid'          => false,
				'resolved_path'  => null,
				'error_code'     => 'bad_extension',
				'error_message'  => __( 'Only .html, .htm and .php files are allowed as a root page.', 'sitepress' ),
			];
		}

		return [
			'valid'          => true,
			'resolved_path'  => $resolved,
			'error_code'     => null,
			'error_message'  => null,
		];
	}
}
