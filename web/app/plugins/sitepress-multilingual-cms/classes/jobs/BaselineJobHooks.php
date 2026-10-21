<?php

namespace WPML\TM\Jobs;

class BaselineJobHooks implements \IWPML_Backend_Action, \IWPML_Frontend_Action, \IWPML_AJAX_Action, \IWPML_REST_Action, \IWPML_CLI_Action {

	const PRIORITY = 20;

	private $create_missing;

	public function __construct( ?callable $create_missing = null ) {
		$this->create_missing = $create_missing;
	}

	public function add_hooks() {
		add_action( 'pre_post_update', [ $this, 'record_baseline_before_source_update' ], self::PRIORITY, 1 );
	}

	public function record_baseline_before_source_update( $post_id ) {
		global $sitepress, $wpml_post_translations;

		if ( ! $sitepress || ! $wpml_post_translations ) {
			return;
		}

		$post = get_post( $post_id );
		if ( ! $post || ! $sitepress->is_translated_post_type( $post->post_type ) ) {
			return;
		}

		if ( $wpml_post_translations->get_source_lang_code( $post_id ) ) {
			return;
		}

		if ( $this->create_missing ) {
			call_user_func( $this->create_missing, $post_id );

			return;
		}

		BaselineJobCreator::build()->create_missing( $post_id, BaselineJobCreator::TRIGGER_ORIGINAL_UPDATE );

		( new \WPML_Element_Translation_Package() )->clearCache();
	}
}
