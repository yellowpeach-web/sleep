<?php

namespace WPML\EditHistory;

class PanelItem {

	public $translationId;

	public $originalId;

	public $language;

	public $reason;

	public $appliedAt;

	public $version;

	public $current;

	public $rows;

	public $removed;

	public function __construct( State $state, bool $current, array $rows ) {
		$this->translationId = $state->subject()->id();
		$this->originalId    = $state->originalId();
		$this->language      = $state->language();
		$this->reason        = (string) $state->itemReason();
		$this->appliedAt     = $state->baseAppliedAt();
		$this->version       = self::version( $state );
		$this->current       = $current;
		$this->rows          = $rows;
		$this->removed       = count( $state->deletedElements() );
	}

	public static function version( State $state ): string {
		return implode( '-', [ (int) $state->baseAteJobId(), (int) $state->baseAppliedAt(), $state->headEntryId() ] );
	}
}
