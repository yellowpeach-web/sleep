<?php

class OTGS_Installer_Fetch_Subscription_Postponed_Exception extends OTGS_Installer_Fetch_Subscription_Exception {

	private $retry_at;

	public function __construct( $retry_at ) {
		$this->retry_at = (int) $retry_at;

		parent::__construct(
			sprintf(
				'The subscription check waits after a recent or failed check; next check at %s UTC.',
				gmdate( 'Y-m-d H:i:s', $this->retry_at )
			)
		);
	}

	public function get_retry_at() {
		return $this->retry_at;
	}

	public static function user_message() {
		return __( 'This site key was checked less than a minute ago. Wait a minute, then try again.', 'installer' );
	}
}
