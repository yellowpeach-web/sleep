<?php

use WPML\FP\Cast;
use WPML\FP\Fns;
use WPML\FP\Lst;
use WPML\FP\Obj;
use WPML\FP\Str;
use WPML\LIB\WP\Attachment;
use WPML\TM\Jobs\FieldId;
use WPML\TM\XLIFF\DeclaredPlaceholders;
use WPML\TM\XLIFF\SegmentedSuggestion;
use WPML\Utilities\Labels;
use WPML\TM\API\Jobs;
use WPML\TM\XLIFF\CurrentTranslation;
use WPML\Translation\TranslationElements\FieldCompression;
use function WPML\FP\spreadArgs;

class WPML_TM_Xliff_Writer {
	const TAB                    = "\t";
	const DEFAULT_GROUP          = 'Main Content';
	const DEFAULT_GROUP_ID       = 'Main_Content-0';
	const CUSTOM_FIELDS_GROUP    = 'Custom Fields';
	const CUSTOM_FIELDS_GROUP_ID = 'Custom_Fields-0';

	const WP_EDITOR_MODIFIED_TIMESTAMP  = 'translation_modified_timestamp';
	const WP_EDITOR_GENERATED_TIMESTAMP = 'generated_timestamp';

	protected $job_factory;

	private $xliff_version;

	private $xliff_shortcodes;

	private $translator_notes;
	private $handled_group_images;

	private $is_xliff_for_ate = false;

	private $shouldMarkDeclaredPlaceholders = false;

	private $use_translation_memory = false;

	private $human_translated_fields = array();

	private $valid_html_tag_regex;

	public function __construct( WPML_Translation_Job_Factory $job_factory, $xliff_version = TRANSLATION_PROXY_XLIFF_VERSION, ?\WPML_TM_XLIFF_Shortcodes $xliff_shortcodes = null ) {
		$this->job_factory   = $job_factory;
		$this->xliff_version = $xliff_version;

		if ( ! $xliff_shortcodes ) {
			$xliff_shortcodes = wpml_tm_xliff_shortcodes();
		}
		$this->xliff_shortcodes = $xliff_shortcodes;
		$this->translator_notes = new WPML_TM_XLIFF_Translator_Notes();
		$this->use_translation_memory = defined( 'WPML_USE_ST_TRANSLATION_MEMORY' ) && WPML_USE_ST_TRANSLATION_MEMORY;
	}

	public function withDeclaredPlaceholders() {
		$this->shouldMarkDeclaredPlaceholders = true;

		return $this;
	}

	public function get_job_xliff_file( $job_id ) {

		return $this->generate_xliff_file( $this->generate_job_xliff( $job_id ) );
	}

	public function generate_job_xliff( $job_id ) {
		return $this->create_job_xliff( $job_id )['xliff'];
	}

	public function generate_job_xliff_or_null( $job_id ) {
		$result = $this->create_job_xliff( $job_id );

		return $result['has_units'] ? $result['xliff'] : null;
	}

	private function create_job_xliff( $job_id ) {
		global $iclTranslationManagement;

		$job = $iclTranslationManagement->get_translation_job( (int) $job_id, true, false, 1 );

		if ( ! is_object( $job ) ) {
			return array(
				'xliff'     => '',
				'has_units' => false,
			);
		}

		$this->is_xliff_for_ate =
			'local' === $job->translation_service
			&& \WPML_TM_ATE_Status::is_enabled_and_activated();

		$translation_units  = $this->get_job_translation_units_data( $job );
		$original           = $job_id . '-' . md5( $job_id . $job->original_doc_id );
		$original_post_type = isset( $job->original_post_type ) ? $job->original_post_type : null;

		$external_file_url = $this->get_external_url( $job );
		$this->get_translator_notes( $job );

		$wp_editor_extra_units = $this->get_wp_editor_extra_units( $job );

		$xliff = $this->generate_xliff(
			$original,
			$job->source_language_code,
			$job->language_code,
			$translation_units,
			$external_file_url,
			$original_post_type,
			$job->wpml_words_to_translate_count,
			$job->wpml_automatic_translation_costs,
			$wp_editor_extra_units
		);

		return array(
			'xliff'     => $xliff,
			'has_units' => ! empty( $translation_units ),
		);
	}

	public function get_strings_xliff_file( $strings, $source_language, $target_language ) {
		$strings = $this->pre_populate_strings_with_translation_memory( $strings, $source_language, $target_language );

		return $this->generate_xliff_file(
			$this->generate_xliff(
				uniqid( 'string-', true ),
				$source_language,
				$target_language,
				$this->generate_strings_translation_units_data( $strings )
			)
		);
	}

