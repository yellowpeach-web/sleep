<?php

class WPML_Single_Url_Cache_Repository {

	const TABLE_NAME = 'icl_url_resolution_cache';
	const MAX_ROWS   = 250000;
	const SQL_NULL   = '__WPML_SINGLE_URL_CACHE_SQL_NULL__';

	private $wpdb;

	private $available = [];

	private $max_rows;

	public function __construct( wpdb $wpdb, $max_rows = null ) {
		$this->wpdb     = $wpdb;
		$this->max_rows = null === $max_rows ? self::MAX_ROWS : max( 1, (int) $max_rows );
	}

	public function max_rows() {
		return $this->max_rows;
	}

	public function get( $key_hex ) {
		if ( ! $this->can_query() ) {
			return null;
		}

		$wpdb = $this->wpdb;
		$row  = $this->without_errors(
			function() use ( $wpdb, $key_hex ) {
				return $wpdb->get_row(
					$wpdb->prepare(
						"SELECT HEX(cache_key) AS cache_key, HEX(url_hash) AS url_hash, generation, source_url,
							source_language, target_language, state, resolved_url, source_object_kind,
							source_object_id, translated_object_id, translation_trid, refresh_after,
							expires_at, refresh_requested, HEX(lease_token) AS lease_token, lease_until,
							attempt_count, next_attempt_at, created_at, updated_at
						FROM {$wpdb->prefix}icl_url_resolution_cache
						WHERE cache_key = UNHEX(%s)
						LIMIT 1",
						$key_hex
					),
					ARRAY_A
				);
			}
		);

		return is_array( $row ) ? $row : null;
	}

	public function ensure_pending( array $identity ) {
		if ( ! $this->can_query() ) {
			return false;
		}

		$now  = current_time( 'mysql', true );
		$wpdb = $this->wpdb;
		$result = $this->without_errors(
			function() use ( $wpdb, $identity, $now ) {
				return $wpdb->query(
					$wpdb->prepare(
						"INSERT IGNORE INTO {$wpdb->prefix}icl_url_resolution_cache
							(cache_key, url_hash, generation, source_url, source_language, target_language,
							 state, refresh_requested, attempt_count, next_attempt_at, created_at, updated_at)
						VALUES
							(UNHEX(%s), UNHEX(%s), %d, %s, %s, %s, %s, 0, 0, %s, %s, %s)",
						$identity['cache_key'],
						$identity['url_hash'],
						$identity['generation'],
						$identity['source_url'],
						$identity['source_language'],
						$identity['target_language'],
						WPML_Single_Url_Cache_Entry::STATE_PENDING,
						$now,
						$now,
						$now
					)
				);
			}
		);

		return false !== $result && $this->is_available();
	}

	public function request_refresh( $key_hex ) {
		if ( ! $this->can_query() ) {
			return;
		}

		$wpdb = $this->wpdb;
		$now  = current_time( 'mysql', true );
		$this->without_errors(
			function() use ( $wpdb, $now, $key_hex ) {
				return $wpdb->query(
					$wpdb->prepare(
						"UPDATE {$wpdb->prefix}icl_url_resolution_cache
						SET refresh_requested = 1,
							next_attempt_at = %s,
							updated_at = %s
						WHERE cache_key = UNHEX(%s)",
						$now,
						$now,
						$key_hex
					)
				);
			}
		);
	}

	public function reset_to_pending( $key_hex ) {
		if ( ! $this->can_query() ) {
			return;
		}

		$wpdb = $this->wpdb;
		$now  = current_time( 'mysql', true );
		$this->without_errors(
			function() use ( $wpdb, $now, $key_hex ) {
				return $wpdb->query(
					$wpdb->prepare(
						"UPDATE {$wpdb->prefix}icl_url_resolution_cache
						SET state = %s, resolved_url = NULL, source_object_kind = NULL,
							source_object_id = NULL, translated_object_id = NULL, translation_trid = NULL,
							refresh_after = NULL, expires_at = NULL, refresh_requested = 0,
							lease_token = NULL, lease_until = NULL, attempt_count = 0,
							next_attempt_at = %s, updated_at = %s
						WHERE cache_key = UNHEX(%s)",
						WPML_Single_Url_Cache_Entry::STATE_PENDING,
						$now,
						$now,
						$key_hex
					)
				);
			}
		);
	}

