<?php

namespace WPML\StringTranslation\Infrastructure\WordPress\HookHandler\TranslateEverything;

use WPML\StringTranslation\Infrastructure\WordPress\HookHandler\AbstractFilterHookHandler;

class AppearancePartOfTypesFilter extends AbstractFilterHookHandler {

	const FILTER_NAME     = 'wpml_appearance_part_of_types';
	const FILTER_ARGS     = 1;
	const FILTER_PRIORITY = 10;

	const STRING_TYPE = 'string';

	protected function onFilter( ...$args ) {
		$types = (array) ( $args[0] ?? [] );

		return array_values( array_unique( array_merge( $types, [ self::STRING_TYPE ] ) ) );
	}
}
