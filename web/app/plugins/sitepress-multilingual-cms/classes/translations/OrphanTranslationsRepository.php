<?php

namespace WPML\Translation;

use WPML\Core\SharedKernel\Component\Translation\Domain\TranslationStatus;

class OrphanTranslationsRepository {

	const UNUSED_STATUSES = [
		TranslationStatus::NOT_TRANSLATED,
		TranslationStatus::ATE_CANCELED,
	];

	private $wpdb;

	public function __construct( \wpdb $wpdb ) {
		$this->wpdb = $wpdb;
	}

	public function countMembers( $trid, $element_type ) {
		$wpdb = $this->wpdb;

		return (int) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(*) FROM {$wpdb->prefix}icl_translations m
					WHERE m.trid = %d AND m.element_type = %s
						AND ( m.element_id IS NOT NULL OR EXISTS (
							SELECT 1 FROM {$wpdb->prefix}icl_translation_status member_status
							WHERE member_status.translation_id = m.translation_id
								AND member_status.status NOT IN ( %d, %d )
						) )",
				array( (int) $trid, (string) $element_type, self::UNUSED_STATUSES[0], self::UNUSED_STATUSES[1] )
			)
		);
	}

	public function memberElementId( $trid, $element_type ) {
		$wpdb = $this->wpdb;

		return (int) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT element_id FROM {$wpdb->prefix}icl_translations WHERE trid = %d AND element_type = %s AND element_id IS NOT NULL ORDER BY translation_id LIMIT 1",
				array( (int) $trid, (string) $element_type )
			)
		);
	}

	public function placeholderIds( $trid, $element_type ) {
		$wpdb = $this->wpdb;

		return array_map(
			'intval',
			(array) $wpdb->get_col(
				$wpdb->prepare(
					"SELECT m.translation_id FROM {$wpdb->prefix}icl_translations m
						WHERE m.trid = %d AND m.element_type = %s
							AND NOT ( m.element_id IS NOT NULL OR EXISTS (
								SELECT 1 FROM {$wpdb->prefix}icl_translation_status member_status
								WHERE member_status.translation_id = m.translation_id
									AND member_status.status NOT IN ( %d, %d )
							) )",
					array( (int) $trid, (string) $element_type, self::UNUSED_STATUSES[0], self::UNUSED_STATUSES[1] )
				)
			)
		);
	}

	public function hasOrphans( $element_type, $source_language ) {
		$wpdb = $this->wpdb;

		return (bool) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT 1
					FROM {$wpdb->posts} p
					INNER JOIN {$wpdb->prefix}icl_translations t
						ON p.ID = t.element_id
						AND t.element_type = %s
						AND t.language_code <> %s
					LEFT JOIN {$wpdb->prefix}icl_translations s
						ON s.trid = t.trid
						AND s.element_type = %s
						AND s.language_code = %s
						AND ( s.element_id IS NOT NULL OR EXISTS (
							SELECT 1 FROM {$wpdb->prefix}icl_translation_status member_status
							WHERE member_status.translation_id = s.translation_id
								AND member_status.status NOT IN ( %d, %d )
						) )
					WHERE s.translation_id IS NULL
						AND NOT EXISTS (
							SELECT 1 FROM {$wpdb->prefix}icl_translations o
							WHERE o.trid = t.trid AND o.element_type <> t.element_type
						)
					LIMIT 1",
				array( $element_type, $source_language, $element_type, $source_language, self::UNUSED_STATUSES[0], self::UNUSED_STATUSES[1] )
			)
		);
	}

	public function getOrphanCandidates( $element_type, $source_language ) {
		$wpdb = $this->wpdb;

		return $wpdb->get_results(
			$wpdb->prepare(
				"SELECT t.trid, t.element_id, t.language_code
					FROM {$wpdb->posts} p
					INNER JOIN {$wpdb->prefix}icl_translations t
						ON p.ID = t.element_id
						AND t.element_type = %s
						AND t.language_code <> %s
					LEFT JOIN {$wpdb->prefix}icl_translations s
						ON s.trid = t.trid
						AND s.element_type = %s
						AND s.language_code = %s
						AND ( s.element_id IS NOT NULL OR EXISTS (
							SELECT 1 FROM {$wpdb->prefix}icl_translation_status member_status
							WHERE member_status.translation_id = s.translation_id
								AND member_status.status NOT IN ( %d, %d )
						) )
					WHERE s.translation_id IS NULL
						AND NOT EXISTS (
							SELECT 1 FROM {$wpdb->prefix}icl_translations o
							WHERE o.trid = t.trid AND o.element_type <> t.element_type
						)
					ORDER BY t.trid",
				array( $element_type, $source_language, $element_type, $source_language, self::UNUSED_STATUSES[0], self::UNUSED_STATUSES[1] )
			)
		);
	}
}