	private function generate_xliff(
		$original_id,
		$source_language,
		$target_language,
		array $translation_units = array(),
		$external_file_url = null,
		$original_post_type = null,
		$words_to_translate_count = null,
		$automatic_translation_costs = null,
		array $wp_editor_extra_units = array()
	) {
		global $sitepress;


		$xliff = new WPML_TM_XLIFF( $this->get_xliff_version(), '1.0', 'utf-8' );

		$phase_group     = array();
		$phase_group     = array_merge( $phase_group, $this->xliff_shortcodes->get() );
		$phase_group     = array_merge( $phase_group, $this->translator_notes->get() );
		$post_type_phase = new WPML_TM_XLIFF_Post_Type( $original_post_type );
		$phase_group     = array_merge( $phase_group, $post_type_phase->get() );

		$source_language_domain = $sitepress->get_domain_by_language( $source_language );
		$target_language_domain = $sitepress->get_domain_by_language( $target_language );

		$jobSender = \WPML\TM\ATE\JobSender\JobSenderRepository::get();

		$xliff
			->setFileAttributes(
				array(
					'original'                           => $original_id,
					'source-language'                    => $source_language,
					'target-language'                    => $target_language,
					'tool:source-language-domain'        => $source_language_domain,
					'tool:target-language-domain'        => $target_language_domain,
					'tool:sender-id'                     => $jobSender->id,
					'tool:sender-username'               => $jobSender->username,
					'tool:sender-email'                  => $jobSender->email,
					'tool:sender-display-name'           => $jobSender->displayName,
					'tool:wpml-words-to-translate-count' => $words_to_translate_count,
					'tool:wpml-automatic-translation-costs' => $automatic_translation_costs,
					'datatype'                           => 'plaintext',
				)
			)
			->setReferences(
				array(
					'external-file' => $external_file_url,
				)
			)
			->setPhaseGroup( $phase_group )
			->setTranslationUnits( $translation_units );

		if ( ! empty( $wp_editor_extra_units ) ) {
			$xliff->setTranslationUnits( $wp_editor_extra_units );
		}

		return $xliff->toString();
	}

	private function get_xliff_version() {
		switch ( $this->xliff_version ) {
			case '10':
				return '1.0';
			case '11':
				return '1.1';
			case '12':
			default:
				return '1.2';
		}
	}

	private function generate_strings_translation_units_data( $strings ) {
		$translation_units = array();

		foreach ( $strings as $string ) {
			$id                  = 'string-' . $string->id;
			$translation_units[] = $this->get_translation_unit_data( $id, 'string', $string->value, $string->translation, $string->translated_from_memory );
		}

		return $translation_units;
	}

	private function pre_populate_strings_with_translation_memory( $strings, $source_lang, $target_lang ) {
		$strings_to_translate    = wp_list_pluck( $strings, 'value' );
		$original_translated_map = $this->get_original_translated_map_from_translation_memory( $strings_to_translate, $source_lang, $target_lang );

		foreach ( $strings as &$string ) {
			$string->translated_from_memory = false;
			$string->translation            = $string->value;

			if ( array_key_exists( $string->value, $original_translated_map ) ) {
				$string->translation            = $original_translated_map[ $string->value ];
				$string->translated_from_memory = true;
			}
		}

		return $strings;
	}

	private function get_original_translated_map_from_translation_memory( $strings_to_translate, $source_lang, $target_lang ) {
		$args = array(
			'strings'     => $strings_to_translate,
			'source_lang' => $source_lang,
			'target_lang' => $target_lang,
		);

		$strings_in_memory = apply_filters( 'wpml_st_get_translation_memory', array(), $args );

		if ( $strings_in_memory ) {
			return wp_list_pluck( $strings_in_memory, 'translation', 'original' );
		}

		return array();
	}

	private function get_job_translation_units_data( $job ) {
		$translation_units = array();
		$elements = $job->elements;

		if ( ! $elements ) {
			return $translation_units;
		}

		$this->human_translated_fields = $this->load_human_translated_fields( $job );

		$elements = array_values( array_filter( $elements, function( $element ) {
			return ( 1 === (int) $element->field_translate );
		} ) );

		$elements = $this->pre_populate_elements_with_translation_memory( $elements, $job->source_language_code, $job->language_code );

		$elementsAsFields = Fns::map( Cast::toArr(), $elements );
		$elementsAsFields = apply_filters( 'wpml_tm_adjust_translation_fields', $elementsAsFields, $job, null );
		$elementsAsFields = Fns::map( function( $elementAsField ) {
			return $this->getExtraData( $elementAsField );
		}, $elementsAsFields );

		$elementsPairs = Lst::zip( $elements, $elementsAsFields );
		Fns::map( spreadArgs( function( $element, $elementAsField ) use ( &$translation_units ) {
			$this->processElement( $element, $elementAsField, $translation_units );
		} ), $elementsPairs );

		return apply_filters( 'wpml_tm_adjust_translation_job', $translation_units, $job );
	}

	private function processElement( $element, $extraData, &$translationUnits ) {
		if ( isset( $extraData['images'] ) && count( $extraData['images'] ) > 0 && ! $this->has_group_image_been_handled( $extraData ) ) {
			$this->handle_extra_data_images( $element, $extraData, $translationUnits );
		};

		if ( isset( $extraData['images'] ) ) {
			unset( $extraData['images'] );
		}

		$this->handle_field_data( $element, $extraData, $translationUnits );
	}

