<?php

use WPML\Templates\PhpTwinRenderer;

class WPML_Twig_Template_Deferred extends WPML_Twig_Template {

	private $build;

	private $template;

	private $php_twins;

	public function __construct( callable $build, array $paths = array() ) {
		$this->build     = $build;
		$this->php_twins = $paths ? new PhpTwinRenderer( $paths ) : null;
	}

	public function show( $model, $template ) {
		if ( $this->php_twins ) {
			$html = $this->php_twins->show( $template, $model );

			if ( null !== $html ) {
				return $html;
			}
		}

		if ( ! $this->template ) {
			$this->template = call_user_func( $this->build );
		}

		return $this->template->show( $model, $template );
	}
}
