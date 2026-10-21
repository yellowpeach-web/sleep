<?php

use WPML\FP\Obj;
use WPML\TM\Jobs\FieldId;
use WPML\TM\Jobs\TermMeta;
use WPML\Translation\TranslationElements\FieldCompression;

require_once WPML_TM_PATH . '/inc/translation-jobs/jobs/wpml-translation-job.class.php';

class WPML_Post_Translation_Job extends WPML_Element_Translation_Job {

	function get_original_document() {

		return get_post( $this->get_original_element_id() );
	}

	public function get_url( $original = false ) {
		$url        = null;
		$element_id = null;

		if ( $original ) {
			$element_id = $this->get_original_element_id();
			$url        = get_permalink( $element_id );
		} else {
			$element_id = $this->get_resultant_element_id();
			$url        = get_edit_post_link( $element_id );
		}

		return apply_filters( 'wpml_element_translation_job_url', $url, $original, $element_id, $this->get_original_document() );
	}

	function is_translatable_post_type() {
		$post_type = $this->get_post_type();

		if ( $post_type ) {
			global $sitepress;
			if ( $sitepress ) {
				$post_types = array_keys( $sitepress->get_translatable_documents() );

				return in_array( $post_type, $post_types, true );
			}
		}

		return false;
	}

	function update_fields_from_post() {
		global $iclTranslationManagement, $wpdb;

		$job_id           = $this->get_id();
		$post_id          = $this->get_resultant_element_id();
		$data['complete'] = 1;
		$data['job_id']   = $job_id;
		$job              = wpml_tm_load_job_factory()->get_translation_job( $job_id, 1 );
		$term_names       = $this->get_term_field_array_for_post();
		$post             = get_post( $post_id );
		if ( is_object( $job ) && is_array( $job->elements ) && is_object( $post ) ) {
			$job_fields = $this->job_field_texts_from_post( $job->elements, $post );
			foreach ( $job->elements as $element ) {
				$field_data = $this->translated_field_data_from_post( $element, $post, $term_names, $job_fields );
				if ( $field_data || ( null !== $field_data && array_key_exists( $element->field_type, $job_fields ) ) ) {
					$wpdb->update( $wpdb->prefix . 'icl_translate',
						array(
							'field_data_translated' => FieldCompression::compressAndTrack( $field_data, true, $job_id ),
							'field_finished'        => 1
						),
						array( 'tid' => $element->tid )
					);
				}
			}
			$iclTranslationManagement->mark_job_done( $job_id );
		}
	}

	public function fields_differ_from_post( $post ) {
		$job = wpml_tm_load_job_factory()->get_translation_job( $this->get_id(), 1 );
		if ( ! is_object( $job ) || ! is_array( $job->elements ) || ! is_object( $post ) ) {
			return false;
		}

		$term_names = $this->get_term_field_array_for_post();
		$unread     = [];
		foreach ( $job->elements as $element ) {
			$field_data = $this->translated_field_data_from_post( $element, $post, $term_names );
			if ( null === $field_data ) {
				$unread[] = $element;
				continue;
			}
			if ( $this->stored_text( $element ) !== (string) $field_data ) {
				return true;
			}
		}

		$job_fields = $unread ? $this->job_field_texts_from_post( $job->elements, $post ) : [];
		foreach ( $unread as $element ) {
			$field_data = $this->translated_field_data_from_post( $element, $post, $term_names, $job_fields );
			if ( null === $field_data ) {
				continue;
			}
			$stored = $this->stored_text( $element );
			if ( $stored !== (string) $field_data && ! $this->is_delivered_custom_field_text( $element, $stored, $job_fields ) ) {
				return true;
			}
		}

		return false;
	}

	private function stored_text( $element ) {
		return (string) FieldCompression::decompress( (string) $element->field_data_translated, true );
	}

	private function translated_field_data_from_post( $element, $post, array $term_names, array $job_fields = [] ) {
		switch ( $element->field_type ) {
			case 'title':
				return $this->encode_field_data( $post->post_title );
			case 'body':
				return $this->encode_field_data( $post->post_content );
			case 'excerpt':
				return $this->encode_field_data( $post->post_excerpt );
			case 'URL':
				return $this->encode_field_data( $post->post_name );
			default:
				if ( isset( $term_names[ $element->field_type ] ) ) {
					return $this->encode_field_data( $term_names[ $element->field_type ] );
				}
				if ( array_key_exists( $element->field_type, $job_fields ) ) {
					return $this->encode_field_data( $job_fields[ $element->field_type ] );
				}
		}

		return null;
	}

