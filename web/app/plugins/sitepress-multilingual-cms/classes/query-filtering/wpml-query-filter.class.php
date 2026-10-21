<?php

class WPML_Query_Filter extends  WPML_Full_Translation_API {

	private $name_filter = array();

	private $comment_language_check;

	private $comment_compat_join_needed = false;

	function __construct(
		&$sitepress,
		&$wpdb,
		&$post_translations,
		&$term_translations,
		?\WPML\QueryFiltering\CommentLanguageMismatchCheck $comment_language_check = null
	) {
		parent::__construct( $sitepress, $wpdb, $post_translations, $term_translations );
		$this->comment_language_check = $comment_language_check;
	}

	public function get_page_name_filter( $post_type = 'page' ) {
		if ( ! isset( $this->name_filter[ $post_type ] ) ) {
			$this->name_filter[ $post_type ] = $post_type === 'page'
				? new WPML_Page_Name_Query_Filter( $this->sitepress, $this->post_translations, $this->wpdb )
				: ( $this->sitepress->is_translated_post_type( $post_type )
					? new WPML_Name_Query_Filter_Translated( $post_type, $this->sitepress, $this->post_translations, $this->wpdb )
					: new WPML_Name_Query_Filter_Untranslated( $post_type, $this->sitepress, $this->post_translations, $this->wpdb ) );
		}

		return $this->name_filter[ $post_type ];
	}

	public function get_404_util() {

		return new WPML_404_Guess( $this->wpdb, $this->sitepress, $this );
	}

	public function filter_single_type_join( $join, $post_type ) {
		if ( 'any' === $post_type ) {
			$join .= $this->any_post_type_join();
		} elseif ( $this->sitepress->is_translated_post_type ( $post_type ) ) {
			$join .= $this->any_post_type_join( false );
		}

		return $join;
	}

