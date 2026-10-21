<?php

class WPML_Temporary_Switch_Language extends WPML_SP_User {

	private $is_switch_open = false;

	private $was_switch_triggered = false;

	public function __construct( &$sitepress, $target_lang ) {
		parent::__construct( $sitepress );
		$this->was_switch_triggered = $sitepress->is_wpml_switch_language_triggered();
		$sitepress->switch_lang( $target_lang );
		$this->is_switch_open = true;
	}

	public function __destruct() {
		$this->restore_lang();
	}

	public function restore_lang() {
		if ( $this->is_switch_open ) {
			$this->is_switch_open = false;
			$this->sitepress->switch_lang();
			$this->sitepress->restore_wpml_switch_language_triggered( $this->was_switch_triggered );
		}
	}
}