	private function job_field_texts_from_post( array $elements, $post ) {
		$strings = $this->package_string_texts( $elements );

		foreach ( $this->builder_string_texts_from_post( $elements, $post ) as $field_type => $text ) {
			if ( ! isset( $strings[ $field_type ] ) || ! $this->is_delivered_builder_text( $strings[ $field_type ], $text ) ) {
				$strings[ $field_type ] = $text;
			}
		}

		return $this->custom_field_texts_from_post( $elements, $post ) + $strings;
	}

	protected function builder_string_texts_from_post( array $elements, $post ) {
		$string_ids = $this->package_string_ids( $elements );
		if ( ! $string_ids ) {
			return [];
		}

		$texts = apply_filters( 'wpml_pb_string_texts_in_post', [], $post );
		if ( ! $texts || ! is_array( $texts ) ) {
			return [];
		}

		$out = [];
		foreach ( $this->string_names_of( array_keys( $string_ids ) ) as $string_id => $name ) {
			if ( ! array_key_exists( $name, $texts ) ) {
				continue;
			}
			foreach ( $string_ids[ $string_id ] as $field_type ) {
				$out[ $field_type ] = (string) $texts[ $name ];
			}
		}

		return $out;
	}

	private function is_delivered_builder_text( $delivered, $text ) {
		return $text === $delivered || $text === wpautop( $delivered );
	}

	private function package_string_ids( array $elements ) {
		$string_ids = [];
		foreach ( $elements as $element ) {
			if ( preg_match( '/^package-string-\d+-(\d+)$/', (string) $element->field_type, $match ) ) {
				$string_ids[ (int) $match[1] ][] = (string) $element->field_type;
			}
		}

		return $string_ids;
	}

	protected function string_names_of( array $ids ) {
		global $wpdb;

		if ( ! $ids || ! \WPML\Upgrade\SchemaFacts::hasTable( 'icl_strings' ) ) {
			return [];
		}

		$rows = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT id, name FROM {$wpdb->prefix}icl_strings WHERE id IN (" . implode( ', ', array_fill( 0, count( $ids ), '%d' ) ) . ')',
				$ids
			)
		);

		$out = [];
		foreach ( (array) $rows as $row ) {
			$out[ (int) $row->id ] = (string) $row->name;
		}

