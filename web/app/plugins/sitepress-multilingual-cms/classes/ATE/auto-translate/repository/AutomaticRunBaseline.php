<?php

namespace WPML\TM\ATE\AutoTranslate\Repository;

use WPML\LIB\WP\Option;

class AutomaticRunBaseline {

	const OPTION = 'wpml_ate_automatic_run_baseline';

	public function observe( $inProgress ) {
		$inProgress = max( 0, (int) $inProgress );
		$stored     = Option::getOr( self::OPTION, [] );

		if ( $inProgress < 1 ) {
			if ( $stored ) {
				Option::delete( self::OPTION );
			}

			return 0;
		}

		$total = is_array( $stored ) ? (int) ( $stored['total'] ?? 0 ) : 0;
		$last  = is_array( $stored ) ? (int) ( $stored['last'] ?? 0 ) : 0;

		if ( $total < 1 ) {
			$total = $inProgress;
		} elseif ( $inProgress > $last ) {
			$total += $inProgress - $last;
		}

		$total = max( $total, $inProgress );

		if ( ! is_array( $stored ) || $total !== (int) ( $stored['total'] ?? -1 ) || $inProgress !== (int) ( $stored['last'] ?? -1 ) ) {
			Option::updateWithoutAutoLoad(
				self::OPTION,
				[
					'total' => $total,
					'last'  => $inProgress,
				]
			);
		}

		return $total;
	}
}
