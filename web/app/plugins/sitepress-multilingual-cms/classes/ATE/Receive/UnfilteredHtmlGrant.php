<?php

namespace WPML\TM\ATE\Receive;

use WPML\TM\Jobs\JobLog;

class UnfilteredHtmlGrant {

	const FILTER_ALLOW = 'wpml_pb_grant_unfiltered_html';

	private $settings;

	private $armed = false;

	public function __construct( ?\WPML_Page_Builder_Settings $settings = null ) {
		$this->settings = $settings ?: new \WPML_Page_Builder_Settings();
	}

	public function arm( $jobId ) {
		if ( $this->armed ) {
			return;
		}

		$allowed = $this->settings->is_raw_html_translatable();

		JobLog::add(
			'unfiltered_html_grant',
			[
				'wpml_job_id' => (int) $jobId,
				'armed'       => $allowed ? 1 : 0,
			]
		);

		if ( ! $allowed ) {
			return;
		}

		$this->armed = true;

		add_filter( self::FILTER_ALLOW, '__return_true' );
	}
}
