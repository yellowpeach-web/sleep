<?php

namespace WPML\TM\ATE\Receive;

use WPML\TM\Jobs\JobLog;

class UnfilteredHtmlGrant {

	const FILTER_ALLOW = 'wpml_pb_grant_unfiltered_html';

	const FILTER_DELIVERY = 'wpml_pb_ate_delivery';

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
				'wpml_job_id'      => (int) $jobId,
				'raw_html_allowed' => $allowed ? 1 : 0,
			]
		);

		$this->armed = true;

		add_filter( self::FILTER_DELIVERY, '__return_true' );

		if ( $allowed ) {
			add_filter( self::FILTER_ALLOW, '__return_true' );
		}
	}
}
