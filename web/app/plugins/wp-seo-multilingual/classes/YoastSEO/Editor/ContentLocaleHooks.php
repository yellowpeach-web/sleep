<?php

namespace WPML\WPSEO\YoastSEO\Editor;

use SitePress;
use WPML\WPSEO\Shared\EditScreen\EditedElementLanguage;

class ContentLocaleHooks implements \IWPML_Backend_Action, \IWPML_DIC_Action {

	const PRIORITY_AFTER_YOAST_ENQUEUES_EDITOR_SCRIPTS = 20;

	const YOAST_EDITOR_SCRIPT_HANDLES = [
		'yoast-seo-post-edit',
		'yoast-seo-post-edit-classic',
		'yoast-seo-term-edit',
		'yoast-seo-elementor',
	];

	private $sitepress;

	private $editedElementLanguage;

	public function __construct( SitePress $sitepress, EditedElementLanguage $editedElementLanguage ) {
		$this->sitepress             = $sitepress;
		$this->editedElementLanguage = $editedElementLanguage;
	}

	public function add_hooks() {
		add_action( 'admin_enqueue_scripts', [ $this, 'setContentLocaleOfEditedElement' ], self::PRIORITY_AFTER_YOAST_ENQUEUES_EDITOR_SCRIPTS );
		add_action( 'elementor/editor/after_enqueue_scripts', [ $this, 'setContentLocaleOfEditedElement' ] );
	}

	public function setContentLocaleOfEditedElement() {
		$languageCode = $this->editedElementLanguage->get();
		$locale       = $languageCode ? $this->sitepress->get_locale( $languageCode ) : null;

		if ( ! $locale ) {
			return;
		}

		$script = sprintf(
			'if ( window.wpseoScriptData && window.wpseoScriptData.metabox ) { window.wpseoScriptData.metabox.contentLocale = %s; }',
			wp_json_encode( $locale )
		);

		foreach ( self::YOAST_EDITOR_SCRIPT_HANDLES as $handle ) {
			wp_add_inline_script( $handle, $script, 'before' );
		}
	}
}
