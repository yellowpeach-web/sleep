<?php

require_once __DIR__ . '/class-wpml-custom-field-batch-writer.php';
require_once __DIR__ . '/RowSequenceReconciler.php';

use WPML\CustomFieldTranslation\RowSequenceReconciler;
use WPML\TM\Settings\MenuItemMetaBoundary;

class WPML_Sync_Custom_Fields {

	private $element_factory;

	private $fields_to_sync;

	private $fields_provider;

	private $dirty_keys = [];

	private $batch_writer;

	private $reconciler;

	private $preference_resolver;

	public function __construct( WPML_Translation_Element_Factory $element_factory, $fields_to_sync, ?WPML_Custom_Field_Batch_Writer $batch_writer = null, ?callable $preference_resolver = null ) {
		$this->element_factory = $element_factory;
		if ( is_array( $fields_to_sync ) ) {
			$this->fields_to_sync = $fields_to_sync;
		} else {
			$this->fields_provider = $fields_to_sync;
		}
		$this->batch_writer = $batch_writer ?: new WPML_Custom_Field_Batch_Writer();
		$this->reconciler   = new RowSequenceReconciler();

		$this->preference_resolver = $preference_resolver ?: 'wpml_resolve_custom_field_preferences';
	}

	private function get_fields_to_sync() {
		if ( null === $this->fields_to_sync ) {
			$this->fields_to_sync = $this->fields_provider ? (array) call_user_func( $this->fields_provider ) : [];
		}

		return $this->fields_to_sync;
	}

	private function get_fields_to_sync_to( $post_id_to ) {
		$fields     = $this->get_fields_to_sync();
		$structural = array_filter( $fields, [ MenuItemMetaBoundary::class, 'isWordPressStructureKey' ] );
		if ( $structural && 'nav_menu_item' === get_post_type( $post_id_to ) ) {
			return array_values( array_diff( $fields, $structural ) );
		}

		return $fields;
	}

	private function may_copy_to( $post_id_to, $meta_key ) {
		return ! MenuItemMetaBoundary::isWordPressStructureKey( $meta_key )
			|| 'nav_menu_item' !== get_post_type( $post_id_to );
	}

	public function sync_to_translations( $post_id_from, $meta_key ) {
		if ( $this->is_copied( (string) $meta_key ) ) {
			$post_element = $this->element_factory->create( $post_id_from, 'post' );
			$translations = $post_element->get_translations();

			foreach ( $translations as $translation ) {
				$translation_id = $translation->get_element_id();
				if ( $translation_id !== $post_id_from ) {
					$this->sync_custom_field( $post_id_from, $translation_id, $meta_key );
				}
			}
		}
	}

	private function is_copied( string $meta_key ): bool {
		if ( in_array( $meta_key, $this->get_fields_to_sync(), true ) ) {
			return true;
		}

		$resolved_preferences = call_user_func( $this->preference_resolver, [ $meta_key ] );

		return WPML_COPY_CUSTOM_FIELD === ( $resolved_preferences[ $meta_key ] ?? null );
	}

	public function sync_all_custom_fields( $post_id_from ) {
		foreach ( $this->get_fields_to_sync() as $meta_key ) {
			$this->sync_to_translations( $post_id_from, $meta_key );
		}
	}

	public function sync_custom_field( $post_id_from, $post_id_to, $meta_key ) {
		if ( ! $this->may_copy_to( $post_id_to, $meta_key ) ) {
			return;
		}

		$custom_fields_from = get_post_meta( $post_id_from );
		$custom_fields_to   = get_post_meta( $post_id_to );

		$values_from = isset( $custom_fields_from[ $meta_key ] ) ? (array) $custom_fields_from[ $meta_key ] : [];
		$values_to   = isset( $custom_fields_to[ $meta_key ] ) ? (array) $custom_fields_to[ $meta_key ] : [];

		$this->sync_custom_field_values( $post_id_from, $post_id_to, $meta_key, $values_from, $values_to );
	}

