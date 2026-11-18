<?php

namespace WPML\TM\ATE\Cancel;

class CancelSource {

	const SAVE_CONTENT = 'save:content';

	const SAVE_META = 'save:meta';

	const MANUAL_SAVE = 'manual_save';

	const SWEEP = 'sweep';

	const RESEND = 'resend';

	const TRANSLATE_EVERYTHING_OFF = 'translate_everything_off';

	const TYPE_LEFT_TRANSLATE_EVERYTHING = 'type_left_translate_everything';

	const DASHBOARD = 'dashboard';

	const SUPPORT_PAGE = 'support_page';

	const TRASH = 'trash';

	const DELETE = 'delete';

	const ORPHAN_CLEANUP = 'orphan_cleanup';

	const LANGUAGE_REMOVED = 'language_removed';

	const REQUEST_CLOSED = 'request_closed';

	const TAXONOMY_UNTRANSLATED = 'taxonomy_untranslated';

	const REPLAY_PREFIX = 'replay:';

	public static function replay( $original ) {
		$original = is_scalar( $original ) ? trim( (string) $original ) : '';

		if ( 0 === strpos( $original, self::REPLAY_PREFIX ) ) {
			return $original;
		}

		return self::REPLAY_PREFIX . ( '' === $original ? \WPML_TM_ATE_API::CANCEL_SOURCE_UNKNOWN : $original );
	}
}
