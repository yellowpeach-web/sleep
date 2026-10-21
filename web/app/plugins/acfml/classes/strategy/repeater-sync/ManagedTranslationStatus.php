<?php

namespace ACFML\Repeater\Sync;

class ManagedTranslationStatus implements TranslationStatus {

	private $records;

	public function __construct( \WPML_TM_Records $records ) {
		$this->records = $records;
	}

	public function isLevelWithOriginal( $translation ) {
		$status = $this->statusOf( $translation );

		if ( ! self::isSettledStatus( (int) $status->status() ) ) {
			return false;
		}

		return ! $status->needs_update();
	}

	public function isSettled( $translation ) {
		return self::isSettledStatus( (int) $this->statusOf( $translation )->status() );
	}

	private static function isSettledStatus( $status ) {
		return ICL_TM_COMPLETE === $status || ICL_TM_NOT_TRANSLATED === $status;
	}

	public function markNeedsUpdate( $translation ) {
		$this->statusOf( $translation )->update( [ 'needs_update' => 1 ] );
	}

	private function statusOf( $translation ) {
		return $this->records->icl_translation_status_by_translation_id( (int) $translation->translation_id );
	}
}
