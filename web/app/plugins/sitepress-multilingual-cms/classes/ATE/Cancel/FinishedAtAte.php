<?php

namespace WPML\TM\ATE\Cancel;

use WPML\Core\Component\Translation\Application\Service\AutomaticJobsCancellation\AteResponse;

class FinishedAtAte {

	const OPTION = 'wpml_tm_ate_finished_put_back';

	const KEEP = 3600;

	public static function idsOf( $response, array $ateJobIds ) {
		$reported = AteResponse::getReportedJobIds( $response );
		if ( ! is_array( $reported ) ) {
			return [];
		}

		return array_values( array_diff( array_map( 'intval', $ateJobIds ), array_map( 'intval', $reported ) ) );
	}

	public static function putBack( array $ateJobIds ) {
		global $wpdb;

		$putBack = [];
		foreach ( array_unique( array_map( 'intval', $ateJobIds ) ) as $ateJobId ) {
			$job = $wpdb->get_row(
				$wpdb->prepare(
					"SELECT j.job_id, j.rid, j.translated FROM {$wpdb->prefix}icl_translate_job j
					WHERE j.editor_job_id = %d AND j.editor = %s
					AND j.job_id = ( SELECT MAX( jj.job_id ) FROM {$wpdb->prefix}icl_translate_job jj WHERE jj.rid = j.rid )",
					$ateJobId,
					\WPML_TM_Editors::ATE
				)
			);
			if ( ! $job || (int) $job->translated ) {
				continue;
			}

			$updated = $wpdb->query(
				$wpdb->prepare(
					"UPDATE {$wpdb->prefix}icl_translation_status SET status = %d WHERE rid = %d AND status IN ( %d, %d, %d )",
					ICL_TM_IN_PROGRESS,
					(int) $job->rid,
					ICL_TM_NOT_TRANSLATED,
					ICL_TM_COMPLETE,
					ICL_TM_ATE_CANCELLED
				)
			);
			if ( $updated ) {
				$putBack[ (int) $job->job_id ] = $ateJobId;
			}
		}

		if ( $putBack ) {
			$now  = time();
			$kept = array_filter( self::read(), fn( $at ) => $now - $at < self::KEEP );
			update_option( self::OPTION, array_fill_keys( array_values( $putBack ), $now ) + $kept, false );
		}

		return $putBack;
	}

	public static function wasPutBack( $ateJobId ) {
		$at = self::read()[ (int) $ateJobId ] ?? 0;

		return $at > 0 && time() - $at < self::KEEP;
	}

	private static function read() {
		$stored = get_option( self::OPTION, [] );

		return is_array( $stored ) ? array_map( 'intval', $stored ) : [];
	}
}
