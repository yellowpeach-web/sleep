<?php

use \WPML\FP\Fns;
use \WPML\TM\Jobs\FieldId;
use \WPML\FP\Logic;
use \WPML\FP\Lst;
use \WPML\FP\Either;
use \WPML\LIB\WP\Post;
use WPML\Translation\TranslationElements\FieldCompression;
use function \WPML\FP\curryN;
use function \WPML\FP\pipe;
use function \WPML\FP\invoke;
use WPML\TM\Jobs\Utils;
use WPML\FP\Relation;
use WPML\FP\Obj;
use WPML\TM\Jobs\JobLog;
use WPML\TM\Taxonomy\TranslatableTermMeta;

class WPML_Element_Translation_Package extends WPML_Translation_Job_Helper {

	const PACKAGE_TYPE_EXTERNAL = 'external';
	const PACKAGE_TYPE_POST     = 'post';

	const POST_IS_ORIGINAL = 'original';
	const POST_IS_UNKNOWN  = 'unknown';

	private $wp_api;

	private static $cache = [
		self::PACKAGE_TYPE_EXTERNAL => [],
		self::PACKAGE_TYPE_POST     => [],
	];

	public function __construct( ?WPML_WP_API $wp_api = null ) {
		global $sitepress;
		if ( $wp_api ) {
			$this->wp_api = $wp_api;
		} else {
			$this->wp_api = $sitepress->get_wp_api();
		}
	}

	private function getCached( $originalId, $packageType, $isOriginal = false ) {
		$originalPath = $isOriginal ? self::POST_IS_ORIGINAL : self::POST_IS_UNKNOWN;
		return Obj::pathOr( null, [ $packageType, $originalId, $originalPath ], self::$cache );
	}

	private function setCached( $originalId, $packageType, $package, $isOriginal = false ) {
		if ( ! in_array( $packageType, [ self::PACKAGE_TYPE_EXTERNAL, self::PACKAGE_TYPE_POST ], true ) ) {
			return;
		}
		$originalPath = $isOriginal ? self::POST_IS_ORIGINAL : self::POST_IS_UNKNOWN;
		self::$cache[ $packageType ][ $originalId ][ $originalPath ] = $package;
	}

	public function clearCache() {
		self::$cache = [
			self::PACKAGE_TYPE_EXTERNAL => [],
			self::PACKAGE_TYPE_POST     => [],
		];
	}

	public function create_translation_package( $post, $isOriginal = false ) {

		$package = array();
		$post    = is_numeric( $post ) ? get_post( $post ) : $post;
		if ( ! $post ) {
			return $package;
		}
		if ( apply_filters( 'wpml_is_external', false, $post ) ) {
			$original_id = $post->ID;

			JobLog::add( 'Creating external translation package for post `' . $original_id . '`' );

			$type          = self::PACKAGE_TYPE_EXTERNAL;
			$cachedPackage = $this->getCached( $original_id, $type, $isOriginal );
			if ( null !== $cachedPackage ) {
				JobLog::add( 'Found cached package for post `' . $original_id . '`' );
				return $cachedPackage;
			}

			$post_contents = (array) $post->string_data;
			JobLog::add(
				'Creating post contents in translation package for post `' . $original_id . '`',
				$post_contents
			);

			if ( isset( $post->title ) ) {
				$package['title'] = apply_filters( 'wpml_tm_external_translation_job_title', $post->title, $original_id );
			}

			if ( empty( $package['title'] ) ) {
				$package['title'] = sprintf(
					/* translators: The placeholder will be replaced with a number (an ID) */
					__( 'External package ID: %d', 'sitepress' ),
					$original_id
				);
			}
		} else {
			$original_id   = $post->ID;

			JobLog::add( 'Creating translation package for post `' . $original_id . '`' );

			$type          = self::PACKAGE_TYPE_POST;
			$cachedPackage = $this->getCached( $original_id, $type, $isOriginal );
			if ( null !== $cachedPackage ) {
				JobLog::add( 'Found cached package for post `' . $original_id . '`' );
				return $cachedPackage;
			}

			$home_url         = get_home_url();
			$package['url']   = htmlentities( $home_url . '?' . ( 'page' === $post->post_type ? 'page_id' : 'p' ) . '=' . ( $post->ID ) );
			$package['title'] = $post->post_title;

			$post_contents = array(
				'title'   => $post->post_title,
				'body'    => $post->post_content,
				'excerpt' => $post->post_excerpt,
			);

			if ( wpml_get_setting_filter( false, 'translated_document_page_url' ) === 'translate' ) {
				$post_contents['URL'] = $post->post_name;
			}

			$custom_fields_to_translate = \WPML\TM\Settings\Repository::getCustomFieldsToTranslate();

			if ( isset( $post->ID ) ) {
				$post_meta                  = get_post_custom( $post->ID );
				$resolved                   = wpml_resolve_custom_field_preferences( is_array( $post_meta ) ? array_keys( $post_meta ) : [] );
				$custom_fields_to_translate = array_unique(
					array_merge( $custom_fields_to_translate, array_keys( $resolved, WPML_TRANSLATE_CUSTOM_FIELD, true ) )
				);
			}

			if ( ! empty( $custom_fields_to_translate ) ) {
				$package = $this->add_custom_field_contents(
					$package,
					$post,
					$custom_fields_to_translate,
					$this->get_tm_setting( array( 'custom_fields_encoding' ) )
				);
			}

			$post_contents = array_merge( $post_contents, $this->get_taxonomy_fields( $post ) );
			JobLog::add(
				'Creating post contents in translation package for post `' . $original_id . '`',
				$post_contents
			);
		}

		$package['contents']['original_id'] = array(
			'translate' => 0,
			'data'      => $original_id,
		);
		$package['type']                    = $type;

		$package['contents'] = $this->buildEntries( $package['contents'], $post_contents );

		$package = apply_filters( 'wpml_tm_translation_job_data', $package, $post, $isOriginal );
		$this->setCached( $original_id, $type, $package, $isOriginal );
		return $package;
	}

