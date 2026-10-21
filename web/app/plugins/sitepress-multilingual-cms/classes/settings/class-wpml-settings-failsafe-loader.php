<?php

use WPML\Core\Component\SettingsStorage\Application\Service\LegacySerializedSettingsRepairer;

class WPML_Settings_Failsafe_Loader {

	const OPTION          = 'icl_sitepress_settings';
	const REGISTRY_OPTION = 'icl_sitepress_settings#__keys';
	const BACKUP_PREFIX   = 'icl_sitepress_settings_corrupt_';
	const STATE_OPTION    = 'icl_sitepress_settings_recovery_state';

	const STATE_GROUP = 'settings_recovery';
	const STATE_KEY   = 'state';

	const NETWORK_FAILURE_PREFIX = 'wpml_settings_unrecoverable_blog_';

	private static $memo = array();

	private static $loads_in_progress = array();

	private static $write_guarded_blogs = array();

	private static $unrecoverable_blogs = array();

	private static $confirmed_missing_rows = array();

	private static $sites_being_initialized = array();

	private static $healthy_housekeeping_done = array();

	private static $network_may_hold_markers = array();

	private static $absent_option;

	public static function isUnrecoverable() {
		return array_key_exists( self::getBlogId(), self::$unrecoverable_blogs );
	}

	public static function isOptionConfirmedMissing() {
		if ( empty( self::$confirmed_missing_rows[ self::getBlogId() ] ) ) {
			return false;
		}

		return ! \WPML\Troubleshooting\ResetWindow::isOpenNow();
	}

	public static function hasNetworkUnrecoverableSettings() {
		if ( self::isUnrecoverable() ) {
			return true;
		}
		if ( ! is_multisite() ) {
			return false;
		}

		global $wpdb;
		$like   = $wpdb->esc_like( self::NETWORK_FAILURE_PREFIX ) . '%';
		$query  = $wpdb->prepare(
			"SELECT meta_id FROM {$wpdb->sitemeta} WHERE meta_key LIKE %s LIMIT 1",
			$like
		);
		$marker = $wpdb->get_var( $query );

		return null !== $marker || ( isset( $wpdb->last_error ) && '' !== $wpdb->last_error );
	}

	public static function load() {
		global $wpdb;

		$blog_id = self::getBlogId();
		if ( ! empty( self::$loads_in_progress[ $blog_id ] ) ) {
			return self::$memo[ $blog_id ] ?? array();
		}
		if ( isset( self::$memo[ $blog_id ] ) ) {
			return self::$memo[ $blog_id ];
		}

		self::$loads_in_progress[ $blog_id ] = true;
		try {
			if ( self::hasReadableVirtualOptionRegistry() ) {
				$settings = get_option( self::OPTION, false );
				if ( is_array( $settings ) ) {
					unset( self::$confirmed_missing_rows[ $blog_id ] );
					self::handleHealthyValue();

					return $settings;
				}
			}

			$initializing_site = self::isInitializingSite( $blog_id );
			$suppress_errors   = $wpdb->suppress_errors( true );
			try {
				$row = self::getStoredRow( self::OPTION );
			} finally {
				$wpdb->suppress_errors( $suppress_errors );
			}

			if (
				false === $row
				&& self::isMissingOptionsTableError( $blog_id )
				&& ( $initializing_site || self::isUninitializedSite( $blog_id ) )
			) {
				$wpdb->last_error = '';
				unset( self::$confirmed_missing_rows[ $blog_id ] );

				return array();
			}

			return self::loadStoredRow( $blog_id, $row );
		} finally {
			unset( self::$loads_in_progress[ $blog_id ] );
		}
	}

	private static function hasReadableVirtualOptionRegistry() {
		$registry = get_option( self::REGISTRY_OPTION, false );

		return is_array( $registry )
			&& isset( $registry['keys'] )
			&& is_array( $registry['keys'] );
	}

	private static function isInitializingSite( $blog_id ) {
		return is_multisite() && ! empty( self::$sites_being_initialized[ $blog_id ] );
	}

	private static function isUninitializedSite( $blog_id ) {
		return is_multisite()
			&& function_exists( 'wp_is_site_initialized' )
			&& ! wp_is_site_initialized( $blog_id );
	}

