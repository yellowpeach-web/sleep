<?php

namespace WPML\TM\ATE\ClonedSites;

use function WPML\Container\make;

class OldIdentityJobs {

	public static function cancel(): int {
		MigrationLogger::begin();

		try {
			$count = (int) make( InProgressJobsCanceller::class )->cancel();
		} catch ( \Throwable $e ) {
			MigrationLogger::jobsCancelFailed( $e );

			return 0;
		}

		MigrationLogger::jobsCancelled( $count );

		return $count;
	}
}
