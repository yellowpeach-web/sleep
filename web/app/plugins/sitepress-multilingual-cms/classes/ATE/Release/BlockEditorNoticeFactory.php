<?php

namespace WPML\TM\ATE\Release;

class BlockEditorNoticeFactory implements \IWPML_Backend_Action_Loader, \IWPML_REST_Action_Loader {

	public function create() {
		return \WPML_TM_ATE_Status::is_enabled_and_activated() ? new BlockEditorNotice() : null;
	}
}