	private static function isMissingOptionsTableError( $blog_id ) {
		global $wpdb;
		$table = $wpdb->get_blog_prefix( $blog_id ) . 'options';
		$error = isset( $wpdb->last_error ) ? (string) $wpdb->last_error : '';

		return false !== stripos( $error, $table )
			&& (bool) preg_match( "/Table .* doesn't exist/i", $error );
	}

	private static function loadStoredRow( $blog_id, $row ) {
		if ( false === $row ) {
			unset( self::$confirmed_missing_rows[ $blog_id ] );
			self::armWriteGuard( $blog_id );

			return self::markUnrecoverable( $blog_id, '', false );
		}

		if ( null === $row ) {
			self::$confirmed_missing_rows[ $blog_id ] = true;
			self::clearOptionCaches();
			self::registerPersistedNotice();

			return self::filterMissingSettings();
		}

		unset( self::$confirmed_missing_rows[ $blog_id ] );
		$settings = self::decodeCompleteSettings( (string) $row['option_value'] );
		if ( is_array( $settings ) ) {
			self::clearOptionCaches();
			self::handleHealthyValue( $row );

			return self::filterValidatedSettings( $settings );
		}

		return self::recoverStoredRow( $blog_id, $row );
	}

	private static function recoverStoredRow( $blog_id, array $row ) {
		$raw         = (string) $row['option_value'];
		$backup_name = self::BACKUP_PREFIX . substr( md5( $raw ), 0, 12 );

		self::armWriteGuard( $blog_id );

		$backup_saved = self::preserveBackup( $backup_name, $raw );
		if ( ! $backup_saved ) {
			return self::markUnrecoverable( $blog_id, $backup_name, false, $row );
		}

		$repaired = LegacySerializedSettingsRepairer::repair( $raw );
		$restored = is_string( $repaired ) ? self::decodeCompleteSettings( $repaired ) : false;

		if ( ! is_array( $restored ) ) {
			$rebuilt = self::rebuildFromRegisteredRows();
			if ( is_array( $rebuilt ) ) {
				$repaired = serialize( $rebuilt );
				$restored = $rebuilt;
			}
		}

		if ( is_string( $repaired ) && is_array( $restored ) ) {
			$current = self::persistRepair( $raw, $repaired );
			if ( is_array( $current ) && $current['option_value'] === $repaired ) {
				self::recordState( 'repaired', $backup_name, true, $current );
				self::clearNetworkFailureIfRowMatches( $blog_id, $current );
				self::disarmBlog( $blog_id );

				return self::filterValidatedSettings( $restored );
			}

			if ( is_array( $current ) ) {
				$current_settings = self::decodeCompleteSettings( (string) $current['option_value'] );
				if ( is_array( $current_settings ) ) {
					self::clearRecoveryState();
					self::clearNetworkFailureIfRowMatches( $blog_id, $current );
					self::disarmBlog( $blog_id );

					return self::filterValidatedSettings( $current_settings );
				}

				$current_raw         = (string) $current['option_value'];
				$current_backup_name = self::BACKUP_PREFIX . substr( md5( $current_raw ), 0, 12 );
				$current_backup      = self::preserveBackup( $current_backup_name, $current_raw );

				return self::markUnrecoverable(
					$blog_id,
					$current_backup_name,
					$current_backup,
					$current
				);
			}

			if ( false === $current ) {
				return self::markUnrecoverable( $blog_id, $backup_name, true );
			}

			self::$memo[ $blog_id ] = array();

			return array();
		}

		return self::markUnrecoverable( $blog_id, $backup_name, true, $row );
	}

	private static function rebuildFromRegisteredRows() {
		$registry_row = self::getStoredRow( self::REGISTRY_OPTION );
		if ( ! is_array( $registry_row ) ) {
			return null;
		}

		$registry = self::decodeCompleteSettings( (string) $registry_row['option_value'] );
		if (
			! is_array( $registry )
			|| ! isset( $registry['keys'] )
			|| ! is_array( $registry['keys'] )
			|| array() === $registry['keys']
		) {
			return null;
		}

		$settings = array();
		foreach ( $registry['keys'] as $key ) {
			if ( ! is_string( $key ) || '' === $key ) {
				return null;
			}

			$row = self::getStoredRow( self::OPTION . '#' . $key );
			if ( ! is_array( $row ) ) {
				return null;
			}

			$envelope = self::decodeCompleteSettings( (string) $row['option_value'] );
			if ( ! is_array( $envelope ) || ! array_key_exists( 'v', $envelope ) ) {
				return null;
			}

			$settings[ $key ] = $envelope['v'];
		}

		return $settings;
	}

