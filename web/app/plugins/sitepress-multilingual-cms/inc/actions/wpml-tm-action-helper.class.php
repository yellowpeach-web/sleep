<?php

use WPML\TM\Settings\ChecksumFieldHistory;
use WPML\TM\Settings\LegacyChecksumOrders;

class WPML_TM_Action_Helper {

	private $legacy_checksum_orders;

	private $checksum_field_history;

	private $earlier_set_checksums = array();

	private $translatable_memo = null;

	private $post_fields_memo = null;

	private $custom_field_scope = null;

	private $older_forms = null;

	private $walk_checksums = null;

	public function __construct(
		?LegacyChecksumOrders $legacy_checksum_orders = null,
		?ChecksumFieldHistory $checksum_field_history = null
	) {
		$this->legacy_checksum_orders = $legacy_checksum_orders;
		$this->checksum_field_history = $checksum_field_history;
	}

	public function get_tm_instance() {

		return wpml_load_core_tm();
	}

	public function create_translation_package( $post ) {
		$package_helper = new WPML_Element_Translation_Package();

		return $package_helper->create_translation_package( $post );
	}

	public function add_translation_job( $rid, $translator_id, $translation_package, $batch_options = array(), $sendFrom = null, $addJobLogs = false, $notify = true ) {

		return $this->get_update_translation_action( $translation_package )
					->add_translation_job( $rid, $translator_id, $translation_package, $batch_options, $sendFrom, $addJobLogs, $notify );
	}

	public function post_md5( $post ) {
		$post = $this->resolve_post( $post );

		if ( ! $post ) {
			return '';
		}

		if ( $this->is_external_element( $post ) ) {
			return md5( $this->filter_post_key( $this->external_element_key( $post ), $post ) );
		}

		$this->checksum_field_history()->observe(
			function () {
				return array_keys( $this->translatable_custom_fields() );
			}
		);

		return $this->checksum_over( $post, array_keys( $this->post_checksum_fields( $post ) ) );
	}

	private function checksum_over( $post, array $names ) {
		$custom_fields_values = $this->without_empty_values( $this->get_post_custom_field_values( $post, $names ) );

		ksort( $custom_fields_values, SORT_STRING );

		return md5(
			$this->filter_post_key(
				$this->build_post_key( $this->post_key_parts( $post ), $custom_fields_values ),
				$post
			)
		);
	}

	public function post_md5_matches( $post, $md5, $current = null ) {
		if ( ! is_string( $md5 ) || '' === $md5 ) {
			return false;
		}

		$post = $this->resolve_post( $post );
		if ( ! $post ) {
			return false;
		}

		if ( ! is_string( $current ) || '' === $current ) {
			$current = $this->post_md5( $post );
		}
		if ( $current === $md5 ) {
			return true;
		}

		if ( $this->is_external_element( $post ) ) {
			return false;
		}

		$key = $this->older_forms_key( $post, $current );
		if ( null === $this->older_forms || $key !== $this->older_forms['key'] ) {
			$this->older_forms = array(
				'key'     => $key,
				'matched' => array(),
				'walked'  => null,
			);
		}
		if ( isset( $this->older_forms['matched'][ $md5 ] ) ) {
			return true;
		}
		if ( ! array_key_exists( 'narrow', $this->older_forms ) ) {
			$this->older_forms['narrow'] = $this->checksum_without_resolved_fields( $post );
		}
		if ( $md5 === $this->older_forms['narrow'] ) {
			return true;
		}
		if ( null !== $this->older_forms['walked'] ) {
			return isset( $this->older_forms['walked'][ $md5 ] ) || $this->matches_an_earlier_set( $post, $md5, $current );
		}

		$outer_scope              = $this->custom_field_scope;
		$outer_checksums          = $this->walk_checksums;
		$this->custom_field_scope = array(
			'post'   => (int) $post->ID,
			'values' => array(),
		);
		$this->walk_checksums     = array();
		try {
			$found  = $this->matches_a_legacy_form( $post, $md5 );
			$walked = $this->walk_checksums;
		} finally {
			$this->custom_field_scope = $outer_scope;
			$this->walk_checksums     = $outer_checksums;
		}

		if ( null === $this->older_forms || $key !== $this->older_forms['key'] ) {
			$this->older_forms = array(
				'key'     => $key,
				'matched' => array(),
				'walked'  => null,
			);
		}
		if ( $found ) {
			$this->older_forms['matched'][ $md5 ] = true;

			return true;
		}
		$this->older_forms['walked'] = $walked;

		return $this->matches_an_earlier_set( $post, $md5, $current );
	}