	public function claim_batch( $generation, $limit ) {
		if ( ! $this->can_query() ) {
			return [];
		}

		$wpdb = $this->wpdb;
		$now  = current_time( 'mysql', true );
		$rows = $this->without_errors(
			function() use ( $wpdb, $generation, $now, $limit ) {
				return $wpdb->get_results(
					$wpdb->prepare(
						"SELECT HEX(cache_key) AS cache_key
						FROM {$wpdb->prefix}icl_url_resolution_cache
						WHERE generation = %d
							AND (state = %s OR state = %s OR refresh_requested = 1)
							AND (next_attempt_at IS NULL OR next_attempt_at <= %s)
							AND (lease_until IS NULL OR lease_until < %s)
						ORDER BY updated_at ASC
						LIMIT %d",
						$generation,
						WPML_Single_Url_Cache_Entry::STATE_PENDING,
						WPML_Single_Url_Cache_Entry::STATE_FAILED,
						$now,
						$now,
						max( 1, (int) $limit )
					),
					ARRAY_A
				);
			}
		);

		if ( ! is_array( $rows ) ) {
			return [];
		}

		$claimed = [];
		foreach ( $rows as $row ) {
			$token       = bin2hex( random_bytes( 16 ) );
			$lease_until = gmdate( 'Y-m-d H:i:s', time() + 5 * MINUTE_IN_SECONDS );
			$updated = $this->without_errors(
				function() use ( $wpdb, $token, $lease_until, $now, $row, $generation ) {
					return $wpdb->query(
						$wpdb->prepare(
							"UPDATE {$wpdb->prefix}icl_url_resolution_cache
							SET lease_token = UNHEX(%s), lease_until = %s,
								attempt_count = attempt_count + 1, updated_at = %s
							WHERE cache_key = UNHEX(%s)
								AND generation = %d
								AND (state = %s OR state = %s OR refresh_requested = 1)
								AND (next_attempt_at IS NULL OR next_attempt_at <= %s)
								AND (lease_until IS NULL OR lease_until < %s)",
							$token,
							$lease_until,
							$now,
							$row['cache_key'],
							$generation,
							WPML_Single_Url_Cache_Entry::STATE_PENDING,
							WPML_Single_Url_Cache_Entry::STATE_FAILED,
							$now,
							$now
						)
					);
				}
			);

			if ( 1 === (int) $updated ) {
				$entry = $this->get( $row['cache_key'] );
				if ( $entry ) {
					$entry['lease_token'] = $token;
					$claimed[]            = $entry;
				}
			}
		}

		return $claimed;
	}

	public function claim_first_attempt( $key_hex, $generation ) {
		if ( ! $this->can_query() ) {
			return null;
		}

		$wpdb        = $this->wpdb;
		$now         = current_time( 'mysql', true );
		$token       = bin2hex( random_bytes( 16 ) );
		$lease_until = gmdate( 'Y-m-d H:i:s', time() + 5 * MINUTE_IN_SECONDS );

		$updated = $this->without_errors(
			function() use ( $wpdb, $token, $lease_until, $now, $key_hex, $generation ) {
				return $wpdb->query(
					$wpdb->prepare(
						"UPDATE {$wpdb->prefix}icl_url_resolution_cache
						SET lease_token = UNHEX(%s), lease_until = %s,
							attempt_count = attempt_count + 1, updated_at = %s
						WHERE cache_key = UNHEX(%s)
							AND generation = %d
							AND state = %s
							AND refresh_requested = 0
							AND attempt_count = 0
							AND (lease_until IS NULL OR lease_until < %s)",
						$token,
						$lease_until,
						$now,
						$key_hex,
						$generation,
						WPML_Single_Url_Cache_Entry::STATE_PENDING,
						$now
					)
				);
			}
		);

		if ( 1 !== (int) $updated ) {
			return null;
		}

		$entry = $this->get( $key_hex );
		if ( ! $entry ) {
			return null;
		}

		$entry['lease_token'] = $token;

		return $entry;
	}

