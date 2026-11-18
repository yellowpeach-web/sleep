<?php

class WPML_User_Language_Switcher_UI {
	private $user_language_switcher;
	private $resources;

	public function __construct( $WPML_User_Language_Switcher, $WPML_User_Language_Switcher_Resources ) {
		$this->user_language_switcher = $WPML_User_Language_Switcher;
		$this->resources              = $WPML_User_Language_Switcher_Resources;
	}

	public function language_switcher( $args, $model ) {
		$this->resources->enqueue_scripts( $args );

		return $this->get_view( $model );
	}

	protected function get_view( $model ) {
		$loader = new WPML_Twig_Template_Loader( array( WPML_PLUGIN_PATH . '/templates/user-language/' ) );

		return $loader->get_template()->show( $model, 'language-switcher.twig' );
	}

}
