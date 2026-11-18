<?php

namespace WPML\Compatibility\Divi\Hooks;

use WPML\Compatibility\Divi\ThemeBuilderRepair\Command;
use WPML\Compatibility\Divi\ThemeBuilderRepair\Page;
use WPML\Compatibility\Divi\ThemeBuilderRepair\Runner;

class ThemeBuilderRepairTool implements \IWPML_Backend_Action, \IWPML_Frontend_Action {

	const AJAX_ACTION = 'wpml_divi_theme_builder_repair';

	const CAPABILITY = 'wpml_manage_support';

	const CLI_COMMAND = 'wpml repair-divi-theme-builder';

	const ORDER = 45;

	public function add_hooks() {
		add_filter( 'wpml_support_tools', [ $this, 'registerTool' ] );
		\WPML\PB\Request\Ajax::register(
			self::AJAX_ACTION,
			[
				'capability' => self::CAPABILITY,
				'nonce'      => [ self::AJAX_ACTION, 'nonce' ],
			],
			[ $this, 'ajax' ]
		);

		if ( defined( 'WP_CLI' ) && WP_CLI && class_exists( '\WP_CLI' ) ) {
			\WP_CLI::add_command( self::CLI_COMMAND, new Command() );
		}
	}

	public function registerTool( $tools ) {
		if ( ! is_array( $tools ) ) {
			return $tools;
		}

		$tools[] = [
			'slug'        => Page::TOOL_SLUG,
			'title'       => Page::title(),
			'description' => Page::description(),
			'tier'        => 3,
			'order'       => self::ORDER,
			'controller'  => function () {
				return new Page();
			},
			'capability'  => self::CAPABILITY,
			'icon'        => 'M4 5a1 1 0 011-1h14a1 1 0 011 1v3H4V5zm0 5h7v10H5a1 1 0 01-1-1v-9zm9 0h7v9a1 1 0 01-1 1h-6V10z',
			'search'      => [
				[
					'label'  => Page::title(),
					'anchor' => 'divi-theme-builder-repair',
				],
			],
		];

		return $tools;
	}

	public function ajax() {
		if ( ! check_ajax_referer( self::AJAX_ACTION, 'nonce', false ) ) {
			wp_send_json_error( [ 'message' => 'nonce' ], 403 );
		} elseif ( ! current_user_can( self::CAPABILITY ) && ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( [ 'message' => 'capability' ], 403 );
		} else {
			$dryRun = ! isset( $_POST['dryRun'] ) || '0' !== sanitize_text_field( wp_unslash( (string) $_POST['dryRun'] ) );

			wp_send_json_success( $this->runner()->run( $dryRun ) );
		}
	}

	protected function runner() {
		global $wpdb, $sitepress;

		return Runner::forSite( $wpdb, $sitepress );
	}
}
