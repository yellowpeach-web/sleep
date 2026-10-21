<?php

class WPML_TM_ICL_Translations extends WPML_TM_Record_User {

	private $table = 'icl_translations';

	private $fields = array();

	private $related = array();

	private $wpdb;

	private $translation_id = 0;

	private $post_translations;

	private $term_translations;

	public function __construct( &$tm_records, $id, $type = 'translation_id' ) {
		$this->wpdb              = $tm_records->wpdb();
		$this->post_translations = $tm_records->get_post_translations();
		$this->term_translations = $tm_records->get_term_translations();

		parent::__construct( $tm_records );
		if ( $id > 0 && $type === 'translation_id' ) {
			$this->{$type} = $id;
		} elseif ( $type === 'id_type_prefix' && isset( $id['element_id'] ) && isset( $id['type_prefix'] ) ) {
			$this->build_from_element_id( $id );
		} elseif ( $type === 'trid_lang' && isset( $id['trid'] ) && isset( $id['language_code'] ) ) {
			$this->build_from_trid( $id );
		} else {
			throw new InvalidArgumentException(
				esc_html(
					sprintf(
						'Unknown column: %s or invalid id: %s',
						(string) $type,
						(string) wp_json_encode( $id )
					)
				)
			);
		}
	}

	private function build_from_element_id( $id ) {
		if ( 'post' === $id['type_prefix'] ) {
			$this->translation_id = $this->post_translations->get_translation_id( $id['element_id'] );
		}
		if ( 'term' === $id['type_prefix'] ) {
			$this->translation_id = $this->term_translations->get_translation_id( $id['element_id'] );
		}
		if ( ! $this->translation_id ) {
			$this->select_translation_id(
				'element',
				array( $id['element_id'], $id['type_prefix'] . '%' )
			);
		}
	}

	private function build_from_trid( $id ) {
		$this->select_translation_id(
			'trid',
			array( $id['trid'], $id['language_code'] )
		);
	}

	public static function prime_translations_cache( $wpdb, array $trids ) {
		$cache = new WPML_WP_Cache( 'WPML_TM_ICL_Translations::translations' );

		$missing = array();
		foreach ( array_unique( array_filter( array_map( 'intval', $trids ) ) ) as $trid ) {
			$found = false;
			$cache->get( $trid, $found );
			if ( ! $found ) {
				$missing[] = $trid;
			}
		}

		if ( ! $missing ) {
			return;
		}

		$grouped = array_fill_keys( $missing, array() );

		$rows = $wpdb->get_results(
			"SELECT translation_id, language_code, trid
			 FROM {$wpdb->prefix}icl_translations
			 WHERE trid IN (" . implode( ',', $missing ) . ')'
		);

		foreach ( (array) $rows as $row ) {
			$grouped[ (int) $row->trid ][] = (object) array(
				'translation_id' => $row->translation_id,
				'language_code'  => $row->language_code,
			);
		}

		foreach ( $grouped as $trid => $translation_ids ) {
			$cache->set( $trid, $translation_ids );
		}
	}

	public function translations() {
		if ( false === (bool) $this->related ) {
			$wpdb = $this->wpdb;
			$trid = $this->trid();

			$found           = false;
			$cache           = new WPML_WP_Cache( 'WPML_TM_ICL_Translations::translations' );
			$translation_ids = $cache->get( $trid, $found );

			if ( ! $found ) {
				$translation_ids = $this->wpdb->get_results(
					$wpdb->prepare(
						"SELECT translation_id, language_code
						FROM {$wpdb->prefix}icl_translations
						WHERE trid = %d",
						$trid
					)
				);

				$cache->set( $trid, $translation_ids );
			}

			foreach ( $translation_ids as $row ) {
				$this->related[ $row->language_code ] = $this->tm_records
					->icl_translations_by_translation_id( $row->translation_id );
			}
		}

		return $this->related;
	}

	private function select_by( $function, $field ) {
		$result = call_user_func( array( $this->post_translations, $function ), $this->translation_id );
		$result = $result ? $result : call_user_func( array( $this->term_translations, $function ), $this->translation_id );
		$result = $result ? $result : $this->select_field( $field );

		return $result;
	}

	public function trid() {
		return $this->select_by( 'get_trid_from_translation_id', 'trid' );
	}

	public function translation_id() {

		return $this->translation_id;
	}

	public function element_id() {
		return $this->select_by( 'get_element_from_translation_id', 'element_id' );
	}

	public function language_code() {

		return $this->select_field( 'language_code' );
	}

	public function source_language_code() {

		$lang = $this->post_translations->get_source_lang_from_translation_id( $this->translation_id );
		$lang = $lang['found'] ? $lang : $this->term_translations->get_source_lang_from_translation_id( $this->translation_id );
		$lang = $lang['found'] ? $lang['code'] : $this->select_field( 'source_language_code' );
		return $lang;
	}

	public function delete() {
		$this->tm_records
			->icl_translation_status_by_translation_id( $this->translation_id )
			->delete();
		$this->wpdb->delete(
			$this->wpdb->prefix . $this->table,
			$this->get_args()
		);

		return $this;
	}

	private function select_field( $field ) {
		$wpdb = $this->wpdb;
		if ( ! in_array( $field, array( 'trid', 'element_id', 'language_code', 'source_language_code' ), true ) ) {
			return null;
		}

		$this->fields[ $field ] = isset( $this->fields[ $field ] ) ? $this->fields[ $field ] : $wpdb->get_var(
			$wpdb->prepare(
				' SELECT ' . esc_sql( $field ) . "
										FROM {$wpdb->prefix}icl_translations
										WHERE translation_id = %d
										LIMIT 1",
				$this->translation_id
			)
		);

		return $this->fields[ $field ];
	}

	private function get_args() {

		return array( 'translation_id' => $this->translation_id );
	}

	private function select_translation_id( $lookup, $prepare_args ) {
		$wpdb = $this->wpdb;

		if ( 'element' === $lookup ) {
			$this->translation_id = $wpdb->get_var(
				$wpdb->prepare(
					"SELECT translation_id FROM {$wpdb->prefix}icl_translations
					WHERE element_id = %d AND element_type LIKE %s
					LIMIT 1",
					$prepare_args[0],
					$prepare_args[1]
				)
			);
		} else {
			$this->translation_id = $wpdb->get_var(
				$wpdb->prepare(
					"SELECT translation_id FROM {$wpdb->prefix}icl_translations
					WHERE trid = %d AND language_code = %s
					LIMIT 1",
					$prepare_args[0],
					$prepare_args[1]
				)
			);
		}
		if ( ! $this->translation_id ) {
			throw new InvalidArgumentException(
				esc_html(
					sprintf(
						'No translation entry found for %s lookup: %s',
						$lookup,
						(string) wp_json_encode( $prepare_args )
					)
				)
			);
		}
	}
}