	public function do_action_before_creating_translation_package( $element ) {
		if ( $element instanceof \WP_Post ) {
			do_action( 'wpml_pb_register_all_strings_for_translation', $element );
		}
	}

	public function filter_translation_package_for_lang( $package, $element, $lang, $sendFrom = null ) {
		$package = apply_filters( 'wpml_translation_package_by_language', $package, $element, $lang, $sendFrom );

		return $package;
	}

	private function buildEntries( $contents, $entries, $parentKey = '' ) {
		foreach ( $entries as $key => $entry ) {
			$fullKey = $parentKey ? $parentKey . '_' . $key : $key;

			if ( is_array( $entry ) || is_object( $entry ) ) {
				$contents = $this->buildEntries( $contents, (array) $entry, $fullKey );
			} else {
				$contents[ $fullKey ] = [
					'translate' => 1,
					'data'      => base64_encode( $entry ),
					'format'    => 'base64',
				];
			}
		}

		return $contents;
	}

	public function save_package_to_job( array $translation_package, $job_id, $prev_translation, $addJobLogs = false ) {
		global $wpdb;

		$show = $wpdb->hide_errors();

		$rows = [];
		$args = [];

		$translation_package['contents'] = $this->dropTermFieldsCoveredInJobLanguage(
			(array) $translation_package['contents'],
			(int) $job_id
		);

		foreach ( $translation_package['contents'] as $field => $value ) {
			$job_translate = array(
				'job_id'                => $job_id,
				'content_id'            => 0,
				'field_type'            => $field,
				'field_wrap_tag'        => isset( $value['wrap_tag'] ) ? $value['wrap_tag'] : '',
				'field_format'          => isset( $value['format'] ) ? $value['format'] : '',
				'field_translate'       => $value['translate'],
				'field_data'            => $value['data'],
				'field_data_translated' => '',
				'field_finished'        => 0,
			);

			if ( array_key_exists( $field, $prev_translation ) && ! empty( $value['data'] ) ) {
				$job_translate['field_data_translated'] = $prev_translation[ $field ]->get_translation();
				$job_translate['field_finished']        = $prev_translation[ $field ]->is_finished( $value['data'] );
			}

			$job_translate = $this->filter_non_translatable_fields( $job_translate );

			$field_data = $job_translate['field_format'] === 'base64'
				? FieldCompression::compressAndTrack( $job_translate['field_data'], true, $job_translate['job_id'] )
				: $job_translate['field_data'];
			$field_data_translated = $job_translate['field_format'] === 'base64'
				? FieldCompression::compressAndTrack( $job_translate['field_data_translated'], true, $job_translate['job_id'] )
				: $job_translate['field_data_translated'];

			$rows[] = '(%d, %d, %s, %s, %s, %d, %s, %s, %d)';
			array_push(
				$args,
				(int) $job_translate['job_id'],
				(int) $job_translate['content_id'],
				(string) $job_translate['field_type'],
				(string) $job_translate['field_wrap_tag'],
				(string) $job_translate['field_format'],
				(int) $job_translate['field_translate'],
				(string) $field_data,
				(string) $field_data_translated,
				(int) $job_translate['field_finished']
			);
		}

		if ( $rows ) {
			$wpdb->query(
				$wpdb->prepare(
					"INSERT INTO {$wpdb->prefix}icl_translate
					(job_id, content_id, field_type, field_wrap_tag, field_format, field_translate, field_data, field_data_translated, field_finished)
					VALUES " . implode( ', ', array_fill( 0, count( $rows ), '(%d, %d, %s, %s, %s, %d, %s, %s, %d)' ) ),
					$args[0],
					$args[1],
					$args[2],
					$args[3],
					$args[4],
					$args[5],
					$args[6],
					$args[7],
					...array_slice( $args, 8 )
				)
			);
			if ( $addJobLogs ) {
				JobLog::add( 'New icl_translate records created', $args );
			}
		}

		$wpdb->show_errors( $show );
	}

