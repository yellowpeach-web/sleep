<?php

namespace WPML\EditHistory;

interface StateStore {

	public function get( Subject $subject );

	public function items( int $limit, int $offset ): array;

	public function saveHead( Subject $subject, string $language, int $originalId, int $entryId, array $head, string $headHash );

	public function saveStart( Subject $subject, string $language, int $originalId, array $lists, int $time );

	public function saveFloor( Subject $subject, int $entryId, array $floor );

	public function saveSends( Subject $subject, array $sends );

	public function applyDelivery( Subject $subject, AppliedDelivery $delivery );

	public function reverted( Subject $subject, array $base );

	public function markStale( Subject $subject, string $baseState );

	public function saveStamps( Subject $original, array $stamps );

	public function delete( Subject $subject );
}
