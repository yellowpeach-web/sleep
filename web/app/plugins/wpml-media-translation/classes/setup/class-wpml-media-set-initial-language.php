<?php
class WPML_Media_Set_Initial_Language implements IWPML_Action {
	private $wpdb;
	private $language;

	public function __construct( wpdb $wpdb, $language ) {
		$this->wpdb     = $wpdb;
		$this->language = $language;
	}

	public function add_hooks() {
		\WPML\Request\Adapter\Ajax::register( 'wpml_media_set_initial_language', \WPML\Request\Policy\Policy::capability( [ 'wpml_manage_media_translation', 'manage_translations' ], \WPML\Request\Policy\Authenticity::actionNonce( 'wpml_media_set_initial_language', 'nonce' ) ), array( $this, 'set' ) );
	}

	public function set() {

		$this->update_db();

		$message = __( 'Setting language to media: done!', 'wpml-media' );

		wp_send_json_success(
			array(
				'message' => $message,
			)
		);

	}

	public function update_db() {
		$wpdb    = $this->wpdb;
		$maxtrid = $wpdb->get_var( "SELECT MAX(trid) FROM {$wpdb->prefix}icl_translations" );

		$wpdb->query(
			$wpdb->prepare(
				"
			INSERT INTO {$wpdb->prefix}icl_translations
				(element_type, element_id, trid, language_code, source_language_code)
			SELECT 'post_attachment', ID, %d + ID,  %s, NULL
			FROM {$wpdb->posts} p
				LEFT JOIN {$wpdb->prefix}icl_translations t
                ON p.ID = t.element_id AND t.element_type = 'post_attachment' 
                WHERE post_type = 'attachment' AND t.translation_id IS NULL",
				$maxtrid,
				$this->language
			)
		);

		do_action( 'wpml_comment_counts_mark_out_of_date' );
	}
}