	private function filter_non_translatable_fields( $job_translate ) {

		if ( $job_translate['field_translate'] ) {
			$data = $job_translate['field_data'];
			if ( 'base64' === $job_translate['field_format'] ) {
				$data = base64_decode( $data );
			}
			$is_translatable = ! WPML_String_Functions::is_not_translatable( $data ) && apply_filters( 'wpml_translation_job_post_meta_value_translated', 1, WPML_TM_Field_Type_Encoding::fromTermMetaFieldType( $job_translate['field_type'] ) );

			$is_translatable = (bool) apply_filters( 'wpml_tm_job_field_is_translatable', $is_translatable, $job_translate, $data );
			if ( ! $is_translatable ) {
				$job_translate['field_translate']       = 0;
				$job_translate['field_data_translated'] = $job_translate['field_data'];
				$job_translate['field_finished']        = 1;
			}
		}

		return $job_translate;
	}

	public function save_job_custom_fields( $job, $post_id, $fields ) {
		$decode_translation = function ( $translation ) {
			return html_entity_decode( str_replace( '&#0A;', "\n", $translation ) );
		};

		$get_field_id = function( $field_name, $el_data ) {
			if (
				strpos( $el_data->field_data, (string) $field_name ) === 0
				&& 1 === preg_match( '/field-(.*?)-name/U', $el_data->field_type, $match )
				&& 1 === preg_match( '/field-' . preg_quote( (string) $field_name, '/' ) . '-[0-9].*?-name/', $el_data->field_type )
			) {
				return $match[1];
			}
			return null;
		};

		$elements = array_values( (array) $job->elements );

		$positions_by_field_type = [];
		$name_rows_by_field_name = [];
		foreach ( $elements as $position => $el_data ) {
			$el_field_type = (string) $el_data->field_type;

			$positions_by_field_type[ $el_field_type ][] = $position;
			foreach ( self::get_field_names_in_field_type( $el_field_type ) as $candidate ) {
				$name_rows_by_field_name[ $candidate ][ $position ] = $el_data;
			}
		}

		$field_names = [];

		foreach ( $fields as $field_name => $val ) {
			if ( '' === (string) $field_name || ! isset( $name_rows_by_field_name[ $field_name ] ) ) {
				continue;
			}

			foreach ( $name_rows_by_field_name[ $field_name ] as $el_data ) {
				$field_id_string = $get_field_id( $field_name, $el_data );
				if ( $field_id_string ) {
					$field_names[ $field_name ] = isset( $field_names[ $field_name ] )
						? $field_names[ $field_name ] : [];

					$value_field_type = 'field-' . $field_id_string;
					$type_field_type  = $value_field_type . '-type';
					$type_position    = isset( $positions_by_field_type[ $type_field_type ] )
						? $positions_by_field_type[ $type_field_type ][0] : null;
					$value_positions  = isset( $positions_by_field_type[ $value_field_type ] )
						? $positions_by_field_type[ $value_field_type ] : [];
					$value_position   = null;
					foreach ( $value_positions as $position ) {
						if ( null !== $type_position && $position > $type_position ) {
							break;
						}
						$value_position = $position;
					}

					$field_translation = false;
					if ( null !== $value_position ) {
						$field_translation = $this->decode_field_data(
							$elements[ $value_position ]->field_data_translated,
							$elements[ $value_position ]->field_format
						);
					}
					if ( null !== $type_position ) {
						$field_type = $elements[ $type_position ]->field_data;
					}
					if ( false !== $field_translation && isset( $field_type ) && 'custom_field' === $field_type ) {
						$field_id_string = $this->remove_field_name_from_start( $field_name, $field_id_string );
						$meta_keys       = wpml_collect( explode( '-', $field_id_string ) )
							->map( [ 'WPML_TM_Field_Type_Encoding', 'decode_hyphen' ] )
							->prepend( $field_name )
							->toArray();
						$field_names     = Utils::insertUnderKeys( $meta_keys, $field_names, $decode_translation( $field_translation ) );
					}
				}
			}
		}

		$this->sync_emptied_translatable_fields(
			$fields,
			$field_names,
			(int) $post_id,
			(int) $job->original_doc_id
		);
		$this->save_custom_field_values( $field_names, $post_id, $job->original_doc_id );
	}

