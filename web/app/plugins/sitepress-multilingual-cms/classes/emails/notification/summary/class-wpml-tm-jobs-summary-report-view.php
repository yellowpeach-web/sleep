<?php

class WPML_TM_Jobs_Summary_Report_View extends WPML_TM_Email_View {

	const WEEKLY_SUMMARY_TEMPLATE = 'notification/summary/summary.twig';

	private $jobs;

	private $manager_id;

	private $summary_text;

	public function get_report_content() {
		$model   = $this->get_model();
		$content = $this->render_header( $model['username'] );
		$content .= $this->template_service->show( $model, self::WEEKLY_SUMMARY_TEMPLATE );
		$content .= $this->render_email_footer();

		return $content;
	}

	private function get_model() {
		$manager = get_userdata( $this->manager_id );

		return array(
			'username'          => $manager ? $manager->display_name : '',
			'jobs'              => $this->jobs,
			'text'              => $this->summary_text,
			'site_name'         => get_bloginfo( 'name' ),
			'number_of_updates' => isset( $this->jobs['completed'] ) ? count( $this->jobs['completed'] ) : 0,
			'strings'           => array(
				/* translators: Heading in the email WPML sends about translation work, above the list of content that is waiting to be translated. */
				'jobs_waiting'          => __( 'Jobs that are waiting for translation', 'sitepress' ),
				/* translators: Column heading in the email WPML sends about translation work: the content in its original language. */
				'original_page'         => __( 'Original Page', 'sitepress' ),
				/* translators: Column heading in a table and a field label, for the translation of a piece of content. Noun. */
				'translation'           => __( 'Translation', 'sitepress' ),
				/* translators: Column heading in the email WPML sends about translation work: the person who translates. */
				'translator'            => __( 'Translator', 'sitepress' ),
				/* translators: Column heading in the email WPML sends about translation work: when the content was last changed and when it was translated. */
				'updated'               => __( 'Updated / Translated', 'sitepress' ),
				/* translators: Column heading in a table: the date something happened. */
				'date'                  => __( 'Date', 'sitepress' ),
				/* translators: Column heading in the email WPML sends about translation work: the date by which the reader has to finish. */
				'your_deadline'         => __( 'Your deadline', 'sitepress' ),
				/* translators: Column heading in the email WPML sends about translation work: the languages the content is being translated into. */
				'translation_languages' => __( 'Translation languages', 'sitepress' ),
				'number_of_pages'       => __( 'Number of pages', 'sitepress' ),
				'number_of_strings'     => __( 'Number of strings', 'sitepress' ),
				'number_of_words'       => __( 'Number of words', 'sitepress' ),
				/* translators: Value in the email WPML sends about translation work, shown when no date or name was set. */
				'undefined'             => __( 'Not set', 'sitepress' ),
			),
			'improve_quality'   => array(
				'title'   => __( 'Want to improve the quality of your site’s translation?', 'sitepress' ),
				'options' => array(
					array(
						'link_url'  => admin_url( 'admin.php?page=' . WPML_TM_FOLDER . '/menu/settings&section=translators' ),
						/* translators: Link text inside a sentence in the email WPML sends about translation work. It starts in lower case because it sits inside the sentence. */
						'link_text' => __( 'translation services that are integrated with WPML', 'sitepress' ),
						/* translators: %s: link to available translation services. */
						'text'      => __( 'Try one of the %s', 'sitepress' ),
					),
				)
			),
		);
	}

	public function set_jobs( $jobs ) {
		$this->jobs = $jobs;

		return $this;
	}

	public function set_manager_id( $manager_id ) {
		$this->manager_id = $manager_id;

		return $this;
	}

	public function set_summary_text( $summary_text ) {
		$this->summary_text = $summary_text;

		return $this;
	}
}