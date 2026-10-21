<?php

namespace WPML\TM\ATE\Log;

use WPML\Collect\Support\Collection;
use WPML\TM\Jobs\JobLog;
use WPML\WP\OptionManager;

class Storage {

	const OPTION_GROUP = 'TM\ATE\Log';
	const OPTION_NAME  = 'logs';
	const MAX_ENTRIES  = 50;

	const MAX_EXTRA_DATA_BYTES = 4096;

	const DEBUG_KEYS = [ 'url', 'method', 'status', 'details', 'extraMessage' ];

	public static function add( Entry $entry, $avoidDuplication = false ) {
		$entry->timestamp = $entry->timestamp ?: time();

		self::mirrorToJobLog( $entry );

		$entries = self::getAll();

		if ( $avoidDuplication ) {
			$entries = $entries->reject(
				function( $iteratedEntry ) use ( $entry ) {
					return (
					$iteratedEntry->wpmlJobId === $entry->wpmlJobId
					&& $entry->ateJobId === $iteratedEntry->ateJobId
					&& $entry->description === $iteratedEntry->description
					&& $entry->eventType === $iteratedEntry->eventType
					);
				}
			);
		}

		$storedEntry            = clone $entry;
		$storedEntry->extraData = self::sanitizeExtraData( (array) $entry->extraData );

		$entries->prepend( $storedEntry );

		$newOptionValue = $entries->forPage( 1, self::MAX_ENTRIES )
								->map(
									function( Entry $entry ) {
										return (array) $entry; }
								)
								  ->toArray();
		OptionManager::updateWithoutAutoLoad( self::OPTION_NAME, self::OPTION_GROUP, $newOptionValue );
	}

	private static function sanitizeExtraData( array $data ) {
		$sanitized = self::scrub( $data );

		if ( self::encodedSize( $sanitized ) <= self::MAX_EXTRA_DATA_BYTES ) {
			return $sanitized;
		}

		if ( isset( $sanitized['requestArgs'] ) && is_array( $sanitized['requestArgs'] ) && array_key_exists( 'body', $sanitized['requestArgs'] ) ) {
			$body                             = isset( $data['requestArgs']['body'] ) ? $data['requestArgs']['body'] : $sanitized['requestArgs']['body'];
			$sanitized['requestArgs']['body'] = '[body omitted: ' . self::encodedSize( $body ) . ' bytes]';
		}

		if ( self::encodedSize( $sanitized ) <= self::MAX_EXTRA_DATA_BYTES ) {
			return $sanitized;
		}

		return self::keepDebugKeys( $sanitized );
	}

	private static function scrub( array $data, $depth = 0 ) {
		$result = [];

		foreach ( $data as $key => $value ) {
			$normalisedKey = str_replace( '-', '_', strtolower( (string) $key ) );

			if ( 'headers' === $normalisedKey ) {
				continue;
			}

			if ( self::isSecretKey( $normalisedKey ) ) {
				$result[ $key ] = '[REDACTED]';
				continue;
			}

			if ( is_array( $value ) ) {
				$result[ $key ] = $depth < JobLog::MAX_DEPTH ? self::scrub( $value, $depth + 1 ) : '[DEPTH_LIMIT]';
				continue;
			}

			if ( is_string( $value ) ) {
				if ( 'url' === substr( $normalisedKey, -3 ) ) {
					$value = substr( $value, 0, strcspn( $value, '?#' ) );
				}

				if ( strlen( $value ) > JobLog::MAX_STRING_LENGTH ) {
					$value = substr( $value, 0, JobLog::MAX_STRING_LENGTH ) . '…[truncated]';
				}
			}

			$result[ $key ] = $value;
		}

		return $result;
	}

	private static function isSecretKey( $normalisedKey ) {
		foreach ( JobLog::SECRET_KEY_NEEDLES as $needle ) {
			if ( strpos( $normalisedKey, $needle ) !== false ) {
				return true;
			}
		}

		return false;
	}

	private static function keepDebugKeys( array $data ) {
		$kept = [];

		foreach ( $data as $key => $value ) {
			if ( in_array( $key, self::DEBUG_KEYS, true ) ) {
				$kept[ $key ] = $value;
				continue;
			}

			if ( is_array( $value ) ) {
				$inner = self::keepDebugKeys( $value );
				if ( $inner ) {
					$kept[ $key ] = $inner;
				}
			}
		}

		return $kept;
	}

	private static function encodedSize( $value ) {
		return is_string( $value ) ? strlen( $value ) : strlen( (string) json_encode( $value ) );
	}

	public static function remove( Entry $entry ) {
		$entries        = self::getAll();
		$entries        = $entries->reject(
			function( $iteratedEntry ) use ( $entry ) {
				return $iteratedEntry->timestamp === $entry->timestamp && $entry->ateJobId === $iteratedEntry->ateJobId;
			}
		);
		$newOptionValue = $entries->forPage( 1, self::MAX_ENTRIES )
				->map(
					function( Entry $entry ) {
						return (array) $entry; }
				)
				->toArray();
		OptionManager::updateWithoutAutoLoad( self::OPTION_NAME, self::OPTION_GROUP, $newOptionValue );
	}

	public static function getAll() {
		return wpml_collect( OptionManager::getOr( [], self::OPTION_NAME, self::OPTION_GROUP ) )
			->map(
				function( array $item ) {
					return new Entry( $item );
				}
			);
	}

	public function getCount(): int {
		return count( OptionManager::getOr( [], self::OPTION_NAME, self::OPTION_GROUP ) );
	}

	private static function mirrorToJobLog( Entry $entry ) {
		try {
			if ( ! class_exists( \WPML\TM\Jobs\JobLog::class ) ) {
				return;
			}

			$data = [
				'description' => $entry->description,
				'event_type'  => $entry->eventType,
				'wpmlJobId'   => $entry->wpmlJobId,
				'ateJobId'    => $entry->ateJobId,
				'extraData'   => $entry->extraData,
			];

			$eventLabel = 'ate_log_event_type_' . (int) $entry->eventType;

			if ( ! empty( $entry->description ) ) {
				\WPML\TM\Jobs\JobLog::addError( $eventLabel, $data );
			} else {
				\WPML\TM\Jobs\JobLog::add( $eventLabel, $data );
			}
		} catch ( \Throwable $e ) {
		}
	}
}