	private static function get_field_names_in_field_type( $field_type ) {
		if (
			! preg_match_all( '/field-/', $field_type, $starts, PREG_OFFSET_CAPTURE )
			|| ! preg_match_all( '/-[0-9]/', $field_type, $ends, PREG_OFFSET_CAPTURE )
		) {
			return [];
		}

		$names = [];
		foreach ( $starts[0] as $start ) {
			$name_start = $start[1] + strlen( 'field-' );
			foreach ( $ends[0] as $end ) {
				if ( $end[1] > $name_start ) {
					$names[] = substr( $field_type, $name_start, $end[1] - $name_start );
				}
			}
		}

		return $names;
	}

	private function remove_field_name_from_start( $field_name, $field_id_string ) {
		return preg_replace( '#' . preg_quote( (string) $field_name, '#' ) . '-?#', '', $field_id_string, 1 );
	}

	private function save_custom_field_values( $fields_in_job, $post_id, $original_post_id ) {
		$encodings   = $this->get_tm_setting( array( 'custom_fields_encoding' ) );
		$stored_meta = $this->get_stored_meta( $post_id );
		$stored_keys    = array();
		$non_ascii_keys = array();
		foreach ( array_keys( $stored_meta ) as $stored_key ) {
			$form = self::get_collation_form( $stored_key );
			if ( null === $form ) {
				$non_ascii_keys[] = $stored_key;
			} else {
				$stored_keys[ $form ][] = $stored_key;
			}
		}
		foreach ( $fields_in_job as $name => $contents ) {
			$contents = (array) $contents;
			$single   = count( $contents ) === 1;
			$encoding = isset( $encodings[ $name ] ) ? $encodings[ $name ] : '';

			$values = [];
			foreach ( $contents as $value ) {

				$value = self::preserve_numerics( $value, $name, $original_post_id, $single, $encoding );
				$value = $encoding ? WPML_Encoding::encode( $value, $encoding ) : $value;
				$value = apply_filters( 'wpml_encode_custom_field', $value, $name, (int) $original_post_id );
				$values[] = $this->prevent_strip_slash_on_json( $value, $encoding );
			}

			if ( $this->is_stored( $stored_meta, $post_id, $name, $values ) ) {
				continue;
			}

			$this->wp_api->delete_post_meta( $post_id, $name );
			foreach ( $values as $value ) {
				$this->wp_api->add_post_meta( $post_id, $name, $value, $single );
			}

			$form = self::get_collation_form( wp_unslash( $name ) );
			if ( null === $form ) {
				$stored_meta    = array();
				$stored_keys    = array();
				$non_ascii_keys = array();
			} else {
				$cleared = isset( $stored_keys[ $form ] ) ? $stored_keys[ $form ] : array();
				foreach ( array_merge( $cleared, $non_ascii_keys ) as $stored_key ) {
					unset( $stored_meta[ $stored_key ] );
				}
				unset( $stored_keys[ $form ] );
				$non_ascii_keys = array();
			}
		}
	}

	private static function get_collation_form( $key ) {
		$key = (string) $key;
		if ( ! preg_match( '/^[\x20-\x7e]*$/', $key ) ) {
			return null;
		}

		return strtolower( rtrim( $key, ' ' ) );
	}

