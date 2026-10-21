<?php

class WPML_Media_Get_Attachment_Translation_Data implements IWPML_Action {

	private $sitepress;

	private $wpdb;

	public function __construct( SitePress $sitepress, wpdb $wpdb ) {
		$this->sitepress = $sitepress;
		$this->wpdb      = $wpdb;
	}

	public function add_hooks() {
		\WPML\Request\Adapter\Ajax::register( 'wpml_media_get_attachment_translation_data', \WPML\Request\Policy\Policy::capability( [ 'upload_files', 'edit_posts' ], \WPML\Request\Policy\Authenticity::actionNonce( 'media-translation', 'wpnonce' ) ), array( $this, 'get_attachment_translation_data' ) );
	}

	public function get_attachment_translation_data() {
		if ( ! wp_verify_nonce( $_GET['wpnonce'], 'media-translation' ) ) {
			/* translators: Error message on the Media Translation page when the security check of a request fails. */
			wp_send_json_error( array( 'error' => __( 'Invalid nonce', 'wpml-media' ) ) );
		}

		$attachment_id       = (int) $_GET['attachmentId'];
		$translated_language = sanitize_text_field( $_GET['translatedLanguage'] );

		$post_ids                              = $this->get_all_posts_in_which_media_file_was_used_by_copy( $attachment_id );
		$was_any_post_translated_automatically = $this->wasAnyPostTranslatedAutomatically( $post_ids, $translated_language );

		$response = array(
			'wasAttachmentTranslatedAutomaticallyInAnyPostAsCopy' => $was_any_post_translated_automatically,
		);

		wp_send_json_success( $response );
	}

	private function get_all_posts_in_which_media_file_was_used_by_copy( $post_id ) {
		if ( ! class_exists( '\WPML\MediaTranslation\UsageOfMediaFilesInPosts' ) ||
			! class_exists( '\WPML\MediaTranslation\PostWithMediaFilesFactory' ) ) {
			return [];
		}

		$post_with_media_files_factory = new \WPML\MediaTranslation\PostWithMediaFilesFactory();
		$post_with_media_files         = $post_with_media_files_factory->create( $post_id );
		$data                          = $post_with_media_files->get_usages_of_media_file_in_posts( $post_id );

		return $data[0];
	}

	private function wasAnyPostTranslatedAutomatically( $post_ids, $translated_language ) {
		$wpdb = $this->wpdb;

		if ( count( $post_ids ) === 0 ) {
			return false;
		}

		$posts = $this->wpdb->get_results(
			"SELECT ID, post_type FROM {$wpdb->posts} WHERE ID IN (" . wpml_prepare_in( $post_ids, '%d' ) . ')',
			ARRAY_A
		);

		if ( count( $posts ) === 0 ) {
			return false;
		}

		$conditions = array();
		$params     = array();
		foreach ( $posts as $post ) {
			$conditions[] = '(orig.element_id=%d AND orig.element_type=%s AND t.language_code=%s AND t.source_language_code IS NOT NULL)';
			$params[]     = $post['ID'];
			$params[]     = 'post_' . $post['post_type'];
			$params[]     = $translated_language;
		}

		$where        = implode( ' OR ', $conditions );
		$query        = "SELECT t.* FROM {$this->wpdb->prefix}icl_translations t"
						. " INNER JOIN {$this->wpdb->prefix}icl_translations orig"
						. ' ON orig.trid = t.trid AND orig.element_type = t.element_type'
						. ' WHERE ' . $where;
		$translations = $this->wpdb->get_results(
			$this->wpdb->prepare(
				$query,
				$params
			),
			ARRAY_A
		);

		$translation_ids = [];
		foreach ( $translations as $translation ) {
			$translation_ids[] = $translation['translation_id'];
		}

		if ( count( $translation_ids ) === 0 ) {
			return false;
		}

		$query = "
			SELECT ts.*
			FROM {$this->wpdb->prefix}icl_translation_status ts
			INNER JOIN (
				SELECT translation_id, MAX(rid) as max_rid
				FROM {$this->wpdb->prefix}icl_translation_status
				WHERE translation_id IN (" . wpml_prepare_in( $translation_ids, '%d' ) . ')
				GROUP BY translation_id
			) latest
			ON ts.translation_id = latest.translation_id AND ts.rid = latest.max_rid
		';

		$results = $this->wpdb->get_results( $query, ARRAY_A );
		$rids    = [];
		foreach ( $results as $row ) {
			$rids[] = $row['rid'];
		}

		if ( count( $rids ) === 0 ) {
			return false;
		}

		$query = "
			SELECT tj.*
			FROM {$this->wpdb->prefix}icl_translate_job tj
			WHERE tj.rid IN (" . wpml_prepare_in( $rids, '%d' ) . ')
		';

		$results = $this->wpdb->get_results( $query, ARRAY_A );
		foreach ( $results as $row ) {
			if ( 1 === (int) $row['automatic'] ) {
				return true;
			}
		}

		return false;
	}
}
