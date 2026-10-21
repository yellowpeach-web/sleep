<?php

namespace WPML\TM\Settings;

use WPML\Infrastructure\WordPress\Component\CustomFieldPreferences\ContainerFreeServices;
use WPML\Infrastructure\WordPress\Component\CustomFieldPreferences\Repository\PreferenceRepository;
use WPML\Upgrade\Commands\OffloadMetaSettings;
use WPML\Upgrade\CommandsStatus;

class MetaSettingsLifecycle {

	const FIRST_VERSION_WITH_ROLLBACK_HELPER = '4.9.6';

	const INCOMING_NOT_PROBED  = 'not-probed';
	const INCOMING_STORE_AWARE = 'store-aware';
	const INCOMING_BLOB_ONLY   = 'blob-only';

	private static $incomingBuildVerdict = self::INCOMING_NOT_PROBED;

	private static $replacementActivationState;

	public static function addHooks() {
		add_filter( 'upgrader_pre_install', [ self::class, 'captureActivationBeforeReplacement' ], 1, 2 );
		add_filter( 'upgrader_source_selection', [ self::class, 'inspectIncomingBuild' ], PHP_INT_MAX, 4 );
		add_filter( 'upgrader_clear_destination', [ self::class, 'restoreBeforeFilesReplace' ], 10, 4 );
		if ( is_admin() && ! wp_doing_ajax() ) {
			add_action( 'wpml_loaded', [ self::class, 'maybeRearm' ], 0 );
			add_action( 'admin_init', [ self::class, 'maybeFinishParkedCutover' ] );
		}
	}

	public static function captureActivationBeforeReplacement( $response, $hook_extra ) {
		if ( is_wp_error( $response ) || ! is_array( $hook_extra ) || ! self::upgradeTargetsWpml( $hook_extra ) ) {
			return $response;
		}
		self::$incomingBuildVerdict = self::INCOMING_NOT_PROBED;

		$plugin          = isset( $hook_extra['plugin'] ) ? (string) $hook_extra['plugin'] : '';
		$site_plugins    = array_values( (array) get_option( 'active_plugins', [] ) );
		$network_plugins = (array) get_site_option( 'active_sitewide_plugins', [] );

		self::$replacementActivationState = [
			'plugin'            => $plugin,
			'site_position'     => array_search( $plugin, $site_plugins, true ),
			'network_active'    => array_key_exists( $plugin, $network_plugins ),
			'network_timestamp' => array_key_exists( $plugin, $network_plugins ) ? $network_plugins[ $plugin ] : null,
		];

		return $response;
	}

	public static function captureUpgradeOrigin() {
		RequestSettings::load();

		$upgraded_from = get_option( 'icl_sitepress_version' );
		if ( $upgraded_from
			&& version_compare( $upgraded_from, ICL_SITEPRESS_VERSION, '<' )
			&& version_compare( $upgraded_from, self::FIRST_VERSION_WITH_ROLLBACK_HELPER, '<' )
		) {
			ContainerFreeServices::state()->armCutoverDeadline( 5 * DAY_IN_SECONDS );
		}
	}

	public static function inspectIncomingBuild( $source, $remote_source = '', $upgrader = null, $hook_extra = [] ) {
		unset( $remote_source, $upgrader );

		$targets_wpml = is_array( $hook_extra ) && self::upgradeTargetsWpml( $hook_extra );
		if ( is_wp_error( $source ) ) {
			if ( $targets_wpml ) {
				self::restoreActivationAfterRejectedReplacement();
			}
			return $source;
		}

		$verdict = self::probeVerdict( $source );
		if (
			( $targets_wpml || self::INCOMING_NOT_PROBED !== $verdict )
			&& ! self::incomingBuildHasFailsafeLoader( $source )
			&& \WPML_Settings_Failsafe_Loader::hasNetworkUnrecoverableSettings()
		) {
			self::restoreActivationAfterRejectedReplacement();
			return new \WP_Error(
				'wpml_settings_unrecoverable_plugin_replacement',
				__( 'WPML cannot be replaced while its settings are unrecoverable because the incoming version does not include settings recovery protection. Restore the settings backup before installing this version.', 'sitepress' )
			);
		}

		if ( $targets_wpml || self::INCOMING_NOT_PROBED !== $verdict ) {
			self::$replacementActivationState = null;
		}
		if ( self::INCOMING_NOT_PROBED !== $verdict ) {
			self::$incomingBuildVerdict = $verdict;
		}
		if ( self::INCOMING_BLOB_ONLY === $verdict ) {
			self::restoreQuietly();
		}
		return $source;
	}

