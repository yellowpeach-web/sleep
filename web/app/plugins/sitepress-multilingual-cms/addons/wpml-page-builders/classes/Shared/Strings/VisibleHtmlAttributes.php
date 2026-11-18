<?php

namespace WPML\PB\Strings;

final class VisibleHtmlAttributes {

	const NAMES = [ 'aria-label', 'title', 'alt', 'placeholder' ];

	public static function matches( string $attributeName ): bool {
		return in_array( strtolower( trim( $attributeName ) ), self::NAMES, true );
	}
}
