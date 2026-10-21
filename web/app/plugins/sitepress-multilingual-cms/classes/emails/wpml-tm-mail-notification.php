<?php

class WPML_TM_Mail_Notification {

	const JOB_REVISED_TEMPLATE  = 'notification/job-revised.twig';
	const JOB_CANCELED_TEMPLATE = 'notification/job-canceled.twig';

	private $mail_cache = array();
	private $process_mail_queue;

	private $wpdb;

	private $sitepress;

	private $job_factory;

	private $email_view;

	private $notification_settings;

	private $has_active_remote_service;

	public function __construct(
		SitePress $sitepress,
		wpdb $wpdb,
		WPML_Translation_Job_Factory $job_factory,
		WPML_TM_Email_Notification_View $email_view,
		array $notification_settings,
		$has_active_remote_service
	) {
		$this->wpdb                      = $wpdb;
		$this->sitepress                 = $sitepress;
		$this->job_factory               = $job_factory;
		$this->email_view                = $email_view;
		$this->notification_settings     = WPML_TM_Default_Settings::apply_manager_notification_defaults(
			array_merge(
				array(
					'resigned' => 0,
				),
				$notification_settings
			)
		);
		$this->has_active_remote_service = $has_active_remote_service;
	}

	public function init() {
		add_action( 'wpml_tm_empty_mail_queue', array( $this, 'send_queued_mails' ), 10, 0 );

		add_action( 'wpml_tm_revised_job_notification', array( $this, 'action_revised_job_email' ), 10 );
		add_action( 'wpml_tm_canceled_job_notification', array( $this, 'action_canceled_job_email' ), 10 );

		add_action( 'wpml_tm_remove_job_notification', array( $this, 'action_translator_removed_mail' ), 10, 2 );
		add_action( 'wpml_tm_jobs_cancelled', array( $this, 'action_cancelled_jobs_translator_mail' ), 10, 1 );
		add_action( 'wpml_tm_resign_job_notification', array( $this, 'action_translator_resign_mail' ), 10, 2 );
		add_action( 'icl_pro_translation_completed', array( $this, 'send_queued_mails' ), 10, 0 );

		if ( is_admin() ) {
			add_action( 'shutdown', array( $this, 'send_queued_mails' ), 10, 0 );
		}
	}

	public function send_queued_mails() {
		$tj_url = admin_url( 'admin.php?page=' . WPML_TM_FOLDER . '/menu/main.php&tab=tasks' );

		foreach ( $this->mail_cache as $type => $mail_to_send ) {
			foreach ( $mail_to_send as $to => $subjects ) {
				foreach ( $subjects as $subject => $content ) {
					$headers      = '';
					$body_to_send = '';
					$body         = $content['body'];
					$home_url     = get_home_url();

					if ( 'completed' === $type ) {
						$headers = array(
							'Content-type: text/html; charset=UTF-8',
						);

						$body_to_send = $body[0];

					} else {
						$body_to_send = "\n\n" . implode( "\n\n\n\n", $body ) . "\n\n\n\n";

						if ( $type === 'translator' ) {
							$footer  = sprintf(
								/* translators: %s: URL to the Translation Jobs page. */
								__( 'You can view your other translation jobs here: %s', 'sitepress' ),
								$tj_url
							) . "\n\n--\n";
							$footer .= sprintf(
								/* translators: Footer of the email WPML sends to a translator. %1$s: the name of the site, %2$s: the web address of the site, which is where the person running it can be reached. */
								__(
									"This message was automatically sent by Translation Management running on %1\$s. To stop receiving these notifications contact the system administrator at %2\$s.\n\nThis email is not monitored for replies.",
									'sitepress'
								),
								get_bloginfo( 'name' ),
								$home_url
							);
						} else {
							$footer = "\n--\n" . sprintf(
								/* translators: Footer of the email WPML sends to a translation manager. %1$s: the name of the site, %2$s: the web address of the site, which is where the person running it can be reached. "Notification Settings" is the name of a screen in the WPML menu. */
								__(
									"This message was automatically sent by Translation Management running on %1\$s. To stop receiving these notifications, go to Notification Settings, or contact the system administrator at %2\$s.\n\nThis email is not monitored for replies.",
									'sitepress'
								),
								get_bloginfo( 'name' ),
								$home_url
							);
						}

						$body_to_send .= $footer;
					}

					$attachments = isset( $content['attachment'] ) ? $content['attachment'] : array();
					$attachments = apply_filters( 'wpml_new_job_notification_attachments', $attachments );

					$attachments = apply_filters( 'WPML_new_job_notification_attachments', $attachments );

					WPML_Mail_Sender::send( $to, $subject, $body_to_send, $headers, $attachments, $type );
				}
			}
		}
		$this->mail_cache         = array();
		$this->process_mail_queue = false;
	}

