<?php

class WPML_TM_ATE_Required_Actions_Base {
	private $ate_enabled;

	protected function is_ate_enabled() {
		if ( null === $this->ate_enabled ) {
			$doc_translation_method = wpml_get_tm_sub_setting( 'doc_translation_method', null );
			$this->ate_enabled      = $doc_translation_method === ICL_TM_TMETHOD_ATE;
		}

		return $this->ate_enabled;
	}
}
