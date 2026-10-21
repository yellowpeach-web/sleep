<?php

namespace WPML\Core\PostTranslation;

class StatusSyncRule {

	const SYNCED = [ 'draft', 'pending', 'private', 'publish', 'future' ];

	public static function isSyncedChange( $oldStatus, $newStatus ) {
		return in_array( $oldStatus, self::SYNCED, true )
		       && in_array( $newStatus, self::SYNCED, true )
		       && ( $oldStatus !== $newStatus || 'future' === $newStatus );
	}

	public static function goesLive( $newStatus ) {
		return in_array( $newStatus, [ 'publish', 'future' ], true );
	}

	public static function target( $oldStatus, $newStatus, $translationStatus, $isDuplicate, $needsReview, $dateDiffers ) {
		if (
			! self::isSyncedChange( $oldStatus, $newStatus )
			|| ! in_array( $translationStatus, self::SYNCED, true )
			|| $isDuplicate
			|| ( $needsReview && self::goesLive( $newStatus ) )
		) {
			return null;
		}

		if ( 'future' === $newStatus ) {
			$follows = 'future' === $oldStatus
				? 'future' === $translationStatus && $dateDiffers
				: 'future' !== $translationStatus || $dateDiffers;

			return $follows ? 'future' : null;
		}

		return $translationStatus === $newStatus ? null : $newStatus;
	}
}
