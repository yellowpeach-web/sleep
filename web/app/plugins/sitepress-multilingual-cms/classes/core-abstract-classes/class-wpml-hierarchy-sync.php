<?php

abstract class WPML_Hierarchy_Sync extends WPML_WPDB_User {

	const CACHE_GROUP = __CLASS__;

	protected $original_elements_table_alias            = 'org';
	protected $translated_elements_table_alias          = 'tra';
	protected $original_elements_language_table_alias   = 'iclo';
	protected $translated_elements_language_table_alias = 'iclt';
	protected $correct_parent_table_alias               = 'corr';
	protected $correct_parent_language_table_alias      = 'iclc';
	protected $original_parent_table_alias              = 'parents';
	protected $original_parent_language_table_alias     = 'parent_lang';
	protected $element_id_column;
	protected $parent_element_id_column;
	protected $parent_id_column;
	protected $element_type_column;
	protected $element_type_prefix;
	protected $elements_table;
	protected $lang_info_table;

	public function __construct( &$wpdb ) {
		parent::__construct( $wpdb );
		$this->lang_info_table = $wpdb->prefix . 'icl_translations';
		add_action( 'clean_post_cache', [ $this, 'clean_cache' ] );
		add_action( 'set_object_terms', [ $this, 'clean_cache' ] );
		add_action( 'wpml_sync_term_hierarchy_done', [ $this, 'clean_cache' ] );
	}

	public function clean_cache() {
		WPML_Non_Persistent_Cache::flush_group( self::CACHE_GROUP );
	}

	public function get_unsynced_elements( $element_types, $ref_lang_code = false, $element_id = null ) {
		$element_types = (array) $element_types;
		$results       = array();
		if ( $element_types ) {
			$key     = md5( (string) wp_json_encode( array( $element_types, $ref_lang_code, $element_id ) ) );
			$found   = false;
			$results = WPML_Non_Persistent_Cache::get( $key, self::CACHE_GROUP, $found );
			if ( ! $found ) {
				$results = $this->elements_table === $this->wpdb->posts
					? $this->get_unsynced_posts( $element_types, $ref_lang_code, $element_id )
					: $this->get_unsynced_terms( $element_types, $ref_lang_code, $element_id );

				WPML_Non_Persistent_Cache::set( $key, $results, self::CACHE_GROUP );
			}
		}

		return $results;
	}

	public function sync_element_hierarchy( $element_types, $ref_lang_code = false, $element_id = null ) {
		$hierarchical_element_types = wpml_collect( $element_types )->filter( [ $this, 'is_hierarchical' ] );

		if ( $hierarchical_element_types->isEmpty() ) {
			return;
		}

		$unsynced = $this->get_unsynced_elements( $hierarchical_element_types->toArray(), $ref_lang_code, $element_id );

		foreach ( $unsynced as $row ) {
			$this->update_hierarchy_for_element( $row );
		}
	}

	abstract public function is_hierarchical( $element_type );

	private function update_hierarchy_for_element( $row ) {
		$update = $this->validate_parent_synchronization( $row );

		if ( $update ) {
			$target_element_id = $row->translated_id;
			$new_parent        = (int) $row->correct_parent;
			$this->wpdb->update( $this->elements_table, array( $this->parent_id_column => $new_parent ), array( $this->element_id_column => $target_element_id ) );
			wp_cache_delete( $row->translated_id, 'terms' );
		}
	}

	private function validate_parent_synchronization( $row ) {
		$is_valid     = false;
		$is_for_posts = ( $this->elements_table === $this->wpdb->posts );
		if ( ! $is_for_posts ) {
			$is_valid = true;
		}

		if ( $row && $is_for_posts ) {
			global $sitepress;

			$target_element_id = $row->translated_id;
			$target_post       = get_post( $target_element_id );
			if ( $target_post ) {
				$parent_must_empty       = false;
				$post_type               = $target_post->post_type;
				$element_type            = 'post_' . $post_type;
				$target_element_language = $sitepress->get_element_language_details( $target_element_id, $element_type );
				$original_element_id     = $sitepress->get_original_element_id( $target_element_id, $element_type );
				if ( $original_element_id ) {
					$parent_has_translation_in_target_language = false;

					$original_element        = get_post( $original_element_id );
					$original_post_parent_id = $original_element->post_parent;
					if ( $original_post_parent_id ) {
						$original_post_parent_trid         = $sitepress->get_element_trid( $original_post_parent_id, $element_type );
						$original_post_parent_translations = $sitepress->get_element_translations( $original_post_parent_trid, $element_type );
						foreach ( $original_post_parent_translations as $original_post_parent_translation ) {
							if ( $original_post_parent_translation->language_code == $target_element_language->language_code ) {
								$parent_has_translation_in_target_language = true;
								break;
							}
						}
					} else {
						$parent_must_empty = true;
					}
					$is_valid = $parent_has_translation_in_target_language || $parent_must_empty;
				}
			}
		}

		return $is_valid;
	}