	private function older_forms_key( $post, $current ) {
		return get_current_blog_id() . ':' . $post->ID . ':' . $current . ':' . md5( implode( "\0", array_keys( $this->translatable_custom_fields() ) ) );
	}

	private function matches_a_legacy_form( $post, $md5 ) {
		$values = $this->get_post_custom_fields( $post );
		$names  = array_keys( $this->translatable_custom_fields() );
		$empty  = array_values(
			array_filter(
				$names,
				function ( $name ) use ( $values ) {
					return array_key_exists( $name, $values ) && $this->is_empty_value( $values[ $name ] );
				}
			)
		);

		if ( ! $empty && count( $values ) < 2 ) {
			return false;
		}

		$current_values = $this->read_custom_field_values( $post, $names );
		$order_values   = array();
		foreach ( $this->legacy_checksum_orders()->forNames( $names ) as $order ) {
			$order_values[] = $this->read_custom_field_values( $post, array_keys( $this->translatable_custom_fields( $order ) ) );
		}

		return $this->matches_a_legacy_field_set( $post, $md5, $current_values, $order_values, $empty );
	}

	protected function legacy_field_set_matches( $post, $md5, array $parts, array $forms ) {
		foreach ( $forms as $values_in_order ) {
			$legacy_key = $this->build_post_key( $parts, $this->filter_custom_field_values( $values_in_order, $post ) );
			$checksum   = md5( $this->filter_post_key( $legacy_key, $post ) );
			if ( null !== $this->walk_checksums ) {
				$this->walk_checksums[ $checksum ] = true;
			}
			if ( $checksum === $md5 ) {
				return true;
			}
		}

		return false;
	}

	private function matches_an_earlier_set( $post, $md5, $current ) {
		return in_array( $md5, $this->earlier_set_checksums( $post, $current ), true );
	}

	private function earlier_set_checksums( $post, $current ) {
		$memo_key = get_current_blog_id() . ':' . $post->ID . ':' . $current;
		if ( isset( $this->earlier_set_checksums[ $memo_key ] ) ) {
			return $this->earlier_set_checksums[ $memo_key ];
		}

		$sets = $this->checksum_field_history()->earlierSets(
			array_keys( $this->translatable_custom_fields() ),
			function ( $name ) use ( $post ) {
				return ! $this->is_empty_value( $this->read_custom_field( $post, $name ) );
			}
		);

		$checksums = array();
		if ( $sets ) {
			$parts = $this->post_key_parts( $post );
			foreach ( $sets as $set ) {
				$values = $this->get_post_custom_field_values( $post, $set );

				$filled = $this->without_empty_values( $values );
				ksort( $filled, SORT_STRING );
				ksort( $values, SORT_STRING );
				$forms = array( $filled, $values );

				foreach ( $this->legacy_checksum_orders()->forNames( $set ) as $order ) {
					$forms[] = $this->get_post_custom_field_values( $post, $order );
				}

				foreach ( $forms as $form ) {
					$checksums[] = md5( $this->filter_post_key( $this->build_post_key( $parts, $form ), $post ) );
				}
			}
		}

		$this->earlier_set_checksums[ $memo_key ] = array_values( array_unique( $checksums ) );

		return $this->earlier_set_checksums[ $memo_key ];
	}

	private function matches_a_legacy_field_set( $post, $md5, array $current_values, array $order_values, array $empty ) {
		$parts = $this->post_key_parts( $post );

		$forms = LegacyChecksumOrders::without( $order_values, array() ) ?: array( $current_values );
		if ( $this->legacy_field_set_matches( $post, $md5, $parts, $forms ) ) {
			return true;
		}

		$set  = $current_values;
		$kept = $order_values;
		foreach ( array_reverse( $empty ) as $name ) {
			unset( $set[ $name ] );
			foreach ( array_keys( $kept ) as $i ) {
				unset( $kept[ $i ][ $name ] );
			}
			$forms = LegacyChecksumOrders::without( $kept, array() ) ?: array( $set );
			if ( $this->legacy_field_set_matches( $post, $md5, $parts, $forms ) ) {
				return true;
			}
			unset( $forms );
		}

		foreach ( array_slice( $empty, 0, -1 ) as $name ) {
			$set = $current_values;
			unset( $set[ $name ] );
			$forms = LegacyChecksumOrders::without( $order_values, array( $name ) ) ?: array( $set );
			if ( $this->legacy_field_set_matches( $post, $md5, $parts, $forms ) ) {
				return true;
			}
		}

		return false;
	}

