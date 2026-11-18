<?php

namespace ACFML\TranslationPolicy;

use ACFML\Options;
use WPML\TranslationPolicy\LateCutoverStep;

class WriteLocks extends LateCutoverStep {

	protected function work(): bool {
		global $wpdb;

		if ( ! function_exists( 'acf_get_field_groups' ) ) {
			return true;
		}

		$isAllWritten = ( new LockRows( $wpdb ) )->sync();
		if ( $isAllWritten ) {
			Options::set( LockRows::OPTION_SYNCED, true );
		}

		return $isAllWritten;
	}
}