	private static function restoreActivationAfterRejectedReplacement() {
		$state = self::$replacementActivationState;

		self::$replacementActivationState = null;
		if ( ! is_array( $state ) || empty( $state['plugin'] ) ) {
			return;
		}

		$plugin       = $state['plugin'];
		$site_plugins = array_values( (array) get_option( 'active_plugins', [] ) );
		if ( false !== $state['site_position'] && ! in_array( $plugin, $site_plugins, true ) ) {
			$position = min( (int) $state['site_position'], count( $site_plugins ) );
			array_splice( $site_plugins, $position, 0, [ $plugin ] );
			update_option( 'active_plugins', $site_plugins );
		}

		$network_plugins = (array) get_site_option( 'active_sitewide_plugins', [] );
		if ( $state['network_active'] && ! array_key_exists( $plugin, $network_plugins ) ) {
			$network_plugins[ $plugin ] = $state['network_timestamp'];
			update_site_option( 'active_sitewide_plugins', $network_plugins );
		}
	}

	private static function probeVerdict( $source ): string {
		if ( ! is_string( $source ) || ! file_exists( trailingslashit( $source ) . 'sitepress.php' ) ) {
			return self::INCOMING_NOT_PROBED;
		}
		return file_exists( trailingslashit( $source ) . 'classes/settings/MetaSettingsLifecycle.php' )
			? self::INCOMING_STORE_AWARE
			: self::INCOMING_BLOB_ONLY;
	}

	private static function incomingBuildHasFailsafeLoader( $source ): bool {
		return is_string( $source )
			&& file_exists( trailingslashit( $source ) . 'classes/settings/class-wpml-settings-failsafe-loader.php' );
	}

	private static function restoreQuietly() {
		try {
			ContainerFreeServices::restore()->restore();
		} catch ( \Throwable $e ) {
			error_log( 'WPML meta-settings restore skipped during plugin replacement: ' . $e->getMessage() );
		}
	}

	public static function restoreBeforeFilesReplace( $removed, $local_destination, $remote_destination, $hook_extra ) {
		if ( is_wp_error( $removed ) || ! self::upgradeTargetsWpml( $hook_extra ) ) {
			return $removed;
		}
		if ( self::INCOMING_NOT_PROBED !== self::$incomingBuildVerdict ) {
			return $removed;
		}
		if ( \WPML_Settings_Failsafe_Loader::hasNetworkUnrecoverableSettings() ) {
			self::keepUninspectedReplacementDeactivated( $hook_extra );

			return $removed;
		}
		self::restoreQuietly();
		return $removed;
	}

	private static function keepUninspectedReplacementDeactivated( array $hook_extra ) {
		$state = self::$replacementActivationState;
		self::$replacementActivationState = null;

		$plugin = is_array( $state ) && ! empty( $state['plugin'] )
			? (string) $state['plugin']
			: ( isset( $hook_extra['plugin'] ) ? (string) $hook_extra['plugin'] : '' );
		if ( '' === $plugin ) {
			return;
		}

		$site_plugins = array_values( (array) get_option( 'active_plugins', [] ) );
		$remaining    = array_values( array_diff( $site_plugins, [ $plugin ] ) );
		if ( $remaining !== $site_plugins ) {
			update_option( 'active_plugins', $remaining );
		}

		$network_plugins = (array) get_site_option( 'active_sitewide_plugins', [] );
		if ( array_key_exists( $plugin, $network_plugins ) ) {
			unset( $network_plugins[ $plugin ] );
			update_site_option( 'active_sitewide_plugins', $network_plugins );
		}
	}

	private static function upgradeTargetsWpml( array $hook_extra ): bool {
		$plugin        = isset( $hook_extra['plugin'] ) ? $hook_extra['plugin'] : '';
		$wpml_basename = defined( 'WPML_PLUGIN_BASENAME' )
			? WPML_PLUGIN_BASENAME
			: 'sitepress-multilingual-cms/sitepress.php';

		return $wpml_basename === $plugin
			|| 0 === strpos( $plugin, 'sitepress-multilingual-cms/' );
	}

	public static function maybeFinishParkedCutover() {
		if ( \WPML_Settings_Failsafe_Loader::isUnrecoverable() ) {
			return;
		}

		if ( ! ContainerFreeServices::state()->read()->isMirroring() ) {
			return;
		}
		if ( ! ContainerFreeServices::state()->canCutOver() ) {
			return;
		}
		$migrate = ContainerFreeServices::migrate();
		if ( $migrate->ensureStoreMatchesBlob() ) {
			$migrate->cutover();
		}
	}

	public static function maybeRearm() {
		if ( \WPML_Settings_Failsafe_Loader::isUnrecoverable() ) {
			return;
		}

		$state = ContainerFreeServices::state();
		if ( $state->isMigrated() ) {
			return;
		}
		if ( $state->read()->isMirroring() && ! $state->canCutOver() ) {
			return;
		}
		$commands = new CommandsStatus();
		if ( ! $commands->hasBeenExecuted( OffloadMetaSettings::class ) ) {
			return;
		}
		$migrate = ContainerFreeServices::migrate();
		if ( $migrate->countSourceRecords() === 0 ) {
			return;
		}
		if ( $state->read()->isMirroring() && $migrate->ensureStoreMatchesBlob() ) {
			return;
		}
		if ( ! PreferenceRepository::truncateTable() ) {
			return;
		}
		$commands->clearExecuted( OffloadMetaSettings::class );
	}
}
