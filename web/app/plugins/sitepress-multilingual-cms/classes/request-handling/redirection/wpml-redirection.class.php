<?php

abstract class WPML_Redirection extends WPML_URL_Converter_User {

	protected $request_handler;

	protected $lang_resolution;

	private $status = 301;

	function __construct( &$url_converter, &$request_handler, &$lang_resolution ) {
		parent::__construct( $url_converter );
		$this->request_handler = $request_handler;
		$this->lang_resolution = $lang_resolution;
	}

	abstract public function get_redirect_target();

	public function get_status() {
		return $this->status;
	}

	protected function set_status( $status ) {
		$this->status = (int) $status;
	}

	protected function removed_language_target( $mode ) {
		$redirect = new \WPML\Languages\RemovedLanguageRedirect( $this->url_converter, $this->request_handler );
		$target   = $redirect->target( $mode );

		if ( false !== $target ) {
			$this->set_status( 301 );
		}

		return $target;
	}

	protected function redirect_hidden_home() {
		$target = false;
		if ( $this->lang_resolution->is_language_hidden( $this->request_handler->get_request_uri_lang() )
			 && ! $this->request_handler->show_hidden()
			 && ! $this->request_handler->is_login_request()
		) {
			$target = $this->url_converter->get_abs_home();

			$this->set_status( 302 );
		}

		return $target;
	}
}
