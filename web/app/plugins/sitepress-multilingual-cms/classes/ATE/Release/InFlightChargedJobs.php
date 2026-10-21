<?php

namespace WPML\TM\ATE\Release;

require_once __DIR__ . '/../../../inc/constants-since-5-0.php';

class InFlightChargedJobs {

	const IN_FLIGHT_STATUSES = [
		ICL_TM_WAITING_FOR_TRANSLATOR,
		ICL_TM_IN_PROGRESS,
		ICL_TM_ATE_UNSOLVABLE,
	];

	const NOTHING = [
		'charged' => 0,
		'unknown' => 0,
		'words'   => 0,
	];

	public static function forJobIds( array $jobIds ) {
		$jobIds = self::normaliseIds( $jobIds );

		if ( ! $jobIds ) {
			return self::NOTHING;
		}

		global $wpdb;

		$placeholders = implode( ', ', array_fill( 0, count( $jobIds ), '%d' ) );

		$sql = self::selectClause()
			. self::fromClause( $wpdb->prefix )
			. " AND j.job_id IN ( {$placeholders} )";

		return self::read( $sql, $jobIds );
	}


	public static function forPost( $postId ) {
		$jobIds = array_map(
			function ( $job ) {
				return (int) $job['job_id'];
			},
			self::jobsForPost( $postId )
		);

		return self::forJobIds( $jobIds );
	}


