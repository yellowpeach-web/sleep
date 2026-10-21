<?php

namespace WPML\StringTranslation\Infrastructure\TranslateEverything;

class ActiveStringBatchJob {

	const WAITING_FOR_TRANSLATOR = 1;

	const IN_PROGRESS = 2;

	const ALIAS = 'in_flight';

	public static function pairsSql( string $prefix ): string {
		return "
			SELECT active_batch.string_id, active_target.language_code
			FROM {$prefix}icl_translation_status active_status
			INNER JOIN {$prefix}icl_translations active_target
				ON active_target.translation_id = active_status.translation_id
				AND active_target.element_type = 'st-batch_strings'
				AND active_target.source_language_code IS NOT NULL
			INNER JOIN {$prefix}icl_translations active_original
				ON active_original.trid = active_target.trid
				AND active_original.element_type = 'st-batch_strings'
				AND active_original.source_language_code IS NULL
			INNER JOIN {$prefix}icl_string_batches active_batch
				ON active_batch.batch_id = active_original.element_id
			WHERE active_status.status IN ( %d, %d )
			GROUP BY active_batch.string_id, active_target.language_code
		";
	}

	public static function joinSql( string $prefix, string $stringAlias, string $languageColumn ): string {
		$alias = self::ALIAS;

		return "
			LEFT JOIN (
				" . self::pairsSql( $prefix ) . "
			) {$alias}
				ON {$alias}.string_id = {$stringAlias}.id
				AND {$alias}.language_code = {$languageColumn}
		";
	}

	public static function whereSql(): string {
		return 'AND ' . self::ALIAS . '.string_id IS NULL';
	}

	public static function args(): array {
		return [
			defined( 'ICL_TM_WAITING_FOR_TRANSLATOR' ) ? (int) constant( 'ICL_TM_WAITING_FOR_TRANSLATOR' ) : self::WAITING_FOR_TRANSLATOR,
			defined( 'ICL_TM_IN_PROGRESS' ) ? (int) constant( 'ICL_TM_IN_PROGRESS' ) : self::IN_PROGRESS,
		];
	}

	public static function stringIdsWithActiveJob( \wpdb $wpdb, array $stringIds, string $languageCode ): array {
		if ( ! $stringIds ) {
			return [];
		}

		$rows = $wpdb->get_col(
			$wpdb->prepare(
				"SELECT DISTINCT in_flight.string_id
				FROM (
					SELECT active_batch.string_id, active_target.language_code
					FROM {$wpdb->prefix}icl_translation_status active_status
					INNER JOIN {$wpdb->prefix}icl_translations active_target
						ON active_target.translation_id = active_status.translation_id
						AND active_target.element_type = 'st-batch_strings'
						AND active_target.source_language_code IS NOT NULL
					INNER JOIN {$wpdb->prefix}icl_translations active_original
						ON active_original.trid = active_target.trid
						AND active_original.element_type = 'st-batch_strings'
						AND active_original.source_language_code IS NULL
					INNER JOIN {$wpdb->prefix}icl_string_batches active_batch
						ON active_batch.batch_id = active_original.element_id
					WHERE active_status.status IN ( %d, %d )
				) in_flight
				WHERE in_flight.string_id IN ( " . implode( ', ', array_fill( 0, count( $stringIds ), '%d' ) ) . ' )
					AND in_flight.language_code = %s',
				array_merge(
					self::args(),
					array_map( 'intval', array_values( $stringIds ) ),
					[ $languageCode ]
				)
			)
		);

		return array_map( 'intval', (array) $rows );
	}

	public static function reportRefused( array $stringIds, string $languageCode, string $logKey ) {
		if ( class_exists( \WPML\TM\Jobs\JobLog::class ) ) {
			\WPML\TM\Jobs\JobLog::addError(
				$logKey,
				[
					'language'   => $languageCode,
					'string_ids' => array_values( $stringIds ),
					'count'      => count( $stringIds ),
				]
			);
		}

		do_action( 'wpml_st_batch_duplicate_job_refused', array_values( $stringIds ), $languageCode );
	}

	public static function markWaiting( array $stringIds, string $languageCode ) {
		$waiting = self::args()[0];

		foreach ( $stringIds as $stringId ) {
			icl_add_string_translation( (int) $stringId, $languageCode, null, $waiting );
		}
	}
}
