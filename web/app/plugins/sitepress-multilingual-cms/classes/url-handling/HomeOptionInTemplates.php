<?php

namespace WPML\UrlHandling;

class HomeOptionInTemplates {

	const FILTER = 'wpml_filter_home_option_in_templates';

	private $sitepress;

	public function __construct( \SitePress $sitepress ) {
		$this->sitepress = $sitepress;
	}

	public function add_hooks() {
		if ( did_action( 'after_setup_theme' ) ) {
			$this->maybe_add_filter();
			return;
		}

		add_action( 'after_setup_theme', array( $this, 'maybe_add_filter' ), PHP_INT_MAX );
	}

	public function maybe_add_filter() {
		if ( apply_filters( self::FILTER, false ) ) {
			add_filter( 'pre_option_home', array( $this->sitepress, 'pre_option_home' ) );
		}
	}
}
