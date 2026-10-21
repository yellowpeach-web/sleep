<?php

class WPML_Query_Utils {

	const ARCHIVE_QUERY_CACHE_GROUP = 'archive_query_has_posts';

	private $wpdb;

	private $wp_api;

	private $display_as_translated_post_types;

	private $was_full_author_query_executed_in_last_call = false;

	private $author_languages_memo = array();

	public function __construct( wpdb $wpdb, WPML_WP_API $wp_api, $display_as_translated_post_types ) {
		$this->wpdb                             = $wpdb;
		$this->wp_api                           = $wp_api;
		$this->display_as_translated_post_types = $display_as_translated_post_types;
	}

	public function get_was_full_author_query_executed_in_last_call() {
		return $this->was_full_author_query_executed_in_last_call;
	}

	public function author_query_has_posts( $post_type, $author_data, $lang, $fallback_lang ) {
		return in_array(
			$lang,
			$this->get_languages_with_published_author_posts( $post_type, $author_data, $fallback_lang ),
			true
		);
	}

	public function get_languages_with_published_author_posts( $post_type, $author_data, $fallback_lang ) {
		$this->was_full_author_query_executed_in_last_call = false;

		$post_types = (array) $post_type;
		$is_display_as_translated_eligible = in_array( $post_type, $this->display_as_translated_post_types );

		$memo_key = md5(
			(string) wp_json_encode(
				array( $author_data->ID, $post_types, $fallback_lang, $is_display_as_translated_eligible )
			)
		);

		if ( array_key_exists( $memo_key, $this->author_languages_memo ) ) {
			return $this->author_languages_memo[ $memo_key ];
		}

		$languages = array();
		if ( $this->author_has_any_published_post( $author_data->ID, $post_types ) ) {
			$this->was_full_author_query_executed_in_last_call = true;

			$languages = $this->fetch_author_post_languages( $author_data->ID, $post_types, $post_type, $fallback_lang );
		}

		$this->author_languages_memo[ $memo_key ] = $languages;

		return $languages;
	}

