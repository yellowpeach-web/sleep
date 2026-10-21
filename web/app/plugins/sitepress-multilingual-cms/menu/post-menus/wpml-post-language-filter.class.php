<?php
class WPML_Post_Language_Filter extends WPML_Language_Filter_Bar {

	private $post_status;
	private $post_type;

	protected function sanitize_request() {
		$request_data    = parent::sanitize_request ();
		$this->post_type = $request_data[ 'req_ptype' ] ? $request_data[ 'req_ptype' ] : 'post';
		$post_statuses   = array_keys ( get_post_stati () );
		$post_status     = get_query_var ( 'post_status' );
		if ( is_string ( $post_status ) ) {
			$post_status = $post_status ? array( $post_status ) : array();
		}
		$illegal_status = array_diff ( $post_status, $post_statuses );
		$this->post_status = array_diff ( $post_status, $illegal_status );
	}

	public function register_scripts() {
		wp_register_script( 'post-edit-languages', ICL_PLUGIN_URL . '/res/js/post-edit-languages.js', array( 'jquery' ), ICL_SITEPRESS_SCRIPT_VERSION, true );
	}

	public function post_language_filter() {
		$this->sanitize_request();
		$this->init();
		$type = $this->post_type;

		if ( !$this->sitepress->is_translated_post_type ( $type ) ) {
			return '';
		}

		$post_edit_languages = array();
		$post_edit_languages['language_links'] = $this->language_links( $type );

		$removed = $this->removed_language_links();
		if ( $removed ) {
			$post_edit_languages['removed_languages']       = $removed;
			/* translators: Word shown next to a language in the list of the post editing screen when that language is no longer used on the site. Past participle used as a state. */
			$post_edit_languages['removed_languages_label'] = esc_js( __( 'Removed', 'sitepress' ) );
			$post_edit_languages['removed_languages_title'] = esc_js(
				__( 'Languages that are off the site and still have content', 'sitepress' )
			);
		}

		wp_localize_script( 'post-edit-languages', 'post_edit_languages_data', $post_edit_languages );
		wp_enqueue_script( 'post-edit-languages' );

		return $post_edit_languages;
	}

	protected function extra_conditions_snippet(){
		$extra_conditions = "";
		if ( !empty( $this->post_status ) ) {
			$status_snippet  = " AND post_status IN (" .wpml_prepare_in($this->post_status) . ") ";
			$extra_conditions .= apply_filters( '_icl_posts_language_count_status', $status_snippet );
		}

		$extra_conditions .= $this->post_status != array( 'trash' ) ? " AND post_status <> 'trash'" : '';
		$extra_conditions .= " AND post_status <> 'auto-draft' ";
		$extra_conditions .= parent::extra_conditions_snippet();

		return $extra_conditions;
	}

	protected function get_count_data( $type ) {
		$wpdb = $this->wpdb;

		if ( empty( $this->active_languages ) ) {
			return array();
		}

		$languages = array_keys( $this->active_languages );

		$include_trash = array( 'trash' ) === $this->post_status ? 1 : 0;

		if ( ! empty( $this->post_status ) && has_filter( '_icl_posts_language_count_status' ) ) {
			$status_snippet = ' AND p.post_status IN (' . wpml_prepare_in( $this->post_status ) . ') ';
			$status_clause  = apply_filters( '_icl_posts_language_count_status', $status_snippet );

			$args = array_merge(
				array( $type, $include_trash ),
				$languages
			);
		} else {
			$has_statuses  = empty( $this->post_status ) ? 0 : 1;
			$statuses      = $has_statuses ? array_values( $this->post_status ) : array( '' );
			$status_clause = 'AND ( %d = 0 OR p.post_status IN (' . implode( ', ', array_fill( 0, count( $statuses ), '%s' ) ) . ') )';

			$args = array_merge(
				array( $type, $has_statuses ),
				$statuses,
				array( $include_trash ),
				$languages
			);
		}

		$language_snippet = apply_filters(
			'wpml_language_filter_extra_conditions_snippet',
			"AND t.language_code IN (" . implode( ', ', array_fill( 0, count( $languages ), '%s' ) ) . ") GROUP BY t.language_code"
		);

		$sql = "SELECT t.language_code, COUNT(p.ID) AS c
				FROM {$wpdb->prefix}icl_translations t
				JOIN {$wpdb->posts} p
					ON t.element_id = p.ID
						AND t.element_type = CONCAT('post_', p.post_type)
				WHERE p.post_type = %s
					{$status_clause}
					AND ( %d = 1 OR p.post_status <> 'trash' )
					AND p.post_status <> 'auto-draft'
					{$language_snippet}";

		return $wpdb->get_results(
			$wpdb->prepare( $sql, $args )
		);
	}

	/**
	 * The "Removed" group: every language that is off the site and still holds
	 * content (DELETION-FLOWS §3.2 option 2, mechanism (c) — the mechanism behind
	 * the option's "The {N} items stay in your database and in your admin lists"
	 * promise). Today those items are unreachable through the language filters and
	 * only the database has them.
	 *
	 * The entries carry the same shape as the active ones, so the group renders
	 * through the same code path and links to the same `edit.php?lang=<code>` view;
	 * WPML\Languages\RemovedLanguages::browsableInAdminList() is what makes that
	 * view actually list the language's items.
	 *
	 * THE COUNT IS THE LANGUAGE'S WHOLE KEPT TOTAL, not a per-post-type one: it is
	 * the {N} of the option's own wording, answered by the landed counting engine
	 * (TranslatedContentOfLanguages::totalsByLanguage), and it is the number the
	 * Removed-languages panel and the removal dialog state as well. The active
	 * languages' counts beside it stay per-post-type and are untouched.
	 *
	 * Answers an empty array on every site that has removed no language, and the
	 * caller then adds no key at all — an ordinary site sees no change whatsoever.
	 *
	 * @return array<int,array<string,mixed>>
	 */
	private function removed_language_links() {
		$removed = \WPML\Languages\RemovedLanguages::listing();

		if ( ! $removed ) {
			return array();
		}

		$links = array();

		foreach ( $removed as $language ) {
			$code = $language['code'];
			$name = $this->sitepress->get_display_language_name( $code );

			$links[] = array(
				'type'     => esc_js( $this->post_type ),
				'statuses' => array_map( 'esc_js', (array) $this->post_status ),
				'code'     => esc_js( $code ),
				'name'     => esc_js( $name ? $name : $language['name'] ),
				'current'  => $code === $this->current_language,
				'count'    => (int) $language['total'],
			);
		}

		return $links;
	}

	private function language_links( $type ) {
		$lang_links = array();

		$languages   = $this->get_counts( $type );
		$post_status = $this->post_status;
		foreach ( $this->active_languages as $code => $lang ) {
			$item = array();
			$item['type'] = esc_js( $type );
			$item['statuses'] = array_map( 'esc_js', $post_status );
			$item['code'] = esc_js( $code );
			$item['name'] = esc_js( $lang[ 'display_name' ] );
			$item['current'] = $code === $this->current_language;
			$item['count'] = isset( $languages[ $code ] ) ? esc_js( $languages[ $code ] ) : -1;
			$lang_links[ ] = $item;
		}

		return $lang_links;
	}
}
