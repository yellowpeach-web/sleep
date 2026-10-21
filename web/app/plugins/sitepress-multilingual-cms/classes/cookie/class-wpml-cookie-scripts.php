<?php

class WPML_Cookie_Scripts {

	private static $registered;

	private $language_cookie_name;

	private $current_language;

	public function __construct( $language_cookie_name, $current_language ) {
		$this->language_cookie_name = $language_cookie_name;
		$this->current_language     = $current_language;
	}

	public function add_hooks() {
		if ( self::$registered instanceof self ) {
			remove_action( 'wp_enqueue_scripts', array( self::$registered, 'enqueue_scripts' ), - PHP_INT_MAX );
		}

		self::$registered = $this;

		add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_scripts' ), - PHP_INT_MAX );
	}

	public static function reset_registration() {
		self::$registered = null;
	}

	public function enqueue_scripts() {
		wp_enqueue_script( 'wpml-cookie', ICL_PLUGIN_URL . '/res/js/cookies/language-cookie.js', array(), ICL_SITEPRESS_SCRIPT_VERSION, false );
		wp_script_add_data( 'wpml-cookie', 'strategy', 'defer' );

		$cookies = array(
			$this->language_cookie_name => array(
				'value'   => $this->current_language,
				'expires' => gmdate( 'D, d M Y H:i:s', time() + DAY_IN_SECONDS ) . ' GMT',
				'maxAge'  => DAY_IN_SECONDS,
				'path'    => '/',
			),
		);

		wp_localize_script( 'wpml-cookie', 'wpml_cookies', $cookies );
	}
}
