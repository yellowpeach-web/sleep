<?php

namespace WPML\WPSEO\Shared\EditScreen;

abstract class BaseTemplatesLanguageHooks implements \IWPML_Backend_Action, \IWPML_DIC_Action {

	private $editedElementLanguage;

	private $editedLanguage;

	public function __construct( EditedElementLanguage $editedElementLanguage ) {
		$this->editedElementLanguage = $editedElementLanguage;
	}

	abstract protected function getTemplatesOptionName();

	abstract protected function reloadTemplates();

	public function add_hooks() {
		add_action( 'current_screen', [ $this, 'translateTemplatesInEditedLanguage' ] );
	}

	public function translateTemplatesInEditedLanguage() {
		$this->editedLanguage = $this->editedElementLanguage->get();

		if ( ! $this->editedLanguage ) {
			return;
		}

		add_filter( 'icl_current_string_language', [ $this, 'useEditedLanguage' ], 10, 2 );
		do_action( 'wpml_st_force_translate_admin_options', [ $this->getTemplatesOptionName() ] );
		$this->reloadTemplates();
	}

	public function useEditedLanguage( $language, $name ) {
		return $this->getTemplatesOptionName() === $name ? $this->editedLanguage : $language;
	}
}
