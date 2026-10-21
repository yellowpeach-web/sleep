<?php

class WPML_TM_Email_Jobs_Summary_View extends WPML_TM_Email_View {

	const JOBS_TEMPLATE   = 'batch-report/email-job-pairs.twig';

	private $blog_translators;

	private $sitepress;

	private $assigned_jobs;

	private $job_elements_count;

	private $job_elements_count_total;

	public function __construct(
		WPML_Twig_Template $template_service,
		WPML_TM_Blog_Translators $blog_translators,
		SitePress $sitepress
	) {
		parent::__construct( $template_service );
		$this->blog_translators = $blog_translators;
		$this->sitepress        = $sitepress;
	}

	private function get_jobs_limit() {
		$notification = wpml_get_tm_sub_setting( 'notification', array() );
		$limit        = isset( $notification['job_limits'] ) ? (int) $notification['job_limits'] : 0;
		return 0 === $limit ? null : $limit;
	}

	public function render_jobs_list( $language_pairs, $translator_id, $title_singular, $title_plural = '%s', $title_sliced = '%1$s %2$s' ) {
		$this->clear_assigned_jobs();
		$limit = $this->get_jobs_limit();

		$model = array(
			'strings' => array(
				/* translators: Name of a kind of content in the translation screens: the single texts of the site, as opposed to posts and pages. Plural noun. */
				'strings_text' => __( 'Strings', 'sitepress' ),
				/* translators: Link text in the email WPML sends to a translator; it opens the screen where the work begins. It starts in lower case because it sits inside a sentence. Verb phrase, imperative. */
				'start_translating_text' => __( 'start translating', 'sitepress' ),
				'take' => _x( 'take it', 'Take a translation job waiting for a translator', 'sitepress' ),
				'strings_link' => admin_url(
					'admin.php?page=tm%2Fmenu%2Fmain.php&tab=strings'
				),
				'closing_sentence' => $this->get_closing_sentence(),
			),
		);

		foreach ( $language_pairs as $lang_pair => $elements ) {
			$languages   = explode( '|', $lang_pair );
			$source_lang = $this->sitepress->get_language_details( $languages[0] );
			$target_lang = $this->sitepress->get_language_details( $languages[1] );
			if ( ! $source_lang || ! $target_lang ) {
					continue;
			}

			$args = array(
				'lang_from' => $languages[0],
				'lang_to'   => $languages[1]
			);
			if ( ! $this->blog_translators->is_translator( $translator_id, $args ) ) {
				continue;
			}

			$model_elements = array();
			$string_added   = false;

			foreach ( $elements as $element ) {
				if ( $limit && $limit <= $this->job_elements_count ) {
					$this->job_elements_count_total += 1;
					continue;
				}

				if ( ! $string_added || 'string' !== $element['type'] ) {
					$model_elements[] = array(
						'original_link'          => get_permalink( $element['element_id'] ),
						/* translators: %d: original document ID. */
						'original_text'          => sprintf( __( 'Link to original document %d', 'sitepress' ), $element['element_id'] ),
						'start_translating_link' => admin_url(
							'admin.php?page=' . WPML_TM_FOLDER . '%2Fmenu%2Fmain.php&tab=tasks&job_id=' . $element['job_id']
						),
						'type' => $element['type'],
					);
					$this->job_elements_count       += 1;
					$this->job_elements_count_total += 1;

					if ( 'string' === $element['type'] ) {
						$string_added = true;
					}

					$this->add_assigned_job( $element['job_id'], $element['type'] );
				}
			}

			if ( ! empty( $model_elements ) ) {
				$model['lang_pairs'][ $lang_pair ] = array(
					/* translators: Heading of a group of jobs in the email WPML sends about translation work. %1$s: the language the content is written in, %2$s: the language it is translated into. */
					'title'    => sprintf( __( 'From %1$s to %2$s:', 'sitepress' ), $source_lang['english_name'], $target_lang['english_name'] ),
					'elements' => $model_elements,
				);
			}
		}

		$model['strings']['title'] = $title_singular;
		if ( 1 < $this->job_elements_count_total ) {
			$model['strings']['title'] = sprintf( $title_plural, $this->job_elements_count_total );
			if ( $limit && $limit < $this->job_elements_count_total ) {
				$model['strings']['title'] = sprintf( $title_sliced, $limit, $this->job_elements_count_total );
			}
		}

		return $this->job_elements_count_total ? $this->template_service->show( $model, self::JOBS_TEMPLATE ) : null;
	}

	public function render_link_to_jobs() {
		return sprintf(
			'<p><a href="%1$s">%2$s</a></p>',
			admin_url( 'admin.php?page=' . WPML_TM_FOLDER . '%2Fmenu%2Fmain.php&tab=tasks' ),
			__( 'View all assigned jobs', 'sitepress' )
		);
	}

	public function render_footer() {
		$site_url     = get_bloginfo( 'url' );
		$profile_link = '<a href="' . admin_url( 'profile.php' ) . '" style="color: #ffffff;">' . /* translators: Text of the link to the reader's own profile page, in the footer of the email WPML sends about translation work. */ esc_html__( 'Your Profile', 'sitepress' ) .'</a>';

		$bottom_text = sprintf(
			/* translators: Last lines of the email WPML sends about translation work. %1$s: the name of the site, %2$s: a link to the site, already wrapped in its tags. The words in quotation marks are the label of a setting in the user's own profile, so use the same wording there. */
			__(
				'You are receiving this email because you have a translator 
			account in %1$s. To stop receiving notifications, 
			log-in to %2$s and unselect "Send me a notification email 
			when there is something new to translate". Please note that 
			this will take you out of the translators pool.', 'sitepress'
			),
			$site_url,
			$profile_link
		);

		return $this->render_email_footer( $bottom_text );
	}

	private function add_assigned_job( $job_id, $type ) {
		$this->assigned_jobs[] = array(
			'job_id' => $job_id,
			'type'   => $type,
		);
	}

	public function get_assigned_jobs( $sliced = false ) {
		$assigned_jobs = $this->assigned_jobs;

		if ( $sliced ) {
			$limit         = $this->get_jobs_limit();
			$assigned_jobs = array_slice( $assigned_jobs, 0, $limit );
		}
		
		return $assigned_jobs;
	}

	private function clear_assigned_jobs() {
		$this->assigned_jobs            = array();
		$this->job_elements_count       = 0;
		$this->job_elements_count_total = 0;
	}

	public function has_sliced_assigned_jobs() {
		$limit = $this->get_jobs_limit();
		return $limit && $limit < $this->job_elements_count_total;
	}

	private function get_closing_sentence() {
		$sentence = null;

		if ( WPML_TM_ATE_Status::is_enabled_and_activated() ) {
			$link = '<a href="' . esc_url( \WPML\OutboundLinks\OutboundLinks::to( 'https://wpml.org/documentation/translating-your-contents/advanced-translation-editor/', array( 'medium' => 'notice', 'campaign' => 'translation-management' ) ) ) . '">' . __( "WPML's Advanced Translation Editor", 'sitepress' ) . '</a>';

			/* translators: %s: link to documentation. */
			$sentence = sprintf( __( "Need help translating? Read how to use %s.", 'sitepress' ), $link );
		}

		return $sentence;
	}
}