	public function complete( $key_hex, $lease_token_hex, array $outcome ) {
		if ( ! $this->can_query() ) {
			return false;
		}

		$wpdb = $this->wpdb;
		$result = $this->without_errors(
			function() use ( $wpdb, $outcome, $key_hex, $lease_token_hex ) {
				return $wpdb->query(
					$wpdb->prepare(
						"UPDATE {$wpdb->prefix}icl_url_resolution_cache
						SET state = %s,
							resolved_url = NULLIF(%s, '__WPML_SINGLE_URL_CACHE_SQL_NULL__'),
							source_object_kind = NULLIF(%s, '__WPML_SINGLE_URL_CACHE_SQL_NULL__'),
							source_object_id = NULLIF(%s, '__WPML_SINGLE_URL_CACHE_SQL_NULL__'),
							translated_object_id = NULLIF(%s, '__WPML_SINGLE_URL_CACHE_SQL_NULL__'),
							translation_trid = NULLIF(%s, '__WPML_SINGLE_URL_CACHE_SQL_NULL__'),
							refresh_after = NULLIF(%s, '__WPML_SINGLE_URL_CACHE_SQL_NULL__'),
							expires_at = NULLIF(%s, '__WPML_SINGLE_URL_CACHE_SQL_NULL__'),
							refresh_requested = 0, lease_token = NULL, lease_until = NULL,
							attempt_count = 0, next_attempt_at = NULL, updated_at = %s
						WHERE cache_key = UNHEX(%s) AND lease_token = UNHEX(%s)",
						$outcome['state'],
						$this->nullable_value( $outcome['resolved_url'] ),
						$this->nullable_value( $outcome['source_object_kind'] ),
						$this->nullable_int( $outcome['source_object_id'] ),
						$this->nullable_int( $outcome['translated_object_id'] ),
						$this->nullable_int( $outcome['translation_trid'] ),
						$this->nullable_value( $outcome['refresh_after'] ),
						$this->nullable_value( $outcome['expires_at'] ),
						current_time( 'mysql', true ),
						$key_hex,
						$lease_token_hex
					)
				);
			}
		);

		return 1 === (int) $result;
	}

	public function fail(
		$key_hex,
		$lease_token_hex,
		$attempt_count,
		$error_code = '',
		$previous_state = ''
	) {
		if ( ! $this->can_query() ) {
			return;
		}

		$attempt_count = max( 1, (int) $attempt_count );
		$is_failed     = $attempt_count >= 5;
		$is_refresh    = in_array(
			$previous_state,
			[
				WPML_Single_Url_Cache_Entry::STATE_POSITIVE,
				WPML_Single_Url_Cache_Entry::STATE_ROUTE,
			],
			true
		);
		$next_state    = $is_refresh
			? $previous_state
			: (
				$is_failed
					? WPML_Single_Url_Cache_Entry::STATE_FAILED
					: WPML_Single_Url_Cache_Entry::STATE_PENDING
			);
		$delays        = [ 60, 300, 1800, 7200, DAY_IN_SECONDS ];
		$delay         = $delays[ min( $attempt_count - 1, count( $delays ) - 1 ) ];
		$wpdb          = $this->wpdb;
		$next_attempt  = gmdate( 'Y-m-d H:i:s', time() + $delay );
		$now           = current_time( 'mysql', true );
		$this->without_errors(
			function() use ( $wpdb, $next_state, $is_refresh, $next_attempt, $now, $key_hex, $lease_token_hex ) {
				return $wpdb->query(
					$wpdb->prepare(
						"UPDATE {$wpdb->prefix}icl_url_resolution_cache
						SET state = %s, lease_token = NULL, lease_until = NULL,
							refresh_requested = %d, next_attempt_at = %s, updated_at = %s
						WHERE cache_key = UNHEX(%s) AND lease_token = UNHEX(%s)",
						$next_state,
						$is_refresh ? 1 : 0,
						$next_attempt,
						$now,
						$key_hex,
						$lease_token_hex
					)
				);
			}
		);
	}

	public function has_pending( $generation ) {
		if ( ! $this->can_query() ) {
			return false;
		}

		$wpdb = $this->wpdb;
		return (bool) $this->without_errors(
			function() use ( $wpdb, $generation ) {
				return $wpdb->get_var(
					$wpdb->prepare(
						"SELECT 1 FROM {$wpdb->prefix}icl_url_resolution_cache
						WHERE generation = %d
							AND (state = %s OR refresh_requested = 1)
						LIMIT 1",
						$generation,
						WPML_Single_Url_Cache_Entry::STATE_PENDING
					)
				);
			}
		);
	}