	public function comments_clauses_filter( $clauses, $obj ) {
		$filtered         = $this->is_comment_query_filtered ( $obj );
		$current_language = $this->sitepress->get_current_language ();

		if ( ! $filtered && $current_language !== 'all' && $this->comment_compat_join_needed ) {
			$clauses['join'] .= $this->get_comment_query_compat_join( $clauses['join'] );
		} elseif ( $filtered && $current_language !== 'all' ) {
			$join_part = $this->get_comment_query_join ( $obj );
			if ( strstr( $clauses['join'], "JOIN {$this->wpdb->posts}" ) !== false ) {
				$join_part = ' AND ';
			}
			$clauses[ 'join' ]
					.= "	JOIN {$this->wpdb->prefix}icl_translations icltr2
							ON icltr2.element_id = {$this->wpdb->comments}.comment_post_ID
							{$join_part} {$this->wpdb->posts}.ID = icltr2.element_id
							AND CONCAT('post_', {$this->wpdb->posts}.post_type) = icltr2.element_type
						LEFT JOIN {$this->wpdb->prefix}icl_translations icltr_comment
							ON icltr_comment.element_id = {$this->wpdb->comments}.comment_ID
								AND icltr_comment.element_type = 'comment'
					";
			$clauses[ 'where' ] .= " " . $this->wpdb->prepare ( " AND icltr2.language_code = %s
															AND (icltr_comment.language_code IS NULL
																	OR icltr_comment.language_code = icltr2.language_code
																) ", $current_language );
		}

		return $clauses;
	}

	public function posts_join_filter( $join, $query ) {
		global $pagenow;

		if ( ! $this->is_join_filter_active( $query, $pagenow ) ) {
			return $join;
		}

		$post_type = $this->get_query_post_types( $query );
		if ( ! $this->covers_translated_types( $post_type ) ) {
			return $join;
		}

		return is_array( $post_type )
			? $join . $this->any_post_type_join()
			: $this->filter_single_type_join( $join, $post_type );
	}

	public function filter_single_type_where( $where, $post_type ) {
		if ( $this->posttypes_not_translated( $post_type ) === false ) {
			$where .= $this->specific_lang_where( $this->sitepress->get_current_language(), $this->sitepress->get_default_language() );
		}

		return $where;
	}

	public function posts_where_filter( $where, $query ) {
		if ( $query === null || $this->where_filter_active( $query ) === false ) {
			return $where;
		}

		$requested_id = $this->get_requested_id( $query );

		$default_language = $this->sitepress->get_default_language();

		$current_language = $this->get_current_language( $requested_id, $query );

		$condition = $current_language === 'all'
			? $this->all_langs_where()
			: $this->specific_lang_where( $current_language, $default_language, $this->named_attachments_without_translation( $query, $current_language ) );
		$where     .= $condition;

		return $where;
	}

	private function named_attachments_without_translation( $query, $current_language ) {
		$post_type = isset( $query->query_vars['post_type'] ) ? $query->query_vars['post_type'] : '';
		if ( (array) $post_type !== array( 'attachment' ) ) {
			return array();
		}

		$named = array();
		if ( ! empty( $query->query_vars['post__in'] ) && is_array( $query->query_vars['post__in'] ) ) {
			$named = $query->query_vars['post__in'];
		}
		if ( ! empty( $query->query_vars['p'] ) ) {
			$named[] = $query->query_vars['p'];
		}

		$without_translation = array();
		foreach ( array_unique( array_map( 'intval', $named ) ) as $attachment_id ) {
			$language = $this->post_translations->get_element_lang_code( $attachment_id );
			if ( $attachment_id > 0
				 && $language
				 && $language !== $current_language
				 && ! $this->post_translations->element_id_in( $attachment_id, $current_language )
			) {
				$without_translation[] = $attachment_id;
			}
		}

		return $without_translation;
	}

	private function get_requested_id( $query ) {
		$requested_id = isset( $_REQUEST['attachment_id'] ) && $_REQUEST['attachment_id'] ? $_REQUEST['attachment_id'] : false;
		$requested_id = isset( $_REQUEST['post_id'] ) && $_REQUEST['post_id'] ? $_REQUEST['post_id'] : $requested_id;

		if ( ! $requested_id && isset( $query->query_vars['attachment_id'] ) ) {
			$requested_id = $query->query_vars['attachment_id'];
		}

		return (int) $requested_id;
	}

	public function in_translated_types_snippet( $not = false, $posts_alias = false ) {
		$not         = $not ? " NOT " : "";
		$posts_alias = $posts_alias ? (string) $posts_alias : (string) $this->wpdb->posts;
		if ( ! preg_match( '/^[A-Za-z0-9_]+$/', $posts_alias ) ) {
			throw new InvalidArgumentException( 'Invalid posts table alias.' );
		}

		$post_types = $this->sitepress->get_translatable_documents( false );
		if ( $post_types ) {
			return "{$posts_alias}.post_type {$not} IN (" . wpml_prepare_in( array_keys( $post_types ) ) . " ) ";
		} else {
			return '';
		}
	}

	private function any_post_type_join( $left = true ) {
		$left = $left ? " LEFT " : "";

		return $left . " JOIN {$this->wpdb->prefix}icl_translations wpml_translations
							ON {$this->wpdb->posts}.ID = wpml_translations.element_id
								AND wpml_translations.element_type = CONCAT('post_', {$this->wpdb->posts}.post_type) ";
	}

	private function is_join_filter_active( $query, $pagenow ) {
		if ( isset( $query->query['suppress_wpml_where_and_join_filter'] ) && $query->query['suppress_wpml_where_and_join_filter'] ) {
			return false;
		}
		$is_attachment_and_cant_be_translated = $query->is_attachment() ? $this->is_media_and_cant_be_translated( 'attachment' ) : false;

		return $pagenow !== 'media-upload.php' && ! $is_attachment_and_cant_be_translated && ! $this->is_queried_object_root( $query );
	}

	private function is_queried_object_root( $query ) {
		$url_settings = $this->sitepress->get_setting( 'urls' );
		$root_id      = ! empty( $url_settings['root_page'] ) ? $url_settings['root_page'] : - 1;

		return isset( $query->queried_object )
		       && isset( $query->queried_object->ID )
		       && $query->queried_object->ID == $root_id;
	}

	private function determine_post_type( $query ) {
		$wpdb       = $this->wpdb;
		$query_vars = isset( $query->query_vars ) && is_array( $query->query_vars ) ? $query->query_vars : array();
		$post_type  = isset( $query_vars['post_type'] ) ? esc_sql( $query_vars['post_type'] ) : false;

		if ( ! $post_type ) {
			if ( ! empty( $query_vars['pagename'] ) ) {
				$post_type = 'page';
			} elseif ( ! empty( $query_vars['attachment'] ) ) {
				$post_type = 'attachment';
			} elseif ( ! empty( $query_vars['p'] ) ) {
				$post_type = $wpdb->get_var(
					$wpdb->prepare(
						"SELECT post_type from {$wpdb->posts} where ID=%d",
						$query_vars['p']
					)
				);
			}
		}

		return $post_type;
	}

	private function get_tax_query_posttype( $query ) {
		$taxonomies = array_filter( (array) $query->get( 'taxonomy' ) );
		if ( ! $taxonomies && isset( $query->tax_query->queried_terms ) && is_array( $query->tax_query->queried_terms ) ) {
			$taxonomies = array_keys( $query->tax_query->queried_terms );
		}

		$post_types = array();
		foreach ( $taxonomies as $taxonomy ) {
			$post_types = array_merge( $post_types, (array) WPML_WP_Taxonomy::get_linked_post_types( $taxonomy ) );
		}

		return array_values( array_unique( $post_types ) );
	}

	private function posttypes_not_translated( $post_types ) {
		$post_types      = is_array( $post_types ) ? $post_types : array( $post_types );
		$none_translated = true;
		foreach ( $post_types as $ptype ) {
			if ( $this->sitepress->is_translated_post_type( $ptype ) ) {
				$none_translated = false;
				break;
			}
		}

		return $none_translated;
	}

	private function all_langs_where() {

		return ' AND wpml_translations.language_code IN (' . wpml_prepare_in( array_keys( $this->sitepress->get_active_languages() ) ) . ') ';
	}

	private function specific_lang_where( $current_language, $fallback_language, $ids_in_any_language = array() ) {
		$any_language = $ids_in_any_language
			? " OR {$this->wpdb->posts}.ID IN (" . implode( ',', array_map( 'intval', $ids_in_any_language ) ) . ')'
			: '';

		return sprintf(
			' AND ( ( ( wpml_translations.language_code = %s OR %s%s ) AND %s ) OR %s )',
			$this->sql_string_literal( $current_language ),
			$this->display_as_translated_snippet( $current_language, $fallback_language ),
			$any_language,
			$this->in_translated_types_snippet(),
			$this->in_translated_types_snippet( true )
		);
	}

	private function sql_string_literal( $value ) {
		return "'" . esc_sql( (string) $value ) . "'";
	}


	private function display_as_translated_snippet( $current_language, $fallback_language ) {
		$content_types = null;
		$skip_content_check = true;

		if ( ! apply_filters( 'wpml_should_force_display_as_translated_snippet', false ) ) {
			$post_types = $this->sitepress->get_display_as_translated_documents();
			if ( ! $post_types || ! apply_filters( 'wpml_should_use_display_as_translated_snippet', ! is_admin(), $post_types ) ) {
				return '0';
			}
			$content_types = array_keys( $post_types );
			$skip_content_check = false;
		}

		$display_as_translated_query = new WPML_Display_As_Translated_Posts_Query( $this->wpdb );

		return $display_as_translated_query->get_language_snippet( $current_language, $fallback_language, $content_types, $skip_content_check );
	}

	private function where_filter_active( $query ) {
		global $pagenow;

		if ( ! $this->is_join_filter_active( $query, $pagenow ) ) {
			return false;
		}

		return $this->covers_translated_types( $this->get_query_post_types( $query ) );
	}

	private function get_query_post_types( $query ) {
		$post_type = $this->determine_post_type( $query );
		if ( ! $post_type && $query->is_tax() ) {
			$post_type = $this->get_tax_query_posttype( $query );
		}
		if ( $query->is_posts_page ) {
			$post_type = 'post';
		}
		if ( ! $post_type ) {
			if ( $query->is_tax() ) {
				$post_type = 'any';
			} else {
				$post_type = $query->is_attachment() ? 'attachment' : 'post';
			}
		}

		return $post_type;
	}

	private function covers_translated_types( $post_type ) {
		return 'any' === $post_type || $this->posttypes_not_translated( $post_type ) === false;
	}

	private function is_media_and_cant_be_translated( $post_type ) {
		$is_attachment_and_cant_be_translated = ( $post_type === 'attachment' && ! $this->sitepress->is_translated_post_type( 'attachment' ) );

		return $is_attachment_and_cant_be_translated;
	}

	private function get_post_id_from_comment_query( WP_Comment_Query $comment_query ) {
		if ( isset( $comment_query->query_vars['post_id'] ) ) {
			return $comment_query->query_vars['post_id'];
		} elseif ( isset( $comment_query->query_vars['post_ID'] ) ) {
			return $comment_query->query_vars['post_ID'];
		}

		return null;
	}

	private function is_comment_query_filtered( $comment_query ) {
		global $pagenow;

		$filtered = true;

		$post_id  = $this->get_post_id_from_comment_query( $comment_query );

		$gate_closed_it = false;

		if ( $post_id ) {
			$post = get_post( $post_id );
			if ( (bool) $post === true && ! $this->sitepress->is_translated_post_type( $post->post_type ) ) {
				$filtered = false;
			}

			if ( $filtered ) {
				$gate_closed_it = ! $this->get_comment_language_check()->groupHasMismatchedCommentLanguage( $post_id );
				$filtered       = ! $gate_closed_it;
			}
		} elseif ( $this->sitepress->get_wp_api()->is_back_end() && 'edit-comments.php' !== $pagenow ) {
			$filtered = false;
		}

		$filtered = apply_filters( 'wpml_is_comment_query_filtered', $filtered, $post_id, $comment_query );

		$this->comment_compat_join_needed = $gate_closed_it && ! $filtered && apply_filters(
			'wpml_comment_query_needs_compat_join',
			true,
			$post_id,
			$comment_query
		);

		return $filtered;
	}

	private function get_comment_query_compat_join( $existing_join ) {
		if ( strstr( $existing_join, "JOIN {$this->wpdb->posts}" ) !== false ) {
			return '';
		}

		return " JOIN {$this->wpdb->posts} ON {$this->wpdb->comments}.comment_post_ID = {$this->wpdb->posts}.ID ";
	}

	private function get_comment_language_check() {
		if ( ! $this->comment_language_check ) {
			$this->comment_language_check = new \WPML\QueryFiltering\CommentLanguageMismatchCheck( $this->wpdb );
		}

		return $this->comment_language_check;
	}

	private function get_comment_query_join( $comment_query ){
		$posts_params = array(
			'post_author',
			'post_name',
			'post_parent',
			'post_status',
			'post_type',
			'post_author__not_in',
			'post_author__in'
		);
		$query_vars   = isset( $comment_query->query_vars ) ? array_filter ( $comment_query->query_vars ) : array();
		$plucked      = wp_array_slice_assoc ( $query_vars, $posts_params );
		$post_fields  = array_filter ( $plucked );
		$posts_query  = !empty( $post_fields );

		$join_part = $posts_query ? " AND " : "JOIN {$this->wpdb->posts} ON ";

		return $join_part;
	}

	private function get_current_language( $requested_id, $query = null ) {
		$explicit_language = $this->get_explicit_query_language( $query );
		if ( $explicit_language ) {
			return $explicit_language;
		}

		$current_language = $this->post_translations->get_element_lang_code( $requested_id );
		if ( ! $current_language ) {
			$current_language = $this->sitepress->get_current_language();
		}
		if ( ! $current_language ) {
			$current_language = $this->sitepress->get_default_language();
		}

		return $current_language;
	}

	private function get_explicit_query_language( $query ) {
		if ( ! $query instanceof WP_Query || ! isset( $query->query['lang'] ) ) {
			return null;
		}

		$language = $query->query['lang'];

		if ( ! is_string( $language ) ) {
			return null;
		}

		$language = trim( $language );

		if ( 'all' === $language ) {
			return 'all';
		}

		$active_languages = $this->sitepress->get_active_languages();

		return isset( $active_languages[ $language ] ) ? $language : null;
	}
}
