<?php

namespace WPML\EditHistory;

class JobRows {

	private $wpdb;

	public function __construct( $wpdb ) {
		$this->wpdb = $wpdb;
	}

	public function lastApplied( int $rid, int $jobId ) {
		$wpdb = $this->wpdb;

		$row = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT editor, editor_job_id FROM {$wpdb->prefix}icl_translate_job
				WHERE rid = %d AND job_id <> %d AND translated = 1 AND editor_job_id > 0
				ORDER BY job_id DESC LIMIT 1",
				$rid,
				$jobId
			),
			ARRAY_A
		);

		if ( ! $row ) {
			return State::BASE_NONE;
		}

		return \WPML_TM_Editors::ATE === $row['editor'] ? [ 'job_id' => (int) $row['editor_job_id'] ] : State::BASE_UNKNOWN;
	}

	public function translationOf( int $jobId ): int {
		$wpdb = $this->wpdb;

		return (int) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT t.element_id FROM {$wpdb->prefix}icl_translate_job j
				INNER JOIN {$wpdb->prefix}icl_translation_status s ON s.rid = j.rid
				INNER JOIN {$wpdb->prefix}icl_translations t ON t.translation_id = s.translation_id
				WHERE j.job_id = %d AND LEFT(t.element_type, 5) = 'post_'",
				$jobId
			)
		);
	}

	public function ofJob( int $jobId ) {
		$wpdb = $this->wpdb;

		$row = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT rid, editor_job_id, translator_id, automatic, sent_from FROM {$wpdb->prefix}icl_translate_job WHERE job_id = %d",
				$jobId
			),
			ARRAY_A
		);

		return $row
			? [
				'rid'           => (int) $row['rid'],
				'ate_job_id'    => (int) $row['editor_job_id'],
				'translator_id' => (int) $row['translator_id'],
				'automatic'     => (bool) ( $row['automatic'] ?? false ),
				'sent_from'     => (int) ( $row['sent_from'] ?? 0 ),
			]
			: null;
	}

	public function takenOver( int $ateJobId ): bool {
		$wpdb = $this->wpdb;

		$row = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT j.editor, j.translated,
					( SELECT COUNT(*) FROM {$wpdb->prefix}icl_translate_job n WHERE n.rid = j.rid AND n.job_id > j.job_id AND n.editor = %s ) AS saves_after
				FROM {$wpdb->prefix}icl_translate_job j WHERE j.editor_job_id = %d ORDER BY j.job_id DESC LIMIT 1",
				\WPML_TM_Editors::WP,
				$ateJobId
			),
			ARRAY_A
		);

		if ( ! $row ) {
			return false;
		}

		return \WPML_TM_Editors::ATE !== $row['editor'] || ( ! (int) $row['translated'] && (int) $row['saves_after'] > 0 );
	}

	public function newestJob( int $rid ) {
		$wpdb = $this->wpdb;

		$row = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT job_id, editor, translated FROM {$wpdb->prefix}icl_translate_job WHERE rid = %d ORDER BY job_id DESC LIMIT 1",
				$rid
			),
			ARRAY_A
		);

		return $row
			? [
				'job_id'     => (int) $row['job_id'],
				'editor'     => (string) $row['editor'],
				'translated' => (bool) $row['translated'],
			]
			: null;
	}

	public function element( int $rid ) {
		$wpdb = $this->wpdb;

		$row = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT o.element_id AS original, o.language_code AS source, t.element_type, t.element_id AS translation, t.language_code AS language
				FROM {$wpdb->prefix}icl_translation_status s
				INNER JOIN {$wpdb->prefix}icl_translations t ON t.translation_id = s.translation_id
				INNER JOIN {$wpdb->prefix}icl_translations o ON o.trid = t.trid AND o.source_language_code IS NULL
				WHERE s.rid = %d LIMIT 1",
				$rid
			),
			ARRAY_A
		);

		if ( ! $row || (int) $row['original'] <= 0 ) {
			return null;
		}

		$type = strtok( (string) $row['element_type'], '_' );

		return [
			'original'    => (int) $row['original'],
			'type'        => $type,
			'source'      => (string) $row['source'],
			'language'    => (string) $row['language'],
			'translation' => 'post' === $type ? (int) $row['translation'] : 0,
		];
	}
}
