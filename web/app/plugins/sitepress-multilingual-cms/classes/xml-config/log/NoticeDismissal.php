<?php

namespace WPML\XMLConfig\Log;

use WPML\WP\OptionManager;

class NoticeDismissal {

	private $option_manager;

	public function __construct( ?OptionManager $option_manager = null ) {
		$this->option_manager = $option_manager;
	}

	public function forget() {
		if ( ! $this->holds_dismissal( \get_option( \WPML_Notices::DISMISSED_OPTION_KEY ) ) ) {
			return;
		}

		$this->get_option_manager()->mutateRaw(
			\WPML_Notices::DISMISSED_OPTION_KEY,
			function ( $dismissed ) {
				return $this->without_dismissal( $dismissed );
			},
			false
		);
	}

	private function holds_dismissal( $dismissed ) {
		return is_array( $dismissed )
			&& isset( $dismissed[ \WPML_XML_Config_Log_Notice::NOTICE_ERROR_GROUP ] )
			&& is_array( $dismissed[ \WPML_XML_Config_Log_Notice::NOTICE_ERROR_GROUP ] )
			&& isset( $dismissed[ \WPML_XML_Config_Log_Notice::NOTICE_ERROR_GROUP ][ \WPML_XML_Config_Log_Notice::NOTICE_ERROR_ID ] );
	}

	private function without_dismissal( $dismissed ) {
		if ( ! $this->holds_dismissal( $dismissed ) ) {
			return $dismissed;
		}

		unset( $dismissed[ \WPML_XML_Config_Log_Notice::NOTICE_ERROR_GROUP ][ \WPML_XML_Config_Log_Notice::NOTICE_ERROR_ID ] );
		if ( ! $dismissed[ \WPML_XML_Config_Log_Notice::NOTICE_ERROR_GROUP ] ) {
			unset( $dismissed[ \WPML_XML_Config_Log_Notice::NOTICE_ERROR_GROUP ] );
		}

		return $dismissed;
	}

	private function get_option_manager() {
		if ( ! $this->option_manager ) {
			$this->option_manager = new OptionManager();
		}

		return $this->option_manager;
	}
}
