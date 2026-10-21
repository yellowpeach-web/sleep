<?php

namespace WPML\TM\Jobs\TakeOver;

class Decision {

	const NONE = 'none';
	const HARD = 'hard';

	public static function forJob( $status, $jobTranslatorId, $currentUserId ) {
		$status          = (int) $status;
		$jobTranslatorId = (int) $jobTranslatorId;
		$currentUserId   = (int) $currentUserId;

		if ( $currentUserId <= 0 || 0 === $jobTranslatorId || $jobTranslatorId === $currentUserId ) {
			return self::NONE;
		}

		if ( ICL_TM_IN_PROGRESS === $status ) {
			return self::HARD;
		}

		return self::NONE;
	}
}
