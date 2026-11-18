<?php

namespace WPML\Core\WP\App;

use WPML\FP\Fns;
use WPML\FP\Obj;
use WPML\LIB\WP\Nonce;
use function WPML\FP\partial;

class Resources {
	const VENDOR = 'wpml-vendor';

	const APPS_WITH_TOOLTIPS = [ 'ate-jobs-sync', 'postEditTranslationEditor', 'settings' ];

	private static $script_vendor_registered = false;

	public static function enqueueApp( $app ) {
		return function( $localize = null, $dependencies = [] ) use ( $app ) {
			$dependencies = array_merge( [ self::vendorAsDependency() ], self::tooltipAsDependency( $app ), $dependencies );
			\WPML\LIB\WP\App\Resources::enqueueWithDeps( $app, ICL_PLUGIN_URL, WPML_PLUGIN_PATH, ICL_SITEPRESS_SCRIPT_VERSION, 'sitepress', $localize, $dependencies );
		};
	}

	public static function enqueueAppWithoutVendor( $app ) {
		return function( $localize = null, $dependencies = [] ) use ( $app ) {
			\WPML\LIB\WP\App\Resources::enqueueWithDeps( $app, ICL_PLUGIN_URL, WPML_PLUGIN_PATH, ICL_SITEPRESS_SCRIPT_VERSION, 'sitepress', $localize, $dependencies );
		};
	}

	public static function enqueueGlobalVariable( $name, $value ) {
		$print_javascript_variable = function() use ( $name, $value ) {

			$value = Obj::over(
				Obj::lensPath( [ 'endpoints' ] ),
				Fns::map( function ( $endpoint ) {
					return [
						'endpoint' => $endpoint,
						'nonce'    => Nonce::create( $endpoint )
					];
				} ),
				$value
			);

			echo "<script type=\"text/javascript\">\n";
			echo $name . ' = ' . json_encode( $value );
			echo "</script>\n";
		};
		add_action('wp_print_scripts', $print_javascript_variable, 0);
	}

	private static function registerVendor() {
		if( self::$script_vendor_registered ) {
			return;
		}

		self::$script_vendor_registered = true;
		wp_register_script(
			self::VENDOR,
			ICL_PLUGIN_URL . '/dist/js/vendor/app.js', [],
			ICL_SITEPRESS_SCRIPT_VERSION
		);

		wp_localize_script(
			self::VENDOR,
			'wpmlOutboundLinks',
			\WPML\OutboundLinks\OutboundLinks::getJsConfig()
		);

		self::defineAjaxUrlBefore( self::VENDOR );
	}

	public static function defineAjaxUrlBefore( $handle ) {
		wp_add_inline_script(
			$handle,
			'window.ajaxurl = window.ajaxurl || ' . wp_json_encode( admin_url( 'admin-ajax.php', 'relative' ) ) . ';',
			'before'
		);
	}

	private static function tooltipAsDependency( $app ) {
		$handle = \OTGS_Assets_Handles::POPOVER_TOOLTIP;

		if ( ! in_array( $app, self::APPS_WITH_TOOLTIPS, true ) || ! wp_script_is( $handle, 'registered' ) ) {
			return [];
		}

		wp_enqueue_style( $handle );

		return [ $handle ];
	}

	public static function vendorAsDependency() {
		self::registerVendor();
		return self::VENDOR;
	}
}

