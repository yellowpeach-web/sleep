<?php

namespace OTGS\Installer\Api\Exception;

class ServiceUnavailable extends \Exception {

	private $status;

	public function __construct( $status ) {
		$this->status = (int) $status;
		parent::__construct( sprintf( 'The service answered HTTP %d instead of a response.', $this->status ) );
	}

	public function getStatus() {
		return $this->status;
	}
}
