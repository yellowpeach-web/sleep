<?php

class WPML_TM_Records {
	public $wpdb;

	private $cache = array(
		'icl_translations' => array(),
		'status'           => array(),
	);

	private $preloaded_statuses = null;

	private $preloaded_status_ids = array();

	private static $flush_generation = 0;

	private static $element_generation = array();

	private $cache_generation = 0;

	private $snapshot_generation = array();

	private $wpml_post_translations;

	private $wpml_term_translations;

	public function __construct(
		wpdb $wpdb,
		WPML_Post_Translation $wpml_post_translations,
		WPML_Term_Translation $wpml_term_translations
	) {
		$this->wpdb                   = $wpdb;
		$this->wpml_post_translations = $wpml_post_translations;
		$this->wpml_term_translations = $wpml_term_translations;
		$this->cache_generation       = self::$flush_generation;

		self::add_invalidation_hooks();
	}

	private static function add_invalidation_hooks() {
		if ( ! function_exists( 'add_action' ) ) {
			return;
		}

		add_action( 'wpml_translation_update', array( __CLASS__, 'forget_element_on_translation_update' ) );
		add_action( 'icl_set_element_language', array( __CLASS__, 'forget_element_on_set_language' ), 10, 2 );
	}

	public static function forget_element_on_translation_update( $args ) {
		if ( is_array( $args ) && isset( $args['element_id'] ) ) {
			self::forget_element( $args['element_id'] );
		}
	}

	public static function forget_element_on_set_language( $translation_id, $element_id = null ) {
		self::forget_element( $element_id );
	}

	public static function forget_element( $element_id ) {
		$element_id = (int) $element_id;
		if ( ! $element_id ) {
			return;
		}

		self::$element_generation[ $element_id ] = self::element_generation( $element_id ) + 1;
	}

	public static function reset_caches() {
		++self::$flush_generation;
		self::$element_generation = array();
	}

	private static function element_generation( $element_id ) {
		$element_id = (int) $element_id;

		return isset( self::$element_generation[ $element_id ] ) ? self::$element_generation[ $element_id ] : 0;
	}

	private function maybe_drop_flushed_cache() {
		if ( $this->cache_generation === self::$flush_generation ) {
			return;
		}

		$this->cache = array(
			'icl_translations' => array(),
			'status'           => array(),
		);

		$this->snapshot_generation  = array();
		$this->preloaded_statuses   = null;
		$this->preloaded_status_ids = array();
		$this->cache_generation     = self::$flush_generation;
	}

	public function wpdb() {
		return $this->wpdb;
	}

	public function get_post_translations() {
		return $this->wpml_post_translations;
	}

	public function get_term_translations() {
		return $this->wpml_term_translations;
	}

	public function icl_translation_status_by_translation_id( $translation_id ) {
		$this->maybe_drop_flushed_cache();

		if ( ! isset( $this->cache['status'][ $translation_id ] ) ) {
			$this->maybe_preload_translation_statuses();
			$this->cache['status'][ $translation_id ] = new WPML_TM_ICL_Translation_Status( $this->wpdb, $this, $translation_id );
		}

		return $this->cache['status'][ $translation_id ];
	}