	private function has_group_image_been_handled( $extra_data ) {
		if ( ! array_key_exists( 'group_id', $extra_data ) ) {
			return false;
		}

		$group_id = $extra_data['group_id'];

		$hash = '';
		foreach ( $extra_data['images'] as $image ) {
			$hash .= md5( $image );
		}

		if ( isset( $this->handled_group_images[ $group_id ] ) && $this->handled_group_images[ $group_id ] === $hash ) {
			return true;
		}

		$this->handled_group_images[ $group_id ] = $hash;

		return false;
	}

	private function handle_field_data( $element, $extra_data, &$translation_units ) {
		$field_data_translated = base64_decode( $element->field_data_translated );
		$field_data            = base64_decode( $element->field_data );

		$field_data = apply_filters( 'wpml_tm_xliff_unit_field_data', $field_data );

		if ( FieldId::is_a_custom_field( $element->field_type ) ) {
			$field_data_translated = apply_filters(
				'wpml_tm_xliff_export_translated_cf',
				$field_data_translated,
				$element
			);
			$field_data            = apply_filters(
				'wpml_tm_xliff_export_original_cf',
				$field_data,
				$element
			);
		}
		$target_holds_a_translation = '' !== trim( (string) $field_data_translated );

		if ( ! null === $field_data_translated || '' === $field_data_translated ) {
			$field_data_translated = $this->remove_invalid_chars( $field_data );
		}

		$field_name = isset( $extra_data['unit'] ) ? $extra_data['unit'] : $element->field_type;

		if ( in_array( $element->field_type, [ 'title', 'body', 'excerpt', 'URL' ], true ) ) {
			$field_type_name = ucfirst( $element->field_type );
			$extra_data      = [
				'unit'     => $field_type_name,
				'type'     => 'text',
				'group'    => self::DEFAULT_GROUP,
				'group_id' => self::DEFAULT_GROUP_ID,
			];

			$field_name = $element->field_type;
		} elseif ( FieldId::is_any_term_field( $element->field_type ) ) {
			$extra_data['group']    = self::DEFAULT_GROUP . '/Taxonomies';
			$extra_data['group_id'] = self::DEFAULT_GROUP_ID . '/Taxonomies-0';

			$term_taxonomy_id = (int) FieldId::get_term_id( $element->field_type );

			$suffix = '';
			if ( FieldId::is_a_term_description( $element->field_type ) ) {
				$suffix = '-description';
			} elseif ( FieldId::is_a_term_meta( $element->field_type ) ) {
				$suffix = '-' . FieldId::getTermMetaKey( $element->field_type );
			}

			$taxonomy_details   = get_term_by( 'term_taxonomy_id', $term_taxonomy_id );
			$field_name         = $taxonomy_details->taxonomy . $suffix;
			$extra_data['unit'] = Labels::labelize( $field_name );
		}

		$is_valid_unit_content = $this->is_valid_unit_content( $field_data );
		$is_valid_unit_content = apply_filters( 'wpml_xliff_is_valid_unit_content', $is_valid_unit_content, $field_data );
		if ( $is_valid_unit_content ) {

			$is_translation_memory_outdated = false;

			if ( isset( $element->field_finished, $element->has_previous_translation ) ) {
				$is_translation_memory_outdated = ( '0' === $element->field_finished && $element->has_previous_translation );
			}

			$is_suggested_translation = $target_holds_a_translation
				&& (
					! empty( $element->has_previous_translation )
					|| ( $this->use_translation_memory && ! empty( $element->translated_from_memory ) )
				);

			$segment_pairs = null;
			if (
				$is_suggested_translation
				&& '' !== $field_data_translated
				&& $this->is_xliff_for_ate
				&& ! FieldId::is_any_term_field( $element->field_type )
			) {
				$segment_pairs = SegmentedSuggestion::split_pair( $field_data, $field_data_translated );
			}

			$completed_by_a_human = '' !== trim( (string) $field_data_translated )
				&& isset( $this->human_translated_fields[ $element->field_type ] )
				&& trim( $this->human_translated_fields[ $element->field_type ] ) === trim( (string) $field_data_translated );

			if ( $segment_pairs ) {
				foreach ( $segment_pairs as $segment_index => $segment_pair ) {
					$segment_field_id       = 0 === $segment_index
						? $element->field_type
						: $element->field_type . SegmentedSuggestion::SEGMENT_ID_SUFFIX . ( $segment_index + 1 );
					$translation_units_data = $this->get_translation_unit_data(
						$segment_field_id,
						$field_name,
						$this->markDeclaredPlaceholders( $segment_pair['source'], $element, $extra_data ),
						$segment_pair['target'],
						$is_suggested_translation,
						$element->field_wrap_tag,
						$extra_data,
						$is_translation_memory_outdated,
						$completed_by_a_human
					);

					if ( 'title' === $field_name ) {
						array_unshift( $translation_units, $translation_units_data );
					} else {
						$translation_units[] = $translation_units_data;
					}
				}
			} else {
				$translation_units_data = $this->get_translation_unit_data(
					$element->field_type,
					$field_name,
					$this->markDeclaredPlaceholders( $field_data, $element, $extra_data ),
					$field_data_translated,
					$is_suggested_translation,
					$element->field_wrap_tag,
					$extra_data,
					$is_translation_memory_outdated,
					$completed_by_a_human
				);

				if ( 'title' === $field_name ) {
					array_unshift( $translation_units, $translation_units_data );
				} else {
					$translation_units[] = $translation_units_data;
				}
			}
		}
	}