	private function get_basic_mail_data( $job_id ) {
		$manager_id = false;

		if ( is_object( $job_id ) ) {
			$job = $job_id;
		} else {
			$job = $this->job_factory->get_translation_job( $job_id, false, 0, true );
		}

		if ( is_object( $job ) ) {
			$data       = $job->get_basic_data();
			$manager_id = isset( $data->manager_id ) ? $data->manager_id : -1;
		}

		if (
			! $job instanceof WPML_Translation_Job ||
			( $manager_id && (int) $manager_id === (int) $job->get_translator_id() )
		) {
			return null;
		}

		$manager       = new WP_User( $manager_id );
		$translator    = new WP_User( $job->get_translator_id() );
		$user_language = $this->sitepress->get_user_admin_language( $manager->ID );

		$mail = array(
			'to' => $manager->display_name . ' <' . $manager->user_email . '>',
		);

		$this->sitepress->switch_locale( $user_language );

		list( $lang_from, $lang_to ) = $this->get_lang_to_from( $job, $user_language );

		$model = array(
			'view_jobs_text' => __( 'View translation jobs', 'sitepress' ),
			'username'       => $manager->display_name,
			'lang_from'      => $lang_from,
			'lang_to'        => $lang_to,
		);

		$document_title = $job->get_title();

		if ( 'string' !== strtolower( $job->get_type() ) ) {
			$model['translation_jobs_url'] = admin_url( 'admin.php?page=' . WPML_TM_FOLDER . '/menu/main.php&tab=jobs' );
			$document_title                = '<a href="' . $job->get_url( true ) . '">' . $document_title . '</a>';
			$model                         = $this->update_model_for_deadline( $model, $job );
		}

		return array(
			'mail'           => $mail,
			'document_title' => $document_title,
			'job'            => $job,
			'manager'        => $manager,
			'translator'     => $translator,
			'model'          => $model,
		);
	}

	public function action_revised_job_email( $job_id ) {
		$this->revised_job_email( $job_id );
	}

	public function revised_job_email( $job_id ) {

		/* translators: %s: site name. */
		$subject     = sprintf( __( 'Translator job updated for %s', 'sitepress' ), get_bloginfo( 'name' ) );
		/* translators: Body of the email WPML sends when a translation comes back from the translation service. "It's ready" is about that translation. %1$s: the name of the site, %2$s: the language it was translated from, %3$s: the language it was translated into. */
		$placeholder = esc_html__(
			'A new translation for %1$s from %2$s to %3$s was created on the Translation Service. It&#8217;s ready to download and will be applied next time the Translation Service delivers completed translations to your site or when you manually fetch them.',
			'sitepress'
		);

		return $this->generic_update_notification_email( $job_id, $subject, $placeholder, self::JOB_REVISED_TEMPLATE );

	}
	public function action_canceled_job_email( WPML_TM_Job_Entity $job ) {
		$this->canceled_job_email( $job );
	}

	public function canceled_job_email( WPML_TM_Job_Entity $job ) {
		if ( ! $job instanceof WPML_TM_Post_Job_Entity ) {
			return false;
		}

		/* translators: %s: site name. */
		$subject     = sprintf( __( 'Translator job canceled for %s', 'sitepress' ), get_bloginfo( 'name' ) );
		/* translators: Body of the email WPML sends when a translation was called off at the translation service. %1$s: the name of the site, %2$s: the language it was to be translated from, %3$s: the language it was to be translated into. */
		$placeholder = esc_html__(
			'The translation for %1$s from %2$s to %3$s was canceled on the Translation Service. You can send this document to translation again from the Translation Dashboard.',
			'sitepress'
		);

		return $this->generic_update_notification_email(
			$job->get_translate_job_id(),
			$subject,
			$placeholder,
			self::JOB_CANCELED_TEMPLATE
		);
	}

