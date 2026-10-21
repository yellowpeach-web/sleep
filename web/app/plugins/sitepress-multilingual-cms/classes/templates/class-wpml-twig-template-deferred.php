<?php

class WPML_Twig_Template_Deferred extends WPML_Twig_Template {

	private $build;

	private $template;

	public function __construct( callable $build ) {
		$this->build = $build;
	}

	public function show( $model, $template ) {
		if ( ! $this->template ) {
			$this->template = call_user_func( $this->build );
		}

		return $this->template->show( $model, $template );
	}
}
