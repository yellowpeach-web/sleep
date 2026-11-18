<?php

namespace WPML\EditHistory;

class PanelItems {

	const LIMIT = 100;

	private $store;

	private $log;

	private $owner;

	private $clientUnits;

	private $mayEdit;

	private $listed;

	public function __construct( StateStore $store, EntryLog $log, callable $owner, callable $clientUnits, callable $mayEdit ) {
		$this->store       = $store;
		$this->log         = $log;
		$this->owner       = $owner;
		$this->clientUnits = $clientUnits;
		$this->mayEdit     = $mayEdit;
	}

	public function forCurrentUser(): array {
		$items = [];
		foreach ( $this->visible() as $state ) {
			$items[] = new PanelItem( $state, $this->current( $state ), $this->rows( $state ) );
		}

		return $items;
	}

	public function hasAnyForCurrentUser(): bool {
		return (bool) $this->visible();
	}

	public function listed( Subject $subject ) {
		if ( null === $this->listed ) {
			$this->listed = [];
			foreach ( $this->visible() as $state ) {
				$this->listed[ self::key( $state->subject() ) ] = $state;
			}
		}

		return $this->listed[ self::key( $subject ) ] ?? null;
	}

	private static function key( Subject $subject ): string {
		return $subject->kind() . ':' . $subject->id();
	}

	private function visible(): array {
		return array_values( array_filter( $this->store->items( self::LIMIT, 0 ), [ $this, 'mayShow' ] ) );
	}

	private function mayShow( State $state ): bool {
		$subject = $state->subject();

		return Subject::POST === $subject->kind() && ( $this->mayEdit )( $subject->id() );
	}

	public function current( State $state ): bool {
		if ( State::ITEM_CURRENT !== $state->itemState() || null === $state->itemReason() || ! $state->hasCopy() ) {
			return false;
		}

		$owner = ( $this->owner )( $state->subject()->id() );
		if ( ! $owner || ! $state->belongsTo( ...$owner ) ) {
			return false;
		}

		return ! $this->editedSinceDelivery( $state );
	}

	private function editedSinceDelivery( State $state ): bool {
		if ( $state->headEntryId() <= $state->baseEntryId() ) {
			return false;
		}

		foreach ( $this->log->since( $state->subject(), $state->language(), $state->baseEntryId() ) as $entry ) {
			if ( ! in_array( $entry->source, Delivery::NOT_CLIENT_EDITS, true ) ) {
				return true;
			}
		}

		return false;
	}

	private function rows( State $state ): array {
		$copy   = $state->copy();
		$client = null === $copy ? null : ( $this->clientUnits )( $state->subject()->id(), $state->originalId(), $copy );

		return null === $client ? [] : PanelDiff::rows( $client, (array) ( $state->base()['units'] ?? [] ) );
	}
}
