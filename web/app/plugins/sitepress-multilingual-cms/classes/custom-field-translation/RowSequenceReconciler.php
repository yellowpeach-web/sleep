<?php

namespace WPML\CustomFieldTranslation;

class RowSequenceReconciler {

	public function plan( array $desired_values, array $current_values, ?callable $must_rewrite_removal = null ) {
		$desired_values = array_values( $desired_values );
		$current_values = array_values( $current_values );

		$removed       = $this->occurrence_diff( $current_values, $desired_values );
		$added_indices = $this->occurrence_diff_indices( $desired_values, $current_values );

		$removed_lookup = array_fill_keys( $removed, true );
		$retained       = [];
		foreach ( $current_values as $value ) {
			if ( ! isset( $removed_lookup[ $value ] ) ) {
				$retained[] = $value;
			}
		}

		$added_values = [];
		foreach ( $added_indices as $index ) {
			$added_values[] = $desired_values[ $index ];
		}

		$rewrite = array_merge( $retained, $added_values ) !== $desired_values;

		if ( ! $rewrite && $must_rewrite_removal ) {
			foreach ( $removed as $value ) {
				if ( $must_rewrite_removal( $value ) ) {
					$rewrite = true;
					break;
				}
			}
		}

		return [
			'removed'       => $rewrite ? $current_values : $removed,
			'added_indices' => $rewrite ? array_keys( $desired_values ) : $added_indices,
			'rewrite'       => $rewrite,
		];
	}

	private function occurrence_diff( array $a, array $b ) {
		$available = [];
		foreach ( $b as $v ) {
			$available[ $v ] = isset( $available[ $v ] ) ? $available[ $v ] + 1 : 1;
		}

		$diff = [];
		foreach ( $a as $v ) {
			if ( isset( $available[ $v ] ) && $available[ $v ] > 0 ) {
				--$available[ $v ];
			} else {
				$diff[] = $v;
			}
		}

		return $diff;
	}

	private function occurrence_diff_indices( array $a, array $b ) {
		$available = [];
		foreach ( $b as $value ) {
			$available[ $value ] = isset( $available[ $value ] ) ? $available[ $value ] + 1 : 1;
		}

		$indices = [];
		foreach ( $a as $index => $value ) {
			if ( isset( $available[ $value ] ) && $available[ $value ] > 0 ) {
				--$available[ $value ];
			} else {
				$indices[] = $index;
			}
		}

		return $indices;
	}
}
