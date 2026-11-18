<?php

namespace WPML\StringTranslation\Application\StringCore\Legacy;

class OldVersionInMemory {

	const FIRST_VERSION = '3.3.0';

	const TRANSLATION_PRIORITY_REMOVED = '5.1.1-dev';

	const STRING_TRANSLATION_REORDERED = '3.5.3-dev';

	public static function isBefore( string $version, $versionInMemory ): bool {
		if ( ! is_string( $versionInMemory ) || '' === $versionInMemory ) {
			return false;
		}

		return version_compare( $versionInMemory, self::FIRST_VERSION, '>=' )
			&& version_compare( $versionInMemory, $version, '<' );
	}

	public static function isBeforeNow( string $version ): bool {
		return self::isBefore( $version, defined( 'WPML_ST_VERSION' ) ? constant( 'WPML_ST_VERSION' ) : null );
	}
}
