<?php

class WPML_Custom_Field_Batch_Writer {

	const MAX_FIELDS_PER_BATCH = 100;
	const MAX_VALUE_BYTES      = 65535;
	const MAX_BATCH_BYTES      = 262144;
	const FALLBACK_LOG_PREFIX  = '[WPML][custom-field-batch-writer-safe-fallback]';

	private $wpdb;

	private $unavailable_context = [];

	private static $logged_fallbacks = [];

	public function __construct( $wpdb = null ) {
		if ( null === $wpdb ) {
			global $wpdb;
		}

		$this->wpdb = $wpdb;
	}

	public function is_available( $post_id_from, $post_id_to, array $meta_keys, $internal_write_tracker ) {
		$this->unavailable_context = [];

		$enabled = apply_filters(
			'wpml_sync_custom_fields_batch_writer_enabled',
			true,
			$post_id_from,
			$post_id_to,
			$meta_keys
		);

		if ( true !== $enabled ) {
			return $this->mark_unavailable( 'disabled_by_filter' );
		}

		if ( ! $this->wpdb || ! $this->wpdb->postmeta ) {
			return $this->mark_unavailable( 'database_unavailable' );
		}

		if ( $post_id_from <= 0 || $post_id_to <= 0 || $post_id_from === $post_id_to ) {
			return $this->mark_unavailable( 'invalid_post_pair' );
		}

		if ( wp_is_post_revision( $post_id_from ) || wp_is_post_revision( $post_id_to ) ) {
			return $this->mark_unavailable( 'revision' );
		}

		if ( has_filter( 'wpml_sync_custom_field_copied_value' ) ) {
			$context   = [ 'hook' => 'wpml_sync_custom_field_copied_value' ];
			$callbacks = $this->get_hook_callbacks( 'wpml_sync_custom_field_copied_value' );
			if ( $callbacks ) {
				$context['callback'] = $this->describe_callback( reset( $callbacks ) );
			}

			return $this->mark_unavailable( 'copied_value_filter', $context );
		}

		$metadata_hooks = [
			'add_post_metadata',
			'delete_post_metadata',
			'add_post_meta',
			'added_post_meta',
			'add_postmeta',
			'added_postmeta',
			'delete_post_meta',
			'deleted_post_meta',
			'delete_postmeta',
			'deleted_postmeta',
		];

		foreach ( $metadata_hooks as $hook_name ) {
			if ( ! $this->are_hook_callbacks_safe( $hook_name, $post_id_from, $post_id_to, $meta_keys, $internal_write_tracker ) ) {
				return false;
			}
		}

		return $this->are_hook_callbacks_safe(
			'wpml_after_copy_custom_field',
			$post_id_from,
			$post_id_to,
			$meta_keys,
			$internal_write_tracker
		);
	}

	public function maybe_log_safe_fallback( $post_id_from, $post_id_to, $configured_field_count ) {
		if ( ! $this->unavailable_context ) {
			return;
		}

		$event = array_merge(
			[
				'reason'                 => 'unknown',
				'blog_id'                => (int) get_current_blog_id(),
				'source_post_id'         => (int) $post_id_from,
				'first_target_post_id'   => (int) $post_id_to,
				'configured_field_count' => (int) $configured_field_count,
			],
			$this->unavailable_context
		);

		$dedupe_key = implode(
			'|',
			[
				$event['blog_id'],
				$event['source_post_id'],
				$event['reason'],
				isset( $event['hook'] ) ? $event['hook'] : '',
				isset( $event['callback'] ) ? $event['callback'] : '',
			]
		);

		if ( isset( self::$logged_fallbacks[ $dedupe_key ] ) ) {
			return;
		}

		self::$logged_fallbacks[ $dedupe_key ] = true;

		$wpml_debug_log_enabled = defined( 'WPML_DEBUG_LOG' ) && WPML_DEBUG_LOG;

		$should_log = apply_filters(
			'wpml_sync_custom_fields_batch_writer_log_safe_fallback',
			$wpml_debug_log_enabled,
			$event
		);

		if ( true !== $should_log ) {
			return;
		}

		$encoded_event = json_encode( $event, JSON_UNESCAPED_SLASHES | JSON_PARTIAL_OUTPUT_ON_ERROR );
		if ( ! is_string( $encoded_event ) ) {
			$encoded_event = '{"reason":"log encoding failed"}';
		}

		error_log( self::FALLBACK_LOG_PREFIX . ' ' . $encoded_event );
	}

