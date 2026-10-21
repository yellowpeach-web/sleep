<?php

namespace WPML\LanguageEditor\Save\Endpoint;

class ResumeTranslation extends TogglePauseTranslation {

	protected function paused() {
		return false;
	}
}
