<?php

namespace WPML\TM\ATE\TranslateEverything;

use WPML\Core\Component\Translation\Domain\Entity\JobError;
use WPML\Core\SharedKernel\Component\Translation\Domain\TranslationStatus;

class DiscardedAutomaticJob {

	const ALIAS = 'discarded_job';

	public static function joinSql( string $prefix, string $statusAlias ): string {
		$alias         = self::ALIAS;
		$notTranslated = (int) TranslationStatus::NOT_TRANSLATED;

		return "
			LEFT JOIN (
				SELECT discarded_status.translation_id,
				       MAX( discarded_error.error_data ) AS error_data
				FROM {$prefix}icl_translate_unsolvable_jobs discarded_error
				INNER JOIN {$prefix}icl_translate_job discarded_record
					ON discarded_record.job_id = discarded_error.job_id
					AND discarded_record.automatic = 1
				INNER JOIN {$prefix}icl_translation_status discarded_status
					ON discarded_status.rid = discarded_record.rid
					AND ( discarded_status.status = {$notTranslated}
					      OR discarded_error.error_data LIKE '" . self::checksumLike() . "' )
				LEFT JOIN {$prefix}icl_translate_job discarded_newer
					ON discarded_newer.rid = discarded_record.rid
					AND discarded_newer.job_id > discarded_record.job_id
				WHERE discarded_newer.job_id IS NULL
				GROUP BY discarded_status.translation_id
			) {$alias}
				ON {$alias}.translation_id = {$statusAlias}.translation_id
		";
	}

	public static function whereSql( string $statusAlias ): string {
		$like   = self::checksumLike();
		$prefix = '%%"' . JobError::ERROR_DATA_SOURCE_MD5 . '":"';

		return 'AND ( ' . self::ALIAS . '.translation_id IS NULL'
			. ' OR ( ' . self::ALIAS . ".error_data NOT LIKE '{$like}' AND {$statusAlias}.needs_update = 1 )"
			. ' OR ( ' . self::ALIAS . ".error_data LIKE '{$like}' AND " . self::ALIAS . ".error_data NOT LIKE CONCAT( '{$prefix}', {$statusAlias}.md5, '\"%%' ) )"
			. ' )';
	}

	private static function checksumLike(): string {
		return '%%"' . JobError::ERROR_DATA_SOURCE_MD5 . '":"%%';
	}
}