	public function sync_custom_fields_batch( $post_id_from, $post_id_to, $custom_fields_from = null, $custom_fields_to = null, $meta_keys_to_sync = null ) {
		$meta_keys_to_sync  = is_array( $meta_keys_to_sync ) || null === $meta_keys_to_sync ? $meta_keys_to_sync : null;
		$sync_all           = null === $meta_keys_to_sync;
		$meta_key_allowlist = null === $meta_keys_to_sync ? [] : array_fill_keys( $meta_keys_to_sync, true );

		$fields_to_sync = $this->get_fields_to_sync_to( $post_id_to );
		if ( ! $fields_to_sync ) {
			return [];
		}
		if ( ! $sync_all ) {
			$configured_meta_keys = array_fill_keys( $fields_to_sync, true );
			if ( ! array_intersect_key( $configured_meta_keys, $meta_key_allowlist ) ) {
				return [];
			}
		}

		if ( ! is_array( $custom_fields_from ) ) {
			$custom_fields_from = get_post_meta( $post_id_from );
			$custom_fields_from = is_array( $custom_fields_from ) ? $custom_fields_from : [];
		}
		if ( ! is_array( $custom_fields_to ) ) {
			$custom_fields_to = get_post_meta( $post_id_to );
			$custom_fields_to = is_array( $custom_fields_to ) ? $custom_fields_to : [];
		}

		$this->dirty_keys = [];
		$track_write      = function ( $unused_meta_id, $object_id, $meta_key ) use ( $post_id_from, $post_id_to ) {
			if ( (int) $object_id === (int) $post_id_from || (int) $object_id === (int) $post_id_to ) {
				$object_id = (int) $object_id;
				$meta_key  = (string) $meta_key;
				$version   = isset( $this->dirty_keys[ $object_id ][ $meta_key ] )
					? $this->dirty_keys[ $object_id ][ $meta_key ]
					: 0;

				$this->dirty_keys[ $object_id ][ $meta_key ] = $version + 1;
			}
		};
		add_action( 'added_post_meta', $track_write, 10, 3 );
		add_action( 'updated_post_meta', $track_write, 10, 3 );
		add_action( 'deleted_post_meta', $track_write, 10, 3 );

		$processed_keys = [];
		$pending_batch  = [];

		try {
			$post_subtype       = (string) get_post_type( $post_id_to );
			$batch_is_available = $this->batch_writer->is_available(
				$post_id_from,
				$post_id_to,
				$fields_to_sync,
				$track_write
			);

			do {
				foreach ( $fields_to_sync as $meta_key ) {
					if ( isset( $processed_keys[ $meta_key ] ) ) {
						continue;
					}

					$is_source_dirty = isset( $this->dirty_keys[ (int) $post_id_from ][ $meta_key ] );
					$is_target_dirty = isset( $this->dirty_keys[ (int) $post_id_to ][ $meta_key ] );
					$is_dirty        = $is_source_dirty || $is_target_dirty;
					if ( ! $sync_all && ! isset( $meta_key_allowlist[ $meta_key ] ) && ! $is_dirty ) {
						continue;
					}

					$values_from = isset( $custom_fields_from[ $meta_key ] ) ? (array) $custom_fields_from[ $meta_key ] : [];
					$values_to   = isset( $custom_fields_to[ $meta_key ] ) ? (array) $custom_fields_to[ $meta_key ] : [];

					if ( isset( $this->dirty_keys[ (int) $post_id_from ][ $meta_key ] ) ) {
						$values_from = $this->get_raw_meta_values( $post_id_from, $meta_key );
					}
					if ( isset( $this->dirty_keys[ (int) $post_id_to ][ $meta_key ] ) ) {
						$values_to = $this->get_raw_meta_values( $post_id_to, $meta_key );
					}

					if ( ! $values_from && ! $values_to && $pending_batch ) {
						$this->flush_pending_batch( $post_id_from, $post_id_to, $pending_batch );
						$values_from = $this->get_raw_meta_values( $post_id_from, $meta_key );
						$values_to   = $this->get_raw_meta_values( $post_id_to, $meta_key );
					}

					$processed_keys[ $meta_key ] = true;
					if ( ! $values_from && ! $values_to ) {
						continue;
					}

					$source_dirty_version = $this->get_dirty_key_version( $post_id_from, $meta_key );
					$target_dirty_version = $this->get_dirty_key_version( $post_id_to, $meta_key );
					$was_dirty            = $source_dirty_version > 0 || $target_dirty_version > 0;

					if (
						$batch_is_available
						&& $this->batch_writer->is_candidate(
							$post_id_to,
							$post_subtype,
							$meta_key,
							$values_from,
							$values_to,
							$was_dirty
						)
					) {
						$pending_batch[ $meta_key ] = [
							'value_from'           => $values_from[0],
							'value_to'             => $values_to[0],
							'source_dirty_version' => $source_dirty_version,
							'target_dirty_version' => $target_dirty_version,
						];

						if ( count( $pending_batch ) >= $this->batch_writer->get_max_fields_per_batch() ) {
							$this->flush_pending_batch( $post_id_from, $post_id_to, $pending_batch );
						}
						continue;
					}

					$this->flush_pending_batch( $post_id_from, $post_id_to, $pending_batch );
					if (
						! $batch_is_available
						&& (
							array_diff( $values_from, $values_to )
							|| array_diff( $values_to, $values_from )
						)
					) {
						$this->batch_writer->maybe_log_safe_fallback(
							$post_id_from,
							$post_id_to,
							count( $fields_to_sync )
						);
					}
					$this->sync_custom_field_values( $post_id_from, $post_id_to, $meta_key, $values_from, $values_to );
				}

				$this->flush_pending_batch( $post_id_from, $post_id_to, $pending_batch );

				$has_pending_dirty_key = false;
				foreach ( $fields_to_sync as $meta_key ) {
					if ( isset( $processed_keys[ $meta_key ] ) ) {
						continue;
					}
					$is_source_dirty = isset( $this->dirty_keys[ (int) $post_id_from ][ $meta_key ] );
					$is_target_dirty = isset( $this->dirty_keys[ (int) $post_id_to ][ $meta_key ] );
					if ( $is_source_dirty || $is_target_dirty ) {
						$has_pending_dirty_key = true;
						break;
					}
				}
			} while ( $has_pending_dirty_key );
		} finally {
			remove_action( 'added_post_meta', $track_write );
			remove_action( 'updated_post_meta', $track_write );
			remove_action( 'deleted_post_meta', $track_write );
			$this->dirty_keys = [];
		}

		return array_keys( $processed_keys );
	}

