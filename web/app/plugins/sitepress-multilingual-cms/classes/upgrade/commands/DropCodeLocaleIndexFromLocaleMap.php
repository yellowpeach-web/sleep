<?php

namespace WPML\Upgrade\Commands;

class DropCodeLocaleIndexFromLocaleMap extends DropIndexFromTable implements \IWPML_Pre_Setup_Upgrade_Command {

	protected function get_table() {
		return 'icl_locale_map';
	}

	protected function get_index() {
		return 'code';
	}
}