	private function get_unsynced_terms( array $element_types, $ref_lang_code, $element_id ) {
		$wpdb             = $this->wpdb;
		$has_reference    = $ref_lang_code ? 1 : 0;
		$restrict_element = $element_id && term_exists( $element_id ) ? 1 : 0;
		$types_bind       = $element_types ? array_values( $element_types ) : array( '' );

		return $wpdb->get_results(
			$wpdb->prepare(
				"SELECT tra.term_taxonomy_id AS translated_id, IFNULL(corr.term_id, 0) AS correct_parent
				 FROM {$wpdb->term_taxonomy} org
				 JOIN {$wpdb->prefix}icl_translations iclo
					ON org.term_taxonomy_id = iclo.element_id
					AND iclo.element_type = CONCAT('tax_', org.taxonomy)
				 JOIN {$wpdb->prefix}icl_translations iclt
					ON iclt.trid = iclo.trid
					AND (
						(%d = 1 AND iclt.language_code != iclo.language_code)
						OR (%d = 0 AND iclt.source_language_code = iclo.language_code)
					)
				 JOIN {$wpdb->term_taxonomy} tra ON tra.term_taxonomy_id = iclt.element_id
				 LEFT JOIN {$wpdb->term_taxonomy} parents ON parents.term_id = org.parent
				 LEFT JOIN {$wpdb->prefix}icl_translations parent_lang
					ON parents.term_taxonomy_id = parent_lang.element_id
					AND parent_lang.element_type = CONCAT('tax_', parents.taxonomy)
				 LEFT JOIN {$wpdb->prefix}icl_translations iclc
					ON iclc.language_code = iclt.language_code
					AND parent_lang.trid = iclc.trid
				 LEFT JOIN {$wpdb->term_taxonomy} corr ON corr.term_taxonomy_id = iclc.element_id
				 WHERE org.taxonomy IN (" . implode( ', ', array_fill( 0, count( $types_bind ), '%s' ) ) . ")
					AND IFNULL(corr.term_id, 0) != tra.parent
					AND (
						(%d = 1 AND iclo.language_code = %s)
						OR (%d = 0 AND iclt.source_language_code IS NOT NULL)
					)
					AND (%d = 0 OR tra.term_id = %d)",
				array_merge(
					array( $has_reference, $has_reference ),
					$types_bind,
					array( $has_reference, (string) $ref_lang_code, $has_reference, $restrict_element, (int) $element_id )
				)
			)
		);
	}

	private function get_unsynced_posts( array $element_types, $ref_lang_code, $element_id ) {
		$wpdb             = $this->wpdb;
		$has_reference    = $ref_lang_code ? 1 : 0;
		$restrict_element = $element_id && term_exists( $element_id ) ? 1 : 0;
		$types_bind       = $element_types ? array_values( $element_types ) : array( '' );

		return $wpdb->get_results(
			$wpdb->prepare(
				"SELECT tra.ID AS translated_id, IFNULL(corr.ID, 0) AS correct_parent
				 FROM {$wpdb->posts} org
				 JOIN {$wpdb->prefix}icl_translations iclo
					ON org.ID = iclo.element_id
					AND iclo.element_type = CONCAT('post_', org.post_type)
				 JOIN {$wpdb->prefix}icl_translations iclt
					ON iclt.trid = iclo.trid
					AND (
						(%d = 1 AND iclt.language_code != iclo.language_code)
						OR (%d = 0 AND iclt.source_language_code = iclo.language_code)
					)
				 JOIN {$wpdb->posts} tra ON tra.ID = iclt.element_id
				 LEFT JOIN {$wpdb->posts} parents ON parents.ID = org.post_parent
				 LEFT JOIN {$wpdb->prefix}icl_translations parent_lang
					ON parents.ID = parent_lang.element_id
					AND parent_lang.element_type = CONCAT('post_', parents.post_type)
				 LEFT JOIN {$wpdb->prefix}icl_translations iclc
					ON iclc.language_code = iclt.language_code
					AND parent_lang.trid = iclc.trid
				 LEFT JOIN {$wpdb->posts} corr ON corr.ID = iclc.element_id
				 WHERE org.post_type IN (" . implode( ', ', array_fill( 0, count( $types_bind ), '%s' ) ) . ")
					AND IFNULL(corr.ID, 0) != tra.post_parent
					AND (
						(%d = 1 AND iclo.language_code = %s)
						OR (%d = 0 AND iclt.source_language_code IS NOT NULL)
					)
					AND (%d = 0 OR tra.ID = %d)",
				array_merge(
					array( $has_reference, $has_reference ),
					$types_bind,
					array( $has_reference, (string) $ref_lang_code, $has_reference, $restrict_element, (int) $element_id )
				)
			)
		);
	}
}