	private function markDeclaredPlaceholders( $field_data, $element, $extra_data ) {
		if ( ! $this->shouldMarkDeclaredPlaceholders || ! $this->is_xliff_for_ate ) {
			return $field_data;
		}

		$patterns = apply_filters( 'wpml_tm_xliff_unit_placeholders', [], $element, $extra_data );

		$patterns = array_values( array_unique( array_filter( (array) $patterns, 'is_string' ) ) );

		return DeclaredPlaceholders::encode( (string) $field_data, $patterns );
	}

	private function handle_extra_data_images( $element, $extra_data_images, &$translation_units ) {
		if ( ! $this->is_xliff_for_ate ) {
			return;
		}
		$images = $extra_data_images['images'];
		unset( $extra_data_images['images'] );

		foreach ( $images as $image ) {
			$image_extra_data = $extra_data_images;

			$image_id = $this->get_image_id_from_url( $image );
			if ( ! $image_id ) {
				continue;
			}

			$original_image_meta_data = $this->get_image_meta_by_id( $image_id, $image );

			if ( $original_image_meta_data ) {
				if ( isset( $original_image_meta_data['width'] ) ) {
					$image_extra_data['image_width'] = (string) $original_image_meta_data['width'];
				}
				if ( isset( $original_image_meta_data['height'] ) ) {
					$image_extra_data['image_height'] = (string) $original_image_meta_data['height'];
				}
				if ( isset( $original_image_meta_data['filesize'] ) ) {
					$image_extra_data['image_filesize'] = (string) $original_image_meta_data['filesize'];
				}

				$image_meta     = wp_get_attachment_metadata( $image_id );
				$thumbnail_size = isset( $image_meta['sizes']['woocommerce_thumbnail'] ) ? 'woocommerce_thumbnail' : 'thumbnail';

				if ( isset( $image_meta['sizes'][ $thumbnail_size ] ) ) {
					$thumbnail_meta = $image_meta['sizes'][ $thumbnail_size ];

					if ( isset( $thumbnail_meta['width'] ) ) {
						$image_extra_data['thumbnail_width'] = (string) $thumbnail_meta['width'];
					}
					if ( isset( $thumbnail_meta['height'] ) ) {
						$image_extra_data['thumbnail_height'] = (string) $thumbnail_meta['height'];
					}
					if ( isset( $thumbnail_meta['filesize'] ) ) {
						$image_extra_data['thumbnail_filesize'] = (string) $thumbnail_meta['filesize'];
					}
					if ( isset( $thumbnail_meta['file'] ) ) {
						$image_extra_data['thumbnail_url'] = $this->get_thumbnail_url( $image, $thumbnail_meta['file'] );
					}
				}
			}

			$field_id                            = str_replace( '-title', '', $element->field_type );
			$image_extra_data['unit']            = 'URL';
			$image_extra_data['type']            = 'text';
			$image_extra_data['image_attribute'] = 'url';

			$id_image_url = $field_id . '_url-img-' . $image_id;

			$translation_units[] = $this->get_translation_unit_data(
				$id_image_url,
				$field_id . '_url',
				$image,
				$image,
				false,
				$element->field_wrap_tag,
				$image_extra_data
			);
		}

	}

	private function getExtraData( $field ) {
		$extradataArray = self::buildFieldExtradata(
			$field,
			(bool) FieldId::is_a_custom_field( Obj::propOr( '', 'field_type', $field ) )
		);

		foreach ( [ 'uid', 'uids' ] as $identityKey ) {
			$identity = Obj::propOr( '', $identityKey, $field );

			if ( '' !== $identity ) {
				$extradataArray[ $identityKey ] = (string) $identity;
			}
		}

		return $extradataArray;
	}

	public static function buildFieldExtradata( array $field, $isCustomField ) {
		$title    = Obj::propOr( '', 'title', $field );
		$group    = Obj::propOr( '', 'group', $field );
		$imageUrl = Obj::propOr( '', 'image', $field );
		$purpose  = Obj::propOr( '', 'purpose', $field );

		if ( is_array( $group ) ) {
			$groupTitleString = implode( '/', array_values( $group ) );
			$groupIdString    = implode( '/', array_keys( $group ) );
		} elseif ( $isCustomField ) {
			$title            = Str::pregReplace( '/^' . FieldId::CUSTOM_FIELD_PREFIX . '/', '', $title );
			$groupTitleString = self::CUSTOM_FIELDS_GROUP;
			$groupIdString    = self::CUSTOM_FIELDS_GROUP_ID;
		} else {
			$groupTitleString = self::DEFAULT_GROUP;
			$groupIdString    = self::DEFAULT_GROUP_ID;
		}

		$extradataArray = [
			'unit'     => Labels::labelize( $title ),
			'type'     => $isCustomField ? 'custom_field' : 'text',
			'group'    => $groupTitleString,
			'group_id' => $groupIdString,
		];

		if ( '' !== $imageUrl ) {
			$extradataArray = array_merge(
				$extradataArray,
				[ 'images' => [ $imageUrl ] ]
			);
		}

		if ( $purpose ) {
			$extradataArray = array_merge(
				$extradataArray,
				[ 'purpose' => $purpose ]
			);
		}

		return $extradataArray;
	}

