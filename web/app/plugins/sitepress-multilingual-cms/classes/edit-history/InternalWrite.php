<?php

namespace WPML\EditHistory;

class InternalWrite {

	const ATE_DELIVERY         = 'ate-delivery';
	const NOTHING_TO_TRANSLATE = 'nothing-to-translate';
	const CLASSIC_EDITOR       = 'cte-resave';
	const SERVICE              = 'service';
	const XLIFF_IMPORT         = 'xliff-import';
	const AUTO_RESAVE          = 'auto-resave';
	const COPY                 = 'copy';
	const REVERT               = 'revert';
	const PUBLIC_API = 'public-api';

	private static $writers = [];

	public static function run( string $writer, callable $write ) {
		self::$writers[] = $writer;

		try {
			return $write();
		} finally {
			array_pop( self::$writers );
		}
	}

	public static function isActive(): bool {
		return [] !== self::$writers;
	}

	public static function writer(): ?string {
		return self::$writers[0] ?? null;
	}
}
