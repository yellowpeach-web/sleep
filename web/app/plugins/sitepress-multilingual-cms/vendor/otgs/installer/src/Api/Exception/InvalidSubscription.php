<?php

namespace OTGS\Installer\Api\Exception;

class InvalidSubscription extends \Exception {

	const KEY_NOT_MATCHING = 'Site key not matching';

	private $details;

	private $from_api;

	public function __construct( $details, $from_api = false ) {
		$this->details  = is_scalar( $details ) ? (string) $details : '';
		$this->from_api = (bool) $from_api;
		parent::__construct( "Unable to register: " . $this->details );
	}

	public function get_details() {
		return $this->details;
	}

	public function refuses_key() {
		return $this->from_api && self::KEY_NOT_MATCHING === trim( $this->details );
	}
}