	private function without_empty_values( array $values ) {
		return array_filter(
			$values,
			function ( $value ) {
				return ! $this->is_empty_value( $value );
			}
		);
	}

	private function is_empty_value( $value ) {
		return null === $value || ( is_scalar( $value ) && '' === (string) $value );
	}

	private function resolve_post( $post ) {
		return is_numeric( $post ) ? get_post( $post ) : $post;
	}

	private function is_external_element( $post ) {
		return isset( $post->external_type ) && $post->external_type;
	}

	private function external_element_key( $post ) {
		$post_key = '';
		foreach ( $post->string_data as $key => $value ) {
			$post_key .= $key . $value;
		}

		return $post_key;
	}

	private function post_key_parts( $post ) {
		$post_tags       = $this->get_post_terms( $post, 'post_tag' );
		$post_categories = $this->get_post_terms( $post, 'category' );
		$post_taxonomies = $this->get_post_taxonomies( $post );

		$content = $post->post_content;
		$content = apply_filters( 'wpml_pb_shortcode_content_for_translation', $content, $post->ID );

		$content = apply_filters( 'wpml_tm_post_md5_content', $content, $post );

		$before = $post->post_title . ';' . $content . ';' . $post->post_excerpt . ';' . implode( ',', $post_tags ) . ';' . implode( ',', $post_categories ) . ';';

		$after = '';
		if ( ! empty( $post_taxonomies ) ) {
			$after .= ';' . implode( ';', $post_taxonomies );
		}
		if ( wpml_get_setting_filter( false, 'translated_document_page_url' ) === 'translate' ) {
			$after .= $post->post_name . ';';
		}

		return array( $before, $after );
	}

	private function build_post_key( array $parts, array $custom_fields_values ) {
		return $parts[0] . implode( ',', $custom_fields_values ) . $parts[1];
	}

	private function filter_post_key( $post_key, $post ) {
		return apply_filters( 'wpml_post_md5_key', $post_key, $post );
	}

	private function legacy_checksum_orders() {
		if ( ! $this->legacy_checksum_orders ) {
			$this->legacy_checksum_orders = new LegacyChecksumOrders();
		}

		return $this->legacy_checksum_orders;
	}

	private function checksum_field_history() {
		if ( ! $this->checksum_field_history ) {
			$this->checksum_field_history = new ChecksumFieldHistory();
		}

		return $this->checksum_field_history;
	}

	private function get_post_terms( $post, $taxonomy, $sort = false ) {
		global $sitepress;

		$terms = array();
		$hasFilter = remove_filter( 'get_term', array( $sitepress, 'get_term_adjust_id' ), 1 );

		$post_taxonomy_terms = wp_get_object_terms( $post->ID, $taxonomy );
		if ( ! is_wp_error( $post_taxonomy_terms ) ) {
			foreach ( $post_taxonomy_terms as $trm ) {
				$terms[] = $trm->name;
			}
		}

		if ( $terms ) {
			sort( $terms, SORT_STRING );
		}

		if ( $hasFilter ) {
			add_filter( 'get_term', array( $sitepress, 'get_term_adjust_id' ), 1, 1 );
		}

		return $terms;
	}

	private function get_post_taxonomies( $post ) {
		global $wpdb, $sitepress_settings;

		$post_taxonomies = array();

		$taxonomies = $wpdb->get_col(
			$wpdb->prepare(
				"
				SELECT DISTINCT tx.taxonomy
				FROM {$wpdb->term_taxonomy} tx JOIN {$wpdb->term_relationships} tr ON tx.term_taxonomy_id = tr.term_taxonomy_id
				WHERE tr.object_id =%d ",
				$post->ID
			)
		);
		sort( $taxonomies, SORT_STRING );
		if ( isset( $sitepress_settings['taxonomies_sync_option'] ) ) {
			foreach ( $taxonomies as $t ) {
				if ( taxonomy_exists( $t ) && isset( $sitepress_settings['taxonomies_sync_option'][ $t ] ) && $sitepress_settings['taxonomies_sync_option'][ $t ] == 1 ) {
					$taxs = $this->get_post_terms( $post, $t );

					if ( $taxs ) {
						$post_taxonomies[] = '[' . $t . ']:' . implode( ',', $taxs );
					}
				}
			}
		}

		return $post_taxonomies;
	}

	private function get_post_custom_fields( $post, ?array $names = null ) {
		return $this->get_post_custom_field_values( $post, array_keys( $this->translatable_custom_fields( $names ) ) );
	}

