<?php

namespace WPML\ContentDeletion;

use WPML\ContentDeletion\Endpoint\PreflightDeletion;
use WPML\ContentDeletion\Endpoint\PreflightRestore;
use WPML\ContentDeletion\Endpoint\SaveSettings;
use WPML\ContentDeletion\Endpoint\UndoItemDelete;

class Endpoints {

	const SAVE = 'saveContentDeletionSettings';

	const PREFLIGHT = 'preflightContentDeletion';

	const PREFLIGHT_RESTORE = 'preflightContentRestore';

	const UNDO = 'undoContentDeletion';

	public static function get() {
		return [
			self::SAVE              => SaveSettings::class,
			self::PREFLIGHT         => PreflightDeletion::class,
			self::PREFLIGHT_RESTORE => PreflightRestore::class,
			self::UNDO              => UndoItemDelete::class,
		];
	}
}