	private function author_has_any_published_post( $author_id, array $post_types ) {
		$wpdb = $this->wpdb;

		if ( $post_types ) {
			return (bool) $wpdb->get_var(
				$wpdb->prepare(
					"SELECT 1 FROM {$wpdb->posts} p
					 WHERE p.post_author=%d
					 AND p.post_type IN (" . implode( ', ', array_fill( 0, count( $post_types ), '%s' ) ) . ")
					 AND post_status='publish'
					 LIMIT 1",
					array_merge( array( $author_id ), array_values( $post_types ) )
				)
			);
		}

		return (bool) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT 1 FROM {$wpdb->posts} p
				 WHERE p.post_author=%d
				 AND post_status='publish'
				 LIMIT 1",
				$author_id
			)
		);
	}

	private function fetch_author_post_languages( $author_id, array $post_types, $original_post_type, $fallback_lang ) {
		$wpdb                  = $this->wpdb;
		$restrict_post_types   = empty( $post_types ) ? 0 : 1;
		$display_as_translated = in_array( $original_post_type, $this->display_as_translated_post_types, true ) ? 1 : 0;
		$post_types_bind       = $post_types ? array_values( $post_types ) : array( '' );
		$display_types_bind    = $this->display_as_translated_post_types ? array_values( $this->display_as_translated_post_types ) : array( '' );
		$element_types_bind         = wpml_post_element_types( $post_types_bind );
		$display_element_types_bind = wpml_post_element_types( $display_types_bind );

		if ( ! $post_types ) {
			return $wpdb->get_col(
				$wpdb->prepare(
					"SELECT DISTINCT wpml_translations.language_code
					 FROM {$wpdb->posts} p
					 JOIN {$wpdb->prefix}icl_translations wpml_translations
						ON p.ID = wpml_translations.element_id
						AND wpml_translations.element_type = CONCAT('post_', p.post_type)
					 WHERE p.post_author = %d
						AND p.post_status = 'publish'",
					$author_id
				)
			);
		}

		return $wpdb->get_col(
			$wpdb->prepare(
				"SELECT DISTINCT wpml_translations.language_code
				 FROM {$wpdb->posts} p
				 JOIN {$wpdb->prefix}icl_translations wpml_translations
					ON p.ID = wpml_translations.element_id
					AND wpml_translations.element_type IN (" . implode( ', ', array_fill( 0, count( $element_types_bind ), '%s' ) ) . ")
				 WHERE p.post_author = %d
					AND (%d = 0 OR p.post_type IN (" . implode( ', ', array_fill( 0, count( $post_types_bind ), '%s' ) ) . "))
					AND p.post_status = 'publish'
				 UNION
				 SELECT DISTINCT langs.code
				 FROM {$wpdb->posts} p
				 JOIN {$wpdb->prefix}icl_translations wpml_translations
					ON p.ID = wpml_translations.element_id
					AND wpml_translations.element_type IN (" . implode( ', ', array_fill( 0, count( $display_element_types_bind ), '%s' ) ) . ")
				 JOIN {$wpdb->prefix}icl_languages langs ON langs.active = 1
				 WHERE %d = 1
					AND p.post_author = %d
					AND (%d = 0 OR p.post_type IN (" . implode( ', ', array_fill( 0, count( $post_types_bind ), '%s' ) ) . "))
					AND p.post_type IN (" . implode( ', ', array_fill( 0, count( $display_types_bind ), '%s' ) ) . ")
					AND p.post_status = 'publish'
					AND wpml_translations.language_code = %s
					AND NOT EXISTS (
						SELECT 1
						FROM {$wpdb->prefix}icl_translations target_translation
						JOIN {$wpdb->posts} target_post ON target_post.ID = target_translation.element_id
						WHERE target_translation.trid = wpml_translations.trid
							AND target_translation.language_code = langs.code
							AND (
								target_post.post_status IN ('publish', 'private')
								OR (target_post.post_type = 'attachment' AND target_post.post_status = 'inherit')
							)
					)",
				array_merge(
					$element_types_bind,
					array( $author_id, $restrict_post_types ),
					$post_types_bind,
					$display_element_types_bind,
					array( $display_as_translated, $author_id, $restrict_post_types ),
					$post_types_bind,
					$display_types_bind,
					array( $fallback_lang )
				)
			)
		);
	}

	public static function flush_archive_language_sets() {
		( new WPML_WP_Cache( self::ARCHIVE_QUERY_CACHE_GROUP ) )->flush_group_cache( true );
	}

	public function archive_query_has_posts( $lang, $fallback_lang, $year = null, $month = null, $day = null, $post_type = 'post' ) {
		return in_array(
			$lang,
			$this->get_languages_with_published_archive_posts( $fallback_lang, $year, $month, $day, $post_type ),
			true
		);
	}

	public function get_languages_with_published_archive_posts( $fallback_lang, $year = null, $month = null, $day = null, $post_type = 'post' ) {
		$can_read_private_posts = $this->wp_api->current_user_can( 'read_private_posts' );

		$cache_args = array(
			'fallback_lang'          => $fallback_lang,
			'year'                   => $year,
			'month'                  => $month,
			'day'                    => $day,
			'post_type'              => $post_type,
			'can_read_private_posts' => $can_read_private_posts,
		);

		$cache_key = md5( (string) wp_json_encode( $cache_args ) );
		$cache     = new WPML_WP_Cache( self::ARCHIVE_QUERY_CACHE_GROUP );
		$found     = false;
		$languages = $cache->get( $cache_key, $found );

		if ( ! $found || ! is_array( $languages ) ) {
			$languages = $this->fetch_archive_post_languages( $fallback_lang, $year, $month, $day, $post_type, $can_read_private_posts );
			$cache->set( $cache_key, $languages );
		}

		return $languages;
	}

	private function fetch_archive_post_languages( $fallback_lang, $year, $month, $day, $post_type, $can_read_private_posts ) {
		$wpdb                  = $this->wpdb;
		$post_types            = (array) $post_type;
		$restrict_post_types   = empty( $post_types ) ? 0 : 1;
		$include_private       = $can_read_private_posts ? 1 : 0;
		$restrict_year         = (bool) $year ? 1 : 0;
		$restrict_month        = (bool) $month ? 1 : 0;
		$restrict_day          = (bool) $day ? 1 : 0;
		$display_as_translated = in_array( $post_type, $this->display_as_translated_post_types, true ) ? 1 : 0;
		$post_types_bind       = $post_types ? array_values( $post_types ) : array( '' );
		$display_types_bind    = $this->display_as_translated_post_types ? array_values( $this->display_as_translated_post_types ) : array( '' );
		$element_types_bind         = wpml_post_element_types( $post_types_bind );
		$display_element_types_bind = wpml_post_element_types( $display_types_bind );

		if ( ! $post_types ) {
			return $wpdb->get_col(
				$wpdb->prepare(
					"SELECT DISTINCT wpml_translations.language_code
					 FROM {$wpdb->posts} p
					 JOIN {$wpdb->prefix}icl_translations wpml_translations
						ON p.ID = wpml_translations.element_id
						AND wpml_translations.element_type = CONCAT('post_', p.post_type)
					 WHERE (p.post_status = 'publish' OR (%d = 1 AND p.post_status = 'private'))
						AND (%d = 0 OR YEAR(p.post_date) = %d)
						AND (%d = 0 OR MONTH(p.post_date) = %d)
						AND (%d = 0 OR DAY(p.post_date) = %d)",
					array( $include_private, $restrict_year, (int) $year, $restrict_month, (int) $month, $restrict_day, (int) $day )
				)
			);
		}

		return $wpdb->get_col(
			$wpdb->prepare(
				"SELECT DISTINCT wpml_translations.language_code
				 FROM {$wpdb->posts} p
				 JOIN {$wpdb->prefix}icl_translations wpml_translations
					ON p.ID = wpml_translations.element_id
					AND wpml_translations.element_type IN (" . implode( ', ', array_fill( 0, count( $element_types_bind ), '%s' ) ) . ")
				 WHERE (p.post_status = 'publish' OR (%d = 1 AND p.post_status = 'private'))
					AND (%d = 0 OR YEAR(p.post_date) = %d)
					AND (%d = 0 OR MONTH(p.post_date) = %d)
					AND (%d = 0 OR DAY(p.post_date) = %d)
					AND (%d = 0 OR p.post_type IN (" . implode( ', ', array_fill( 0, count( $post_types_bind ), '%s' ) ) . "))
				 UNION
				 SELECT DISTINCT langs.code
				 FROM {$wpdb->posts} p
				 JOIN {$wpdb->prefix}icl_translations wpml_translations
					ON p.ID = wpml_translations.element_id
					AND wpml_translations.element_type IN (" . implode( ', ', array_fill( 0, count( $display_element_types_bind ), '%s' ) ) . ")
				 JOIN {$wpdb->prefix}icl_languages langs ON langs.active = 1
				 WHERE %d = 1
					AND (p.post_status = 'publish' OR (%d = 1 AND p.post_status = 'private'))
					AND (%d = 0 OR YEAR(p.post_date) = %d)
					AND (%d = 0 OR MONTH(p.post_date) = %d)
					AND (%d = 0 OR DAY(p.post_date) = %d)
					AND (%d = 0 OR p.post_type IN (" . implode( ', ', array_fill( 0, count( $post_types_bind ), '%s' ) ) . "))
					AND p.post_type IN (" . implode( ', ', array_fill( 0, count( $display_types_bind ), '%s' ) ) . ")
					AND wpml_translations.language_code = %s
					AND NOT EXISTS (
						SELECT 1
						FROM {$wpdb->prefix}icl_translations target_translation
						JOIN {$wpdb->posts} target_post ON target_post.ID = target_translation.element_id
						WHERE target_translation.trid = wpml_translations.trid
							AND target_translation.language_code = langs.code
							AND (
								target_post.post_status IN ('publish', 'private')
								OR (target_post.post_type = 'attachment' AND target_post.post_status = 'inherit')
							)
					)",
				array_merge(
					$element_types_bind,
					array( $include_private, $restrict_year, (int) $year, $restrict_month, (int) $month, $restrict_day, (int) $day, $restrict_post_types ),
					$post_types_bind,
					$display_element_types_bind,
					array( $display_as_translated, $include_private, $restrict_year, (int) $year, $restrict_month, (int) $month, $restrict_day, (int) $day, $restrict_post_types ),
					$post_types_bind,
					$display_types_bind,
					array( $fallback_lang )
				)
			)
		);
	}

}
