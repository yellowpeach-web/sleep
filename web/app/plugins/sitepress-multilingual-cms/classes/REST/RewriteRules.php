<?php

namespace WPML\Core\REST;

class RewriteRules implements \IWPML_REST_Action, \IWPML_DIC_Action {
	private $sitepress;

	private $memo;

	public function __construct( \SitePress $sitepress ) {
		$this->sitepress = $sitepress;
	}

	public function add_hooks() {
		add_action( 'init', [ $this, 'addOptionRewriteRulesHook' ] );
	}

	public function addOptionRewriteRulesHook() {
		if ( $this->isLangInDirectory() && $this->isUseDirectoryForDefaultLanguage() && $this->isInstalledInSubdirectory() ) {
			add_filter( 'option_rewrite_rules', [ $this, 'updateRules' ] );
		}
	}

	public function updateRules( $rewriteRules ) {
		if ( ! is_array( $rewriteRules ) ) {
			return $rewriteRules;
		}

		if ( ! $this->memo ) {
			$this->memo = \WPML\Utilities\RequestMemo::forRewriteRules( [ 'registered_post_type', 'registered_taxonomy' ] );
		}

		return $this->memo->rewriteRules(
			$rewriteRules,
			function ( $rewriteRules ) {
				return $this->buildRules( $rewriteRules );
			},
			$this->getRestUrlPrefix(),
			true
		);
	}

	private function buildRules( $rewriteRules ) {
		$subdirectory = $this->getSubdirectory();
		$restPrefix   = $this->getRestUrlPrefix();

		$quotedPrefix = preg_quote( $restPrefix, '#' );

		$mapKeys = function ( $value, $key ) use ( $subdirectory, $restPrefix, $quotedPrefix ) {
			if ( $key === '^' . $restPrefix . '/?$' ) {
				$key = "^($subdirectory/)?$quotedPrefix/?$";
			} elseif ( $key === '^' . $restPrefix . '/(.*)?' ) {
				$key   = "^($subdirectory/)?$quotedPrefix/(.*)?";
				$value = str_replace( 'matches[1]', 'matches[2]', $value );
			}

			return [ $key => $value ];
		};

		return \wpml_collect( $rewriteRules )->mapWithKeys( $mapKeys )->toArray();
	}

	private function getRestUrlPrefix() {
		$prefix = function_exists( 'rest_get_url_prefix' ) ? (string) rest_get_url_prefix() : '';
		$prefix = trim( $prefix, '/' );

		return '' === $prefix ? 'wp-json' : $prefix;
	}

	private function isLangInDirectory() {
		return (int) $this->sitepress->get_setting( 'language_negotiation_type' ) === WPML_LANGUAGE_NEGOTIATION_TYPE_DIRECTORY;
	}

	private function isUseDirectoryForDefaultLanguage() {
		$urlSettings = $this->sitepress->get_setting( 'urls' );

		return isset( $urlSettings['directory_for_default_language'] ) && $urlSettings['directory_for_default_language'];
	}

	private function isInstalledInSubdirectory() {
		return ! empty( $this->getSubdirectory() );
	}

	private function getSubdirectory() {
		$url       = get_option( 'home' );
		$home_path = trim( (string) parse_url( $url, PHP_URL_PATH ), '/' );

		return $home_path;
	}
}
