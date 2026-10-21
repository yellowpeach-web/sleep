<?php

abstract class WPML_Ajax_Update_Link_Targets_In_Content extends WPML_WPDB_User implements IWPML_AJAX_Action_Run {

	private $translate_link_targets;
	private $post_data;
	protected $translate_link_target_global_state;

	public function __construct( WPML_Translate_Link_Target_Global_State $translate_link_target_global_state, &$wpdb, $post_data ) {
		parent::__construct( $wpdb );
		$this->translate_link_target_global_state = $translate_link_target_global_state;
		$this->translate_link_targets             = $this->create_translate_link_target();
		$this->post_data                          = $post_data;
	}

	public function run() {
		if ( wp_verify_nonce( $this->post_data['nonce'], 'WPML_Ajax_Update_Link_Targets' ) ) {

			$last_processed = $this->translate_link_targets->fix( $this->post_data['last_processed'], $this->post_data['number_to_process'] );

			$number_left = $this->translate_link_targets->last_batch_was_full()
				? $this->translate_link_targets->get_number_to_be_fixed( $last_processed + 1 )
				: 0;

			if ( 0 === $number_left && 0 === (int) $this->create_sibling_translate_link_target()->get_number_to_be_fixed() ) {
				$this->translate_link_target_global_state->clear_rescan_required();
			}

			return new WPML_Ajax_Response(
				true,
				array(
					'last_processed' => (int) $last_processed,
					'number_left'    => $number_left,
					'links_fixed'    => $this->translate_link_targets->get_number_of_links_that_were_fixed(),

				)
			);
		} else {
			return new WPML_Ajax_Response( false, 'wrong nonce' );
		}
	}

	abstract protected function create_translate_link_target();

	abstract protected function create_sibling_translate_link_target();

}