	public function is_candidate( $post_id_to, $post_subtype, $meta_key, array $values_from, array $values_to, $was_dirty ) {
		if (
			$was_dirty
			|| '_thumbnail_id' === $meta_key
			|| '_icl_lang_duplicate_of' === $meta_key
			|| has_filter( 'wpml_sync_custom_field_copied_value' )
			|| 1 !== count( $values_from )
			|| 1 !== count( $values_to )
			|| ! is_string( $values_from[0] )
			|| ! is_string( $values_to[0] )
			|| $values_from[0] === $values_to[0]
			|| ! $this->is_canonical_raw_value( $values_from[0] )
			|| ! $this->is_canonical_raw_value( $values_to[0] )
			|| strlen( $values_from[0] ) > self::MAX_VALUE_BYTES
			|| strlen( $values_to[0] ) > self::MAX_VALUE_BYTES
			|| has_filter( "sanitize_post_meta_{$meta_key}" )
			|| ( $post_subtype && has_filter( "sanitize_post_meta_{$meta_key}_for_{$post_subtype}" ) )
		) {
			return false;
		}

		return true === apply_filters(
			'wpml_sync_custom_field_can_batch_replace',
			true,
			$post_id_to,
			$meta_key,
			$values_from,
			$values_to
		);
	}

	public function get_max_fields_per_batch() {
		return self::MAX_FIELDS_PER_BATCH;
	}

	private function is_canonical_raw_value( $raw_value ) {
		return (string) maybe_serialize( maybe_unserialize( $raw_value ) ) === $raw_value;
	}

	public function replace( $post_id_to, array $candidates ) {
		if ( count( $candidates ) < 2 || count( $candidates ) > self::MAX_FIELDS_PER_BATCH ) {
			return [];
		}

		$target_rows = $this->load_target_rows( $post_id_to, array_keys( $candidates ) );
		$eligible    = [];
		$batch_bytes = 0;

		foreach ( $candidates as $meta_key => $candidate ) {
			if (
				! isset( $target_rows[ $meta_key ] )
				|| 1 !== count( $target_rows[ $meta_key ] )
				|| $candidate['value_to'] !== $target_rows[ $meta_key ][0]['meta_value']
			) {
				continue;
			}

			$candidate_bytes = strlen( $meta_key ) + strlen( $candidate['value_from'] ) + strlen( $candidate['value_to'] );
			if ( $batch_bytes + $candidate_bytes > self::MAX_BATCH_BYTES ) {
				break;
			}

			$eligible[ $meta_key ] = [
				'meta_id'    => $target_rows[ $meta_key ][0]['meta_id'],
				'meta_key'   => $meta_key,
				'value_from' => $candidate['value_from'],
				'value_to'   => $candidate['value_to'],
			];
			$batch_bytes          += $candidate_bytes;
		}

		if ( count( $eligible ) < 2 ) {
			return [];
		}

		$rows = [];
		$args = [];
		foreach ( $eligible as $row ) {
			$rows[] = $rows
				? 'UNION ALL SELECT %d, %s, %s, %s'
				: 'SELECT %d AS meta_id, %s AS meta_key, %s AS old_value, %s AS new_value';
			$args[] = $row['meta_id'];
			$args[] = $row['meta_key'];
			$args[] = $row['value_to'];
			$args[] = $row['value_from'];
		}

		$query  = "UPDATE {$this->wpdb->postmeta} AS target";
		$query .= ' JOIN (' . implode( ' ', $rows ) . ') AS batch
			ON batch.meta_id = target.meta_id
			AND batch.meta_key = target.meta_key
			AND BINARY batch.old_value = BINARY target.meta_value
			SET target.meta_value = batch.new_value
			WHERE target.post_id = %d';
		$args[] = $post_id_to;

		$prepared = $this->prepare( $query, $args );
		$updated  = false !== $prepared
			? $this->wpdb->query( $prepared )
			: false;

		if ( false === $updated ) {
			return [];
		}

		wp_cache_delete( $post_id_to, 'post_meta' );
		wp_cache_set_posts_last_changed();

		$current_rows = $this->load_target_rows( $post_id_to, array_keys( $eligible ) );
		$successes    = [];

		foreach ( $eligible as $meta_key => $row ) {
			if (
				isset( $current_rows[ $meta_key ] )
				&& 1 === count( $current_rows[ $meta_key ] )
				&& $row['meta_id'] === $current_rows[ $meta_key ][0]['meta_id']
				&& $row['value_from'] === $current_rows[ $meta_key ][0]['meta_value']
			) {
				$successes[ $meta_key ] = [
					'meta_id'      => $row['meta_id'],
					'values_after' => [ $row['value_from'] ],
				];
			}
		}

		return $successes;
	}

