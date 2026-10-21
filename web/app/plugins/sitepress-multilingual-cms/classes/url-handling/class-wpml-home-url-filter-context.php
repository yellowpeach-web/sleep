<?php

class WPML_Home_Url_Filter_Context {

	const REST_REQUEST  = 'rest-request';
	const REWRITE_RULES = 'rewrite-rules';
	const PAGINATION    = 'pagination';

	private $language_negotiation_type;

	private $orig_scheme;

	private $debug_backtrace;

	public function __construct( $language_negotiation_type, $orig_scheme, WPML_Debug_BackTrace $debug_backtrace ) {
		$this->language_negotiation_type = $language_negotiation_type;
		$this->orig_scheme               = $orig_scheme;
		$this->debug_backtrace           = $debug_backtrace;
	}

	public function should_not_filter() {
		return $this->is_rest_request()
			|| $this->is_called_from_guarded_function();
	}

	public function is_rest_request() {
		return in_array( $this->orig_scheme, array( 'json', 'rest' ), true );
	}

	public function is_called_from_guarded_function() {
		$functions = array( array( 'WP_Rewrite', 'rewrite_rules' ) );

		if ( WPML_LANGUAGE_NEGOTIATION_TYPE_PARAMETER === $this->language_negotiation_type ) {
			$functions[] = 'get_pagenum_link';
		}

		return $this->debug_backtrace->are_functions_in_call_stack( $functions );
	}
}