	private static function holds_object( $value ) {
		if ( is_object( $value ) ) {
			return true;
		}
		if ( is_array( $value ) ) {
			foreach ( $value as $item ) {
				if ( self::holds_object( $item ) ) {
					return true;
				}
			}
		}

		return false;
	}

	private function get_stored_meta( $post_id ) {
		$cache = update_meta_cache( 'post', array( (int) $post_id ) );

		return isset( $cache[ (int) $post_id ] ) && is_array( $cache[ (int) $post_id ] ) ? $cache[ (int) $post_id ] : array();
	}

	private function is_stored( $stored_meta, $post_id, $name, $values ) {
		$meta_key = wp_unslash( $name );
		$rows     = isset( $stored_meta[ $meta_key ] ) ? array_values( $stored_meta[ $meta_key ] ) : array();
		if ( ! $rows || count( $rows ) !== count( $values ) ) {
			return false;
		}

		$subtype = get_object_subtype( 'post', (int) $post_id );
		foreach ( $values as $index => $value ) {
			if ( self::holds_object( $value ) ) {
				return false;
			}
			$value = maybe_serialize( sanitize_meta( $meta_key, wp_unslash( $value ), 'post', $subtype ) );
			if ( ! is_scalar( $value ) || (string) $value !== $rows[ $index ] ) {
				return false;
			}
		}

		return true;
	}

	private function sync_emptied_translatable_fields(
		$fields,
		$field_names_in_job,
		$translation_post_id,
		$original_post_id
	) {
		if ( ! $original_post_id || ! $translation_post_id ) {
			return;
		}

		foreach ( $fields as $field_name => $status ) {
			if ( '' === (string) $field_name ) {
				continue;
			}

			if ( (int) $status !== WPML_TRANSLATE_CUSTOM_FIELD ) {
				continue;
			}

			if ( array_key_exists( $field_name, $field_names_in_job ) ) {
				continue;
			}

			$original_values               = get_post_meta( $original_post_id, $field_name );
			$original_has_meaningful_value = ! empty( array_filter( (array) $original_values ) );

			if ( $original_has_meaningful_value ) {
				continue;
			}

			$translation_values = get_post_meta( $translation_post_id, $field_name );

			if ( empty( $original_values ) ) {
				if ( ! empty( $translation_values ) ) {
					$this->wp_api->delete_post_meta( $translation_post_id, $field_name );
				}
				continue;
			}

			if ( $translation_values === $original_values ) {
				continue;
			}

			$this->wp_api->delete_post_meta( $translation_post_id, $field_name );

			$is_single = count( $original_values ) === 1;
			foreach ( $original_values as $value ) {
				$this->wp_api->add_post_meta( $translation_post_id, $field_name, $value, $is_single );
			}
		}
	}

	private function prevent_strip_slash_on_json( $value, $encoding ) {
		if ( in_array( 'json', explode( ',', $encoding ), true ) ) {
			$value = wp_slash( $value );
		}

		return $value;
	}

	private function add_custom_field_contents( $package, $post, $fields_to_translate, $fields_encoding ) {
		foreach ( $fields_to_translate as $key ) {
			$encoding             = isset( $fields_encoding[ $key ] ) ? $fields_encoding[ $key ] : '';
			$custom_fields_values = array_values( array_filter( get_post_meta( $post->ID, $key ) ) );
			foreach ( $custom_fields_values as $index => $custom_field_val ) {
				$custom_field_val = apply_filters( 'wpml_decode_custom_field', $custom_field_val, $key );
				$package          = $this->add_single_field_content( $package, $key, array( $index ), $custom_field_val, $encoding );
			}
		}

		return $package;
	}

	public function get_custom_field_texts( $post, array $keys ) {
		$package = $this->add_custom_field_contents(
			[ 'contents' => [] ],
			$post,
			$keys,
			(array) $this->get_tm_setting( array( 'custom_fields_encoding' ) )
		);

		$texts = [];
		foreach ( $package['contents'] as $field_type => $entry ) {
			if ( ! empty( $entry['translate'] ) ) {
				$texts[ $field_type ] = (string) base64_decode( $entry['data'] );
			}
		}

		return $texts;
	}