	private function get_raw_meta_values( $post_id, $meta_key ) {
		global $wpdb;

		return $wpdb->get_col(
			$wpdb->prepare(
				"SELECT meta_value FROM {$wpdb->postmeta} WHERE post_id = %d AND meta_key = %s ORDER BY meta_id ASC",
				$post_id,
				$meta_key
			)
		);
	}

	private function get_dirty_key_version( $post_id, $meta_key ) {
		$post_id = (int) $post_id;

		return isset( $this->dirty_keys[ $post_id ][ $meta_key ] )
			? (int) $this->dirty_keys[ $post_id ][ $meta_key ]
			: 0;
	}

	private function flush_pending_batch( $post_id_from, $post_id_to, array &$pending_batch ) {
		if ( ! $pending_batch ) {
			return;
		}

		$successful = count( $pending_batch ) > 1
			? $this->batch_writer->replace( $post_id_to, $pending_batch )
			: [];

		if ( $successful ) {
			do_action(
				'wpml_after_batch_copy_custom_fields',
				$post_id_from,
				$post_id_to,
				array_keys( $successful ),
				$successful
			);
		}

		foreach ( $pending_batch as $meta_key => $candidate ) {
			$source_was_dirtied = $candidate['source_dirty_version'] !== $this->get_dirty_key_version( $post_id_from, $meta_key );
			$target_was_dirtied = $candidate['target_dirty_version'] !== $this->get_dirty_key_version( $post_id_to, $meta_key );

			if ( isset( $successful[ $meta_key ] ) && ! $source_was_dirtied && ! $target_was_dirtied ) {
				$this->notify_custom_field_synced(
					$post_id_from,
					$post_id_to,
					$meta_key,
					$successful[ $meta_key ]['values_after']
				);
				continue;
			}

			$this->sync_custom_field_values(
				$post_id_from,
				$post_id_to,
				$meta_key,
				$this->get_raw_meta_values( $post_id_from, $meta_key ),
				$this->get_raw_meta_values( $post_id_to, $meta_key )
			);
		}

		$pending_batch = [];
	}

