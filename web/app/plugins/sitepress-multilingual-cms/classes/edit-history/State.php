<?php

namespace WPML\EditHistory;

class State {

	const BASE_NONE    = 'none';
	const BASE_APPLIED = 'applied';
	const BASE_UNKNOWN = 'unknown';

	const ITEM_CURRENT = 'current';
	const ITEM_STALE   = 'stale';

	const BLOBS = [ 'base', 'head', 'floor', 'sends', 'copy', 'stamps' ];

	private $row;

	private $loadBlob;

	public function __construct( array $row, $loadBlob = null ) {
		$this->row      = $row;
		$this->loadBlob = $loadBlob;
	}

	public function subject(): Subject {
		return new Subject( (string) $this->row['subject_kind'], (int) $this->row['subject_id'] );
	}

	public function language(): string {
		return (string) ( $this->row['language'] ?? '' );
	}

	public function originalId(): int {
		return (int) ( $this->row['original_id'] ?? 0 );
	}

	public function baseState(): string {
		return (string) ( $this->row['base_state'] ?? self::BASE_NONE );
	}

	public function baseAteJobId() {
		return $this->nullableInt( 'base_ate_job_id' );
	}

	public function baseDeliveryId() {
		return $this->nullableInt( 'base_delivery_id' );
	}

	public function baseJobId() {
		return $this->nullableInt( 'base_job_id' );
	}

	public function baseAppliedAt() {
		return $this->nullableInt( 'base_applied_at' );
	}

	public function base() {
		return Codec::decode( $this->blob( 'base' ) );
	}

	public function baseEntryId(): int {
		return (int) ( $this->row['base_entry_id'] ?? 0 );
	}

	public function deletedElements(): array {
		if ( self::BASE_APPLIED !== $this->baseState() ) {
			return [];
		}

		return array_values( (array) ( $this->base()['deleted'] ?? [] ) );
	}

	public function headEntryId(): int {
		return (int) ( $this->row['head_entry_id'] ?? 0 );
	}

	public function headHash(): string {
		return (string) ( $this->row['head_hash'] ?? '' );
	}

	public function isHeadOf( string $language, int $originalId ): bool {
		return '' !== $this->headHash() && $this->belongsTo( $language, $originalId );
	}

	public function head() {
		return Codec::decode( $this->blob( 'head' ) );
	}

	public function replayStart(): array {
		$floor = Codec::decode( $this->blob( 'floor' ) );
		if ( null !== $floor ) {
			return [ 'entry_id' => (int) $this->row['floor_entry_id'], 'lists' => $floor ];
		}

		$base = $this->base();

		return [
			'entry_id' => (int) ( $this->row['base_entry_id'] ?? 0 ),
			'lists'    => [
				'units'    => $base['units'] ?? [],
				'original' => $base['original'] ?? [],
			],
		];
	}

	public function sends(): array {
		return Codec::decode( $this->blob( 'sends' ) ) ?? [];
	}

	public function itemState() {
		return $this->row['item_state'] ?? null;
	}

	public function itemReason() {
		return $this->row['item_reason'] ?? null;
	}

	public function hasCopy(): bool {
		return (int) ( $this->row['copy_bytes'] ?? 0 ) > 0;
	}

	public function copy() {
		return Codec::decode( $this->blob( 'copy' ) );
	}

	public function stamps(): array {
		return Codec::decode( $this->blob( 'stamps' ) ) ?? [];
	}

	public function belongsTo( string $language, int $originalId ): bool {
		return (string) $this->row['language'] === $language && (int) $this->row['original_id'] === $originalId;
	}

	private function blob( string $column ) {
		if ( ! array_key_exists( $column, $this->row ) ) {
			$this->row[ $column ] = $this->loadBlob ? ( $this->loadBlob )( $column ) : null;
		}

		return $this->row[ $column ];
	}

	private function nullableInt( string $column ) {
		return isset( $this->row[ $column ] ) ? (int) $this->row[ $column ] : null;
	}
}