	private function pre_populate_elements_with_translation_memory( array $elements, $source_lang, $target_lang ) {
		$strings_to_translate = array();

		foreach ( $elements as &$element ) {

			if ( preg_match( '/^package-string/', $element->field_type ) ) {
				$strings_to_translate[ $element->tid ] = base64_decode( $element->field_data );
			}

			$element->has_previous_translation = '' === trim( $element->field_data_translated ) ? false : true;

			$element->translated_from_memory = FieldId::is_any_term_field( $element->field_type )
				&& $element->field_data_translated
				&& $element->field_data !== $element->field_data_translated;
		}

		$original_translated_map = $this->get_original_translated_map_from_translation_memory( $strings_to_translate, $source_lang, $target_lang );

		if ( $original_translated_map ) {

			foreach ( $elements as &$element ) {

				if ( array_key_exists( $element->tid, $strings_to_translate )
					 && array_key_exists( $strings_to_translate[ $element->tid ], $original_translated_map )
				) {
					$element->field_data_translated = base64_encode( $original_translated_map[ $strings_to_translate[ $element->tid ] ] );
					$element->translated_from_memory = true;
				}
			}
		}

		return $elements;
	}

	private function get_translation_unit_data(
		$field_id,
		$field_name,
		$field_data,
		$field_data_translated,
		$is_suggested_translation = false,
		$field_wrap_tag = '',
		$extradata = [],
		$is_translation_memory_outdated = false,
		$completed_by_a_human = false
	) {
		global $sitepress;

		if ( null === $field_data ) {
			$field_data = '';
		}
		if ( null === $field_data_translated ) {
			$field_data_translated = '';
		}

		$field_data = $this->remove_invalid_chars( $field_data );

		$translation_unit = array();

		$field_data            = $this->remove_line_breaks_inside_tags( $field_data );
		$field_data_translated = $this->remove_line_breaks_inside_tags( $field_data_translated );

		if ( $sitepress->get_setting( 'xliff_newlines' ) === WPML_XLIFF_TM_NEWLINES_REPLACE ) {
			$field_data            = $this->replace_new_line_with_tag( $field_data );
			$field_data_translated = $this->replace_new_line_with_tag( $field_data_translated );
		}

		$translation_unit['attributes']['resname']  = $field_name;
		$translation_unit['attributes']['restype']  = 'string';
		$translation_unit['attributes']['datatype'] = 'html';
		$translation_unit['attributes']['id']       = $field_id;
		$translation_unit['source']                 = array( 'content' => $field_data );
		$translation_unit['target']                 = array( 'content' => $field_data_translated );
		$translation_unit['note']                   = array( 'content' => $field_wrap_tag );

		if ( $extradata && $this->is_xliff_for_ate ) {
			$translation_unit['extradata'] = $extradata;
		}

		if ( $is_suggested_translation && '' !== $field_data_translated ) {
			$translation_unit['target']['attributes'] = array(
				'state'           => 'needs-review-translation',
				'state-qualifier' => CurrentTranslation::is( $field_data, $field_data_translated, $is_translation_memory_outdated, $completed_by_a_human )
					? 'tm-suggestion'
					: 'leveraged-tm',
			);
		}

		return $translation_unit;
	}

	protected function replace_new_line_with_tag( $string ) {
		return str_replace( array( "\n", "\r" ), array( '<br class="xliff-newline" />', '' ), $string );
	}

	private function remove_line_breaks_inside_tags( $string ) {
		return preg_replace_callback( $this->get_valid_html_tag_regex(), array( $this, 'remove_line_breaks_inside_tag_callback' ), $string );
	}

	private function get_valid_html_tag_regex() {
		if ( ! $this->valid_html_tag_regex ) {
			$allowed_tags               = implode( '|', array_keys( (array) wp_kses_allowed_html( 'post' ) ) );
			$this->valid_html_tag_regex = '/(<\s*(?:' . $allowed_tags . ')\b[^>]*>)/m';

		}

		return $this->valid_html_tag_regex;
	}

	private function remove_line_breaks_inside_tag_callback( array $matches ) {
		$parts = preg_split(
			'/("[^"]*"|\'[^\']*\')/',
			$matches[0],
			-1,
			PREG_SPLIT_DELIM_CAPTURE | PREG_SPLIT_NO_EMPTY
		);

		if ( ! is_array( $parts ) ) {
			return $matches[0];
		}

		$tag_string = '';
		foreach ( $parts as $part ) {
			if ( self::is_quoted_value( $part ) ) {
				$tag_string .= $part;
				continue;
			}

			$part        = preg_replace( '/[\n\r\t ]+/', ' ', $part );
			$part        = preg_replace( '/<[\s]+/', '<', $part );
			$part        = preg_replace( '/[\s]+>/', '>', $part );
			$tag_string .= $part;
		}

		return $tag_string;
	}

