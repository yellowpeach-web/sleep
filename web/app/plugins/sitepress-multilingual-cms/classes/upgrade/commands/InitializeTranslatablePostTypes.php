<?php

namespace WPML\Upgrade\Commands;

class InitializeTranslatablePostTypes extends \WPML_Upgrade_Run_All {

	private $wpdb;

	private $sitepress;

	public function __construct( array $args ) {
		$this->wpdb      = $args[0];
		$this->sitepress = $args[1];
	}

	protected function run() {
		$sync_option      = $this->sitepress->get_setting( 'custom_posts_sync_option', [] );
		$default_language = $this->sitepress->get_default_language();
		$initializer      = new \WPML_Initialize_Language_For_Post_Type( $this->wpdb );

		foreach ( (array) $sync_option as $post_type => $mode ) {
			if ( ! in_array( (int) $mode, [ WPML_CONTENT_TYPE_TRANSLATE, WPML_CONTENT_TYPE_DISPLAY_AS_IF_TRANSLATED ], true ) ) {
				continue;
			}
			if ( ! $initializer->run( $post_type, $default_language ) ) {
				return false;
			}
		}

		return true;
	}
}
