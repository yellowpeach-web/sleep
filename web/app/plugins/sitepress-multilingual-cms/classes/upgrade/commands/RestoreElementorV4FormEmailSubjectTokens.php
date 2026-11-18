<?php

namespace WPML\Upgrade\Commands;

use WPML\PB\Elementor\Hooks\FormEmailPlaceholders;

class RestoreElementorV4FormEmailSubjectTokens extends RestoreElementorFormEmailTokens {

	public const STATE_OPTION = 'wpml_restore_elementor_v4_form_email_subject_tokens';

	protected function getPatternsByStringName(): array {
		return [ FormEmailPlaceholders::V4_SUBJECT_STRING_NAME_PATTERN => FormEmailPlaceholders::V4_PLACEHOLDER_PATTERNS ];
	}
}
