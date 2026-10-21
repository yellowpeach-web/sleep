<?php

namespace WPML\TM\ATE\Receive;

use WPML\FP\Obj;
use WPML\TM\ATE\Review\ReviewStatus;

class ClientEdits {

	const REASON = 'wpml_translation_edited';

	public static function areInTheWay( $wpmlJob, $deliveredAteJobId ) {
		if ( ReviewStatus::EDITING !== Obj::prop( 'review_status', $wpmlJob ) ) {
			return false;
		}

		$deliveredAteJobId = (int) $deliveredAteJobId;

		return $deliveredAteJobId <= 0 || $deliveredAteJobId !== self::reviewersAteJobId( (int) Obj::prop( 'rid', $wpmlJob ) );
	}

	private static function reviewersAteJobId( $rid ) {
		global $wpdb;

		if ( $rid <= 0 ) {
			return 0;
		}

		$ateJobId = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT editor_job_id FROM {$wpdb->prefix}icl_translate_job WHERE rid = %d ORDER BY job_id DESC LIMIT 1",
				$rid
			)
		);

		return (int) $ateJobId;
	}
}
