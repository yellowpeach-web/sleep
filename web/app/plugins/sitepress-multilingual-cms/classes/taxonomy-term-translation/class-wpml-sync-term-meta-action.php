<?php

require_once __DIR__ . '/../custom-field-translation/RowSequenceReconciler.php';

use WPML\CustomFieldTranslation\RowSequenceReconciler;

class WPML_Sync_Term_Meta_Action {

	private $reconciler;

	private $sitepress;

	private $term_taxonomy_id;

	private $is_new_term;

	public function __construct( $sitepress, $term_taxonomy_id, $is_new_term = false ) {
		$this->sitepress        = $sitepress;
		$this->term_taxonomy_id = $term_taxonomy_id;
		$this->is_new_term      = $is_new_term;
		$this->reconciler       = new RowSequenceReconciler();
	}

	public function run() {
		$term_taxonomy_id_from = $this->sitepress->term_translations()->get_original_element( $this->term_taxonomy_id );

		if ( ! $term_taxonomy_id_from ) {
			$term_taxonomy_id_from = $this->term_taxonomy_id;
		}

		$translations = $this->sitepress
			->term_translations()
			->get_element_translations( $term_taxonomy_id_from, false, true );

		if ( ! empty( $translations ) ) {
			foreach ( $translations as $term_taxonomy_id_to ) {
				$this->copy_custom_fields( (int) $term_taxonomy_id_to, $term_taxonomy_id_from );
			}
		}
	}

	public function copy_keys_to_translations( array $meta_keys, $only_into_empty ) {
		$term_taxonomy_id_from = $this->sitepress->term_translations()->get_original_element( $this->term_taxonomy_id ) ?: $this->term_taxonomy_id;
		$term_id_from          = $this->sitepress->term_translations()->adjust_term_id_for_ttid( $term_taxonomy_id_from );
		$translations          = $this->sitepress->term_translations()->get_element_translations( $term_taxonomy_id_from, false, true );

		foreach ( array_map( 'intval', (array) $translations ) as $term_taxonomy_id_to ) {
			$term_id_to = $this->sitepress->term_translations()->adjust_term_id_for_ttid( $term_taxonomy_id_to );

			foreach ( $meta_keys as $meta_key ) {
				$meta_from = $this->sitepress->get_wp_api()->get_term_meta( $term_id_from, $meta_key );
				$meta_to   = $this->sitepress->get_wp_api()->get_term_meta( $term_id_to, $meta_key );

				if ( $only_into_empty && ! $this->is_empty_meta( $meta_to ) ) {
					continue;
				}

				if ( $meta_from || $meta_to ) {
					$this->sync_custom_field( $term_id_from, $term_id_to, $meta_key );
				}
			}
		}
	}

	private function is_empty_meta( $values ) {
		return ! array_filter(
			(array) $values,
			function ( $value ) {
				return '' !== $value && null !== $value;
			}
		);
	}

	private function copy_custom_fields( $term_taxonomy_id_to, $term_taxonomy_id_from ) {
		$cf_copy = array();

		$term_id_to   = $this->sitepress->term_translations()->adjust_term_id_for_ttid( $term_taxonomy_id_to );
		$term_id_from = $this->sitepress->term_translations()->adjust_term_id_for_ttid( $term_taxonomy_id_from );

		$setting_factory = $this->sitepress->core_tm()->settings_factory();
		$meta_keys       = array_unique( array_merge( $setting_factory->get_term_meta_keys(), $this->own_meta_keys_not_ignored( (int) $term_id_from ) ) );

		foreach ( $meta_keys as $meta_key ) {
			$meta_key_status = $setting_factory->term_meta_setting( $meta_key )->status();

			if ( WPML_COPY_CUSTOM_FIELD === $meta_key_status
				 || $this->should_copy_once( $meta_key_status, $term_taxonomy_id_to )
			) {
				$cf_copy[] = $meta_key;
			}
		}

		foreach ( $cf_copy as $meta_key ) {
			$meta_from = $this->sitepress->get_wp_api()->get_term_meta( $term_id_from, $meta_key );
			$meta_to   = $this->sitepress->get_wp_api()->get_term_meta( $term_id_to, $meta_key );
			if ( $meta_from || $meta_to ) {
				$this->sync_custom_field( $term_id_from, $term_id_to, $meta_key );
			}
		}
	}

	private function sync_custom_field(
		$term_id_from,
		$term_id_to,
		$meta_key
	) {
		$wpdb = $this->sitepress->wpdb();
		$values_from = $wpdb->get_col(
			$wpdb->prepare(
				"SELECT meta_value FROM {$wpdb->termmeta} WHERE term_id=%d AND meta_key=%s ORDER BY meta_id ASC",
				$term_id_from,
				$meta_key
			)
		);
		$values_to   = $wpdb->get_col(
			$wpdb->prepare(
				"SELECT meta_value FROM {$wpdb->termmeta} WHERE term_id=%d AND meta_key=%s ORDER BY meta_id ASC",
				$term_id_to,
				$meta_key
			)
		);

		if ( $values_from === $values_to ) {
			return;
		}

		$plan = $this->reconciler->plan(
			$values_from,
			$values_to,
			function ( $value ) {
				return null === $value;
			}
		);

		if ( $plan['rewrite'] ) {
			$wpdb->query(
				$wpdb->prepare(
					"DELETE FROM {$wpdb->termmeta} WHERE term_id=%d AND meta_key=%s",
					array( $term_id_to, $meta_key )
				)
			);
		} else {
			foreach ( array_unique( $plan['removed'] ) as $v ) {
				$wpdb->query(
					$wpdb->prepare(
						"DELETE FROM {$wpdb->termmeta}
												WHERE term_id=%d
												AND meta_key=%s
												AND meta_value=%s",
						array( $term_id_to, $meta_key, $v )
					)
				);
			}
		}

		foreach ( $plan['added_indices'] as $index ) {
			$wpdb->query( $wpdb->prepare(
				"INSERT INTO {$wpdb->termmeta}(term_id, meta_key, meta_value)
												VALUES(%d, %s, %s)",
				array( $term_id_to, $meta_key, $values_from[ $index ] )
			) );
		}

		wp_cache_delete( $term_id_to, 'term_meta' );

		do_action( 'wpml_after_copy_term_field', $term_id_from, $term_id_to, $meta_key );

		wp_cache_init();
	}

	private function own_meta_keys_not_ignored( $term_id ) {
		$term_meta = $this->sitepress->get_wp_api()->get_term_meta( $term_id );
		if ( ! is_array( $term_meta ) || ! $term_meta ) {
			return array();
		}
		$meta_keys    = array_map( 'strval', array_keys( $term_meta ) );
		$ignored_keys = array_keys( wpml_resolve_custom_field_preferences( $meta_keys, 'term' ), WPML_IGNORE_CUSTOM_FIELD, true );

		return array_values( array_diff( $meta_keys, array_map( 'strval', $ignored_keys ) ) );
	}

	private function should_copy_once( $meta_key_status, $term_taxonomy_id_to ) {
		return $this->is_new_term
			   && WPML_COPY_ONCE_CUSTOM_FIELD === $meta_key_status
			   && $term_taxonomy_id_to === $this->term_taxonomy_id;
	}
}
