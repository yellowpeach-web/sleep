<?php

namespace WPML\TM\ATE\Review;

use WPML\FP\Obj;
use WPML\FP\Relation;
use WPML\Setup\Option;
use WPML\TM\API\Jobs;

class TermJob {

	const ELEMENT_TYPE_PREFIX = 'tax';

	public static function isTermJob( $job ) {
		return (bool) $job && Relation::propEq( 'element_type_prefix', self::ELEMENT_TYPE_PREFIX, $job );
	}

	public static function flagAcceptedJobs( array $wpmlJobIds ) {
		$wpmlJobIds = array_values( array_filter( array_map( 'intval', $wpmlJobIds ) ) );
		if ( ! $wpmlJobIds || ! self::reviewIsOn() ) {
			return 0;
		}

		$flagged = 0;
		foreach ( self::readJobs( $wpmlJobIds ) as $row ) {
			if ( self::isAutomatic( $row ) && self::isReviewedUnderOwnMode( $row ) && ! self::isExcluded( (string) Obj::prop( 'element_type', $row ) ) ) {
				Jobs::setReviewStatus( (int) Obj::prop( 'job_id', $row ), ReviewStatus::NEEDS_REVIEW );
				++$flagged;
			}
		}

		return $flagged;
	}

	public static function onDelivered( $jobId ) {
		$job = self::get( (int) $jobId );
		if ( ! $job ) {
			return;
		}

		$reviewStatus = Obj::prop( 'review_status', $job );

		if ( ReviewStatus::EDITING === $reviewStatus ) {
			Jobs::setReviewStatus( (int) $jobId, ReviewStatus::ACCEPTED );

			return;
		}

		if (
			null === $reviewStatus
			&& self::isAutomatic( $job )
			&& self::reviewIsOn()
			&& self::isReviewedUnderOwnMode( $job )
			&& ! self::isExcluded( (string) Obj::prop( 'element_type', $job ) )
		) {
			Jobs::setReviewStatus( (int) $jobId, ReviewStatus::NEEDS_REVIEW );
		}
	}

	public static function get( $jobId ) {
		$rows = self::readJobs( [ (int) $jobId ] );
		$row  = is_array( $rows ) && $rows ? reset( $rows ) : null;

		if ( ! is_object( $row ) || 0 !== strpos( (string) Obj::prop( 'element_type', $row ), self::ELEMENT_TYPE_PREFIX . '_' ) ) {
			return null;
		}

		$row->job_id              = (int) $row->job_id;
		$row->element_type_prefix = self::ELEMENT_TYPE_PREFIX;

		return $row;
	}

	public static function assign( $jobId, $translatorId ) {
		global $wpdb;

		$job = self::get( (int) $jobId );
		if ( ! $job ) {
			return false;
		}

		$wpdb->update(
			$wpdb->prefix . 'icl_translate_job',
			[ 'translator_id' => (int) $translatorId ],
			[ 'job_id' => (int) $jobId ],
			[ '%d' ],
			[ '%d' ]
		);
		$wpdb->query(
			$wpdb->prepare(
				"UPDATE {$wpdb->prefix}icl_translation_status SET translator_id = %d WHERE rid = %d",
				(int) $translatorId,
				(int) Obj::prop( 'rid', $job )
			)
		);

		return true;
	}

	public static function isExcluded( $elementType ) {
		return (bool) apply_filters( 'wpml_tm_skip_element_type_from_review', false, $elementType );
	}

	private static function reviewIsOn() {
		return \WPML_TM_ATE_Status::is_enabled_and_activated();
	}

	private static function isReviewedUnderOwnMode( $row ) {
		return Option::NO_REVIEW !== ApplyJob::reviewModeOf( $row );
	}

	private static function isAutomatic( $row ) {
		return (bool) Obj::prop( 'automatic', $row );
	}

	private static function readJobs( array $jobIds ) {
		global $wpdb;

		if ( ! $jobIds ) {
			return [];
		}

		$jobIds = array_map( 'intval', $jobIds );

		$rows = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT tj.job_id, tj.rid, tj.automatic, tj.editor_job_id, tj.translated, tj.translator_id, tj.review_mode,
					t.element_type, t.element_id, t.trid, t.language_code, t.source_language_code,
					ts.status, ts.review_status, ts.translation_id
				FROM {$wpdb->prefix}icl_translate_job tj
				INNER JOIN {$wpdb->prefix}icl_translation_status ts ON ts.rid = tj.rid
				INNER JOIN {$wpdb->prefix}icl_translations t ON t.translation_id = ts.translation_id
				WHERE tj.job_id IN (" . implode( ',', array_fill( 0, count( $jobIds ), '%d' ) ) . ")
				LIMIT %d",
				array_merge( $jobIds, [ count( $jobIds ) ] )
			)
		);

		return is_array( $rows ) ? $rows : [];
	}
}
