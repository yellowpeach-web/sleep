<?php

namespace WPML\TM\XLIFF;

class CurrentTranslation {

	public static function is( string $source, string $translation, bool $isOutdated, bool $completedByHuman = false ): bool {
		return ! $isOutdated
			&& '' !== trim( $translation )
			&& ( $translation !== $source || $completedByHuman );
	}
}
