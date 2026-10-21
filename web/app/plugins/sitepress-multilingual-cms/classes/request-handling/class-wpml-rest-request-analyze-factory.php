<?php

class WPML_REST_Request_Analyze_Factory {

	private static $rewrite_rules;

	private static $rewrite_rules_read = false;

	private static $forget_hooks_added = false;

	public static function create() {
		global $wpml_url_converter, $wpml_language_resolution;

		return new WPML_REST_Request_Analyze(
			$wpml_url_converter,
			$wpml_language_resolution->get_active_language_codes(),
			new WP_Rewrite(),
			[ self::class, 'read_rewrite_rules' ]
		);
	}

	public static function read_rewrite_rules() {
		if ( ! self::$rewrite_rules_read ) {
			$rules = get_option( 'rewrite_rules' );

			self::$rewrite_rules      = $rules;
			self::$rewrite_rules_read = true;
			self::add_forget_hooks();
		}

		return self::$rewrite_rules;
	}

	public static function forget_rewrite_rules() {
		self::$rewrite_rules      = null;
		self::$rewrite_rules_read = false;
	}

	private static function add_forget_hooks() {
		if ( self::$forget_hooks_added ) {
			return;
		}
		self::$forget_hooks_added = true;

		foreach ( [ 'add_option_rewrite_rules', 'update_option_rewrite_rules', 'delete_option_rewrite_rules' ] as $hook ) {
			add_action( $hook, [ self::class, 'forget_rewrite_rules' ] );
		}
	}
}
