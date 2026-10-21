<?php

namespace WPML\TM\ATE\Dashboard;

use WPML\ATE\Proxies\ProxyInterceptorLoader;
use WPML_TM_ATE_AMS_Endpoints;

class ATEDashboardLoader {
	const ATE_DASHBOARD_ID = 'eate_dashboard';

	private $proxy;
	private $endpoints;

	function __construct( ProxyInterceptorLoader $proxy, WPML_TM_ATE_AMS_Endpoints $endpoints ) {
		$this->proxy     = $proxy;
		$this->endpoints = $endpoints;
	}


	public function registerScript( $enqueue = true ) {
		if ( $this->proxy->shouldEnableProxy() ) {
			return $this->registerScriptUsingProxy( $enqueue );
		} else {
			return $this->registerScriptWithoutProxy( $enqueue );
		}
	}

	public function initializeScript( $params ) {
		$initializer_handle = self::ATE_DASHBOARD_ID . '-init';

		wp_register_script( $initializer_handle, '', [ self::ATE_DASHBOARD_ID ], ICL_SITEPRESS_SCRIPT_VERSION, true );
		wp_enqueue_script( $initializer_handle );

		wp_add_inline_script( $initializer_handle, self::getGuardedBootScript( $params ) );
	}

	private static function getGuardedBootScript( $params ) {
		$encoded = wp_json_encode( $params );
		if ( false === $encoded ) {
			$encoded = '{}';
		}

		return '(function () {'
			. 'var attempts = 0;'
			. 'function boot() {'
			. 'if (typeof window.ateDashboard !== "function") {'
			. 'if (attempts++ < 100) { setTimeout(boot, 100); }'
			. 'return;'
			. '}'
			. 'window.ateDashboard(' . $encoded . ');'
			. '}'
			. 'if (document.readyState === "complete") { boot(); }'
			. 'else { window.addEventListener("load", boot); }'
			. '}());';
	}

	private function registerScriptUsingProxy( $enqueue = true ) {
		$handle = self::ATE_DASHBOARD_ID;
		$this->proxy->enqueueJS(
			$handle,
			$this->getATEDashboardUrl(), [ ProxyInterceptorLoader::HANDLE_JS ], ICL_SITEPRESS_SCRIPT_VERSION, true, $enqueue );

		return $handle;
	}

	private function registerScriptWithoutProxy( $enqueue = true ) {
		$handle    = self::ATE_DASHBOARD_ID;
		$src       = $this->getATEDashboardUrl();
		$deps      = [];
		$in_footer = true;
		wp_register_script( self::ATE_DASHBOARD_ID, $src, $deps, ICL_SITEPRESS_SCRIPT_VERSION, $in_footer );
		if ( $enqueue ) {
			wp_enqueue_script( $handle );
		}

		return $handle;
	}

	private function getATEDashboardUrl() {
		return $this->endpoints->get_ate_dashboard_url();
	}

	public function getRegisteredScriptUrl() {
		$wp_scripts = wp_scripts();

		if ( ! isset( $wp_scripts->registered[ self::ATE_DASHBOARD_ID ] ) ) {
			return false;
		}

		$script = $wp_scripts->registered[ self::ATE_DASHBOARD_ID ];
		$src    = $script->src;

		if ( ! $src ) {
			return $src;
		}

		if ( null === $script->ver ) {
			return $src;
		}

		$version = $script->ver ? $script->ver : $wp_scripts->default_version;

		return $version ? add_query_arg( 'ver', $version, $src ) : $src;
	}
}
