<?php

namespace WPML\EditHistory;

class WpdbEntryLog implements EntryLog, PersonalData {

	private $wpdb;

	public function __construct( $wpdb ) {
		$this->wpdb = $wpdb;
	}

	public function append( Entry $entry ): int {
		$units = Codec::encode( $entry->lists );

		$this->wpdb->insert(
			$this->wpdb->prefix . Tables::ENTRIES,
			[
				'subject_kind' => $entry->subject->kind(),
				'subject_id'   => $entry->subject->id(),
				'language'     => $entry->language,
				'created_at'   => $entry->time,
				'source'       => $entry->source,
				'editor'       => $entry->editor,
				'user_id'      => $entry->userId,
				'units'        => $units,
				'bytes'        => strlen( $units ),
			],
			[ '%s', '%d', '%s', '%d', '%s', '%s', '%d', '%s', '%d' ]
		);

		return (int) $this->wpdb->insert_id;
	}

	public function since( Subject $subject, string $language, int $afterId ): array {
		$wpdb = $this->wpdb;

		return $this->entries(
			$wpdb->get_results(
				$wpdb->prepare(
					"SELECT id, subject_kind, subject_id, language, created_at, source, editor, user_id, units FROM {$wpdb->prefix}icl_edit_entries WHERE subject_kind = %s AND subject_id = %d AND id > %d AND language = %s ORDER BY id",
					$subject->kind(),
					$subject->id(),
					$afterId,
					$language
				),
				ARRAY_A
			)
		);
	}

	public function size( Subject $subject ): array {
		$wpdb = $this->wpdb;

		$row = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT COUNT(*) AS entries, COALESCE(SUM(bytes), 0) AS bytes FROM {$wpdb->prefix}icl_edit_entries WHERE subject_kind = %s AND subject_id = %d",
				$subject->kind(),
				$subject->id()
			),
			ARRAY_A
		);

		return [
			'count' => (int) ( $row['entries'] ?? 0 ),
			'bytes' => (int) ( $row['bytes'] ?? 0 ),
		];
	}

	public function subjectsWithEntriesBefore( int $time, int $limit, ?Subject $after = null ): array {
		$wpdb = $this->wpdb;
		$kind = $after ? $after->kind() : '';
		$id   = $after ? $after->id() : 0;

		$rows = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT DISTINCT subject_kind, subject_id FROM {$wpdb->prefix}icl_edit_entries
				WHERE created_at < %d AND (subject_kind > %s OR (subject_kind = %s AND subject_id > %d))
				ORDER BY subject_kind, subject_id LIMIT %d",
				$time,
				$kind,
				$kind,
				$id,
				$limit
			),
			ARRAY_A
		);

		return array_map(
			fn( $row ) => new Subject( (string) $row['subject_kind'], (int) $row['subject_id'] ),
			(array) $rows
		);
	}

	public function delete( Subject $subject, int $entryId ): int {
		$wpdb = $this->wpdb;

		return (int) $wpdb->query(
			$wpdb->prepare(
				"DELETE FROM {$wpdb->prefix}icl_edit_entries WHERE subject_kind = %s AND subject_id = %d AND id = %d",
				$subject->kind(),
				$subject->id(),
				$entryId
			)
		);
	}

	public function deleteUpTo( Subject $subject, int $entryId ): int {
		$wpdb = $this->wpdb;

		return (int) $wpdb->query(
			$wpdb->prepare(
				"DELETE FROM {$wpdb->prefix}icl_edit_entries WHERE subject_kind = %s AND subject_id = %d AND id <= %d",
				$subject->kind(),
				$subject->id(),
				$entryId
			)
		);
	}

	public function deleteSubject( Subject $subject ): int {
		$wpdb = $this->wpdb;

		return (int) $wpdb->query(
			$wpdb->prepare(
				"DELETE FROM {$wpdb->prefix}icl_edit_entries WHERE subject_kind = %s AND subject_id = %d",
				$subject->kind(),
				$subject->id()
			)
		);
	}

	public function byUser( int $userId, int $limit, int $offset ): array {
		$wpdb = $this->wpdb;

		return $this->entries(
			$wpdb->get_results(
				$wpdb->prepare(
					"SELECT id, subject_kind, subject_id, language, created_at, source, editor, user_id, units FROM {$wpdb->prefix}icl_edit_entries WHERE user_id = %d ORDER BY id LIMIT %d OFFSET %d",
					$userId,
					$limit,
					$offset
				),
				ARRAY_A
			)
		);
	}

	public function forgetUser( int $userId ): int {
		$wpdb = $this->wpdb;

		return (int) $wpdb->query(
			$wpdb->prepare( "UPDATE {$wpdb->prefix}icl_edit_entries SET user_id = NULL WHERE user_id = %d", $userId )
		);
	}

	private function entries( $rows ): array {
		return array_map(
			fn( $row ) => new Entry(
				new Subject( (string) $row['subject_kind'], (int) $row['subject_id'] ),
				(string) $row['language'],
				(int) $row['created_at'],
				(string) $row['source'],
				(string) $row['editor'],
				null === $row['user_id'] ? null : (int) $row['user_id'],
				Codec::decode( $row['units'] ) ?? [],
				(int) $row['id']
			),
			(array) $rows
		);
	}
}