	private function add_single_field_content( $package, $key, $custom_field_index, $custom_field_val, $encoding ) {
		if ( $encoding && is_scalar( $custom_field_val ) ) {
			$custom_field_val = WPML_Encoding::decode( $custom_field_val, $encoding );
			$encoding         = '';
		}
		if ( is_scalar( $custom_field_val ) ) {
			list( $cf, $key_index ) = WPML_TM_Field_Type_Encoding::encode( $key, $custom_field_index );
			$package['contents'][ $cf ]           = array(
				'translate' => 1,
				'data'      => base64_encode( (string) $custom_field_val ),
				'format'    => 'base64',
			);
			$package['contents'][ $cf . '-name' ] = array(
				'translate' => 0,
				'data'      => $key_index,
			);
			$package['contents'][ $cf . '-type' ] = array(
				'translate' => 0,
				'data'      => 'custom_field',
			);

		} else {
			foreach ( (array) $custom_field_val as $ind => $value ) {
				$package = $this->add_single_field_content(
					$package,
					$key,
					array_merge( $custom_field_index, array( $ind ) ),
					$value,
					$encoding
				);
			}
		}

		return $package;
	}

	public static function preserve_numerics( $value, $name, $original_post_id, $single, $encoding = '' ) {
		$original = self::get_original_custom_field( $name, $original_post_id, $single, $encoding );

		if ( is_array( $value ) ) {
			return self::preserve_numerics_recursive( $original, $value );
		}

		return self::restore_scalar_type( $original, $value );
	}

	private static function get_original_custom_field( $name, $original_post_id, $single, $encoding ) {
		$meta = get_post_meta( (int) $original_post_id, $name, $single );
		$meta = apply_filters( 'wpml_decode_custom_field', $meta, $name );

		if ( $encoding && is_scalar( $meta ) ) {
			$meta = WPML_Encoding::decode( $meta, $encoding );
		}

		return $meta;
	}

	private static function preserve_numerics_recursive( $original, $value ) {
		if ( ! is_array( $original ) || ! is_array( $value ) ) {
			return self::restore_scalar_type( $original, $value );
		}

		$corresponds = (bool) array_intersect_key( $value, $original );

		$restored = array();
		foreach ( $original as $key => $data ) {
			if ( array_key_exists( $key, $value ) ) {
				$restored[ $key ] = self::preserve_numerics_recursive( $data, $value[ $key ] );
			} elseif ( $corresponds ) {
				$restored[ $key ] = $data;
			}
		}

		foreach ( $value as $key => $data ) {
			if ( ! array_key_exists( $key, $restored ) ) {
				$restored[ $key ] = $data;
			}
		}

		return $restored;
	}

	private static function restore_scalar_type( $original, $value ) {
		if ( is_bool( $original ) ) {
			return $original;
		}
		if ( is_int( $original ) && is_numeric( $value ) ) {
			return (int) $value;
		}
		if ( is_float( $original ) && is_numeric( $value ) ) {
			return (float) $value;
		}

		return $value;
	}

	private function get_taxonomy_fields( $post ) {
		global $sitepress;

		$termMetaKeysToTranslate = self::getTermMetaKeysToTranslate();

		$getTermFields = function ( $term ) {
			return [
				[ FieldId::forTerm( $term->term_taxonomy_id ), $term->name ],
				[ FieldId::forTermDescription( $term->term_taxonomy_id ), $term->description ],
			];
		};

		$getTermMetaFields = curryN(
			2,
			function ( $termMetaKeysToTranslate, $term ) {

				$getMeta = curryN(
					3,
					function ( $termId, $termTaxonomyId, $key ) {
						return (object) [
							'id'   => $termTaxonomyId,
							'key'  => $key,
							'meta' => get_term_meta( $termId, $key ),
						];
					}
				);

				$hasMeta = function ( $termData ) {
					return isset( $termData->meta[0] );
				};

				$makeField = function ( $termData ) {
					return [ FieldId::forTermMeta( $termData->id, $termData->key ), $termData->meta[0] ];
				};

				$get = pipe(
					Fns::map( $getMeta( $term->term_id, $term->term_taxonomy_id ) ),
					Fns::filter( $hasMeta ),
					Fns::map( $makeField )
				);

				return $get( array_unique( array_merge( $termMetaKeysToTranslate, TranslatableTermMeta::resolvedKeysOfTerm( (int) $term->term_id ) ) ) );
			}
		);

		$getAll = Fns::converge( Lst::concat(), [ $getTermFields, $getTermMetaFields( $termMetaKeysToTranslate ) ] );

		return wpml_collect( $sitepress->get_translatable_taxonomies( false, $post->post_type ) )
			->map( Post::getTerms( $post->ID ) )
			->filter( Fns::isRight() )
			->map( invoke( 'get' ) )
			->flatten()
			->map( $getAll )
			->mapWithKeys( Lst::fromPairs() )
			->toArray();
	}