	public static function keepStoredValue( $new_value, $old_value ) {
		return ! empty( self::$write_guarded_blogs[ self::getBlogId() ] )
			? $old_value
			: $new_value;
	}

	public static function keepOptionPresent( $pre, $option = self::OPTION, $default_value = false ) {
		unset( $default_value );

		return self::OPTION === $option && ! empty( self::$write_guarded_blogs[ self::getBlogId() ] )
			? array()
			: $pre;
	}

	private static function armWriteGuard( $blog_id ) {
		self::$write_guarded_blogs[ $blog_id ] = true;

		add_filter( 'pre_update_option_' . self::OPTION, array( self::class, 'keepStoredValue' ), -PHP_INT_MAX, 2 );
		add_filter( 'pre_update_option_' . self::OPTION, array( self::class, 'keepStoredValue' ), PHP_INT_MAX, 2 );
		add_filter( 'pre_option_' . self::OPTION, array( self::class, 'keepOptionPresent' ), PHP_INT_MAX, 3 );
		add_filter( 'pre_option', array( self::class, 'keepOptionPresent' ), PHP_INT_MAX, 3 );
		add_action( 'delete_option_' . self::OPTION, array( self::class, 'forgetOptionAbsenceWhileGuarded' ), -PHP_INT_MAX );
		self::clearOptionCaches();
	}

	public static function forgetOptionAbsenceWhileGuarded() {
		if ( ! empty( self::$write_guarded_blogs[ self::getBlogId() ] ) ) {
			self::clearNamedOptionCaches( self::OPTION );
		}
	}

	public static function deleteSettingsForDestructiveReset() {
		self::beginDestructiveReset();

		return delete_option( self::OPTION );
	}

	public static function beginDestructiveReset() {
		$blog_id = self::getBlogId();
		self::forgetHealthyHousekeeping();
		self::armWriteGuard( $blog_id );
		self::$memo[ $blog_id ]                = array();
		self::$unrecoverable_blogs[ $blog_id ] = true;
	}

	public static function finishDestructiveReset() {
		global $wpdb;

		$blog_id = self::getBlogId();
		$row     = self::getStoredRow( self::OPTION );
		if ( false === $row ) {
			return false;
		}

		if ( is_array( $row ) ) {
			$query = $wpdb->prepare(
				"DELETE FROM {$wpdb->options} WHERE option_name = %s",
				self::OPTION
			);
			$wpdb->query( $query );
			self::clearOptionCaches();
			$row = self::getStoredRow( self::OPTION );
		}

		if ( null !== $row ) {
			return false;
		}

		self::clearRecoveryState();
		self::clearNetworkFailureIfSettingsAbsent( $blog_id );
		self::disarmBlog( $blog_id );
		self::forgetHealthyHousekeeping();
		self::$confirmed_missing_rows[ $blog_id ] = true;

		return true;
	}

	private static function markUnrecoverable( $blog_id, $backup_name, $backup_saved, ?array $row = null ) {
		self::$memo[ $blog_id ]                = array();
		self::$unrecoverable_blogs[ $blog_id ] = true;
		self::recordNetworkFailure( $blog_id, $row );
		self::recordState( 'failed', $backup_name, $backup_saved, $row );

		return array();
	}

	private static function disarmBlog( $blog_id ) {
		unset(
			self::$memo[ $blog_id ],
			self::$write_guarded_blogs[ $blog_id ],
			self::$unrecoverable_blogs[ $blog_id ],
			self::$confirmed_missing_rows[ $blog_id ]
		);
	}

