<?php

namespace WPML\LanguageEditor\Save;

class ChangeOrdering {

	const GUARDED_FIELDS = [ 'display_code', 'locale' ];

	const TEMP_PREFIX = '__wpml_swap_';

	public static function plan( array $changes ) {
		$steps = [];

		foreach ( self::GUARDED_FIELDS as $field ) {
			$entries = self::entriesFor( $changes, $field );

			if ( 'display_code' === $field && self::hasDuplicateTarget( $entries ) ) {
				return [
					'ok'    => false,
					'error' => $field . '_collision',
				];
			}

			$steps = array_merge( $steps, self::orderFieldWrites( $entries, $field ) );
		}

		return [
			'ok'    => true,
			'steps' => $steps,
		];
	}

	private static function entriesFor( array $changes, $field ) {
		$entries = [];
		foreach ( $changes as $change ) {
			if ( ! isset( $change[ $field ]['old'], $change[ $field ]['new'], $change['code'] ) ) {
				continue;
			}
			$old = (string) $change[ $field ]['old'];
			$new = (string) $change[ $field ]['new'];
			if ( $old === $new ) {
				continue;
			}
			$entries[] = [
				'code' => (string) $change['code'],
				'old'  => $old,
				'new'  => $new,
			];
		}
		return $entries;
	}

	private static function hasDuplicateTarget( array $entries ) {
		$targetOwner = [];
		foreach ( $entries as $entry ) {
			$key = $entry['new'];
			if ( isset( $targetOwner[ $key ] ) && $targetOwner[ $key ] !== $entry['code'] ) {
				return true;
			}
			$targetOwner[ $key ] = $entry['code'];
		}
		return false;
	}

	private static function orderFieldWrites( array $entries, $field ) {
		$steps    = [];
		$deferred = [];
		$pending  = array_values( $entries );

		while ( $pending ) {
			$emittedIdx = self::findUnblocked( $pending );

			if ( null !== $emittedIdx ) {
				$entry   = $pending[ $emittedIdx ];
				$steps[] = self::step( $entry['code'], $field, $entry['new'], false );
				unset( $pending[ $emittedIdx ] );
				$pending = array_values( $pending );
				continue;
			}

			$cutIdx     = self::smallestCodeIndex( $pending );
			$cut        = $pending[ $cutIdx ];
			$steps[]    = self::step( $cut['code'], $field, self::tempValue( $cut['code'] ), true );
			$deferred[] = self::step( $cut['code'], $field, $cut['new'], false );
			unset( $pending[ $cutIdx ] );
			$pending = array_values( $pending );
		}

		return array_merge( $steps, $deferred );
	}

	private static function findUnblocked( array $pending ) {
		foreach ( $pending as $i => $entry ) {
			$blocked = false;
			foreach ( $pending as $j => $other ) {
				if ( $j !== $i && $other['old'] === $entry['new'] ) {
					$blocked = true;
					break;
				}
			}
			if ( ! $blocked ) {
				return $i;
			}
		}
		return null;
	}

	private static function smallestCodeIndex( array $pending ) {
		$best = 0;
		foreach ( $pending as $i => $entry ) {
			if ( strcmp( $entry['code'], $pending[ $best ]['code'] ) < 0 ) {
				$best = $i;
			}
		}
		return $best;
	}

	private static function tempValue( $code ) {
		return self::TEMP_PREFIX . $code . '_' . substr( md5( uniqid( '', true ) ), 0, 8 );
	}

	private static function step( $code, $field, $value, $temp ) {
		return [
			'code'  => $code,
			'field' => $field,
			'value' => $value,
			'temp'  => $temp,
		];
	}
}