	public function count_unfinished( $generation ) {
		if ( ! $this->can_query() ) {
			return 0;
		}

		$wpdb = $this->wpdb;
		return (int) $this->without_errors(
			function() use ( $wpdb, $generation ) {
				return $wpdb->get_var(
					$wpdb->prepare(
						"SELECT COUNT(*) FROM {$wpdb->prefix}icl_url_resolution_cache
						WHERE generation = %d
							AND (state = %s OR state = %s OR refresh_requested = 1)",
						$generation,
						WPML_Single_Url_Cache_Entry::STATE_PENDING,
						WPML_Single_Url_Cache_Entry::STATE_FAILED
					)
				);
			}
		);
	}

	public function next_work_timestamp( $generation ) {
		if ( ! $this->can_query() ) {
			return null;
		}

		$wpdb = $this->wpdb;
		$value = $this->without_errors(
			function() use ( $wpdb, $generation ) {
				return $wpdb->get_var(
					$wpdb->prepare(
						"SELECT MIN(
							CASE
								WHEN refresh_requested = 1 THEN COALESCE(next_attempt_at, updated_at)
								ELSE next_attempt_at
							END
						)
						FROM {$wpdb->prefix}icl_url_resolution_cache
						WHERE generation = %d
							AND (state = %s OR state = %s OR refresh_requested = 1)",
						$generation,
						WPML_Single_Url_Cache_Entry::STATE_PENDING,
						WPML_Single_Url_Cache_Entry::STATE_FAILED
					)
				);
			}
		);

		if ( ! $value ) {
			return null;
		}

