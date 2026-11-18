<?php

namespace WPML\StringTranslation\Infrastructure\WordPress\HookHandler\TranslateEverything;

use WPML\StringTranslation\Infrastructure\WordPress\HookHandler\AbstractFilterHookHandler;

class AppearanceSurfaceLabelsFilter extends AbstractFilterHookHandler {

	const FILTER_NAME     = 'wpml_appearance_surface_labels';
	const FILTER_ARGS     = 1;
	const FILTER_PRIORITY = 10;

	protected function onFilter( ...$args ) {
		$labels = isset( $args[0] ) && is_array( $args[0] ) ? $args[0] : [];

		$labels[ AppearancePartOfTypesFilter::STRING_TYPE ] = __( 'Strings', 'wpml-string-translation' );

		return $labels;
	}
}
