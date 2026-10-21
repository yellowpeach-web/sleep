<?php

/**
 * Keeps `icl_translation_status` moving on a Blog license.
 *
 * A Blog license never loads tm.php, and tm.php holds the only listener on
 * `wpml_tm_save_post` - the action core fires from
 * WPML_Post_Translation::after_save_post() on every post save. Without that
 * listener the row behind the "needs update" icon is never written, so the
 * icon never leaves the pencil (wpmldev-3902; lost in 4.5.0 with
 * wpmlcore-7668).
 *
 * The read side works without TM: WPML_Post_Status::needs_update() queries
 * the table directly and WPML_Post_Status_Display renders the circled arrow
 * from it. This class supplies only the two writes tm.php would own, and
 * nothing else its listener does - no translation job, no translator
 * registration, no editor bookkeeping. Those are Translation Management,
 * which the Blog license withholds.
 *
 * Registered only from the Blog-license branch of Plugins::loadEmbeddedTM(),
 * so it never runs alongside tm.php's own listener.
 */
class WPML_Blog_License_Translation_Status {

	private $sitepress;

	private $action_helper;

	private $post_actions;

	public function __construct(
		SitePress $sitepress,
		WPML_TM_Action_Helper $action_helper,
		WPML_TM_Post_Actions $post_actions
	) {
		$this->sitepress     = $sitepress;
		$this->action_helper = $action_helper;
		$this->post_actions  = $post_actions;
	}

	public static function on_save_post( $post_id, $post, $force_set_status = false ) {
		static $instance = null;

		if ( null === $instance ) {
			$instance = self::create();
		}

		if ( $instance ) {
			$instance->save_post( $post_id, $post, $force_set_status );
		}
	}

	private static function create() {
		global $wpdb, $sitepress, $wpml_post_translations, $wpml_term_translations;

		if ( ! $sitepress instanceof SitePress ) {
			return null;
		}

		$tm_records    = new WPML_TM_Records( $wpdb, $wpml_post_translations, $wpml_term_translations );
		$action_helper = new WPML_TM_Action_Helper();

		// not defined on a Blog license.
		$post_actions = new WPML_TM_Post_Actions(
			$action_helper,
			( new WPML_TM_Blog_Translators_Factory() )->create(),
			$tm_records
		);

		return new self( $sitepress, $action_helper, $post_actions );
	}

	public function save_post( $post_id, $post, $force_set_status = false ) {
		if ( ! $post instanceof WP_Post ) {
			return;
		}
		if ( 'revision' === $post->post_type || 'auto-draft' === $post->post_status || isset( $_POST['autosave'] ) ) {
			return;
		}

		$element_type = 'post_' . $post->post_type;
		$trid         = $this->sitepress->get_element_trid( $post_id, $element_type );
		if ( ! $trid ) {
			return;
		}

		$translations = $this->sitepress->get_element_translations( $trid, $element_type, false, true );

		if ( $this->is_original( $post_id, $translations ) ) {
			$this->mark_translations_stale( $post_id, $translations );
		} else {
			$this->mark_translation_current( $post_id, $translations, $force_set_status );
		}
	}

	/**
	 * The original was saved: flag every translation whose stored md5 no
	 * longer matches. The updater is WPML_TM_Post_Actions' own, so the rule
	 * for a translation with no status row yet - written as ICL_TM_COMPLETE
	 * plus needs_update when the translated post is published - lives in one
	 * place for both licenses.
	 *
	 * @param int        $post_id
	 * @param stdClass[] $translations
	 */
	private function mark_translations_stale( $post_id, array $translations ) {
		if ( ! empty( $_POST['icl_minor_edit'] ) ) {
			return;
		}

		call_user_func( $this->post_actions->get_translation_statuses_updater( $post_id, $translations ) );
	}

	private function mark_translation_current( $post_id, array $translations, $force_set_status ) {
		if ( $this->is_quick_edit() ) {
			return;
		}

		$own      = $this->find_by_element_id( $post_id, $translations );
		$original = $this->find_original( $translations );
		if ( ! $own || ! $original || empty( $own->translation_id ) ) {
			return;
		}

		$original_post = get_post( $original->element_id );
		if ( ! $original_post ) {
			return;
		}

		$status = $force_set_status > 0 ? (int) $force_set_status : ICL_TM_COMPLETE;
		if ( ICL_TM_COMPLETE === $status && get_post_meta( $post_id, '_icl_lang_duplicate_of', true ) ) {
			$status = ICL_TM_DUPLICATE;
		}

		$this->action_helper->get_tm_instance()->update_translation_status(
			[
				'translation_id'      => $own->translation_id,
				'status'              => $status,
				'translator_id'       => get_current_user_id(),
				'needs_update'        => 0,
				'md5'                 => $this->action_helper->post_md5( $original_post ),
				'translation_service' => 'local',
			]
		);
	}

	private function is_original( $post_id, array $translations ) {
		$own = $this->find_by_element_id( $post_id, $translations );

		return $own && ! empty( $own->original );
	}

	private function find_by_element_id( $post_id, array $translations ) {
		foreach ( $translations as $translation ) {
			if ( isset( $translation->element_id ) && (int) $translation->element_id === (int) $post_id ) {
				return $translation;
			}
		}

		return null;
	}

	private function find_original( array $translations ) {
		foreach ( $translations as $translation ) {
			if ( ! empty( $translation->original ) ) {
				return $translation;
			}
		}

		return null;
	}

	private function is_quick_edit() {
		return isset( $_POST['action'] ) && 'inline-save' === $_POST['action'];
	}
}