		$timestamp = strtotime( $value . ' UTC' );
		return $timestamp ? $timestamp : null;
	}

	public function seed_generation( $old_generation, $new_generation, $cursor, $limit ) {
		if ( ! $this->can_query() ) {
			return [ 'inserted' => 0, 'cursor' => $cursor, 'done' => true ];
		}

		$wpdb  = $this->wpdb;
		$limit = max( 1, (int) $limit );
		$after = is_string( $cursor )
			&& preg_match( '/^(\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2})\|([0-9A-Fa-f]{64})$/', $cursor, $parts )
			? [ $parts[1], $parts[2] ]
			: null;
		$rows  = $this->without_errors(
			function() use ( $wpdb, $old_generation, $after, $limit ) {
				if ( null === $after ) {
					return $wpdb->get_results(
						$wpdb->prepare(
							"SELECT HEX(cache_key) AS cache_key, source_url, source_language, target_language, updated_at
							FROM {$wpdb->prefix}icl_url_resolution_cache
							WHERE generation = %d
							ORDER BY updated_at DESC, cache_key DESC
							LIMIT %d",
							$old_generation,
							$limit
						),
						ARRAY_A
					);
				}

				return $wpdb->get_results(
					$wpdb->prepare(
						"SELECT HEX(cache_key) AS cache_key, source_url, source_language, target_language, updated_at
						FROM {$wpdb->prefix}icl_url_resolution_cache
						WHERE generation = %d
							AND ( updated_at < %s OR ( updated_at = %s AND cache_key < UNHEX(%s) ) )
						ORDER BY updated_at DESC, cache_key DESC
						LIMIT %d",
						$old_generation,
						$after[0],
						$after[0],
						$after[1],
						$limit
					),
					ARRAY_A
				);
			}
		);

		if ( ! is_array( $rows ) || ! $rows ) {
			return [ 'inserted' => 0, 'cursor' => $cursor, 'done' => true ];
		}

		$inserted = 0;
		foreach ( $rows as $row ) {
			$key = WPML_Single_Url_Cache_Key::build(
				get_current_blog_id(),
				$new_generation,
				$row['source_url'],
				$row['source_language'],
				$row['target_language']
			);
			if (
				$this->ensure_pending(
					[
						'cache_key'       => $key,
						'url_hash'        => WPML_Single_Url_Cache_Key::url_hash( $row['source_url'] ),
						'generation'      => $new_generation,
						'source_url'      => $row['source_url'],
						'source_language' => $row['source_language'],
						'target_language' => $row['target_language'],
					]
				)
			) {
				++$inserted;
			}
		}

		$last = end( $rows );

		return [
			'inserted' => $inserted,
			'cursor'   => $last['updated_at'] . '|' . $last['cache_key'],
			'done'     => count( $rows ) < $limit,
		];
	}

	public function has_rows( $generation ) {
		if ( ! $this->can_query() ) {
			return false;
		}

		$wpdb = $this->wpdb;
		return (bool) $this->without_errors(
			function() use ( $wpdb, $generation ) {
				return $wpdb->get_var(
					$wpdb->prepare(
						"SELECT 1 FROM {$wpdb->prefix}icl_url_resolution_cache WHERE generation = %d LIMIT 1",
						$generation
					)
				);
			}
		);
	}

	public function count_generation( $generation ) {
		if ( ! $this->can_query() ) {
			return 0;
		}

		$wpdb = $this->wpdb;
		return (int) $this->without_errors(
			function() use ( $wpdb, $generation ) {
				return $wpdb->get_var(
					$wpdb->prepare( "SELECT COUNT(*) FROM {$wpdb->prefix}icl_url_resolution_cache WHERE generation = %d", $generation )
				);
			}
		);
	}

	public function status( $generation ) {
		$counts = array_fill_keys(
			[
				WPML_Single_Url_Cache_Entry::STATE_POSITIVE,
				WPML_Single_Url_Cache_Entry::STATE_ROUTE,
				WPML_Single_Url_Cache_Entry::STATE_MISSING_TRANSLATION,
				WPML_Single_Url_Cache_Entry::STATE_NEGATIVE,
				WPML_Single_Url_Cache_Entry::STATE_PENDING,
				WPML_Single_Url_Cache_Entry::STATE_FAILED,
			],
			0
		);

		if ( ! $this->can_query() ) {
			return [ 'available' => false, 'counts' => $counts, 'oldest_pending' => null ];
		}

		$wpdb = $this->wpdb;
		$rows = $this->without_errors(
			function() use ( $wpdb, $generation ) {
				return $wpdb->get_results(
					$wpdb->prepare(
						"SELECT state, COUNT(*) AS total
						FROM {$wpdb->prefix}icl_url_resolution_cache
						WHERE generation = %d
						GROUP BY state",
						$generation
					),
					ARRAY_A
				);
			}
		);

		if ( is_array( $rows ) ) {
			foreach ( $rows as $row ) {
				if ( array_key_exists( $row['state'], $counts ) ) {
					$counts[ $row['state'] ] = (int) $row['total'];
				}
			}
		}

		return [
			'available'      => $this->is_available(),
			'counts'         => $counts,
			'oldest_pending' => $this->without_errors(
				function() use ( $wpdb, $generation ) {
					return $wpdb->get_var(
						$wpdb->prepare(
							"SELECT MIN(created_at) FROM {$wpdb->prefix}icl_url_resolution_cache
							WHERE generation = %d AND state = %s",
							$generation,
							WPML_Single_Url_Cache_Entry::STATE_PENDING
						)
					);
				}
			),
		];
	}

	public function cleanup( $generation, $limit = 500 ) {
		if ( ! $this->can_query() ) {
			return [ 'cache_keys' => [], 'more' => false ];
		}

		$limit       = max( 1, (int) $limit );
		$cache_keys  = $this->cleanup_keys( $generation, $limit );
		$remaining   = $limit - count( $cache_keys );
		$current_rows = $this->count_rows();

		if ( $remaining > 0 && $current_rows > $this->max_rows ) {
			$cache_keys = array_merge(
				$cache_keys,
				$this->cleanup_keys( null, min( $remaining, $current_rows - $this->max_rows ) )
			);
		}

		return [
			'cache_keys' => array_values( array_unique( $cache_keys ) ),
			'more'       => $this->has_older_generation( $generation )
				|| $this->count_rows() > $this->max_rows,
		];
	}

	public function cleanup_during_rebuild( $source_generation, $target_generation, $limit = 500 ) {
		if ( ! $this->can_query() ) {
			return [ 'cache_keys' => [], 'more' => false ];
		}

		$source_generation = (int) $source_generation;
		$target_generation = (int) $target_generation;
		$limit             = max( 1, (int) $limit );
		$wpdb              = $this->wpdb;
		$now               = current_time( 'mysql', true );
		$cache_keys        = $this->delete_selected_keys(
			$wpdb->prepare(
				"SELECT HEX(cache_key) FROM {$wpdb->prefix}icl_url_resolution_cache
				WHERE generation < %d
					AND generation <> %d
					AND (lease_until IS NULL OR lease_until < %s)
				LIMIT %d",
				$target_generation,
				$source_generation,
				$now,
				$limit
			),
			$now
		);
		$remaining         = $limit - count( $cache_keys );
		$outside           = $this->count_rows_outside( $source_generation );
		$overflow          = [];

		if ( $remaining > 0 && $outside > $this->max_rows ) {
			$overflow = $this->delete_selected_keys(
				$wpdb->prepare(
					"SELECT HEX(cache_key) FROM {$wpdb->prefix}icl_url_resolution_cache
					WHERE generation <> %d
						AND (lease_until IS NULL OR lease_until < %s)
					ORDER BY updated_at ASC
					LIMIT %d",
					$source_generation,
					$now,
					min( $remaining, $outside - $this->max_rows )
				),
				$now
			);
		}

		return [
			'cache_keys' => array_values( array_unique( array_merge( $cache_keys, $overflow ) ) ),
			'more'       => $this->has_superseded_generation( $source_generation, $target_generation )
				|| $outside - count( $overflow ) > $this->max_rows,
		];
	}

	public function invalidate_object( $kind, $object_id ) {
		if ( ! $this->can_query() ) {
			return [];
		}

		$wpdb = $this->wpdb;
		$keys = $this->without_errors(
			function() use ( $wpdb, $kind, $object_id ) {
				return $wpdb->get_col(
					$wpdb->prepare(
						"SELECT HEX(cache_key)
						FROM {$wpdb->prefix}icl_url_resolution_cache
						WHERE (source_object_kind = %s AND source_object_id = %d)
							OR (source_object_kind = %s AND translated_object_id = %d)
						LIMIT 501",
						$kind,
						$object_id,
						$kind,
						$object_id
					)
				);
			}
		);

		return $this->delete_keys( $keys );
	}

	public function invalidate_trid( $trid ) {
		if ( ! $this->can_query() ) {
			return [];
		}

		$wpdb = $this->wpdb;
		$keys = $this->without_errors(
			function() use ( $wpdb, $trid ) {
				return $wpdb->get_col(
					$wpdb->prepare(
						"SELECT HEX(cache_key)
						FROM {$wpdb->prefix}icl_url_resolution_cache
						WHERE translation_trid = %d
						LIMIT 501",
						$trid
					)
				);
			}
		);

		return $this->delete_keys( $keys );
	}

	public function is_available() {
		$blog_id = get_current_blog_id();
		return ! isset( $this->available[ $blog_id ] ) || $this->available[ $blog_id ];
	}

	private function can_query() {
		return $this->is_available();
	}

	private function table() {
		return $this->wpdb->prefix . self::TABLE_NAME;
	}

	private function cleanup_keys( $generation, $limit ) {
		if ( $limit < 1 || ! $this->can_query() ) {
			return [];
		}

		$wpdb = $this->wpdb;
		$now  = current_time( 'mysql', true );
		$sql   = null === $generation
			? $this->wpdb->prepare(
				"SELECT HEX(cache_key) FROM {$wpdb->prefix}icl_url_resolution_cache
				WHERE (lease_until IS NULL OR lease_until < %s)
				ORDER BY updated_at ASC
				LIMIT %d",
				$now,
				$limit
			)
			: $this->wpdb->prepare(
				"SELECT HEX(cache_key) FROM {$wpdb->prefix}icl_url_resolution_cache
				WHERE generation < %d
					AND (lease_until IS NULL OR lease_until < %s)
				ORDER BY updated_at ASC
				LIMIT %d",
				$generation,
				$now,
				$limit
			);

		return $this->delete_selected_keys( $sql, $now );
	}

	private function delete_selected_keys( $sql, $now ) {
		$wpdb = $this->wpdb;
		$keys = $this->without_errors(
			function() use ( $sql ) {
				return $this->wpdb->get_col( $sql );
			}
		);

		if ( ! is_array( $keys ) || ! $keys || ! $this->can_query() ) {
			return [];
		}

		$keys = array_values( array_unique( array_map( 'strval', $keys ) ) );

		$result = $this->without_errors(
			function() use ( $wpdb, $keys, $now ) {
				return $wpdb->query(
					$wpdb->prepare(
						"DELETE FROM {$wpdb->prefix}icl_url_resolution_cache
						WHERE (lease_until IS NULL OR lease_until < %s)
							AND cache_key IN (" . implode( ', ', array_fill( 0, count( $keys ), 'UNHEX(%s)' ) ) . ')',
						$now,
						...$keys
					)
				);
			}
		);

		return false === $result || ! $this->can_query() ? [] : $keys;
	}

	private function has_older_generation( $generation ) {
		if ( ! $this->can_query() ) {
			return false;
		}

		$wpdb = $this->wpdb;
		return (bool) $this->without_errors(
			function() use ( $wpdb, $generation ) {
				return $wpdb->get_var(
					$wpdb->prepare(
						"SELECT 1 FROM {$wpdb->prefix}icl_url_resolution_cache WHERE generation < %d LIMIT 1",
						$generation
					)
				);
			}
		);
	}

	private function has_superseded_generation( $source_generation, $target_generation ) {
		if ( ! $this->can_query() ) {
			return false;
		}

		$wpdb = $this->wpdb;
		return (bool) $this->without_errors(
			function() use ( $wpdb, $source_generation, $target_generation ) {
				return $wpdb->get_var(
					$wpdb->prepare(
						"SELECT 1 FROM {$wpdb->prefix}icl_url_resolution_cache WHERE generation < %d AND generation <> %d LIMIT 1",
						$target_generation,
						$source_generation
					)
				);
			}
		);
	}

	private function count_rows_outside( $source_generation ) {
		if ( ! $this->can_query() ) {
			return 0;
		}

		$wpdb = $this->wpdb;
		return (int) $this->without_errors(
			function() use ( $wpdb, $source_generation ) {
				return $wpdb->get_var(
					$wpdb->prepare(
						"SELECT COUNT(*) FROM {$wpdb->prefix}icl_url_resolution_cache WHERE generation <> %d",
						$source_generation
					)
				);
			}
		);
	}

	private function count_rows() {
		if ( ! $this->can_query() ) {
			return 0;
		}

		$wpdb = $this->wpdb;

		return (int) $this->without_errors(
			function() use ( $wpdb ) {
				return $this->wpdb->get_var( "SELECT COUNT(*) FROM {$wpdb->prefix}icl_url_resolution_cache" );
			}
		);
	}

	private function without_errors( $callback ) {
		$blog_id      = get_current_blog_id();
		$old_suppress = $this->wpdb->suppress_errors( true );
		$this->wpdb->last_error = '';

		try {
			$result = $callback();
			if ( $this->wpdb->last_error ) {
				$this->available[ $blog_id ] = false;
			} else {
				$this->available[ $blog_id ] = true;
			}
		} finally {
			$this->wpdb->suppress_errors( $old_suppress );
		}

		return isset( $result ) ? $result : null;
	}

	private function nullable_int( $value ) {
		return null === $value || ! $value ? self::SQL_NULL : (string) (int) $value;
	}

	private function nullable_value( $value ) {
		return null === $value ? self::SQL_NULL : $value;
	}

	private function delete_keys( $keys ) {
		if ( ! is_array( $keys ) || ! $keys ) {
			return [];
		}

		$keys = array_values( array_unique( array_map( 'strval', $keys ) ) );
		if ( count( $keys ) > 500 ) {
			return $keys;
		}

		$wpdb = $this->wpdb;

		$result = $this->without_errors(
			function() use ( $wpdb, $keys ) {
				return $wpdb->query(
					$wpdb->prepare(
						"DELETE FROM {$wpdb->prefix}icl_url_resolution_cache WHERE cache_key IN ("
						. implode( ', ', array_fill( 0, count( $keys ), 'UNHEX(%s)' ) ) . ')',
						...$keys
					)
				);
			}
		);

		return false === $result ? [] : $keys;
	}
}