	public static function jobsForPost( $postId ) {
		$postId = (int) $postId;

		if ( $postId <= 0 ) {
			return [];
		}

		global $wpdb;

		$rows = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT j.job_id AS job_id,
				        j.editor_job_id AS editor_job_id,
				        j.rid AS rid
				   FROM {$wpdb->prefix}icl_translations deleted
				   INNER JOIN {$wpdb->prefix}icl_translations affected
				           ON affected.trid = deleted.trid
				          AND affected.element_type = deleted.element_type
				   INNER JOIN {$wpdb->prefix}icl_translation_status ts
				           ON ts.translation_id = affected.translation_id
				   INNER JOIN {$wpdb->prefix}icl_translate_job j
				           ON j.rid = ts.rid
				  WHERE deleted.element_id = %d
				    AND deleted.element_type LIKE %s
				    AND (
				          deleted.source_language_code IS NULL
				          OR affected.translation_id = deleted.translation_id
				        )
				    AND j.automatic = 1
				    AND j.editor = %s
				    AND j.editor_job_id > 0
				    AND j.translated = 0
				    AND j.job_id = ( SELECT MAX( newest.job_id ) FROM {$wpdb->prefix}icl_translate_job newest WHERE newest.rid = j.rid )
				    AND ts.status IN ( %d, %d, %d )",
				$postId,
				$wpdb->esc_like( 'post_' ) . '%',
				\WPML_TM_Editors::ATE,
				ICL_TM_WAITING_FOR_TRANSLATOR,
				ICL_TM_IN_PROGRESS,
				ICL_TM_ATE_UNSOLVABLE
			),
			ARRAY_A
		);

		if ( ! is_array( $rows ) ) {
			return [];
		}

		$jobs = [];
		foreach ( $rows as $row ) {
			$jobs[ (int) $row['job_id'] ] = [
				'job_id'        => (int) $row['job_id'],
				'editor_job_id' => (int) $row['editor_job_id'],
				'rid'           => (int) $row['rid'],
			];
		}

		return array_values( $jobs );
	}


	public static function jobsForTerm( $termTaxonomyId, $taxonomy ) {
		$termTaxonomyId = (int) $termTaxonomyId;

		if ( $termTaxonomyId <= 0 || ! is_string( $taxonomy ) || '' === $taxonomy ) {
			return [];
		}

		global $wpdb;

		$rows = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT j.job_id AS job_id,
				        j.editor_job_id AS editor_job_id,
				        j.rid AS rid
				   FROM {$wpdb->prefix}icl_translations deleted
				   INNER JOIN {$wpdb->prefix}icl_translations affected
				           ON affected.trid = deleted.trid
				          AND affected.element_type = deleted.element_type
				   INNER JOIN {$wpdb->prefix}icl_translation_status ts
				           ON ts.translation_id = affected.translation_id
				   INNER JOIN {$wpdb->prefix}icl_translate_job j
				           ON j.rid = ts.rid
				  WHERE deleted.element_id = %d
				    AND deleted.element_type = %s
				    AND (
				          deleted.source_language_code IS NULL
				          OR affected.translation_id = deleted.translation_id
				        )
				    AND j.automatic = 1
				    AND j.editor = %s
				    AND j.editor_job_id > 0
				    AND j.translated = 0
				    AND j.job_id = ( SELECT MAX( newest.job_id ) FROM {$wpdb->prefix}icl_translate_job newest WHERE newest.rid = j.rid )
				    AND ts.status IN ( %d, %d, %d )",
				$termTaxonomyId,
				'tax_' . $taxonomy,
				\WPML_TM_Editors::ATE,
				ICL_TM_WAITING_FOR_TRANSLATOR,
				ICL_TM_IN_PROGRESS,
				ICL_TM_ATE_UNSOLVABLE
			),
			ARRAY_A
		);

		if ( ! is_array( $rows ) ) {
			return [];
		}

		$jobs = [];
		foreach ( $rows as $row ) {
			$jobs[ (int) $row['job_id'] ] = [
				'job_id'        => (int) $row['job_id'],
				'editor_job_id' => (int) $row['editor_job_id'],
				'rid'           => (int) $row['rid'],
			];
		}

		return array_values( $jobs );
	}


	public static function chargedAteJobIds( array $ateJobIds ) {
		$ateJobIds = self::normaliseIds( $ateJobIds );

		if ( ! $ateJobIds ) {
			return [];
		}

		global $wpdb;

		$placeholders = implode( ', ', array_fill( 0, count( $ateJobIds ), '%d' ) );

		$rows = $wpdb->get_col(
			$wpdb->prepare(
				'SELECT j.editor_job_id '
				. self::jobFromClause( $wpdb->prefix )
				. ' AND j.wpml_automatic_translation_costs IS NOT NULL'
				. " AND j.editor_job_id IN ( {$placeholders} )",
				$ateJobIds
			)
		);

		if ( ! is_array( $rows ) ) {
			return [];
		}

		$charged = [];
		foreach ( $rows as $ateJobId ) {
			$charged[ (int) $ateJobId ] = true;
		}

		return $charged;
	}


	private static function normaliseIds( array $ids ) {
		return array_values(
			array_unique(
				array_filter(
					array_map( 'intval', $ids ),
					function ( $id ) {
						return $id > 0;
					}
				)
			)
		);
	}


	private static function selectClause() {
		return 'SELECT SUM( CASE WHEN j.wpml_automatic_translation_costs IS NULL THEN 0 ELSE 1 END ) AS charged,
		               SUM( CASE WHEN j.wpml_automatic_translation_costs IS NULL THEN 1 ELSE 0 END ) AS unknown_costs,
		               SUM( CASE WHEN j.wpml_automatic_translation_costs IS NULL
		                         THEN 0 ELSE COALESCE( j.wpml_words_to_translate_count, 0 ) END ) AS words ';
	}


	private static function fromClause( $prefix ) {
		return "FROM {$prefix}icl_translate_job j
		        INNER JOIN {$prefix}icl_translation_status ts ON ts.rid = j.rid
		        " . self::latestGenerationJoin( $prefix ) . "
		        WHERE j.automatic = 1
		          AND j.editor = 'ate'
		          AND j.editor_job_id > 0
		          AND j.translated = 0
		          AND ts.status IN ( 1, 2, 41 )";
	}


	private static function jobFromClause( $prefix ) {
		return "FROM {$prefix}icl_translate_job j
		        INNER JOIN {$prefix}icl_translation_status ts ON ts.rid = j.rid
		        WHERE j.automatic = 1
		          AND j.editor = 'ate'
		          AND j.editor_job_id > 0
		          AND j.translated = 0
		          AND ts.status IN ( 1, 2, 41 )";
	}


	private static function latestGenerationJoin( $prefix ) {
		return "INNER JOIN (
		            SELECT rid, MAX( job_id ) AS max_job_id
		              FROM {$prefix}icl_translate_job
		             GROUP BY rid
		        ) latest ON latest.rid = j.rid AND latest.max_job_id = j.job_id";
	}


	private static function read( $sql, array $bindings ) {
		global $wpdb;

		$row = $wpdb->get_row( $wpdb->prepare( $sql, $bindings ), ARRAY_A );

		if ( ! is_array( $row ) ) {
			return self::NOTHING;
		}

		return [
			'charged' => isset( $row['charged'] ) ? (int) $row['charged'] : 0,
			'unknown' => isset( $row['unknown_costs'] ) ? (int) $row['unknown_costs'] : 0,
			'words'   => isset( $row['words'] ) ? (int) $row['words'] : 0,
		];
	}


	public static function countForCopy( array $counts ) {
		return (int) $counts['charged'] + (int) $counts['unknown'];
	}


	public static function moneyMoved( array $counts ) {
		return (int) $counts['charged'] > 0;
	}

}
