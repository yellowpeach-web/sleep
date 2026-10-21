<?php

class WPML_API_Hook_Single_Url implements IWPML_Action {

	private $resolver;

	public function __construct( WPML_Resolve_Single_Url $resolver ) {
		$this->resolver = $resolver;
	}

	public function add_hooks() {
		add_filter( 'wpml_resolve_single_url', [ $this, 'resolve' ], 10, 4 );
	}

	public function resolve( $resolution, $url, $target_language, $source_language = null ) {
		if ( null !== $resolution ) {
			return $resolution;
		}

		return $this->resolver->resolve( $url, $target_language, $source_language );
	}
}