		return $out;
	}

	protected function custom_field_texts_from_post( array $elements, $post ) {
		$names = [];
		foreach ( $elements as $element ) {
			$name = $this->custom_field_name_of( $element, $elements );
			if ( null !== $name ) {
				$names[ (string) $element->field_type ] = $name;
			}
		}
		if ( ! $names ) {
			return [];
		}

		$keys = [];
		foreach ( (array) get_post_custom_keys( (int) $post->ID ) as $key ) {
			$starts_a_name = '/^' . preg_quote( (string) $key, '/' ) . '-\\d/';
			foreach ( $names as $name ) {
				if ( preg_match( $starts_a_name, $name ) ) {
					$keys[] = (string) $key;
					break;
				}
			}
		}

		$texts = $keys ? $this->translation_package()->get_custom_field_texts( $post, $keys ) : [];

		$out = [];
		foreach ( array_keys( $names ) as $field_type ) {
			$out[ $field_type ] = isset( $texts[ $field_type ] ) ? $texts[ $field_type ] : '';
		}

		return $out;
	}

	private function custom_field_name_of( $element, array $elements ) {
		$field_type = (string) $element->field_type;
		if ( 0 !== strpos( $field_type, 'field-' ) || $this->has_suffix( $field_type, '-name' ) || $this->has_suffix( $field_type, '-type' ) ) {
			return null;
		}

		$name = null;
		$type = null;
		foreach ( $elements as $sibling ) {
			if ( $field_type . '-name' === $sibling->field_type ) {
				$name = (string) $sibling->field_data;
			} elseif ( $field_type . '-type' === $sibling->field_type ) {
				$type = (string) $sibling->field_data;
			}
		}

		return 'custom_field' === $type && null !== $name && '' !== $name ? $name : null;
	}

	private function has_suffix( $text, $suffix ) {
		return substr( $text, -strlen( $suffix ) ) === $suffix;
	}

	private function is_delivered_custom_field_text( $element, $stored, array $job_fields ) {
		$field_type = (string) $element->field_type;
		if ( 0 !== strpos( $field_type, 'field-' ) || ! array_key_exists( $field_type, $job_fields ) ) {
			return false;
		}
		$delivered = html_entity_decode( str_replace( '&#0A;', "\n", (string) base64_decode( $stored ) ) );

		return $delivered === (string) $job_fields[ $field_type ];
	}

	protected function package_string_texts( array $elements ) {
		global $wpdb;

		$string_ids = $this->package_string_ids( $elements );
		$language   = $this->get_language_code();
		if ( ! $string_ids || ! $language || ! \WPML\Upgrade\SchemaFacts::hasTable( 'icl_string_translations' ) ) {
			return [];
		}

		$ids = array_keys( $string_ids );
		$rows = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT string_id, value FROM {$wpdb->prefix}icl_string_translations
				WHERE language = %s AND value IS NOT NULL AND string_id IN (" . implode( ', ', array_fill( 0, count( $ids ), '%d' ) ) . ')',
				array_merge( [ $language ], $ids )
			)
		);

		$out = [];
		foreach ( (array) $rows as $row ) {
			foreach ( $string_ids[ (int) $row->string_id ] ?? [] as $field_type ) {
				$out[ $field_type ] = (string) $row->value;
			}
		}

		return $out;
	}

	protected function translation_package() {
		return new WPML_Element_Translation_Package();
	}

	public function set_translated_field_by_type( $field_type, $encoded_value ) {
		global $wpdb;

		$wpdb->update(
			$wpdb->prefix . 'icl_translate',
			[
				'field_data_translated' => $encoded_value,
				'field_finished'        => 1,
			],
			[
				'field_type' => (string) $field_type,
				'job_id'     => $this->get_id(),
			]
		);
	}

	function save_terms_to_post() {
		global $sitepress, $wpdb;

		$lang_code = $this->get_language_code();

		if ( $sitepress->get_setting( 'tm_block_retranslating_terms' ) ) {
			$this->load_terms_from_post_into_job( true );
		}
		$terms = $this->get_terms_in_job_rows();
		foreach ( $terms as $term ) {
			$is_new_term     = ! $sitepress->term_translations()->element_id_in( (int) $term->term_taxonomy_id, $lang_code );
			$new_term_action = new WPML_Update_Term_Action( $wpdb, $sitepress, [
				'term'        => base64_decode( $term->field_data_translated ),
				'description' => TermMeta::getTermDescription( $this->get_id(), $term->term_taxonomy_id ),
				'lang_code'   => $lang_code,
				'trid'        => $term->trid,
				'taxonomy'    => $term->taxonomy
			] );
			$new_term = $new_term_action->execute();
			if ( ! empty( $new_term['term_taxonomy_id'] ) ) {
				( new WPML_Sync_Term_Meta_Action( $sitepress, (int) $new_term['term_taxonomy_id'], $is_new_term ) )->run();
			}

			foreach ( TermMeta::getTermMeta( $this->get_id(), $term->term_taxonomy_id ) as $meta ) {
				update_term_meta( $new_term['term_id'], FieldId::getTermMetaKey( (string) $meta->field_type ), $meta->field_data_translated );
			}
		}

		$term_helper = wpml_get_term_translation_util();
		$term_helper->sync_terms( $this->get_original_element_id(), $this->get_language_code() );
	}

	function load_terms_from_post_into_job( $delete = null ) {
		global $sitepress;

		$delete = isset( $delete ) ? $delete : $sitepress->get_setting( 'tm_block_retranslating_terms' );
		$this->set_translated_term_values( $delete );
	}

	public function get_title() {
		$title = $this->get_title_from_db();

		if ( $title ) {
			return $title;
		}

		$original_post = $this->get_original_document();

		return is_object( $original_post ) && isset( $original_post->post_title )
			? $original_post->post_title : $this->original_del_text;
	}

	public function get_type_title() {
		$post_type = $this->get_post_type();
		if ( ! $post_type ) {
			return '';
		}
		$post_type = get_post_type_object( $post_type );

		return $post_type->labels->singular_name;
	}

	public function get_post_type() {
		$original_post = $this->get_original_document();

		return $original_post ? $original_post->post_type : false;
	}

	protected function load_resultant_element_id() {
		global $wpdb;
		$this->maybe_load_basic_data();

		return $wpdb->get_var( $wpdb->prepare( "SELECT element_id
												FROM {$wpdb->prefix}icl_translations
												WHERE translation_id = %d
												LIMIT 1",
			$this->basic_data->translation_id ) );
	}

	private function has_term_rows() {
		global $wpdb;

		return (bool) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT 1 FROM {$wpdb->prefix}icl_translate WHERE job_id = %d AND field_type LIKE %s LIMIT 1",
				$this->get_id(),
				$wpdb->esc_like( 't_' ) . '%'
			)
		);
	}

	protected function get_terms_in_job_rows(){
		global $wpdb;

		if ( ! $this->has_term_rows() ) {
			return [];
		}

		$term_field_pattern = $wpdb->esc_like( 't_' ) . '%';

		$results = $wpdb->get_results( $wpdb->prepare("	SELECT
													  tt.taxonomy,
													  tt.term_taxonomy_id,
													  iclt.trid,
													  j.field_data_translated
													FROM {$wpdb->prefix}icl_translate j
													JOIN {$wpdb->term_taxonomy} tt
														ON tt.term_taxonomy_id = CAST( SUBSTRING( j.field_type, 3 ) AS UNSIGNED )
															AND j.field_type = CONCAT('t_', tt.term_taxonomy_id)
													JOIN {$wpdb->prefix}icl_translations iclt
														ON iclt.element_id = tt.term_taxonomy_id
															AND CONCAT('tax_', tt.taxonomy) = iclt.element_type
													WHERE j.job_id = %d AND j.field_type LIKE %s", $this->get_id(), $term_field_pattern ) );

		foreach ( $results as $result ) {
			$result->field_data_translated = FieldCompression::decompress( $result->field_data_translated, true );
		}

		return $results;
	}

	protected function get_term_field_array_for_post() {
		global $wpdb;

		$post_id = $this->get_resultant_element_id();
		$res   = $wpdb->get_results( $wpdb->prepare( "SELECT o.term_taxonomy_id, t.name
								  FROM {$wpdb->term_relationships} o
								  JOIN {$wpdb->term_taxonomy} tt ON tt.term_taxonomy_id = o.term_taxonomy_id
								  JOIN {$wpdb->terms} t ON t.term_id = tt.term_id
								  WHERE o.object_id = %d",
			$post_id ) );

		$result = array();

		foreach ( $res as $term ) {
			$result[ 't_' . $term->term_taxonomy_id ] = $term->name;
		}

		return $result;
	}

	protected function set_translated_term_values( $delete ) {
		global $wpdb;

		if ( ! $this->has_term_rows() ) {
			return;
		}

		$translate_table    = $wpdb->prefix . 'icl_translate';
		$term_field_pattern = $wpdb->esc_like( 't_' ) . '%';

		$job_id                         = $this->get_id();

		$term_values = $wpdb->get_results( $wpdb->prepare("
		SELECT
		    {$wpdb->terms}.name,
		    {$wpdb->term_taxonomy}.description,
		    SUBSTR(translate.field_type, 3) original_term_id,
		    {$wpdb->terms}.term_id translated_term_id
		FROM {$wpdb->prefix}icl_translate translate
			INNER JOIN {$wpdb->prefix}icl_translations original_translation ON original_translation.element_id = SUBSTR(translate.field_type, 3)
			INNER JOIN {$wpdb->prefix}icl_translations translation ON translation.trid = original_translation.trid AND translation.language_code = %s
			INNER JOIN {$wpdb->term_taxonomy} ON {$wpdb->term_taxonomy}.term_taxonomy_id = translation.element_id AND CONCAT('tax_', {$wpdb->term_taxonomy}.taxonomy) = translation.element_type
			INNER JOIN {$wpdb->terms} ON {$wpdb->terms}.term_id = {$wpdb->term_taxonomy}.term_id
		WHERE translate.job_id = %d AND translate.field_type LIKE %s
		", $this->get_language_code(), $job_id, $term_field_pattern ) );
		foreach ( $term_values as $term ) {
			if ( $delete ) {
				$wpdb->query(
					$wpdb->prepare(
						"DELETE FROM {$wpdb->prefix}icl_translate
						WHERE job_id = %d
							AND (field_type LIKE %s OR field_type LIKE %s OR field_type = %s OR field_type = %s)",
						$job_id,
						'tfield-%-' . $term->original_term_id,
						'tfield-%-' . $term->original_term_id . '\_%',
						't_' . $term->original_term_id,
						'tdesc_' . $term->original_term_id
					)
				);
			} else {
				$wpdb->update(
					$translate_table,
					[ 'field_data_translated' => FieldCompression::compressAndTrack( $term->name, false, $job_id ), 'field_finished' => 1 ],
					[ 'field_type' => 't_' . $term->original_term_id, 'job_id' => $job_id ]
				);
				$wpdb->update(
					$translate_table,
					[ 'field_data_translated' => FieldCompression::compressAndTrack( $term->description, false, $job_id ), 'field_finished' => 1 ],
					[ 'field_type' => 'tdesc_' . $term->original_term_id, 'job_id' => $job_id ]
				);

				$meta_values = $wpdb->get_results(
					$wpdb->prepare(
						"SELECT meta_key, meta_value FROM {$wpdb->termmeta} WHERE term_id = %d",
						$term->translated_term_id
					)
				);
				foreach ( $meta_values as $meta ) {
					$wpdb->update(
						$translate_table,
						[ 'field_finished' => 1, 'field_data_translated' => FieldCompression::compressAndTrack( $meta->meta_value, false, $job_id )  ],
						[ 'job_id' => $job_id, 'field_type' => 'tfield-' . $meta->meta_key . '-' . $term->original_term_id ]
					);
				}
			}
		}
	}

	protected function filter_is_translator_args( array $args ) {
		return Obj::assoc( 'post_id', $this->get_original_element_id(), $args );
	}
}
