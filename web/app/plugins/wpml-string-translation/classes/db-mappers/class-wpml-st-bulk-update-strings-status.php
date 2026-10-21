<?php

class WPML_ST_Bulk_Update_Strings_Status {

	private $wpdb;

	private $active_lang_codes;

	public function __construct( wpdb $wpdb, array $active_lang_codes ) {
		$this->wpdb = $wpdb;
		$this->active_lang_codes = \WPML\ST\TranslationPauseScope::translatable(
			array_map( 'strval', $active_lang_codes )
		);
	}

	public function run() {
		return $this->recompute( null, null );
	}

	private function recompute( $from_id, $to_id ) {
		$aggregates = $this->get_translation_aggregates_subquery( $from_id, $to_id );
		$new_status = $this->get_new_status_snippet();
		$where      = "s.status != ( {$new_status} )" . $this->get_heal_where_snippet( $from_id, $to_id );

		$select_changed = "
			SELECT s.id
			FROM {$this->wpdb->prefix}icl_strings AS s
			LEFT JOIN ( {$aggregates} ) AS agg ON agg.string_id = s.id
			WHERE {$where}";

		$changed_ids = $this->execute( $select_changed, 'col' );

		if ( $changed_ids ) {
			$this->execute(
				"
				UPDATE {$this->wpdb->prefix}icl_strings AS s
				LEFT JOIN ( {$aggregates} ) AS agg ON agg.string_id = s.id
				SET s.status = ( {$new_status} )
				WHERE {$where}",
				'query'
			);
		}

		return $changed_ids;
	}

	private function execute( $sql, $mode ) {
		if ( 'col' === $mode ) {
			return $this->wpdb->get_col( $sql );
		}

		if ( 'var' === $mode ) {
			return $this->wpdb->get_var( $sql );
		}

		return $this->wpdb->query( $sql );
	}

	public function run_for_zeroed_range( $from_id, $to_id ) {
		$from_id = (int) $from_id;
		$to_id   = (int) $to_id;
		$wpdb    = $this->wpdb;

		$count_zeroed = $wpdb->prepare(
			"SELECT COUNT(*) FROM {$wpdb->prefix}icl_strings WHERE status = %d AND id > %d AND id <= %d",
			ICL_TM_NOT_TRANSLATED,
			$from_id,
			$to_id
		);
		$scanned      = (int) $this->execute( $count_zeroed, 'var' );

		if ( ! $scanned ) {
			return array(
				'scanned' => 0,
				'changed' => 0,
			);
		}

		$changed = $this->recompute( $from_id, $to_id );

		return array(
			'scanned' => $scanned,
			'changed' => count( $changed ),
		);
	}

	public function get_max_string_id() {
		$wpdb = $this->wpdb;

		return (int) $this->execute( "SELECT MAX(id) FROM {$wpdb->prefix}icl_strings", 'var' );
	}

	public function get_page_end_id( $after_id, $rows ) {
		$wpdb = $this->wpdb;

		$page_end = $wpdb->prepare(
			"SELECT MAX(id) AS page_end FROM ( SELECT id FROM {$wpdb->prefix}icl_strings WHERE id > %d ORDER BY id LIMIT %d ) AS page",
			(int) $after_id,
			max( 1, (int) $rows )
		);

		return (int) $this->execute( $page_end, 'var' );
	}

	private function get_heal_where_snippet( $from_id, $to_id ) {
		if ( null === $from_id || null === $to_id ) {
			return '';
		}

		$wpdb = $this->wpdb;

		$sql = $wpdb->prepare(
			' AND s.id > %d AND s.id <= %d AND s.status = %d AND agg.has_complete_or_mo = 1',
			(int) $from_id,
			(int) $to_id,
			ICL_TM_NOT_TRANSLATED
		);

		return $sql;
	}

	private function get_translation_aggregates_subquery( $from_id = null, $to_id = null ) {
		$wpdb      = $this->wpdb;
		$in_scope  = $this->in_scope_languages_snippet();
		$range_sql = '';
		if ( null !== $from_id && null !== $to_id ) {
			$range_sql = $wpdb->prepare(
				' AND st.string_id > %d AND st.string_id <= %d',
				(int) $from_id,
				(int) $to_id
			);
		}

		$sql = $wpdb->prepare(
			"SELECT st.string_id,
				MAX( st.status != %d OR ( st.mo_string IS NOT NULL AND LENGTH( st.mo_string ) > 0 ) ) AS has_translated_or_mo,
				MAX( st.language IN( {$in_scope} ) AND st.status = %d AND ( st.mo_string IS NULL OR LENGTH( st.mo_string ) = 0 ) ) AS has_waiting,
				MAX( st.language IN( {$in_scope} ) AND st.status = %d AND ( st.mo_string IS NULL OR LENGTH( st.mo_string ) = 0 ) ) AS has_needs_update,
				MAX( st.status = %d OR ( st.mo_string IS NOT NULL AND LENGTH( st.mo_string ) > 0 ) ) AS has_complete_or_mo,
				MAX( st.status != %d AND ( st.mo_string IS NULL OR LENGTH( st.mo_string ) = 0 ) ) AS has_not_complete,
				MAX( st.language IN( {$in_scope} ) AND st.status = %d AND ( st.mo_string IS NULL OR LENGTH( st.mo_string ) = 0 ) ) AS has_untranslated_row,
				COUNT( DISTINCT CASE WHEN st.language IN( {$in_scope} ) THEN st.language END ) AS active_translations
			FROM {$wpdb->prefix}icl_string_translations AS st
			JOIN {$wpdb->prefix}icl_strings AS s2 ON s2.id = st.string_id
			WHERE st.language != s2.language{$range_sql}
			GROUP BY st.string_id",
			ICL_TM_NOT_TRANSLATED,
			ICL_TM_WAITING_FOR_TRANSLATOR,
			ICL_TM_NEEDS_UPDATE,
			ICL_TM_COMPLETE,
			ICL_TM_COMPLETE,
			ICL_TM_NOT_TRANSLATED
		);

		return $sql;
	}

	private function get_new_status_snippet() {
		$wpdb     = $this->wpdb;
		$expected = $this->get_expected_translations_snippet();

		$sql = $wpdb->prepare(
			"CASE
				WHEN agg.string_id IS NULL OR agg.has_translated_or_mo = 0 THEN %d
				WHEN agg.has_waiting = 1 THEN %d
				WHEN agg.has_needs_update = 1 THEN %d
				WHEN agg.active_translations < {$expected} AND agg.has_complete_or_mo = 1 THEN %d
				WHEN agg.active_translations < {$expected} AND agg.has_not_complete = 1 THEN %d
				WHEN agg.has_untranslated_row = 1 THEN %d
				ELSE %d
			END",
			ICL_TM_NOT_TRANSLATED,
			ICL_TM_WAITING_FOR_TRANSLATOR,
			ICL_TM_NEEDS_UPDATE,
			ICL_STRING_TRANSLATION_PARTIAL,
			ICL_TM_NOT_TRANSLATED,
			ICL_STRING_TRANSLATION_PARTIAL,
			ICL_TM_COMPLETE
		);

		return $sql;
	}

	private function get_expected_translations_snippet() {
		return sprintf(
			'( %d - CASE WHEN s.language IN( %s ) THEN 1 ELSE 0 END )',
			count( $this->active_lang_codes ),
			$this->in_scope_languages_snippet()
		);
	}

	private function in_scope_languages_snippet() {
		return $this->active_lang_codes ? wpml_prepare_in( $this->active_lang_codes ) : 'NULL';
	}
}
