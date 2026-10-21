<?php

namespace ACFML\Repeater\Sync;

class UntrackedTranslationStatus implements TranslationStatus {

	public function isLevelWithOriginal( $translation ) {
		return true;
	}

	public function isSettled( $translation ) {
		return true;
	}

	public function markNeedsUpdate( $translation ) {
	}
}
