<?php

class WPML_Ajax_Update_Link_Targets_In_Posts extends WPML_Ajax_Update_Link_Targets_In_Content {

	private $pro_translation;

	public function __construct( WPML_Translate_Link_Target_Global_State $translate_link_target_global_state, &$wpdb, $pro_translation, $post_data ) {
		$this->pro_translation = $pro_translation;
		parent::__construct( $translate_link_target_global_state, $wpdb, $post_data );
	}

	protected function create_translate_link_target() {
		return new WPML_Translate_Link_Targets_In_Posts_Global( $this->translate_link_target_global_state, $this->wpdb, $this->pro_translation );
	}

	protected function create_sibling_translate_link_target() {
		return new WPML_Translate_Link_Targets_In_Strings_Global( $this->translate_link_target_global_state, $this->wpdb, new WPML_WP_API(), $this->pro_translation );
	}

}
