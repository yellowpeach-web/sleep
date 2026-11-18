<?php

namespace WPML\EditHistory;

class WpdbStateStore implements StateStore {

	private $wpdb;

	private $now;

	public function __construct( $wpdb, $now = null ) {
		$this->wpdb = $wpdb;
		$this->now  = $now ?? 'time';
	}

	public function get( Subject $subject ) {
		$wpdb = $this->wpdb;

		$row = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT subject_kind, subject_id, language, original_id, base_state, base_job_id, base_ate_job_id, base_delivery_id,
					base_entry_id, base_applied_at, head_entry_id, head_hash, floor_entry_id, item_state, item_reason, copy_bytes, updated_at
				FROM {$wpdb->prefix}icl_edit_state WHERE subject_kind = %s AND subject_id = %d",
				$subject->kind(),
				$subject->id()
			),
			ARRAY_A
		);

		return is_array( $row ) ? $this->state( $row ) : null;
	}

	public function items( int $limit, int $offset ): array {
		$wpdb = $this->wpdb;

		$rows = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT subject_kind, subject_id, language, original_id, base_state, base_ate_job_id, base_delivery_id, base_job_id, base_entry_id,
					base_applied_at, head_entry_id, item_state, item_reason, copy_bytes
				FROM {$wpdb->prefix}icl_edit_state
				WHERE item_reason IS NOT NULL AND copy_bytes > 0
				ORDER BY item_reason = 'unclear' DESC, base_applied_at DESC, subject_kind, subject_id
				LIMIT %d OFFSET %d",
				$limit,
				$offset
			),
			ARRAY_A
		);

		return array_map( fn( $row ) => $this->state( $row ), (array) $rows );
	}

	private function state( array $row ): State {
		$subject = new Subject( (string) $row['subject_kind'], (int) $row['subject_id'] );

		return new State( $row, fn( $column ) => $this->blob( $subject, $column ) );
	}

	private function blob( Subject $subject, string $column ) {
		$wpdb = $this->wpdb;
		$kind = $subject->kind();
		$id   = $subject->id();

		switch ( $column ) {
			case 'base':
				return $wpdb->get_var( $wpdb->prepare( "SELECT base FROM {$wpdb->prefix}icl_edit_state WHERE subject_kind = %s AND subject_id = %d", $kind, $id ) );
			case 'head':
				return $wpdb->get_var( $wpdb->prepare( "SELECT head FROM {$wpdb->prefix}icl_edit_state WHERE subject_kind = %s AND subject_id = %d", $kind, $id ) );
			case 'floor':
				return $wpdb->get_var( $wpdb->prepare( "SELECT floor FROM {$wpdb->prefix}icl_edit_state WHERE subject_kind = %s AND subject_id = %d", $kind, $id ) );
			case 'sends':
				return $wpdb->get_var( $wpdb->prepare( "SELECT sends FROM {$wpdb->prefix}icl_edit_state WHERE subject_kind = %s AND subject_id = %d", $kind, $id ) );
			case 'copy':
				return $wpdb->get_var( $wpdb->prepare( "SELECT copy FROM {$wpdb->prefix}icl_edit_state WHERE subject_kind = %s AND subject_id = %d", $kind, $id ) );
			case 'stamps':
				return $wpdb->get_var( $wpdb->prepare( "SELECT stamps FROM {$wpdb->prefix}icl_edit_state WHERE subject_kind = %s AND subject_id = %d", $kind, $id ) );
			default:
				return null;
		}
	}

	public function saveHead( Subject $subject, string $language, int $originalId, int $entryId, array $head, string $headHash ) {
		$wpdb = $this->wpdb;

		$wpdb->query(
			$wpdb->prepare(
				"INSERT INTO {$wpdb->prefix}icl_edit_state (subject_kind, subject_id, language, original_id, head_entry_id, head_hash, head, updated_at)
				VALUES (%s, %d, %s, %d, %d, %s, %s, %d)
				ON DUPLICATE KEY UPDATE language = VALUES(language), original_id = VALUES(original_id), head_entry_id = VALUES(head_entry_id),
					head_hash = VALUES(head_hash), head = VALUES(head), updated_at = VALUES(updated_at)",
				$subject->kind(),
				$subject->id(),
				$language,
				$originalId,
				$entryId,
				$headHash,
				Codec::encode( $head ),
				( $this->now )()
			)
		);
	}

	public function saveStart( Subject $subject, string $language, int $originalId, array $lists, int $time ) {
		$wpdb = $this->wpdb;

		$wpdb->query(
			$wpdb->prepare(
				"INSERT INTO {$wpdb->prefix}icl_edit_state (subject_kind, subject_id, language, original_id, base_state, base_entry_id, base_applied_at, base, updated_at)
				VALUES (%s, %d, %s, %d, %s, 0, %d, %s, %d)
				ON DUPLICATE KEY UPDATE language = VALUES(language), original_id = VALUES(original_id),
					base_state = IF(base_state = %s, base_state, VALUES(base_state)), base_job_id = NULL, base_ate_job_id = NULL,
					base_delivery_id = NULL, base_entry_id = 0, base_applied_at = VALUES(base_applied_at), base = VALUES(base),
					floor_entry_id = 0, floor = NULL, updated_at = VALUES(updated_at)",
				$subject->kind(),
				$subject->id(),
				$language,
				$originalId,
				State::BASE_NONE,
				$time,
				Codec::encode( $lists ),
				( $this->now )(),
				State::BASE_UNKNOWN
			)
		);
	}

	public function saveFloor( Subject $subject, int $entryId, array $floor ) {
		$wpdb = $this->wpdb;

		$wpdb->query(
			$wpdb->prepare(
				"INSERT INTO {$wpdb->prefix}icl_edit_state (subject_kind, subject_id, floor_entry_id, floor, updated_at)
				VALUES (%s, %d, %d, %s, %d)
				ON DUPLICATE KEY UPDATE floor_entry_id = VALUES(floor_entry_id), floor = VALUES(floor), updated_at = VALUES(updated_at)",
				$subject->kind(),
				$subject->id(),
				$entryId,
				Codec::encode( $floor ),
				( $this->now )()
			)
		);
	}

	public function saveSends( Subject $subject, array $sends ) {
		$wpdb = $this->wpdb;

		$wpdb->query(
			$wpdb->prepare(
				"INSERT INTO {$wpdb->prefix}icl_edit_state (subject_kind, subject_id, sends, updated_at)
				VALUES (%s, %d, NULLIF(%s, ''), %d)
				ON DUPLICATE KEY UPDATE sends = VALUES(sends), updated_at = VALUES(updated_at)",
				$subject->kind(),
				$subject->id(),
				$sends ? Codec::encode( array_values( $sends ) ) : '',
				( $this->now )()
			)
		);
	}

	public function applyDelivery( Subject $subject, AppliedDelivery $delivery ) {
		$wpdb = $this->wpdb;
		$head = [
			'units'    => $delivery->base['units'] ?? [],
			'original' => $delivery->base['original'] ?? [],
		];
		$copy = null === $delivery->copy ? '' : Codec::encode( $delivery->copy );

		$wpdb->query(
			$wpdb->prepare(
				"INSERT INTO {$wpdb->prefix}icl_edit_state (subject_kind, subject_id, language, original_id,
					base_state, base_job_id, base_ate_job_id, base_delivery_id, base_entry_id, base_applied_at, base,
					head_entry_id, head_hash, head, floor_entry_id, floor, item_state, item_reason, copy, copy_bytes, updated_at)
				VALUES (%s, %d, %s, %d, %s, %d, %d, NULLIF(%d, 0), %d, %d, %s, %d, %s, %s, 0, NULL, NULLIF(%s, ''), NULLIF(%s, ''), NULLIF(%s, ''), %d, %d)
				ON DUPLICATE KEY UPDATE language = VALUES(language), original_id = VALUES(original_id),
					base_state = VALUES(base_state), base_job_id = VALUES(base_job_id), base_ate_job_id = VALUES(base_ate_job_id),
					base_delivery_id = VALUES(base_delivery_id), base_entry_id = VALUES(base_entry_id), base_applied_at = VALUES(base_applied_at),
					base = VALUES(base), head_entry_id = VALUES(head_entry_id), head_hash = VALUES(head_hash), head = VALUES(head),
					floor_entry_id = 0, floor = NULL, item_state = VALUES(item_state), item_reason = VALUES(item_reason),
					copy = VALUES(copy), copy_bytes = VALUES(copy_bytes), updated_at = VALUES(updated_at)",
				$subject->kind(),
				$subject->id(),
				$delivery->language,
				$delivery->originalId,
				State::BASE_APPLIED,
				$delivery->jobId,
				$delivery->ateJobId,
				(int) $delivery->deliveryId,
				$delivery->entryId,
				$delivery->appliedAt,
				Codec::encode( $delivery->base ),
				$delivery->entryId,
				$delivery->baseHash,
				Codec::encode( $head ),
				null === $delivery->itemReason ? '' : State::ITEM_CURRENT,
				(string) $delivery->itemReason,
				$copy,
				strlen( $copy ),
				( $this->now )()
			)
		);
	}

	public function reverted( Subject $subject, array $base ) {
		$wpdb = $this->wpdb;

		$wpdb->query(
			$wpdb->prepare(
				"UPDATE {$wpdb->prefix}icl_edit_state SET base = %s, copy = NULL, copy_bytes = 0, item_state = %s, updated_at = %d WHERE subject_kind = %s AND subject_id = %d",
				Codec::encode( $base ),
				State::ITEM_STALE,
				( $this->now )(),
				$subject->kind(),
				$subject->id()
			)
		);
	}

	public function markStale( Subject $subject, string $baseState ) {
		$wpdb = $this->wpdb;

		$wpdb->query(
			$wpdb->prepare(
				"UPDATE {$wpdb->prefix}icl_edit_state SET base_state = %s, item_state = IF(item_state IS NULL, NULL, %s), updated_at = %d WHERE subject_kind = %s AND subject_id = %d",
				$baseState,
				State::ITEM_STALE,
				( $this->now )(),
				$subject->kind(),
				$subject->id()
			)
		);
	}

	public function saveStamps( Subject $original, array $stamps ) {
		$wpdb = $this->wpdb;

		$wpdb->query(
			$wpdb->prepare(
				"INSERT INTO {$wpdb->prefix}icl_edit_state (subject_kind, subject_id, stamps, updated_at)
				VALUES (%s, %d, %s, %d)
				ON DUPLICATE KEY UPDATE stamps = VALUES(stamps), updated_at = VALUES(updated_at)",
				$original->kind(),
				$original->id(),
				Codec::encode( $stamps ),
				( $this->now )()
			)
		);
	}

	public function delete( Subject $subject ) {
		$this->wpdb->delete(
			$this->wpdb->prefix . Tables::STATE,
			[
				'subject_kind' => $subject->kind(),
				'subject_id'   => $subject->id(),
			],
			[ '%s', '%d' ]
		);
	}
}