	private static function recordNetworkFailure( $blog_id, ?array $row = null ) {
		if ( ! is_multisite() ) {
			return;
		}

		global $wpdb;
		try {
			$network_id = self::getNetworkIdForBlog( $blog_id );
			if ( ! $network_id ) {
				return;
			}

			$option_name = self::NETWORK_FAILURE_PREFIX . (int) $blog_id;
			$marker      = maybe_serialize(
				array(
					'incident' => self::getFailureIncidentId( $row ),
					'time'     => time(),
				)
			);

			if ( is_array( $row ) ) {
				$query = $wpdb->prepare(
					"INSERT INTO {$wpdb->sitemeta} (site_id, meta_key, meta_value) SELECT %d, %s, %s FROM {$wpdb->options} AS settings WHERE settings.option_name = %s AND settings.option_id = %d AND BINARY settings.option_value = BINARY %s AND settings.autoload = %s AND NOT EXISTS (SELECT 1 FROM {$wpdb->sitemeta} AS marker WHERE marker.site_id = %d AND marker.meta_key = %s)",
					$network_id,
					$option_name,
					$marker,
					self::OPTION,
					(int) $row['option_id'],
					(string) $row['option_value'],
					(string) $row['autoload'],
					$network_id,
					$option_name
				);
			} else {
				$query = $wpdb->prepare(
					"INSERT INTO {$wpdb->sitemeta} (site_id, meta_key, meta_value) SELECT %d, %s, %s WHERE NOT EXISTS (SELECT 1 FROM {$wpdb->sitemeta} AS marker WHERE marker.site_id = %d AND marker.meta_key = %s)",
					$network_id,
					$option_name,
					$marker,
					$network_id,
					$option_name
				);
			}

			self::$network_may_hold_markers[ $network_id ] = true;
			self::forgetHealthyHousekeeping( $blog_id );
			$wpdb->query( $query );
			self::clearNetworkOptionCaches( $network_id, $option_name );
		} catch ( \Throwable $e ) {
			return;
		}
	}

	private static function clearNetworkFailure( $blog_id, $network_id = null ) {
		if ( ! is_multisite() ) {
			return;
		}

		global $wpdb;
		try {
			$network_id = $network_id ? (int) $network_id : self::getNetworkIdForBlog( $blog_id );
			if ( ! $network_id ) {
				return;
			}

			self::forgetHealthyHousekeeping( $blog_id );
			$option_name = self::NETWORK_FAILURE_PREFIX . (int) $blog_id;
			$query       = $wpdb->prepare(
				"DELETE FROM {$wpdb->sitemeta} WHERE site_id = %d AND meta_key = %s",
				$network_id,
				$option_name
			);
			$wpdb->query( $query );
			self::clearNetworkOptionCaches( $network_id, $option_name );
		} catch ( \Throwable $e ) {
			return;
		}
	}

	private static function clearNetworkFailureIfRowMatches( $blog_id, array $row ) {
		if ( ! is_multisite() ) {
			return;
		}

		global $wpdb;
		try {
			self::forgetHealthyHousekeeping( $blog_id );
			$network_id  = self::getNetworkIdForBlog( $blog_id );
			$option_name = self::NETWORK_FAILURE_PREFIX . (int) $blog_id;
			$query       = $wpdb->prepare(
				"DELETE marker FROM {$wpdb->sitemeta} AS marker INNER JOIN {$wpdb->options} AS settings ON settings.option_name = %s AND settings.option_id = %d AND BINARY settings.option_value = BINARY %s AND settings.autoload = %s WHERE marker.site_id = %d AND marker.meta_key = %s",
				self::OPTION,
				(int) $row['option_id'],
				(string) $row['option_value'],
				(string) $row['autoload'],
				$network_id,
				$option_name
			);
			$wpdb->query( $query );
			self::clearNetworkOptionCaches( $network_id, $option_name );
		} catch ( \Throwable $e ) {
			return;
		}
	}

	private static function clearNetworkFailureIfSettingsAbsent( $blog_id ) {
		if ( ! is_multisite() ) {
			return;
		}

		global $wpdb;
		try {
			self::forgetHealthyHousekeeping( $blog_id );
			$network_id  = self::getNetworkIdForBlog( $blog_id );
			$option_name = self::NETWORK_FAILURE_PREFIX . (int) $blog_id;
			$query       = $wpdb->prepare(
				"DELETE marker FROM {$wpdb->sitemeta} AS marker WHERE marker.site_id = %d AND marker.meta_key = %s AND NOT EXISTS (SELECT 1 FROM {$wpdb->options} AS settings WHERE settings.option_name = %s)",
				$network_id,
				$option_name,
				self::OPTION
			);
			$wpdb->query( $query );
			self::clearNetworkOptionCaches( $network_id, $option_name );
		} catch ( \Throwable $e ) {
			return;
		}
	}