	private function generic_update_notification_email( $job_id, $mail_subject, $body_placeholder, $template ) {
		if ( ICL_TM_NOTIFICATION_IMMEDIATELY !== (int) $this->notification_settings[ WPML_TM_Emails_Settings::SERVICE_JOB_UPDATE ] ) {
			return null;
		}

		$basic_mail_data = $this->get_basic_mail_data( $job_id );
		if ( null === $basic_mail_data ) {
			return null;
		}

		$mail           = $basic_mail_data['mail'];
		$document_title = $basic_mail_data['document_title'];

		$model     = $basic_mail_data['model'];
		$lang_from = $model['lang_from'];
		$lang_to   = $model['lang_to'];

		$mail['subject']  = $mail_subject;
		$model['message'] = sprintf( $body_placeholder, $document_title, $lang_from, $lang_to );
		$mail['body']     = $this->email_view->render_model( $model, $template );
		$mail['type']     = 'completed';
		$this->enqueue_mail( $mail );

		$this->sitepress->switch_locale();

		return $mail;
	}

	private function update_model_for_deadline( array $model, WPML_Element_Translation_Job $job ) {
		if ( $job->is_completed_on_time() ) {
			$model['deadline_status'] = __( 'The translation job was completed on time.', 'sitepress' );
			return $model;
		}

		$overdue_days = $job->get_number_of_days_overdue();

		if ( ! $overdue_days ) {
			$model['deadline_status'] = '';
			return $model;
		}

		$model['deadline_status'] = sprintf(
			/* translators: Line in the email WPML sends about work that is late. %s: by how many days it is late. */
			_n(
				'This translation job is overdue by %s day.',
				'This translation job is overdue by %s days.',
				$overdue_days,
				'sitepress'
			),
			$overdue_days
		);

		if ( $overdue_days >= 7 ) {
			$model['promote_translation_services'] = ! $this->has_active_remote_service;
		}

		return $model;
	}

	public function action_translator_removed_mail( $translator_id, $job ) {
		$this->translator_removed_mail( $translator_id, $job );
	}

	public function translator_removed_mail( $translator_id, $job ) {
		list( $manager_id, $job ) = $this->get_mail_elements( $job );
		if ( ! $job || $manager_id == $translator_id ) {
			return false;
		}
		$translator    = new WP_User( $translator_id );
		$manager       = new WP_User( $manager_id );
		$user_language = $this->sitepress->get_user_admin_language( $manager->ID );
		$doc_title     = $job->get_title();
		$this->sitepress->switch_locale( $user_language );
		list( $lang_from, $lang_to ) = $this->get_lang_to_from( $job, $user_language );
		$mail['to']                  = $translator->display_name . ' <' . $translator->user_email . '>';
		/* translators: %s: site name. */
		$mail['subject']             = sprintf( __( 'Removed from translation job on %s', 'sitepress' ), get_bloginfo( 'name' ) );
		$mail['body']                = sprintf(
			/* translators: Body of the email WPML sends when a translator is taken off a job. %1$s: the title of the content, %2$s: the language it is translated from, %3$s: the language it is translated into. */
			__( 'You have been removed from the translation job "%1$s" for %2$s to %3$s.', 'sitepress' ),
			$doc_title,
			$lang_from,
			$lang_to
		);
		$mail['type']                = 'translator';
		$this->enqueue_mail( $mail );
		$this->sitepress->switch_locale();

		return $mail;
	}

	public function action_cancelled_jobs_translator_mail( $jobs ) {
		if ( ICL_TM_NOTIFICATION_IMMEDIATELY !== (int) $this->notification_settings['resigned'] ) {
			return;
		}

		$actor_id = (int) get_current_user_id();
		$queued   = false;

		foreach ( is_object( $jobs ) ? array( $jobs ) : (array) $jobs as $job ) {
			list( $job_id, $translator_id, $method ) = $this->cancelled_job_recipient( $job );

			if (
				! $job_id
				|| ! $translator_id
				|| $translator_id === $actor_id
				|| ( null !== $method && 'local-translator' !== $method )
				|| ! get_userdata( $translator_id )
			) {
				continue;
			}

			do_action( 'wpml_tm_remove_job_notification', $translator_id, $job_id );
			$queued = true;
		}

		if ( $queued ) {
			do_action( 'wpml_tm_empty_mail_queue' );
		}
	}

