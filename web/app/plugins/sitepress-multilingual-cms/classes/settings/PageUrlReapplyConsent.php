<?php

namespace WPML\TM\Settings;

use WPML\WP\OptionManager;

class PageUrlReapplyConsent {

	const SETTING = 'page-url-reapply-consent';

	public static function record( $applyToExisting, $taskId, $transition ) {
		OptionManager::update(
			'TM',
			self::SETTING,
			[
				'applyToExisting' => (bool) $applyToExisting,
				'taskId'          => $taskId ? (int) $taskId : null,
				'transition'      => (string) $transition,
				'recordedAt'      => time(),
			]
		);
	}

	public static function last() {
		$record = OptionManager::getOr( null, 'TM', self::SETTING );

		return is_array( $record ) ? $record : null;
	}
}
