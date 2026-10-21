<?php

class WPML_TM_ATE_API_Error {

	public function log( $message ) {
		$wpml_admin_notices = wpml_get_admin_notices();

		$notice = new WPML_Notice(
			WPML_TM_ATE_Jobs_Actions::RESPONSE_ATE_ERROR_NOTICE_ID,
			sprintf(
				/* translators: First part of an error message about automatic translation; the reason given by the service follows the colon. %s: that reason. Keep the space at the end. */
				__( 'There was a problem communicating with our translation system: %s ', 'sitepress' ),
				'(<i>' . esc_html( (string) $message ) . '</i>)'
			),
			WPML_TM_ATE_Jobs_Actions::RESPONSE_ATE_ERROR_NOTICE_GROUP
		);
		$notice->set_css_class_types( array( 'warning' ) );
		$notice->add_capability_check( array( 'manage_options', 'wpml_manage_translation_management' ) );
		$notice->set_flash();
		$wpml_admin_notices->add_notice( $notice );
	}
}