	private static function clearNetworkOptionCaches( $network_id, $option_name ) {
		wp_cache_delete( $network_id . ':' . $option_name, 'site-options' );
		$notoptions_key = $network_id . ':notoptions';
		$notoptions     = wp_cache_get( $notoptions_key, 'site-options' );
		if ( is_array( $notoptions ) && isset( $notoptions[ $option_name ] ) ) {
			unset( $notoptions[ $option_name ] );
			wp_cache_set( $notoptions_key, $notoptions, 'site-options' );
		}
	}

	private static function getNetworkIdForBlog( $blog_id ) {
		$site = get_site( (int) $blog_id );
		if ( is_object( $site ) && isset( $site->site_id ) && 0 < (int) $site->site_id ) {
			return (int) $site->site_id;
		}

		return (int) get_current_network_id();
	}

	public static function beginSiteInitialization( $site ) {
		$blog_id = self::getSiteBlogId( $site );
		if ( 0 < $blog_id ) {
			self::$sites_being_initialized[ $blog_id ] = true;
		}
	}

	public static function finishSiteInitialization( $site ) {
		unset( self::$sites_being_initialized[ self::getSiteBlogId( $site ) ] );
	}

	public static function clearNetworkFailureForDeletedSite( $site ) {
		$blog_id    = self::getSiteBlogId( $site );
		$network_id = is_object( $site ) && isset( $site->site_id ) ? (int) $site->site_id : null;
		if ( 0 < $blog_id ) {
			self::disarmBlog( $blog_id );
			self::clearNetworkFailure( $blog_id, $network_id );
		}
	}

	private static function getSiteBlogId( $site ) {
		if ( is_object( $site ) ) {
			if ( isset( $site->blog_id ) ) {
				return (int) $site->blog_id;
			}
			if ( isset( $site->id ) ) {
				return (int) $site->id;
			}

			return 0;
		}

		return (int) $site;
	}

	private static function preserveBackup( $backup_name, $raw ) {
		global $wpdb;

		$stored_raw = maybe_serialize( $raw );
		$query      = $wpdb->prepare(
			"INSERT IGNORE INTO {$wpdb->options} (option_name, option_value, autoload) VALUES (%s, %s, %s)",
			$backup_name,
			$stored_raw,
			'no'
		);
		$wpdb->query( $query );

		$backup = self::getStoredRow( $backup_name );

		return is_array( $backup )
			&& $stored_raw === $backup['option_value']
			&& ! self::isAutoloadedValue( $backup['autoload'] );
	}

	private static function isAutoloadedValue( $autoload ) {
		$autoloaded_values   = function_exists( 'wp_autoload_values_to_autoload' )
			? wp_autoload_values_to_autoload()
			: array( 'yes', 'on', 'auto-on', 'auto' );
		$autoloaded_values[] = 'auto';

		return in_array( $autoload, $autoloaded_values, true );
	}

	private static function getStoredRow( $option_name ) {
		global $wpdb;
		$query = $wpdb->prepare(
			"SELECT option_id, option_value, autoload FROM {$wpdb->options} WHERE option_name = %s LIMIT 1",
			$option_name
		);

		$result = $wpdb->query( $query );
		if ( false === $result || ( isset( $wpdb->last_error ) && '' !== $wpdb->last_error ) ) {
			return false;
		}
		if ( 0 === (int) $result ) {
			return null;
		}

		$row = isset( $wpdb->last_result[0] ) && is_object( $wpdb->last_result[0] )
			? get_object_vars( $wpdb->last_result[0] )
			: null;

		return is_array( $row )
			&& array_key_exists( 'option_id', $row )
			&& array_key_exists( 'option_value', $row )
			&& array_key_exists( 'autoload', $row )
			? $row
			: false;
	}

	private static function persistRepair( $corrupt_raw, $repaired_raw ) {
		global $wpdb;

		$query = $wpdb->prepare(
			"UPDATE {$wpdb->options} SET option_value = %s WHERE option_name = %s AND BINARY option_value = BINARY %s",
			$repaired_raw,
			self::OPTION,
			$corrupt_raw
		);
		$wpdb->query( $query );
		self::clearOptionCaches();

		return self::getStoredRow( self::OPTION );
	}

