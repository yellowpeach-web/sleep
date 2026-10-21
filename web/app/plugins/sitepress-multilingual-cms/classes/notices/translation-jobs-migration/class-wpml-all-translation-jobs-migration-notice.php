<?php

class WPML_All_Translation_Jobs_Migration_Notice extends WPML_Translation_Jobs_Migration_Notice {

	protected function get_model() {
		return array(
			'strings' => array(
				'title'              => __( 'Problem receiving translation jobs?', 'sitepress' ),
				'description'        => __( 'WPML needs to update its table of translation jobs, so that your site can continue receiving completed translations. This process will take a few minutes and does not modify content or translations in your site.', 'sitepress' ),
				/* translators: Button label in a notice that offers to bring old translation jobs up to date. Verb phrase, imperative. */
				'button'             => __( 'Start update', 'sitepress' ),
				/* translators: Word between two numbers above a list, as in "Displaying 1 of 20": the first is what is shown, the second is how many there are in all. */
				'of'                 => __( 'of', 'sitepress' ),
				/* translators: Words after a number in that notice, as in "12 jobs fixed": how many jobs have been repaired so far. */
				'jobs_migrated'      => __( 'jobs fixed', 'sitepress' ),
				'communicationError' => __(
					'The communication error with Translation Proxy has appeared. Please try later.',
					'sitepress'
				),
			),
			'nonce'   => wp_nonce_field(
				WPML_Translation_Jobs_Migration_Ajax::ACTION,
				WPML_Translation_Jobs_Migration_Ajax::ACTION,
				false,
				false
			),
		);
	}

	protected function get_notice_id() {
		return 'all-translation-jobs-migration';
	}
}
