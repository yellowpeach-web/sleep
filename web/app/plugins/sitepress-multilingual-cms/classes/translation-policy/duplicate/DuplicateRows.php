<?php

namespace WPML\TranslationPolicy\Duplicate;

class DuplicateRows {

	const DUPLICATE_META = '_icl_lang_duplicate_of';

	const POST_TYPES = 'post\\_%';

	const ATTACHMENT = 'post_attachment';

	private $wpdb;

	public function __construct( \wpdb $wpdb ) {
		$this->wpdb = $wpdb;
	}

	public function count(): array {
		$wpdb = $this->wpdb;

		$row = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT COUNT(duplicate.meta_id) AS duplicates, COUNT(*) - COUNT(duplicate.meta_id) AS independent
				FROM {$wpdb->prefix}icl_translation_status status
				INNER JOIN {$wpdb->prefix}icl_translations translation ON translation.translation_id = status.translation_id
				LEFT JOIN {$wpdb->postmeta} duplicate ON duplicate.post_id = translation.element_id AND duplicate.meta_key = %s
				WHERE status.status = %d
					AND translation.element_type LIKE %s
					AND translation.element_type <> %s
					AND translation.source_language_code IS NOT NULL",
				self::DUPLICATE_META,
				ICL_TM_DUPLICATE,
				self::POST_TYPES,
				self::ATTACHMENT
			)
		);

		return [
			'duplicates'  => (int) ( $row->duplicates ?? 0 ),
			'independent' => (int) ( $row->independent ?? 0 ),
		];
	}

	public function next( int $limit ): array {
		$wpdb = $this->wpdb;

		$rows = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT status.rid, translation.element_id, translation.language_code, original.element_id AS original_id, duplicate.meta_id
				FROM {$wpdb->prefix}icl_translation_status status
				INNER JOIN {$wpdb->prefix}icl_translations translation ON translation.translation_id = status.translation_id
				LEFT JOIN {$wpdb->prefix}icl_translations original ON original.trid = translation.trid AND original.source_language_code IS NULL
				LEFT JOIN {$wpdb->postmeta} duplicate ON duplicate.post_id = translation.element_id AND duplicate.meta_key = %s
				WHERE status.status = %d
					AND translation.element_type LIKE %s
					AND translation.element_type <> %s
					AND translation.source_language_code IS NOT NULL
				ORDER BY status.rid
				LIMIT %d",
				self::DUPLICATE_META,
				ICL_TM_DUPLICATE,
				self::POST_TYPES,
				self::ATTACHMENT,
				$limit
			)
		);

		return array_map(
			fn( $row ) => [
				'rid'         => (int) $row->rid,
				'postId'      => (int) $row->element_id,
				'language'    => (string) $row->language_code,
				'originalId'  => (int) $row->original_id,
				'isDuplicate' => null !== $row->meta_id,
			],
			(array) $rows
		);
	}

	public function markComplete( int $rid ): bool {
		return false !== $this->wpdb->update(
			$this->wpdb->prefix . 'icl_translation_status',
			[ 'status' => ICL_TM_COMPLETE ],
			[
				'rid'    => $rid,
				'status' => ICL_TM_DUPLICATE,
			],
			[ '%d' ],
			[ '%d', '%d' ]
		);
	}
}
