<?php

namespace WPML\LIB\WP\App;

use WPML\FP\Fns;
use WPML\FP\Obj;
use WPML\LIB\WP\Nonce;
use WPML\LIB\WP\WordPress;

class Resources {

	public static function enqueue( $app, $pluginBaseUrl, $pluginBasePath, $version, $domain = null, $localize = null ) {
		static::enqueueWithDeps( $app, $pluginBaseUrl, $pluginBasePath, $version, $domain, $localize, [] );
	}

	public static function enqueueWithDeps( $app, $pluginBaseUrl, $pluginBasePath, $version, $domain = null, $localize = null, $dependencies = [] ) {
		$handle = sprintf(
			'wpml-%s-ui',
			str_replace( '/', '_', $app )
		);

		$sharedStyleDependencies = [];
		foreach ( self::vendorStyleChunks( $app, $pluginBasePath ) as $chunk ) {
			$chunkHandle = sprintf( 'wpml-%s-%s', basename( $pluginBasePath ), $chunk );
			wp_register_script(
				$chunkHandle,
				"$pluginBaseUrl/dist/js/$chunk/app.js",
				[],
				$version
			);
			wp_enqueue_style(
				$chunkHandle,
				"$pluginBaseUrl/dist/css/$chunk/styles.css",
				[],
				$version
			);
			$dependencies[]             = $chunkHandle;
			$sharedStyleDependencies[] = $chunkHandle;
		}

		wp_register_script(
			$handle,
			"$pluginBaseUrl/dist/js/$app/app.js",
			$dependencies,
			$version
		);

		if ( $localize ) {
			if ( isset( $localize['data']['endpoint'] ) ) {
				$localize['data']['nonce'] = Nonce::create( $localize['data']['endpoint'] );
			}
			if ( isset( $localize['data']['endpoints'] ) ) {
				$localize = Obj::over(
					Obj::lensPath( [ 'data', 'endpoints' ] ),
					Fns::map( function ( $endpoint ) {
						return [
							'endpoint' => $endpoint,
							'nonce'    => Nonce::create( $endpoint )
						];
					} ),
					$localize
				);
			}
			wp_localize_script( $handle, $localize['name'], $localize['data'] );
		}

		wp_enqueue_script( $handle );

		if ( file_exists( "$pluginBasePath/dist/css/$app/styles.css" ) ) {
			wp_enqueue_style(
				$handle,
				"$pluginBaseUrl/dist/css/$app/styles.css",
				$sharedStyleDependencies,
				$version
			);
		}

		if ( $domain && WordPress::versionCompare( '>=', '5.0.0' ) ) {
			$rootPath = $domain === 'wpml-translation-management' && defined( 'WPML_PLUGIN_PATH' )
				? WPML_PLUGIN_PATH
				: $pluginBasePath;

			wp_set_script_translations( $handle, $domain, "$rootPath/locale/jed" );
		}
	}

	public static function vendorStyleChunks( $app, $pluginBasePath ) {
		$manifest = "$pluginBasePath/dist/css/vendor-styles.json";
		if ( ! file_exists( $manifest ) ) {
			return [];
		}

		$entries = json_decode( (string) file_get_contents( $manifest ), true );
		$chunks  = is_array( $entries ) && isset( $entries[ $app ] ) && is_array( $entries[ $app ] )
			? $entries[ $app ]
			: [];

		return array_values( array_filter( $chunks, 'is_string' ) );
	}
}