	private static function decodeCompleteSettings( $raw ) {
		if ( ! LegacySerializedSettingsRepairer::isCompleteSerializedArray( $raw ) ) {
			return false;
		}

		try {
			$value = maybe_unserialize( $raw );
		} catch ( \Throwable $e ) {
			return false;
		}

		return is_array( $value ) ? $value : false;
	}

	private static function filterValidatedSettings( array $settings ) {
		$pre = apply_filters( 'pre_option_' . self::OPTION, false, self::OPTION, false );
		$pre = apply_filters( 'pre_option', $pre, self::OPTION, false );
		if ( false !== $pre ) {
			return is_array( $pre ) ? $pre : $settings;
		}

		$value = apply_filters( 'option_' . self::OPTION, $settings, self::OPTION );

		return is_array( $value ) ? $value : $settings;
	}

	private static function filterMissingSettings() {
		$pre = apply_filters( 'pre_option_' . self::OPTION, false, self::OPTION, false );
		$pre = apply_filters( 'pre_option', $pre, self::OPTION, false );
		if ( false !== $pre ) {
			return is_array( $pre ) ? $pre : array();
		}

		$value = apply_filters( 'default_option_' . self::OPTION, false, self::OPTION, false );

		return is_array( $value ) ? $value : array();
	}

	private static function handleHealthyValue( ?array $row = null ) {
		if ( ! is_admin() ) {
			return;
		}

		$blog_id = self::getBlogId();
		if ( ! empty( self::$healthy_housekeeping_done[ $blog_id ] ) ) {
			return;
		}

		self::runHealthyHousekeeping( $blog_id, $row );
		self::$healthy_housekeeping_done[ $blog_id ] = true;
	}

	private static function runHealthyHousekeeping( $blog_id, ?array $row = null ) {
		$state = self::getStateThroughOptionCache();
		if ( is_array( $state ) && ! empty( $state['state_unreadable'] ) ) {
			self::addNoticeHook();

			return;
		}

		if ( is_array( $state ) && 'failed' === ( $state['result'] ?? null ) ) {
			self::armWriteGuard( $blog_id );
			try {
				self::clearRecoveryState();
				self::clearNetworkFailureAfterHealthyLoad( $blog_id, $row );
			} finally {
				self::disarmBlog( $blog_id );
			}

			return;
		}

		self::clearNetworkFailureAfterHealthyLoad( $blog_id, $row );

		if ( $state ) {
			self::addNoticeHook();
		}
	}

	private static function clearNetworkFailureAfterHealthyLoad( $blog_id, ?array $row = null ) {
		if ( ! is_multisite() ) {
			return;
		}

		$network_id = self::getNetworkIdForBlog( $blog_id );
		if ( $network_id && ! self::networkMayHoldFailureMarkers( $network_id ) ) {
			return;
		}

		if ( is_array( $row ) ) {
			self::clearNetworkFailureIfRowMatches( $blog_id, $row );
		} else {
			self::clearNetworkFailure( $blog_id );
		}
	}

	private static function networkMayHoldFailureMarkers( $network_id ) {
		if ( ! array_key_exists( $network_id, self::$network_may_hold_markers ) ) {
			global $wpdb;
			try {
				$marker = $wpdb->get_var(
					$wpdb->prepare(
						"SELECT meta_id FROM {$wpdb->sitemeta} WHERE site_id = %d AND meta_key LIKE %s LIMIT 1",
						$network_id,
						$wpdb->esc_like( self::NETWORK_FAILURE_PREFIX ) . '%'
					)
				);

				self::$network_may_hold_markers[ $network_id ] = null !== $marker
					|| ( isset( $wpdb->last_error ) && '' !== $wpdb->last_error );
			} catch ( \Throwable $e ) {
				return true;
			}
		}

		return self::$network_may_hold_markers[ $network_id ];
	}

	private static function forgetHealthyHousekeeping( $blog_id = null ) {
		if ( null === $blog_id ) {
			self::$healthy_housekeeping_done = array();
			self::$network_may_hold_markers  = array();

			return;
		}

		unset( self::$healthy_housekeeping_done[ (int) $blog_id ] );
	}

