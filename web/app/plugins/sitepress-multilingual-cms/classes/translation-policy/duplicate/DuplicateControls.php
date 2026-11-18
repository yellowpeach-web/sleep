<?php

namespace WPML\TranslationPolicy\Duplicate;

use WPML\TranslationPolicy\FeatureSwitch;

class DuplicateControls {

	public static function offered(): bool {
		return ! FeatureSwitch::isOn();
	}

	public static function refusal(): string {
		return __( 'Duplicate is no longer available. Translate the content instead.', 'sitepress' );
	}
}