	private function maybe_preload_translation_statuses() {
		$wpdb = $this->wpdb;

		if ( null === $this->preloaded_statuses ) {
			$translation_ids = array_map( 'intval', $this->wpml_post_translations->get_translations_ids() );
			if ( $translation_ids ) {
				$this->preloaded_status_ids = array_flip( array_map( 'intval', $translation_ids ) );
				$this->preloaded_statuses   = $this->wpdb->get_results(
					$wpdb->prepare(
						"SELECT status, translation_id
					FROM {$wpdb->prefix}icl_translation_status
					WHERE translation_id in (" . implode( ', ', array_fill( 0, count( $translation_ids ), '%d' ) ) . ')',
						$translation_ids
					)
				);
			} else {
				$this->preloaded_statuses = array();
			}
		}
	}

	public function is_translation_status_preloaded( $translation_id ) {
		$this->maybe_drop_flushed_cache();
		$this->maybe_preload_translation_statuses();

		return isset( $this->preloaded_status_ids[ (int) $translation_id ] );
	}

	public function get_preloaded_translation_status( $translation_id, $rid ) {
		$data = null;
		if ( $this->preloaded_statuses ) {
			foreach ( $this->preloaded_statuses as $status ) {
				if ( $translation_id && $status->translation_id == $translation_id ) {
					$data = $status;
					break;
				} elseif ( $rid && $status->translation_id == $rid ) {
					$data = $status;
					break;
				}
			}
		}

		return $data;
	}

	public function icl_translation_status_by_rid( $rid ) {

		return new WPML_TM_ICL_Translation_Status( $this->wpdb, $this, $rid, 'rid' );
	}

	public function icl_translate_job_by_job_id( $job_id ) {

		return new WPML_TM_ICL_Translate_Job( $this, $job_id );
	}

	public function icl_translations_by_translation_id( $translation_id ) {

		return new WPML_TM_ICL_Translations( $this, $translation_id );
	}

	public function icl_translations_by_element_id_and_type_prefix(
		$element_id,
		$type_prefix
	) {
		$this->maybe_drop_flushed_cache();

		$key        = md5( $element_id . $type_prefix );
		$generation = self::element_generation( $element_id );
		$cached     = isset( $this->cache['icl_translations'][ $key ] );
		$invalid    = $cached
			&& ( ! isset( $this->snapshot_generation[ $key ] ) || $this->snapshot_generation[ $key ] !== $generation );

		if ( ! $cached || $invalid ) {
			$record = $invalid
				? $this->rebuild_icl_translations( $element_id, $type_prefix, $this->cache['icl_translations'][ $key ] )
				: new WPML_TM_ICL_Translations(
					$this,
					array(
						'element_id'  => $element_id,
						'type_prefix' => $type_prefix,
					),
					'id_type_prefix'
				);

			$this->cache['icl_translations'][ $key ] = $record;
			$this->snapshot_generation[ $key ]       = $generation;
		}

		return $this->cache['icl_translations'][ $key ];
	}

	private function rebuild_icl_translations( $element_id, $type_prefix, $current ) {
		$row = $this->live_translations_row( $element_id, $type_prefix );

		if ( ! $row || ! $row->translation_id ) {
			return $current;
		}

		return new WPML_TM_ICL_Translations( $this, $row->translation_id, 'translation_id' );
	}

	private function live_translations_row( $element_id, $type_prefix ) {
		$wpdb = $this->wpdb;

		return $wpdb->get_row(
			$wpdb->prepare(
				"SELECT translation_id, trid, language_code
				 FROM {$wpdb->prefix}icl_translations
				 WHERE element_id = %d
				   AND element_type LIKE %s
				 LIMIT 1",
				$element_id,
				$type_prefix . '%'
			)
		);
	}

	public function trid_by_element_id_and_type_prefix( $element_id, $type_prefix ) {
		$row = $this->live_translations_row( $element_id, $type_prefix );

		return $row && $row->trid ? (int) $row->trid : null;
	}

	public function language_code_by_element_id_and_type_prefix( $element_id, $type_prefix ) {
		$row = $this->live_translations_row( $element_id, $type_prefix );

		return $row && $row->language_code ? $row->language_code : null;
	}

	public function icl_translations_by_trid_and_lang( $trid, $lang ) {
		$this->maybe_drop_flushed_cache();

		$key = md5( $trid . $lang );
		if ( ! isset( $this->cache['icl_translations'][ $key ] ) ) {
			$this->cache['icl_translations'][ $key ] = new WPML_TM_ICL_Translations(
				$this,
				array(
					'trid'          => $trid,
					'language_code' => $lang,
				),
				'trid_lang'
			);
		}

		return $this->cache['icl_translations'][ $key ];
	}

 public function get_element_ids_from_trid( $trid ) {
		$wpdb = $this->wpdb;

		return $wpdb->get_col(
			$wpdb->prepare(
				"SELECT element_id
				 FROM {$wpdb->prefix}icl_translations
				 WHERE trid = %d",
				$trid
			)
		);
}

}
