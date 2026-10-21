<?php

namespace WPML\Upgrade\Commands;

use WPML\WP\OptionManager;

class ForgetSiteWideSiteKeyNoticeDismissal implements \IWPML_Upgrade_Command {

	const OPTION = '_wpml_dismissed_notices';

	const NOTICE_GROUP = 'wpml-site-key-notices';
	const NOTICE_ID    = 'wpml-site-key-notice-regular';

	private $optionManager;

	private $results;

	public function __construct( array $args = [] ) {
		$this->optionManager = isset( $args[0] ) ? $args[0] : new OptionManager();
	}

	public function run() {
		if ( $this->holdsTheRecord( get_option( self::OPTION ) ) ) {
			$this->optionManager->mutateRaw(
				self::OPTION,
				function ( $current ) {
					if ( $this->holdsTheRecord( $current ) ) {
						unset( $current[ self::NOTICE_GROUP ][ self::NOTICE_ID ] );
					}

					return $current;
				},
				false
			);
		}

		$this->results = true;

		return $this->results;
	}

	private function holdsTheRecord( $dismissed ) {
		return is_array( $dismissed ) && isset( $dismissed[ self::NOTICE_GROUP ][ self::NOTICE_ID ] );
	}

	public function run_admin() {
		return $this->run();
	}

	public function run_ajax() {
		return null;
	}

	public function run_frontend() {
		return null;
	}

	public function get_results() {
		return $this->results;
	}
}