	private function get_post_custom_field_values( $post, array $names ) {
		return $this->filter_custom_field_values( $this->read_custom_field_values( $post, $names ), $post );
	}

	private function read_custom_field_values( $post, array $names ) {
		$custom_fields_values = array();

		foreach ( $names as $cf ) {
			$custom_fields_values[ $cf ] = $this->read_custom_field( $post, $cf );
		}

		return $custom_fields_values;
	}

	private function filter_custom_field_values( array $custom_fields_values, $post ) {
		return apply_filters( 'wpml_custom_field_values_for_post_signature', $custom_fields_values, $post->ID );
	}

	private function read_custom_field( $post, $name ) {
		$scoped = null !== $this->custom_field_scope && (int) $post->ID === $this->custom_field_scope['post'];
		if ( $scoped && array_key_exists( (string) $name, $this->custom_field_scope['values'] ) ) {
			return $this->custom_field_scope['values'][ (string) $name ];
		}

		$value = get_post_meta( $post->ID, $name, true );
		$value = is_scalar( $value ) ? $value : wp_json_encode( $value );

		if ( $scoped ) {
			$this->custom_field_scope['values'][ (string) $name ] = $value;
		}

		return $value;
	}

	private function translatable_custom_fields( ?array $names = null ) {
		$map = \WPML\TM\Settings\Repository::getCustomFields();
		if ( null === $this->translatable_memo || $map !== $this->translatable_memo['map'] ) {
			$this->translatable_memo = array(
				'map'    => $map,
				'fields' => $this->translatable_fields_of( $map ),
			);
		}
		$fields = $this->translatable_memo['fields'];

		if ( null === $names ) {
			return $fields;
		}

		$chosen = array();
		foreach ( $names as $cf ) {
			if ( array_key_exists( $cf, $fields ) ) {
				$chosen[ $cf ] = $fields[ $cf ];
			}
		}

		return $chosen;
	}

	private function post_checksum_fields( $post ) {
		$fields = $this->translatable_custom_fields();
		if ( ! isset( $post->ID ) ) {
			return $fields;
		}

		$map  = \WPML\TM\Settings\Repository::getCustomFields();
		$meta = get_post_custom( $post->ID );
		$keys = is_array( $meta ) ? array_keys( $meta ) : array();
		$id   = get_current_blog_id() . ':' . $post->ID;

		if (
			null !== $this->post_fields_memo
			&& $id === $this->post_fields_memo['post']
			&& $keys === $this->post_fields_memo['keys']
			&& $map === $this->post_fields_memo['map']
		) {
			return $this->post_fields_memo['all'];
		}

		$unmapped = array_values(
			array_filter(
				$keys,
				function ( $key ) use ( $map ) {
					return ! array_key_exists( $key, $map );
				}
			)
		);

		$all = $fields;
		if ( $unmapped ) {
			foreach ( wpml_resolve_custom_field_preferences( $unmapped ) as $name => $mode ) {
				if (
					! array_key_exists( $name, $map )
					&& in_array( (int) $mode, array( WPML_TRANSLATE_CUSTOM_FIELD, WPML_COPY_ONCE_CUSTOM_FIELD ), true )
				) {
					$all[ $name ] = $mode;
				}
			}
		}

		$this->post_fields_memo = array(
			'post' => $id,
			'keys' => $keys,
			'map'  => $map,
			'all'  => $all,
		);

		return $all;
	}

	private function checksum_without_resolved_fields( $post ) {
		$fields = $this->translatable_custom_fields();
		if ( count( $this->post_checksum_fields( $post ) ) === count( $fields ) ) {
			return null;
		}

		return $this->checksum_over( $post, array_keys( $fields ) );
	}

	protected function translatable_fields_of( array $map ) {
		$fields = array();
		foreach ( $map as $cf => $op ) {
			if ( in_array( (int) $op, array( WPML_TRANSLATE_CUSTOM_FIELD, WPML_COPY_ONCE_CUSTOM_FIELD ), true ) ) {
				$fields[ $cf ] = $op;
			}
		}

		return $fields;
	}

	private function get_update_translation_action( $translation_package ) {
		require_once WPML_TM_PATH . '/inc/translation-jobs/helpers/wpml-update-external-translation-data-action.class.php';
		require_once WPML_TM_PATH . '/inc/translation-jobs/helpers/wpml-update-post-translation-data-action.class.php';

		return array_key_exists( 'type', $translation_package ) && $translation_package['type'] === 'post'
			? new WPML_TM_Update_Post_Translation_Data_Action() : new WPML_TM_Update_External_Translation_Data_Action();
	}
}
