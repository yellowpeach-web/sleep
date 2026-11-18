<?php

namespace WPML\TM\ATE\Handshake;

class ReportHooks {

	const HANDSHAKE_SINCE = '5.3';

	public static function addHooks() {
		add_action( 'wpml_set_translate_everything', [ self::class, 'onSettingsChanged' ] );
		add_action( 'update_option_icl_sitepress_settings', [ self::class, 'onSettingsChanged' ] );
		add_action( 'wpml_deactivated', [ self::class, 'onDeactivated' ] );
		add_action( 'upgrader_process_complete', [ self::class, 'onPluginReplaced' ], 10, 2 );
	}

	public static function onSettingsChanged() {
		try {
			Reports::queueStateIfChanged();
		} catch ( \Throwable $e ) {
			return;
		}
	}

	public static function onDeactivated() {
		self::undeclare( Reports::UNDECLARE_DEACTIVATED );
	}

	public static function onPluginReplaced( $upgrader, $options = [] ) {
		if ( ! is_array( $options ) || 'plugin' !== ( $options['type'] ?? '' ) || ! defined( 'WPML_PLUGIN_BASENAME' ) ) {
			return;
		}

		$plugins = isset( $options['plugins'] ) ? (array) $options['plugins'] : ( isset( $options['plugin'] ) ? [ $options['plugin'] ] : [] );

		if ( ! in_array( WPML_PLUGIN_BASENAME, $plugins, true ) ) {
			return;
		}

		$installed = static::installedVersion();

		if ( '' !== $installed && version_compare( $installed, self::HANDSHAKE_SINCE, '<' ) ) {
			self::undeclare( Reports::UNDECLARE_DOWNGRADED, [ 'to_version' => $installed ] );
		}
	}

	protected static function installedVersion() {
		if ( ! defined( 'WPML_PLUGIN_PATH' ) || ! defined( 'WPML_PLUGIN_FILE' ) || ! function_exists( 'get_file_data' ) ) {
			return '';
		}

		$data = get_file_data( WPML_PLUGIN_PATH . '/' . WPML_PLUGIN_FILE, [ 'Version' => 'Version' ] );

		return isset( $data['Version'] ) ? (string) $data['Version'] : '';
	}

	private static function undeclare( $reason, array $extra = [] ) {
		Reports::add( Reports::UNDECLARE, array_merge( [ 'reason' => $reason ], $extra ) );
		Reports::flush();
	}
}