	private static function is_quoted_value( $part ) {
		if ( strlen( $part ) < 2 ) {
			return false;
		}

		$first = $part[0];
		$last  = substr( $part, -1 );

		return ( '"' === $first && '"' === $last ) || ( "'" === $first && "'" === $last );
	}

	private function remove_invalid_chars( $string ) {
		return preg_replace( '/[\x00-\x08\x0B-\x0C\x0E-\x1F]/', '', $string );
	}

	private function generate_xliff_file( $xliff_content ) {
		$file = fopen( 'php://temp', 'rb+' );

		if ( $file ) {
			fwrite( $file, $xliff_content );
			rewind( $file );
		}

		return $file;
	}

	private function get_external_url( $job ) {
		$external_file_url = null;
		if ( isset( $job->original_doc_id ) && 'post' === $job->element_type_prefix ) {
			$external_file_url = get_permalink( $job->original_doc_id );

			return $external_file_url;
		}

		return $external_file_url;
	}

	private function is_valid_unit_content( $content ) {
		$stripped = preg_replace(
			'/[\x{00A0}\x{1680}\x{180E}\x{2000}-\x{200D}\x{2028}\x{2029}\x{202F}\x{205F}\x{2060}\x{3000}\x{FEFF}\x{FFFE}]/u',
			'',
			(string) $content
		);

		if ( null !== $stripped ) {
			$content = $stripped;
		}

		return '' !== trim( $content );
	}

	private function get_translator_notes( $job ) {
		$this->translator_notes = new WPML_TM_XLIFF_Translator_Notes(
			isset( $job->original_doc_id ) ? $job->original_doc_id : 0
		);
	}

	private function get_image_id_from_url( $image_url ) {
		if ( ! is_string( $image_url ) || trim( $image_url ) === '' ) {
			return false;
		}

		$original_image_url = $this->is_sized( $image_url ) ? $this->get_original_image_url( $image_url ) : $image_url;

		$image_id = false !== $original_image_url ? Attachment::idFromUrl( $original_image_url ) : null;

		if ( false !== $original_image_url && ! $image_id ) {
			$scaled_url = preg_replace( '/(\.\w+)$/', '-scaled$1', $original_image_url );
			$image_id   = Attachment::idFromUrl( $scaled_url );
		}

		if ( $image_id ) {
			return $image_id;
		}

		return false;
	}

	private function get_image_meta_by_id( $image_id, $image_url ) {
		$metadata = wp_get_attachment_metadata( $image_id );

		if ( isset( $metadata['sizes'] ) && preg_match( '/-(\d+)x(\d+)\.\w+$/', $image_url, $matches ) ) {
			$url_width  = (int) $matches[1];
			$url_height = (int) $matches[2];

			foreach ( $metadata['sizes'] as $meta_info ) {
				if ( $meta_info['width'] === $url_width && $meta_info['height'] === $url_height ) {
					return $meta_info;
				}
			}
		} elseif ( is_array( $metadata ) ) {
			return $metadata;
		}

		return null;
	}

	private function get_original_image_url( $sized_url ) {
		if ( ! is_string( $sized_url ) || trim( $sized_url ) === '' ) {
			return false;
		}

		$upload_dir = wp_upload_dir();

		if ( ! isset( $upload_dir['baseurl'] ) || ! isset( $upload_dir['basedir'] ) ) {
			return false;
		}

		$relative_thumbnail_path = str_replace( $upload_dir['baseurl'] . '/', '', $sized_url );
		$original_path           = preg_replace( '/-\d+x\d+(?=\.\w+$)/', '', $relative_thumbnail_path );
		$original_full_path      = $upload_dir['basedir'] . '/' . $original_path;

		if ( $this->check_file_exists( $original_full_path ) ) {
			return $upload_dir['baseurl'] . '/' . $original_path;
		}

		return false;
	}

	public function check_file_exists( $file ) {
		return file_exists( $file );
	}

	private function is_sized( $image_url ) {
		if ( ! is_string( $image_url ) ) {
			return false;
		}

		return preg_match( '/-\d+x\d+\.\w+$/', $image_url ) === 1;
	}

	private function get_thumbnail_url( $original_file_url, $thumbnail_filename ) {

		if ( ! is_string( $original_file_url ) || ! is_string( $thumbnail_filename ) || trim( $original_file_url ) === '' || trim( $thumbnail_filename ) === '' ) {
			return null;
		}

		$upload_dir = wp_upload_dir();

		if ( ! isset( $upload_dir['baseurl'] ) ) {
			return null;
		}

		$relative_path = str_replace( $upload_dir['baseurl'] . '/', '', $original_file_url );
		$directory     = dirname( $relative_path );

		return trailingslashit( $upload_dir['baseurl'] ) . trailingslashit( $directory ) . $thumbnail_filename;
	}