	private function sync_custom_field_values( $post_id_from, $post_id_to, $meta_key, array $values_from, array $values_to ) {
		$values_from = array_values( $values_from );
		$values_to   = array_values( $values_to );

		if ( $values_from === $values_to ) {
			return;
		}

		$must_rewrite_removal = function ( $raw_value ) {
			$value = maybe_unserialize( $raw_value );

			return '' === $value || null === $value || false === $value;
		};

		$plan = $this->reconciler->plan( $values_from, $values_to, $must_rewrite_removal );
		$args = [
			'values_from' => $values_from,
			'values_to'   => $values_to,
			'removed'     => $plan['removed'],
			'added'       => $this->values_at_indices( $values_from, $plan['added_indices'] ),
		];

		$destination_values = $values_from;
		$copied_values      = [];
		$filtered_indices   = [];
		do {
			$indices_to_filter = array_diff( $plan['added_indices'], $filtered_indices );
			foreach ( $indices_to_filter as $index ) {
				$copied_value                 = $this->filter_copied_value( $values_from[ $index ], $post_id_from, $post_id_to, $meta_key, $args );
				$copied_values[ $index ]      = $copied_value;
				$destination_values[ $index ] = (string) maybe_serialize( $copied_value );
				$filtered_indices[]           = $index;
			}

			$plan = $this->reconciler->plan( $destination_values, $values_to, $must_rewrite_removal );
		} while ( $indices_to_filter );

		$empty_like_removals = [];
		foreach ( array_unique( $plan['removed'] ) as $v ) {
			$value_to_delete = maybe_unserialize( $v );
			if ( '' === $value_to_delete || null === $value_to_delete || false === $value_to_delete ) {
				$empty_like_removals[] = $value_to_delete;
				continue;
			}
			delete_post_meta( $post_id_to, $meta_key, $value_to_delete );
		}
		foreach ( $empty_like_removals as $value_to_delete ) {
			delete_post_meta( $post_id_to, $meta_key, $value_to_delete );
		}

		foreach ( $plan['added_indices'] as $index ) {
			$copied_value = array_key_exists( $index, $copied_values )
				? $copied_values[ $index ]
				: $this->filter_copied_value( $values_from[ $index ], $post_id_from, $post_id_to, $meta_key, $args );

			add_post_meta( $post_id_to, $meta_key, wp_slash( $copied_value ) );
		}

		$values_after = $this->get_raw_meta_values( $post_id_to, $meta_key );

		$this->notify_custom_field_synced( $post_id_from, $post_id_to, $meta_key, $values_after );
	}

	private function values_at_indices( array $values, array $indices ) {
		$selected = [];
		foreach ( $indices as $index ) {
			$selected[] = $values[ $index ];
		}

		return $selected;
	}

	private function filter_copied_value( $raw_value, $post_id_from, $post_id_to, $meta_key, array $args ) {
		$copied_value = maybe_unserialize( $raw_value );

		return apply_filters( 'wpml_sync_custom_field_copied_value', $copied_value, $post_id_from, $post_id_to, $meta_key, $args );
	}

	private function notify_custom_field_synced( $post_id_from, $post_id_to, $meta_key, array $values_after ) {
		do_action( 'wpml_after_copy_custom_field', $post_id_from, $post_id_to, $meta_key, $values_after );
	}
}
