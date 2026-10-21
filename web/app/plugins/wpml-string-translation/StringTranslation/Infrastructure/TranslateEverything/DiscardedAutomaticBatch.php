<?php

namespace WPML\StringTranslation\Infrastructure\TranslateEverything;

class DiscardedAutomaticBatch {

	const ATE_UNSOLVABLE = 41;

	const ALIAS = 'discarded';

	public static function joinSql( string $prefix, string $stringAlias, string $languageColumn ): string {
		$alias = self::ALIAS;

		return "
			LEFT JOIN (
				SELECT discarded_batch.string_id, discarded_target.language_code
				FROM {$prefix}icl_translate_unsolvable_jobs discarded_error
				INNER JOIN {$prefix}icl_translate_job discarded_job
					ON discarded_job.job_id = discarded_error.job_id
					AND discarded_job.automatic = 1
				INNER JOIN {$prefix}icl_translation_status discarded_status
					ON discarded_status.rid = discarded_job.rid
					AND discarded_status.status IN ( %d, %d )
				INNER JOIN {$prefix}icl_translations discarded_target
					ON discarded_target.translation_id = discarded_status.translation_id
					AND discarded_target.element_type = 'st-batch_strings'
					AND discarded_target.source_language_code IS NOT NULL
				INNER JOIN {$prefix}icl_translations discarded_original
					ON discarded_original.trid = discarded_target.trid
					AND discarded_original.element_type = 'st-batch_strings'
					AND discarded_original.source_language_code IS NULL
				INNER JOIN {$prefix}icl_string_batches discarded_batch
					ON discarded_batch.batch_id = discarded_original.element_id
				GROUP BY discarded_batch.string_id, discarded_target.language_code
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
			defined( 'ICL_TM_NOT_TRANSLATED' ) ? (int) constant( 'ICL_TM_NOT_TRANSLATED' ) : 0,
			defined( 'ICL_TM_ATE_UNSOLVABLE' ) ? (int) constant( 'ICL_TM_ATE_UNSOLVABLE' ) : self::ATE_UNSOLVABLE,
		];
	}
}