	private function has_previous_non_wpml_editor_job( $job ) {
		if ( ! isset( $job->job_id ) ) {
			return false;
		}

		if ( isset( $job->editor ) && $this->is_non_wpml_editor( $job->editor ) ) {
			return true;
		}

		$previous_job = Jobs::getPreviousJob( $job->job_id );

		return is_object( $previous_job ) && $this->is_non_wpml_editor( $previous_job->editor );
	}

	private function is_non_wpml_editor( $editor ) {
		return ! in_array( $editor, [ WPML_TM_Editors::ATE, WPML_TM_Editors::WPML ], true );
	}

	private function load_human_translated_fields( $job ) {
		if ( ! isset( $job->job_id ) ) {
			return array();
		}

		$previous_job = Jobs::getPreviousJob( $job->job_id );

		if ( ! is_object( $previous_job ) || ! $this->is_completed_by_a_human( $previous_job ) ) {
			return array();
		}

		global $wpdb;

		$rows = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT field_type, field_data_translated
				FROM {$wpdb->prefix}icl_translate
				WHERE job_id = %d AND field_translate = 1",
				(int) $previous_job->job_id
			)
		);

		$fields = array();
		foreach ( (array) $rows as $row ) {
			$value = FieldCompression::decompress( $row->field_data_translated );
			if ( '' !== trim( (string) $value ) ) {
				$fields[ $row->field_type ] = (string) $value;
			}
		}

		return $fields;
	}

	private function is_completed_by_a_human( $jobRow ) {
		$isFinished = 1 === (int) $jobRow->translated;
		if ( ! $isFinished ) {
			return false;
		}

		$writtenInAnEditorAPersonUses = in_array( $jobRow->editor, [ WPML_TM_Editors::WP, WPML_TM_Editors::WPML ], true );
		$ownedByATranslator           = ! (int) $jobRow->automatic && (int) $jobRow->translator_id > 0;

		return $writtenInAnEditorAPersonUses || $ownedByATranslator;
	}

	private function get_wp_editor_extra_units( $job ) {
		if ( ! $this->is_xliff_for_ate ) {
			return [];
		}

		if ( ! $this->has_previous_non_wpml_editor_job( $job ) ) {
			return [];
		}

		if ( ! isset( $job->elements ) || ! is_array( $job->elements ) ) {
			return [];
		}

		$extra_units = [];
		$extradata = null;

		foreach ( $job->elements as $element ) {
			if ( 1 === (int) $element->field_translate ) {
				continue;
			}

			if ( 'body' === $element->field_type ) {
				$original_post_id   = isset( $job->original_doc_id ) ? (int) $job->original_doc_id : 0;
				$translated_post_id = isset( $job->element_type_prefix, $job->element_id ) && 'post' === $job->element_type_prefix
					? (int) $job->element_id
					: 0;

				$pb_source = $this->get_element_uid_markup( $original_post_id );
				$pb_target = $this->get_element_uid_markup( $translated_post_id );

				if ( null !== $pb_source && null !== $pb_target ) {
					$source = $this->remove_invalid_chars( $pb_source );
					$target = $this->remove_invalid_chars( $pb_target );
				} else {
					$source = base64_decode( $element->field_data );
					$source = $this->remove_invalid_chars( $source );
					$source = $this->remove_line_breaks_inside_tags( $source );

					$target = base64_decode( $element->field_data_translated );
					$target = $this->remove_invalid_chars( $target );
					$target = $this->remove_line_breaks_inside_tags( $target );

					if ( ! $this->is_valid_unit_content( $target ) ) {
						continue;
					}

					$source = $this->decorate_wp_editor_markup( $source, $original_post_id );
					$target = $this->decorate_wp_editor_markup( $target, $translated_post_id );
				}

				if ( null === $extradata ) {
					$extradata = $this->get_wp_editor_extradata( $job );
				}

				$extra_units[] = $this->build_wp_editor_translation_unit(
					$element->field_type,
					$source,
					$target,
					$extradata
				);
			}
		}

		return $extra_units;
	}

	private function get_wp_editor_extradata( $job ) {
		$extradata = [];

		$modified_timestamp = $this->get_translation_modified_timestamp( $job );

		if ( null !== $modified_timestamp ) {
			$extradata[ self::WP_EDITOR_MODIFIED_TIMESTAMP ] = $modified_timestamp;
		}

		$extradata[ self::WP_EDITOR_GENERATED_TIMESTAMP ] = time();

		return $extradata;
	}

	private function get_translation_modified_timestamp( $job ) {
		$element_type_prefix = isset( $job->element_type_prefix ) ? $job->element_type_prefix : '';

		if ( 'post' !== $element_type_prefix ) {
			return null;
		}

		$element_id = isset( $job->element_id ) ? (int) $job->element_id : 0;

		if ( ! $element_id ) {
			return null;
		}

		$post = get_post( $element_id );

		if ( ! $post instanceof WP_Post ) {
			return null;
		}

		$modified = $post->post_modified_gmt;

		if ( empty( $modified ) || '0000-00-00 00:00:00' === $modified ) {
			return null;
		}

		$timestamp = strtotime( $modified . ' UTC' );

		return false === $timestamp ? null : $timestamp;
	}

	private function build_wp_editor_translation_unit( $type, $source, $target, array $extradata = [] ) {
		$unit = [
			'attributes' => [
				'resname'   => 'wpml-wp-editor:' . $type,
				'restype'   => 'string',
				'datatype'  => 'html',
				'id'        => 'wpml-wp-editor:' . $type,
				'translate' => 'no',
			],
			'source'     => [ 'content' => $source ],
			'target'     => [ 'content' => $target ],
			'note'       => [ 'content' => '' ],
		];

		if ( $extradata ) {
			$unit['extradata'] = $extradata;
		}

		return $unit;
	}

	private function get_element_uid_markup( $post_id ) {
		if ( $post_id <= 0 ) {
			return null;
		}

		$markup = apply_filters( 'wpml_pb_element_uid_markup', null, $post_id );

		return is_string( $markup ) && '' !== $markup ? $markup : null;
	}

	private function decorate_wp_editor_markup( $markup, $post_id ) {
		if ( ! function_exists( 'has_blocks' ) || ! has_blocks( $markup ) ) {
			return $markup;
		}

		$block_uids = apply_filters( 'wpml_pb_block_uids', [], $post_id, $markup );
		$block_uids = is_array( $block_uids ) ? $block_uids : [];

		$uid_timestamps = apply_filters( 'wpml_pb_block_uid_timestamps', [], $post_id );

		$block_texts = apply_filters( 'wpml_pb_block_uid_texts', [], $post_id, $markup );
		$block_texts = is_array( $block_texts ) ? $block_texts : [];

		$blocks  = parse_blocks( $markup );
		$changed = false;

		$this->decorate_blocks_with_uids( $blocks, '', $block_uids, $uid_timestamps, $changed, $block_texts );

		return $changed ? serialize_blocks( $blocks ) : $markup;
	}

	private function decorate_blocks_with_uids( array &$blocks, $prefix, array $block_uids, array $uid_timestamps, &$changed, array $block_texts = [] ) {
		foreach ( $blocks as $position => &$block ) {
			$path = '' === $prefix ? (string) $position : $prefix . '.' . $position;

			if ( ! empty( $block['blockName'] ) ) {
				$uid = isset( $block_uids[ $path ] ) ? (string) $block_uids[ $path ] : '';

				if ( '' === $uid && ! empty( $block['attrs']['wpmlUid'] ) ) {
					$uid = (string) $block['attrs']['wpmlUid'];
				}

				if ( '' !== $uid && ! empty( $block['innerContent'] ) ) {
					$this->stamp_block_with_uid( $block, $uid, $uid_timestamps, $changed );
				} elseif ( '' !== $uid && isset( $block_texts[ $path ] ) && '' !== (string) $block_texts[ $path ] ) {
					$this->stamp_void_block_with_uid( $block, $uid, (string) $block_texts[ $path ], $uid_timestamps, $changed );
				}
			}

			if ( ! empty( $block['innerBlocks'] ) ) {
				$this->decorate_blocks_with_uids( $block['innerBlocks'], $path, $block_uids, $uid_timestamps, $changed, $block_texts );
			}
		}
		unset( $block );
	}

	private function stamp_block_with_uid( array &$block, $uid, array $uid_timestamps, &$changed ) {
		$attributes = ' data-wpml-uid="' . esc_attr( $uid ) . '"';

		if ( isset( $uid_timestamps[ $uid ]['created'] ) ) {
			$attributes .= ' data-wpml-created="' . (int) $uid_timestamps[ $uid ]['created'] . '"';
		}
		if ( isset( $uid_timestamps[ $uid ]['modified'] ) ) {
			$attributes .= ' data-wpml-modified="' . (int) $uid_timestamps[ $uid ]['modified'] . '"';
		}

		foreach ( $block['innerContent'] as $index => $chunk ) {
			if ( null === $chunk || false === strpos( $chunk, '<' ) ) {
				continue;
			}

			$decorated = preg_replace( '/<([a-zA-Z][a-zA-Z0-9:-]*)/', '<$1' . $attributes, $chunk, 1 );

			if ( null !== $decorated && $decorated !== $chunk ) {
				$block['innerContent'][ $index ] = $decorated;

				unset( $block['attrs']['wpmlUid'] );

				$changed = true;
			}

			break;
		}
	}

	private function stamp_void_block_with_uid( array &$block, $uid, $text, array $uid_timestamps, &$changed ) {
		$attributes = ' data-wpml-uid="' . esc_attr( $uid ) . '"';

		if ( isset( $uid_timestamps[ $uid ]['created'] ) ) {
			$attributes .= ' data-wpml-created="' . (int) $uid_timestamps[ $uid ]['created'] . '"';
		}

		if ( isset( $uid_timestamps[ $uid ]['modified'] ) ) {
			$attributes .= ' data-wpml-modified="' . (int) $uid_timestamps[ $uid ]['modified'] . '"';
		}

		$wrapper               = '<div' . $attributes . '>' . force_balance_tags( $text ) . '</div>';
		$block['innerHTML']    = $wrapper;
		$block['innerContent'] = [ $wrapper ];
		unset( $block['attrs']['wpmlUid'] );
		$changed = true;
	}
}
