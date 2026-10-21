<?php

class WPML_TM_Emails_Settings {

	const TEMPLATE                = 'emails-settings.twig';
	const COMPLETED_JOB_FREQUENCY = 'completed_frequency';

	const MANAGER_RESIGNED = 'manager_resigned';

	const SERVICE_JOB_UPDATE = 'service_job_update';

	const NOTIFY_IMMEDIATELY      = 1;
	const NOTIFY_DAILY            = 2;
	const NOTIFY_WEEKLY           = 3;
	const JOB_LIMITS              = 'job_limits';
	const JOB_LIMITS_ALL          = 0;
	const JOB_LIMITS_5            = 5;
	const JOB_LIMITS_10           = 10;
	const JOB_LIMITS_15           = 15;
	const JOB_LIMITS_20           = 20;

	private $template_service;

	private $tm;

	public function __construct( IWPML_Template_Service $template_service, TranslationManagement $tm ) {
		$this->template_service = $template_service;
		$this->tm               = $tm;
	}

	public function add_hooks() {
		add_action( 'wpml_tm_translation_notification_setting_after', array( $this, 'render' ) );
		add_action( 'wpml_tm_notification_settings_saved', array( $this, 'remove_scheduled_summary_email' ) );
	}

	public function render() {
		echo $this->template_service->show( $this->get_model(), self::TEMPLATE );
	}

	private function get_model() {
		return array(
			'strings'  => array(
				/* translators: Heading of the section that sets which emails WPML sends. */
				'section_title'            => __( 'Notifications', 'sitepress' ),
				'section_title_translator' => __( 'Notification emails to translators', 'sitepress' ),
				'label_new_job'            => __( 'Notify translators when new jobs are waiting for them', 'sitepress' ),
				/* translators: Label in front of the field that sets how many jobs one email may list. The field follows the words, so they end without a full stop. */
				'label_job_limits'         => __( 'Limit number of jobs included in the email to', 'sitepress' ),
				'label_include_xliff'      => __( 'Include XLIFF files in the notification emails', 'sitepress' ),
				'label_resigned_job'       => __( 'Notify translators when jobs are removed from their queue', 'sitepress' ),
				'section_title_manager'    => __( 'Notification emails to the translation manager', 'sitepress' ),
				'label_manager_resigned'   => __( 'Notify the translation manager when a translator resigns from a job', 'sitepress' ),
				'label_service_job_update' => __( 'Notify the translation manager when the translation service updates or cancels a job', 'sitepress' ),
				/* translators: %s: completed-jobs summary frequency (never, once a day, once a week). */
				'label_completed_job'      => esc_html__( 'Send the translation manager a completed-jobs summary %s', 'sitepress' ),
				/* translators: %s: number of overdue days. */
				'label_overdue_job'        => esc_html__( 'Notify the translation manager when jobs are late by %s days', 'sitepress' ),
				/* translators: Button label that keeps what was entered. Verb, imperative. */
				'label_save'               => esc_attr__( 'Save', 'sitepress' ),
			),
			'settings' => array(
				'new_job'             => array(
					'value'   => self::NOTIFY_IMMEDIATELY,
					'checked' => checked( self::NOTIFY_IMMEDIATELY, $this->tm->settings['notification']['new-job'], false ),
				),
				'include_xliff'       => array(
					'value'    => 1,
					'checked'  => checked( 1, $this->tm->settings['notification']['include_xliff'], false ),
					'disabled' => disabled( 0, $this->tm->settings['notification']['new-job'], false ),
				),
				'resigned'            => array(
					'value'   => self::NOTIFY_IMMEDIATELY,
					'checked' => checked( self::NOTIFY_IMMEDIATELY, $this->tm->settings['notification']['resigned'], false ),
				),
				'completed_frequency' => array(
					'options' => array(
						array(
							/* translators: Value shown in place of a date when the thing has not happened yet. Written in lower case as the code shows it. */
							'label'   => __( 'never', 'sitepress' ),
							'value'   => ICL_TM_NOTIFICATION_NONE,
							'checked' => selected( ICL_TM_NOTIFICATION_NONE, $this->tm->settings['notification'][ self::COMPLETED_JOB_FREQUENCY ], false ),
						),
						array(
							/* translators: Label of the option that sends the email once every day. It starts in lower case because it follows the words "Send a summary". */
							'label'   => __( 'once a day', 'sitepress' ),
							'value'   => self::NOTIFY_DAILY,
							'checked' => selected( self::NOTIFY_DAILY, $this->tm->settings['notification'][ self::COMPLETED_JOB_FREQUENCY ], false ),
						),
						array(
							/* translators: Label of the option that sends the email once every week. It starts in lower case because it follows the words "Send a summary". */
							'label'   => __( 'once a week', 'sitepress' ),
							'value'   => self::NOTIFY_WEEKLY,
							'checked' => selected( self::NOTIFY_WEEKLY, $this->tm->settings['notification'][ self::COMPLETED_JOB_FREQUENCY ], false ),
						),
					),
				),
				'job_limits'          => array(
					'options'  => array(
						array(
							/* translators: Label of the option that puts every job into the email instead of only a few. */
							'label'   => __( 'Send all', 'sitepress' ),
							'value'   => self::JOB_LIMITS_ALL,
							'checked' => selected( self::JOB_LIMITS_ALL, $this->tm->settings['notification'][ self::JOB_LIMITS ], false ),
						),
						array(
							'label'   => '5',
							'value'   => self::JOB_LIMITS_5,
							'checked' => selected( self::JOB_LIMITS_5, $this->tm->settings['notification'][ self::JOB_LIMITS ], false ),
						),
						array(
							'label'   => '10',
							'value'   => self::JOB_LIMITS_10,
							'checked' => selected( self::JOB_LIMITS_10, $this->tm->settings['notification'][ self::JOB_LIMITS ], false ),
						),
						array(
							'label'   => '15',
							'value'   => self::JOB_LIMITS_15,
							'checked' => selected( self::JOB_LIMITS_15, $this->tm->settings['notification'][ self::JOB_LIMITS ], false ),
						),
						array(
							'label'   => '20',
							'value'   => self::JOB_LIMITS_20,
							'checked' => selected( self::JOB_LIMITS_20, $this->tm->settings['notification'][ self::JOB_LIMITS ], false ),
						),
					),
					'disabled' => disabled( 0, $this->tm->settings['notification']['new-job'], false ),
				),
				'manager_resigned'    => array(
					'value'   => self::NOTIFY_IMMEDIATELY,
					'checked' => checked( self::NOTIFY_IMMEDIATELY, $this->tm->settings['notification'][ self::MANAGER_RESIGNED ], false ),
				),
				'service_job_update'  => array(
					'value'   => self::NOTIFY_IMMEDIATELY,
					'checked' => checked( self::NOTIFY_IMMEDIATELY, $this->tm->settings['notification'][ self::SERVICE_JOB_UPDATE ], false ),
				),
				'overdue'             => array(
					'value'   => 1,
					'checked' => checked( self::NOTIFY_IMMEDIATELY, $this->tm->settings['notification']['overdue'], false ),
				),
				'overdue_offset'      => array(
					'value'    => $this->tm->settings['notification']['overdue_offset'],
					'disabled' => disabled( 0, $this->tm->settings['notification']['overdue'], false ),
				),
			),
		);
	}

	public function remove_scheduled_summary_email() {
		wp_clear_scheduled_hook( WPML_TM_Jobs_Summary_Report_Hooks::EVENT_HOOK );
	}
}