	private function are_hook_callbacks_safe( $hook_name, $post_id_from, $post_id_to, array $meta_keys, $internal_write_tracker ) {
		foreach ( $this->get_hook_callbacks( $hook_name ) as $callback ) {
			if ( $this->callbacks_are_identical( $callback, $internal_write_tracker ) ) {
				continue;
			}

			$is_safe = $this->is_known_safe_callback( $hook_name, $callback );

			$is_safe = apply_filters(
				'wpml_sync_custom_fields_batch_writer_is_safe_callback',
				$is_safe,
				$hook_name,
				$callback,
				$post_id_from,
				$post_id_to,
				$meta_keys
			);

			if ( true !== $is_safe ) {
				$this->mark_unavailable(
					'unsafe_callback',
					[
						'hook'     => $hook_name,
						'callback' => $this->describe_callback( $callback ),
					]
				);

				return false;
			}
		}

		return true;
	}

	private function mark_unavailable( $reason, array $context = [] ) {
		$this->unavailable_context = array_merge( [ 'reason' => $reason ], $context );

		return false;
	}

	private function describe_callback( $callback ) {
		if ( is_string( $callback ) ) {
			return $this->sanitize_callback_label( $callback );
		}

		if ( is_array( $callback ) && 2 === count( $callback ) ) {
			$class = is_object( $callback[0] ) ? get_class( $callback[0] ) : (string) $callback[0];

			return $this->sanitize_callback_label( $class . '::' . (string) $callback[1] );
		}

		if ( $callback instanceof Closure ) {
			return 'Closure';
		}

		if ( is_object( $callback ) ) {
			return $this->sanitize_callback_label( get_class( $callback ) . '::__invoke' );
		}

		return gettype( $callback );
	}

	private function sanitize_callback_label( $label ) {
		if ( 0 === strpos( $label, 'class@anonymous' ) ) {
			return 'anonymous-class';
		}

		$label = preg_replace( '/[\x00-\x1F\x7F]/', '', $label );
		$label = is_string( $label ) ? $label : 'unknown';

		return substr( $label, 0, 200 );
	}

	private function is_known_safe_callback( $hook_name, $callback ) {
		if ( is_string( $callback ) && 'wp_cache_set_posts_last_changed' === $callback ) {
			return true;
		}

		if ( ! is_array( $callback ) || 2 !== count( $callback ) || ! is_object( $callback[0] ) ) {
			return false;
		}

		$method = $callback[1];

		if ( is_a( $callback[0], 'WPML_Post_Translation' ) && 'record_changed_post_meta' === $method ) {
			return true;
		}

		if (
			is_a( $callback[0], 'WPML_Custom_Columns' )
			&& 'forgetPreloadedDuplicateOf' === $method
		) {
			return true;
		}

		if (
			is_a( $callback[0], \WPML\CustomFieldTranslation\CopiedFieldResets::class )
			&& 'recordCopy' === $method
		) {
			return true;
		}

		return is_a( $callback[0], 'WPML_Media_Attachments_Duplication' )
			&& 'record_original_thumbnail_ids_and_sync' === $method;
	}

	private function get_hook_callbacks( $hook_name ) {
		global $wp_filter;

		if ( empty( $wp_filter[ $hook_name ] ) ) {
			return [];
		}

		$hook = $wp_filter[ $hook_name ];
		if ( is_object( $hook ) && isset( $hook->callbacks ) ) {
			$priorities = $hook->callbacks;
		} elseif ( is_array( $hook ) ) {
			$priorities = $hook;
		} else {
			return [];
		}

		$callbacks = [];
		foreach ( $priorities as $entries ) {
			foreach ( $entries as $entry ) {
				if ( isset( $entry['function'] ) && is_callable( $entry['function'] ) ) {
					$callbacks[] = $entry['function'];
				}
			}
		}

		return $callbacks;
	}

	private function callbacks_are_identical( $first, $second ) {
		return $first === $second;
	}

	private function load_target_rows( $post_id, array $meta_keys ) {
		if ( ! $meta_keys ) {
			return [];
		}

		$placeholders = implode( ', ', array_fill( 0, count( $meta_keys ), '%s' ) );
		$args         = array_merge( [ $post_id ], $meta_keys );
		$query        = $this->prepare(
			"SELECT meta_id, meta_key, meta_value FROM {$this->wpdb->postmeta} FORCE INDEX (post_id) WHERE post_id = %d AND meta_key IN ({$placeholders}) ORDER BY meta_id ASC",
			$args
		);

		if ( false === $query ) {
			return [];
		}

		$map  = [];
		$rows = $this->wpdb->get_results( $query, ARRAY_A );
		foreach ( $rows as $row ) {
			$map[ $row['meta_key'] ][] = [
				'meta_id'    => (int) $row['meta_id'],
				'meta_value' => (string) $row['meta_value'],
			];
		}

		return $map;
	}

	private function prepare( $query, array $args ) {
		$parameters = array_merge( [ $query ], $args );

		return call_user_func_array( [ $this->wpdb, 'prepare' ], $parameters );
	}
}
