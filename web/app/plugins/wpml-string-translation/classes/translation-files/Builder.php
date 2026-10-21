<?php

namespace WPML\ST\TranslationFile;

abstract class Builder {
	protected $plural_form = PluralForms::TWO_FORMS;
	protected $language;

	public function set_language( $language ) {
		$this->language = $language;

		return $this;
	}

	public function set_plural_form( $plural_form ) {
		$this->plural_form = $plural_form;

		return $this;
	}

	abstract public function get_content( array $strings);

}
