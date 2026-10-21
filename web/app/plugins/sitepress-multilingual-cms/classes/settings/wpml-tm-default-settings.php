<?php

class WPML_TM_Default_Settings {

	public static function apply_notification_defaults( array $settings ) {
		$notification = isset( $settings['notification'] ) && is_array( $settings['notification'] )
			? $settings['notification']
			: array();

		$notification += array(
			'new-job'       => WPML_TM_Emails_Settings::NOTIFY_IMMEDIATELY,
			'include_xliff' => ICL_TM_NOTIFICATION_NONE,
			'job_limits'    => WPML_TM_Emails_Settings::JOB_LIMITS_ALL,
		);

		if ( ! isset( $notification[ WPML_TM_Emails_Settings::COMPLETED_JOB_FREQUENCY ] ) ) {
			$notification[ WPML_TM_Emails_Settings::COMPLETED_JOB_FREQUENCY ] = WPML_TM_Emails_Settings::NOTIFY_WEEKLY;
		}

		$notification += array(
			'resigned'       => WPML_TM_Emails_Settings::NOTIFY_IMMEDIATELY,
			'overdue'        => WPML_TM_Emails_Settings::NOTIFY_IMMEDIATELY,
			'overdue_offset' => 7,
		);

		$settings['notification'] = self::apply_manager_notification_defaults( $notification );

		return $settings;
	}

	public static function apply_manager_notification_defaults( array $notification ) {
		if ( ! isset( $notification[ WPML_TM_Emails_Settings::MANAGER_RESIGNED ] ) ) {
			$notification[ WPML_TM_Emails_Settings::MANAGER_RESIGNED ] = isset( $notification['resigned'] )
				? $notification['resigned']
				: ICL_TM_NOTIFICATION_NONE;
		}

		if ( ! isset( $notification[ WPML_TM_Emails_Settings::SERVICE_JOB_UPDATE ] ) ) {
			$notification[ WPML_TM_Emails_Settings::SERVICE_JOB_UPDATE ] = WPML_TM_Emails_Settings::NOTIFY_IMMEDIATELY;
		}

		return $notification;
	}
}