	private static function registerPersistedNotice() {
		if ( is_admin() && self::getState() ) {
			self::addNoticeHook();
		}
	}

	private static function addNoticeHook() {
		if ( ! has_action( 'admin_notices', array( self::class, 'renderNotice' ) ) ) {
			add_action( 'admin_notices', array( self::class, 'renderNotice' ) );
		}
	}

	private static function clearOptionCaches() {
		self::clearNamedOptionCaches( self::OPTION );
	}

	private static function clearNamedOptionCaches( $option_name ) {
		wp_cache_delete( $option_name, 'options' );
		wp_cache_delete( 'alloptions', 'options' );

		$notoptions = wp_cache_get( 'notoptions', 'options' );
		if ( is_array( $notoptions ) && isset( $notoptions[ $option_name ] ) ) {
			unset( $notoptions[ $option_name ] );
			wp_cache_set( 'notoptions', $notoptions, 'options' );
		}
	}

	private static function recordState( $result, $backup_name, $backup_saved, ?array $row = null ) {
		$incident = 'failed' === $result ? self::getFailureIncidentId( $row ) : null;
		$current  = self::getState();
		if (
			'failed' === $result
			&& is_array( $current )
			&& empty( $current['state_unreadable'] )
			&& 'failed' === ( $current['result'] ?? null )
			&& ( $current['incident'] ?? null ) === $incident
			&& ( $current['backup'] ?? null ) === $backup_name
			&& (bool) ( $current['backup_saved'] ?? false ) === (bool) $backup_saved
		) {
			self::addNoticeHook();

			return;
		}

		$state = array(
			'result'       => $result,
			'backup'       => $backup_name,
			'backup_saved' => (bool) $backup_saved,
			'incident'     => $incident,
			'time'         => time(),
		);

		self::forgetHealthyHousekeeping( self::getBlogId() );
		update_option( self::STATE_OPTION, $state, false );

		if ( 'failed' === $result ) {
			$message = $backup_saved
				? sprintf(
					'WPML: icl_sitepress_settings is corrupt and automatic repair FAILED - the configuration was NOT overwritten. Original value preserved in option "%s".',
					$backup_name
				)
				: 'WPML: icl_sitepress_settings is corrupt and automatic repair FAILED. The configuration was NOT overwritten, but the corruption backup could not be created.';
			error_log( $message );
		}

		self::addNoticeHook();
	}

	private static function getFailureIncidentId( ?array $row = null ) {
		return is_array( $row )
			? hash( 'sha256', (string) $row['option_value'] )
			: 'unreadable-row';
	}

	private static function getState() {
		foreach ( self::getStateOptionFormats() as $option_name => $format ) {
			$state = self::readStateOption( $option_name, $format );
			if ( null !== $state ) {
				return $state;
			}
		}

		return null;
	}

	private static function getStateThroughOptionCache() {
		if ( null === self::$absent_option ) {
			self::$absent_option = new \stdClass();
		}

		foreach ( self::getStateOptionFormats() as $option_name => $format ) {
			$state = self::readStateOptionThroughCache( $option_name, $format );
			if ( null !== $state ) {
				return $state;
			}
		}

		return null;
	}

	private static function readStateOptionThroughCache( $option_name, $format ) {
		try {
			$value = get_option( $option_name, self::$absent_option );
		} catch ( \Throwable $e ) {
			return self::readStateOption( $option_name, $format );
		}

		if ( self::$absent_option === $value ) {
			return null;
		}

		$state = self::decodeStateValue( $value, $format );
		if ( is_array( $state ) && ! empty( $state['state_unreadable'] ) ) {
			return self::readStateOption( $option_name, $format );
		}

		return $state;
	}

	private static function getStateOptionFormats() {
		return array(
			self::STATE_OPTION                => 'direct',
			self::getLegacyStateOptionName()  => 'envelope',
			'WPML(' . self::STATE_GROUP . ')' => 'group',
		);
	}

	private static function readStateOption( $option_name, $format ) {
		$row = self::getStoredRow( $option_name );
		if ( null === $row ) {
			return null;
		}
		if ( false === $row ) {
			return self::getUnreadableState();
		}

		try {
			$value = self::unserializeAuxiliaryState( $row['option_value'] );
		} catch ( \Throwable $e ) {
			return self::getUnreadableState();
		}

		return self::decodeStateValue( $value, $format );
	}