	private function dropTermFieldsCoveredInJobLanguage( array $contents, $jobId ) {
		$termIds = self::termTaxonomyIdsInContents( $contents );
		if ( ! $termIds ) {
			return $contents;
		}

		$lang = $this->jobTargetLanguage( $jobId );
		if ( '' === $lang ) {
			return $contents;
		}

		$covered = self::termsCoveredInLanguage( $termIds, $lang );
		if ( ! $covered ) {
			return $contents;
		}

		foreach ( array_keys( $contents ) as $field ) {
			$field = (string) $field;
			foreach ( $covered as $ttid ) {
				if (
					FieldId::TERM_PREFIX . $ttid === $field
					|| FieldId::TERM_DESCRIPTION_PREFIX . $ttid === $field
					|| ( 0 === strpos( $field, FieldId::TERM_META_FIELD_PREFIX )
						&& preg_match( '/-' . $ttid . '(_.*)?$/', $field ) )
				) {
					unset( $contents[ $field ] );
					break;
				}
			}
		}

		return $contents;
	}

	private static function termTaxonomyIdsInContents( array $contents ) {
		$ids = [];
		foreach ( array_keys( $contents ) as $field ) {
			if ( preg_match( '/^' . FieldId::TERM_PREFIX . '(\d+)$/', (string) $field, $match ) ) {
				$ids[] = (int) $match[1];
			}
		}

		return $ids;
	}

	private function jobTargetLanguage( $jobId ) {
		global $wpdb;

		return (string) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT t.language_code
				FROM {$wpdb->prefix}icl_translate_job j
				JOIN {$wpdb->prefix}icl_translation_status s ON s.rid = j.rid
				JOIN {$wpdb->prefix}icl_translations t ON t.translation_id = s.translation_id
				WHERE j.job_id = %d",
				$jobId
			)
		);
	}

	public static function termsCoveredInLanguage( array $termTaxonomyIds, $lang ) {
		global $wpdb;

		$termTaxonomyIds = array_values( array_unique( array_filter( array_map( 'intval', $termTaxonomyIds ) ) ) );
		if ( ! $termTaxonomyIds || ! $lang ) {
			return [];
		}

		$covered = $wpdb->get_col(
			$wpdb->prepare(
				"SELECT DISTINCT original.element_id
				FROM {$wpdb->prefix}icl_translations original
				JOIN {$wpdb->prefix}icl_translations translation
					ON translation.trid = original.trid
					AND translation.element_type = original.element_type
					AND translation.language_code = %s
				LEFT JOIN {$wpdb->prefix}icl_translation_status status
					ON status.translation_id = translation.translation_id
				WHERE original.element_id IN (" . implode( ', ', array_fill( 0, count( $termTaxonomyIds ), '%d' ) ) . ')
					AND original.element_type LIKE %s
					AND ( translation.element_id IS NOT NULL OR status.status IN ( %d, %d ) )',
				array_merge(
					[ $lang ],
					$termTaxonomyIds,
					[ $wpdb->esc_like( 'tax_' ) . '%', ICL_TM_WAITING_FOR_TRANSLATOR, ICL_TM_IN_PROGRESS ]
				)
			)
		);

		return array_map( 'intval', (array) $covered );
	}

	public static function isTermCoveredInLanguage( $termTaxonomyId, $lang ) {
		return in_array( (int) $termTaxonomyId, self::termsCoveredInLanguage( [ (int) $termTaxonomyId ], (string) $lang ), true );
	}

	public static function getTermMetaKeysToTranslate() {
		$fieldTranslation = new WPML_Custom_Field_Setting_Factory( self::get_core_translation_management() );

		$settingsFactory      = self::get_core_translation_management()->settings_factory();

		$translatableMetaKeys = pipe(
			[ $settingsFactory, 'term_meta_setting' ],
			invoke( 'status' ),
			Relation::equals( WPML_TRANSLATE_CUSTOM_FIELD )
		);

		return wpml_collect( $fieldTranslation->get_term_meta_keys() )
			->filter( $translatableMetaKeys )
			->values()
			->toArray();
	}
}