	private function cancelled_job_recipient( $job ) {
		if ( $job instanceof WPML_TM_Post_Job_Entity ) {
			if ( $job->is_automatic() ) {
				$method = 'automatic';
			} elseif ( $job->get_translation_service() && 'local' !== $job->get_translation_service() ) {
				$method = 'translation-service';
			} else {
				$method = 'local-translator';
			}

			return array( (int) $job->get_translate_job_id(), (int) $job->get_translator_id(), $method );
		}

		$job_id        = isset( $job->job_id ) ? (int) $job->job_id : 0;
		$translator_id = isset( $job->translator_id ) ? (int) $job->translator_id : 0;
		$method        = isset( $job->translation_method ) ? (string) $job->translation_method : null;

		if ( $job_id && ! isset( $job->translator_id ) ) {
			$loaded = $this->job_factory->get_translation_job( $job_id, false, 0, true );
			if ( $loaded ) {
				$data          = $loaded->get_basic_data();
				$translator_id = isset( $data->translator_id ) ? (int) $data->translator_id : 0;
			}
		}

		return array( $job_id, $translator_id, $method );
	}

	public function action_translator_resign_mail( $translator_id, $job_id ) {
		$this->translator_resign_mail( $translator_id, $job_id );
	}

	public function translator_resign_mail( $translator_id, $job_id ) {
		list( $manager_id, $job ) = $this->get_mail_elements( $job_id );
		if ( ! $job || $manager_id == $translator_id ) {
			return false;
		}
		$translator    = new WP_User( $translator_id );
		$manager       = new WP_User( $manager_id );
		$tj_url        = admin_url( 'admin.php?page=' . WPML_TM_FOLDER . '/menu/main.php&tab=jobs' );
		$doc_title     = $job->get_title();
		$user_language = $this->sitepress->get_user_admin_language( $manager->ID );
		$this->sitepress->switch_locale( $user_language );
		list( $lang_from, $lang_to ) = $this->get_lang_to_from( $job, $user_language );
		$mail                        = array();
		if ( ICL_TM_NOTIFICATION_IMMEDIATELY === (int) $this->notification_settings[ WPML_TM_Emails_Settings::MANAGER_RESIGNED ] ) {
			$mail['to']         = $manager->display_name . ' <' . $manager->user_email . '>';
			$mail['subject']    = sprintf(
				/* translators: %s: site name. */
				__( 'Translator has resigned from job on %s', 'sitepress' ),
				get_bloginfo( 'name' )
			);
			/* translators: Shown in place of the title of a piece of content that no longer exists. Past participle used as a state. */
			$original_doc_title = $doc_title ? $doc_title : __( 'Deleted', 'sitepress' );
			$mail['body']       = sprintf(
				/* translators: Body of the email WPML sends when a translator gives up a job. %1$s: the name of the translator, %2$s: the title of the content, %3$s: the language it is translated from, %4$s: the language it is translated into, %5$s: a line break, %6$s: the address of the screen listing the translation jobs. */
				__(
					'Translator %1$s has resigned from the translation job "%2$s" for %3$s to %4$s.%5$sView translation jobs: %6$s',
					'sitepress'
				),
				$translator->display_name,
				$original_doc_title,
				$lang_from,
				$lang_to,
				"\n",
				$tj_url
			);
			$mail['type']       = 'admin';
			$this->enqueue_mail( $mail );
		}
		$this->sitepress->switch_locale();

		return $mail;
	}

	private function enqueue_mail( $mail ) {
		if ( $mail !== 'empty_queue' ) {
			$this->mail_cache[ $mail['type'] ][ $mail['to'] ][ $mail['subject'] ]['body'][] = $mail['body'];
			if ( isset( $mail['attachment'] ) ) {
				$this->mail_cache[ $mail['type'] ][ $mail['to'] ][ $mail['subject'] ]['attachment'][] = $mail['attachment'];
			}
			$this->process_mail_queue = true;
		}
	}

	private function get_mail_elements( $job_id ) {
		$job = is_object( $job_id ) ? $job_id : $this->job_factory->get_translation_job(
			$job_id,
			false,
			0,
			true
		);
		if ( is_object( $job ) ) {
			$data       = $job->get_basic_data();
			$manager_id = isset( $data->manager_id ) ? $data->manager_id : - 1;
		} else {
			$job        = false;
			$manager_id = false;
		}

		return array( $manager_id, $job );
	}

	private function get_lang_to_from( $job, $user_language ) {
		$wpdb      = $this->wpdb;
		$lang_from = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT name FROM {$wpdb->prefix}icl_languages_translations WHERE language_code=%s AND display_language_code=%s LIMIT 1",
				$job->get_source_language_code(),
				$user_language
			)
		);
		$lang_to   = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT name FROM {$wpdb->prefix}icl_languages_translations WHERE language_code=%s AND display_language_code=%s LIMIT 1",
				$job->get_language_code(),
				$user_language
			)
		);

		return array( $lang_from, $lang_to );
	}
}
