<?php

namespace WPML\PB\Elementor\Hooks;

use WPML\FP\Fns;
use WPML\LIB\WP\Hooks;

class EditorLanguage implements \IWPML_Backend_Action, \IWPML_Frontend_Action, \IWPML_DIC_Action {

	const EDITOR_ACTION = 'elementor';

	private $sitepress;

	public function __construct( \SitePress $sitepress ) {
		$this->sitepress = $sitepress;
	}

	public function add_hooks() {
		add_filter( 'wpml_post_edit_screen_actions', [ $this, 'addEditorAction' ] );

		Hooks::onAction( 'elementor/editor/init' )
			->then( [ $this, 'set' ] );
	}

	public function addEditorAction( $actions ) {
		$actions   = (array) $actions;
		$actions[] = self::EDITOR_ACTION;

		return array_values( array_unique( $actions ) );
	}

	public function set() {
		$currentLanguage = $this->sitepress->get_current_language();
		$adminLanguage   = $this->sitepress->get_admin_language();
		if ( $currentLanguage !== $adminLanguage ) {
			$locale = $this->sitepress->get_locale_from_language_code( $adminLanguage );
			add_filter( 'pre_determine_locale', Fns::always( $locale ) );
		}
	}
}
