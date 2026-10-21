<?php

namespace WPML\LanguageEditor\Presets;

final class FlagUpsertRule {

	public static function fillWhenEmpty( $column, $onlyWhenSeeded = false ) {
		$stored = '`' . $column . '`';
		$seeded = 'VALUES(`' . $column . '`)';

		$replace = "{$stored} IS NULL OR {$stored} = ''";
		if ( $onlyWhenSeeded ) {
			$replace = "{$seeded} IS NOT NULL AND {$seeded} <> '' AND ( {$replace} )";
		}

		return "{$stored} = IF( {$replace}, {$seeded}, {$stored} )";
	}
}