	private static function decodeStateValue( $value, $format ) {
		if ( 'envelope' === $format ) {
			if ( ! is_array( $value ) || 1 !== count( $value ) || ! array_key_exists( 'v', $value ) ) {
				return self::getUnreadableState();
			}
			$value = $value['v'];
		} elseif ( 'group' === $format ) {
			if ( ! is_array( $value ) ) {
				return self::getUnreadableState();
			}
			if ( ! array_key_exists( self::STATE_KEY, $value ) ) {
				return null;
			}
			$value = $value[ self::STATE_KEY ];
		}

		return self::validateStateValue( $value );
	}

	private static function validateStateValue( $state ) {
		if ( null === $state ) {
			return null;
		}
		if (
			is_array( $state )
			&& isset( $state['result'], $state['time'], $state['backup'] )
			&& in_array( $state['result'], array( 'failed', 'repaired' ), true )
			&& is_int( $state['time'] )
			&& is_string( $state['backup'] )
		) {
			return array(
				'result'       => $state['result'],
				'backup'       => $state['backup'],
				'backup_saved' => array_key_exists( 'backup_saved', $state )
					? (bool) $state['backup_saved']
					: '' !== $state['backup'],
				'incident'     => isset( $state['incident'] ) && is_string( $state['incident'] )
					? $state['incident']
					: null,
				'time'         => $state['time'],
			);
		}

		return self::getUnreadableState();
	}

	private static function unserializeAuxiliaryState( $raw ) {
		if ( ! is_serialized( $raw ) ) {
			return $raw;
		}

		return @unserialize( trim( $raw ), array( 'allowed_classes' => false ) );
	}

	private static function getUnreadableState() {
		return array(
			'result'           => 'failed',
			'backup'           => '',
			'backup_saved'     => false,
			'incident'         => 'unreadable-state',
			'state_unreadable' => true,
			'time'             => 0,
		);
	}

	private static function clearRecoveryState() {
		self::forgetHealthyHousekeeping( self::getBlogId() );
		delete_option( self::STATE_OPTION );
		delete_option( self::getLegacyStateOptionName() );
		delete_option( 'WPML(' . self::STATE_GROUP . ')' );
	}

	private static function getLegacyStateOptionName() {
		return 'WPML(' . self::STATE_GROUP . '/' . self::STATE_KEY . ')';
	}

	private static function getBlogId() {
		return (int) get_current_blog_id();
	}

	public static function renderNotice() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		$state = self::getState();
		if ( ! is_array( $state ) || ! isset( $state['result'], $state['time'] ) ) {
			return;
		}

		if ( 'repaired' === $state['result'] && time() - $state['time'] > 2 * DAY_IN_SECONDS ) {
			self::clearRecoveryState();

			return;
		}

		if ( 'failed' === $state['result'] ) {
			if ( ! empty( $state['backup_saved'] ) && ! empty( $state['backup'] ) ) {
				$message = sprintf(
					/* translators: %s: name of the backup option */
					__( 'The stored configuration is corrupted and automatic repair failed. Your configuration was NOT overwritten: the original data is preserved in the option "%s". Restore a database backup or contact WPML support before reconfiguring.', 'sitepress' ),
					$state['backup']
				);
			} else {
				$message = __( 'The stored configuration is corrupted and automatic repair failed. Your configuration was NOT overwritten, but WPML could not create the safety backup. Restore a database backup or contact WPML support before reconfiguring.', 'sitepress' );
			}

			printf(
				'<div class="notice notice-error"><p><strong>%s</strong> %s</p></div>',
				esc_html__( 'WPML could not read its settings.', 'sitepress' ),
				esc_html( $message )
			);

			return;
		}

		if ( 'repaired' === $state['result'] && isset( $state['backup'] ) ) {
			printf(
				'<div class="notice notice-warning is-dismissible"><p>%s</p></div>',
				esc_html(
					sprintf(
						/* translators: %s: name of the backup option */
						__( 'WPML detected corrupted settings and repaired them automatically. The original value was preserved in the option "%s".', 'sitepress' ),
						$state['backup']
					)
				)
			);
		}
	}
}
