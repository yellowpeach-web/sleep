<?php

class WPML_Translation_Jobs_Missing_TP_ID_Migration_Notice extends WPML_Translation_Jobs_Migration_Notice {

	protected function get_model() {
		return array(
			'strings' => array(
				'title'              => __( 'WPML Translation Jobs Migration', 'sitepress' ),
				/* translators: the placeholder is replaced with the current version of WPML */
				'description'        => sprintf( __( 'WPML found some remote jobs on your site that must be migrated in order to work with WPML %s. You might not be able to access some of the WPML administration pages until this migration is fully completed.', 'sitepress' ), ICL_SITEPRESS_VERSION ),
				/* translators: Button label in a notice that offers to bring old translation jobs up to date, starting straight away. Verb phrase, imperative. */
				'button'             => __( 'Run now', 'sitepress' ),
				/* translators: Word between two numbers above a list, as in "Displaying 1 of 20": the first is what is shown, the second is how many there are in all. */
				'of'                 => __( 'of', 'sitepress' ),
				/* translators: Words after a number in that notice, as in "12 jobs migrated": how many jobs have been brought over so far. */
				'jobs_migrated'      => __( 'jobs migrated', 'sitepress' ),
				'communicationError' => __(
					'The communication error with Translation Proxy has appeared. Please try later.',
					'sitepress'
				),
			),
			'nonce'   => wp_nonce_field( WPML_Translation_Jobs_Migration_Ajax::ACTION, WPML_Translation_Jobs_Migration_Ajax::ACTION, false, false ),
		);
	}

	protected function get_notice_id() {
		return 'translation-jobs-migration';
	}
}
