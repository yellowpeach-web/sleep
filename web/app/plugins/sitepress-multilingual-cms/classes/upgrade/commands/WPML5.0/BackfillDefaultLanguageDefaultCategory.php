<?php

namespace WPML\Upgrade\Commands;

class BackfillDefaultLanguageDefaultCategory implements \IWPML_Upgrade_Command {

	private $sitepress;

	private $results;

	public function __construct( array $args ) {
		$this->sitepress = $args[0];
	}

	public function run() {
		$this->backfill();

		$this->results = true;

		return $this->results;
	}

	public function run_admin() {
		return $this->run();
	}

	public function run_ajax() {
		return null;
	}

	public function run_frontend() {
		return $this->run();
	}

	public function get_results() {
		return $this->results;
	}

	private function backfill() {
		$default_language = $this->defaultLanguage();
		if ( '' === $default_language ) {
			return;
		}

		$default_categories = (array) $this->sitepress->get_setting( 'default_categories', array() );

		$stored   = isset( $default_categories[ $default_language ] ) ? (int) $default_categories[ $default_language ] : 0;
		$resolved = (int) \SitePress_Setup::resolve_default_language_category( $default_categories, $default_language );

		if ( $resolved <= 0 ) {
			return;
		}

		if ( $resolved === $stored ) {
			return;
		}

		$default_categories[ $default_language ] = $resolved;

		$this->sitepress->set_default_categories( $default_categories );
	}

	private function defaultLanguage() {
		$default_language = (string) $this->sitepress->get_default_language();

		if ( '' === $default_language ) {
			$default_language = (string) $this->sitepress->get_setting( 'default_language' );
		}

		return $default_language;
	}
}
