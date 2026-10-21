<?php

namespace WPML\LanguageEditor\Save\Endpoint;

class PauseTranslation extends TogglePauseTranslation {

	protected function paused() {
		return true;
	}
}
