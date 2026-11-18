<?php

function wpml_tm_element_md5( $element ) {
	$helper = new WPML_TM_Action_Helper();

	return $helper->post_md5( $element );
}

add_filter( 'wpml_tm_element_md5', 'wpml_tm_element_md5', 10, 1 );

function wpml_tm_filter_post_target_langs(
	$allowed_langs,
	$element_id,
	$element_type_prefix
) {
	global $wpml_tm_translation_status, $wpml_post_translations;
	$tm_records = wpml_tm_get_records();

	$allowed_langs_filter = new WPML_TM_Post_Target_Lang_Filter(
		$tm_records,
		$wpml_tm_translation_status,
		$wpml_post_translations
	);

	return $allowed_langs_filter->filter_target_langs(
		$allowed_langs,
		$element_id,
		$element_type_prefix
	);
}

add_filter(
	'wpml_allowed_target_langs',
	'wpml_tm_filter_post_target_langs',
	10,
	3
);

function wpml_tm_package_jobs_in_flight( $jobIds = null, $packageId = 0 ) {
	global $wpdb;

	unset( $jobIds );

	$rows = $wpdb->get_col(
		$wpdb->prepare(
			"SELECT DISTINCT job.job_id
			FROM {$wpdb->prefix}icl_translations original
			INNER JOIN {$wpdb->prefix}icl_translations target ON target.trid = original.trid
			AND target.source_language_code IS NOT NULL
			INNER JOIN {$wpdb->prefix}icl_translation_status ts ON ts.translation_id = target.translation_id
			INNER JOIN {$wpdb->prefix}icl_translate_job job ON job.rid = ts.rid AND job.revision IS NULL
			WHERE original.element_id = %d AND original.element_type LIKE %s
			AND original.source_language_code IS NULL AND ts.status IN (%d,%d,%d,%d,%d)",
			(int) $packageId,
			$wpdb->esc_like( 'package_' ) . '%',
			\WPML\Core\SharedKernel\Component\Translation\Domain\TranslationStatus::CARRIES_UNITS[0],
			\WPML\Core\SharedKernel\Component\Translation\Domain\TranslationStatus::CARRIES_UNITS[1],
			\WPML\Core\SharedKernel\Component\Translation\Domain\TranslationStatus::CARRIES_UNITS[2],
			\WPML\Core\SharedKernel\Component\Translation\Domain\TranslationStatus::CARRIES_UNITS[3],
			\WPML\Core\SharedKernel\Component\Translation\Domain\TranslationStatus::CARRIES_UNITS[4]
		)
	);

	$rows = array_values( array_unique( array_map( 'intval', (array) $rows ) ) );
	sort( $rows, SORT_NUMERIC );

	return $rows;
}
add_filter( 'wpml_tm_package_jobs_in_flight', 'wpml_tm_package_jobs_in_flight', 10, 2 );

function wpml_tm_term_units_in_flight( $jobIds = null, $ttid = 0, $unitPrefix = 't_' ) {
	global $wpdb;

	unset( $jobIds );

	$rows = $wpdb->get_col(
		$wpdb->prepare(
			"SELECT STRAIGHT_JOIN DISTINCT job.job_id
			FROM {$wpdb->prefix}icl_translate unit
			INNER JOIN {$wpdb->prefix}icl_translate_job job ON job.job_id = unit.job_id AND job.revision IS NULL
			INNER JOIN {$wpdb->prefix}icl_translation_status ts ON ts.rid = job.rid
			WHERE unit.field_type = CONCAT( %s, %d ) AND ts.status IN (%d,%d,%d,%d,%d)",
			(string) $unitPrefix,
			(int) $ttid,
			\WPML\Core\SharedKernel\Component\Translation\Domain\TranslationStatus::CARRIES_UNITS[0],
			\WPML\Core\SharedKernel\Component\Translation\Domain\TranslationStatus::CARRIES_UNITS[1],
			\WPML\Core\SharedKernel\Component\Translation\Domain\TranslationStatus::CARRIES_UNITS[2],
			\WPML\Core\SharedKernel\Component\Translation\Domain\TranslationStatus::CARRIES_UNITS[3],
			\WPML\Core\SharedKernel\Component\Translation\Domain\TranslationStatus::CARRIES_UNITS[4]
		)
	);

	$rows = array_values( array_unique( array_map( 'intval', (array) $rows ) ) );
	sort( $rows, SORT_NUMERIC );

	return $rows;
}
add_filter( 'wpml_tm_term_units_in_flight', 'wpml_tm_term_units_in_flight', 10, 3 );

function wpml_tm_cancel_jobs( $result = null, array $jobIds = [] ) {
	unset( $result );

	return \WPML\Translation\CancelJobsServiceFactory::create()->cancelJobs( $jobIds );
}
add_filter( 'wpml_tm_cancel_jobs', 'wpml_tm_cancel_jobs', 10, 2 );
