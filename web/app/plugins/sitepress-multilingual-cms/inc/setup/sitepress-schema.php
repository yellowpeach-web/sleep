<?php

use WPML\Infrastructure\WordPress\Component\Translation\Domain\Links\Repository;
use WPML\TM\Upgrade\Commands\CreateAteDownloadQueueTable;
use WPML\TM\Upgrade\Commands\CreateTermBatchMembersTable;
use WPML\TM\Upgrade\Commands\CreateUnsolvableJobsTable;
use WPML\Upgrade\CreateTable;
use WPML\Upgrade\StringTablesSchema;
use WPML\Upgrade\Commands\CreateBackgroundTaskTable;
use WPML\Upgrade\Commands\CreateElementKnowledgeTable;
use WPML\Upgrade\Commands\CreateAppearancesTable;
use WPML\Knowledge\AppearanceRepository;
use WPML\Upgrade\Commands\CreateMediaUrlLookupTable;
use WPML\Upgrade\Commands\CreateLanguagePresetsTable;
use WPML\Upgrade\Commands\CreateCountriesTable;
use WPML\Upgrade\Commands\CreateLanguagePresetCountriesTable;
use WPML\Upgrade\Commands\CreateUrlResolutionCacheTable;

require_once WPML_PLUGIN_PATH . '/classes/upgrade/commands/WPML5.0/CreateUrlResolutionCacheTable.php';
require_once WPML_PLUGIN_PATH . '/classes/upgrade/commands/WPML5.0/CreateElementKnowledgeTable.php';
require_once WPML_PLUGIN_PATH . '/classes/upgrade/commands/WPML5.0/CreateAppearancesTable.php';

function icl_sitepress_activate() {
	global $wpdb;

	try {
		CreateTable::forgetLastRefusal();

		SitePress_Setup::fill_languages();
		SitePress_Setup::fill_languages_translations();
		SitePress_Setup::fill_flags();

		$table_name  = $wpdb->prefix . 'icl_translations';
		$found_table = (string) $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $table_name ) );
		if ( 0 !== strcasecmp( $found_table, $table_name ) ) {
			if ( ! CreateTable::create(
				$wpdb,
				$wpdb->prefix . 'icl_translations',
				"
	             CREATE TABLE IF NOT EXISTS `{$wpdb->prefix}icl_translations` (
	                `translation_id` BIGINT NOT NULL AUTO_INCREMENT PRIMARY KEY ,
                `element_type` VARCHAR( 60 ) NOT NULL DEFAULT 'post_post',
                `element_id` BIGINT NULL DEFAULT NULL ,
                `trid` BIGINT NOT NULL ,
                `language_code` VARCHAR( 7 ) NOT NULL,
                `source_language_code` VARCHAR( 7 ),
                UNIQUE KEY `el_type_id` (`element_type`,`element_id`),
                UNIQUE KEY `trid_lang` (`trid`,`language_code`),
                KEY `trid` (`trid`),
                KEY `id_type_language` (`element_id`, `element_type`, `language_code`)
	            )"
			) ) {
				throw new Exception( $wpdb->last_error );
			}
		}

		$table_name  = $wpdb->prefix . 'icl_translation_status';
		$found_table = (string) $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $table_name ) );
		if ( 0 !== strcasecmp( $found_table, $table_name ) ) {
			if ( ! CreateTable::create(
				$wpdb,
				$wpdb->prefix . 'icl_translation_status',
				"
	                 CREATE TABLE IF NOT EXISTS `{$wpdb->prefix}icl_translation_status` (
                 `rid` bigint(20) NOT NULL AUTO_INCREMENT,
                 `translation_id` bigint(20) NOT NULL,
                 `status` tinyint(4) NOT NULL,
                 `translator_id` bigint(20) NOT NULL,
                 `needs_update` tinyint(4) NOT NULL,
                 `md5` varchar(32) NOT NULL,
                 `translation_service` varchar(16) NOT NULL,
                 `batch_id` int DEFAULT 0 NOT NULL,
                 `translation_package` longtext NOT NULL,
                 `timestamp` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
                 `links_fixed` tinyint(4) NOT NULL DEFAULT 0,
                 `_prevstate` longtext,
                 `uuid` varchar(36) NULL,
                 `tp_id` INT NULL DEFAULT NULL,
                 `tp_revision` INT NOT NULL DEFAULT 1,
                 `ts_status` TEXT NULL DEFAULT NULL,
                 `review_status` ENUM('NEEDS_REVIEW', 'EDITING', 'ACCEPTED'),
                 `ate_comm_retry_count` INT(11) UNSIGNED DEFAULT 0,
                 PRIMARY KEY (`rid`),
                 UNIQUE KEY `translation_id` (`translation_id`),
                 KEY `review_status` (`review_status`),
                 INDEX `status_rid` ( `status`, `rid` )
	                )"
			) ) {
				throw new Exception( $wpdb->last_error );
			}
		}

		$table_name  = $wpdb->prefix . 'icl_translate_job';
		$found_table = (string) $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $table_name ) );
		if ( 0 !== strcasecmp( $found_table, $table_name ) ) {
			if ( ! CreateTable::create(
				$wpdb,
				$wpdb->prefix . 'icl_translate_job',
				"
	                 CREATE TABLE IF NOT EXISTS `{$wpdb->prefix}icl_translate_job` (
                `job_id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY ,
                `rid` BIGINT UNSIGNED NOT NULL ,
                `translator_id` BIGINT UNSIGNED NOT NULL ,
                `translated` TINYINT UNSIGNED NOT NULL DEFAULT 0,
                `manager_id` BIGINT UNSIGNED NOT NULL ,
                `revision` INT UNSIGNED NULL,
                `title` TEXT NULL,
                `deadline_date` DATETIME NULL,
                `completed_date` DATETIME NULL,
                `editor` VARCHAR(16) NULL,
                `editor_job_id` BIGINT(20) UNSIGNED NULL,
                `edit_timestamp` INT(11) UNSIGNED NULL,
                `automatic` TINYINT UNSIGNED NOT NULL DEFAULT 0,
                `sent_from` TINYINT UNSIGNED NULL,
                `created_by_translate_everything` TINYINT UNSIGNED NOT NULL DEFAULT 0,
                `review_mode` VARCHAR(20) NULL,
                `ate_sync_count` INT(6) UNSIGNED DEFAULT 0,
                `wpml_words_to_translate_count` INT(11) UNSIGNED NULL,
                `wpml_words_to_translate_count_lifetime_max` INT(11) UNSIGNED NULL,
                `wpml_words_to_translate_count_unconfirmed_max` INT(11) UNSIGNED NULL,
                `wpml_automatic_translation_costs` INT(11) UNSIGNED NULL,
                `ate_receive_token` VARCHAR(64) NULL,
                INDEX ( `rid` , `translator_id` ),
                INDEX `rid_job_id` ( `rid` , `job_id` )
	                )"
			) ) {
				throw new Exception( $wpdb->last_error );
			}
		}

		$table_name  = $wpdb->prefix . 'icl_translate';
		$found_table = (string) $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $table_name ) );
		if ( 0 !== strcasecmp( $found_table, $table_name ) ) {
			if ( ! CreateTable::create(
				$wpdb,
				$wpdb->prefix . 'icl_translate',
				"
	                 CREATE TABLE IF NOT EXISTS `{$wpdb->prefix}icl_translate` (
                `tid` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY ,
                `job_id` BIGINT UNSIGNED NOT NULL ,
                `content_id` BIGINT UNSIGNED NOT NULL ,
                `timestamp` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ,
                `field_type` VARCHAR( 263 ) NOT NULL ,
                `field_wrap_tag` VARCHAR( 16 ) NOT NULL ,
                `field_format` VARCHAR( 16 ) NOT NULL ,
                `field_translate` TINYINT NOT NULL ,
                `field_data` longtext NOT NULL ,
                `field_data_translated` longtext NOT NULL ,
                `field_finished` TINYINT NOT NULL DEFAULT 0,
                INDEX `field_type_job_id` ( `field_type`(64), `job_id` ),
                INDEX ( `job_id` )
	                )"
			) ) {
				throw new Exception( $wpdb->last_error );
			}
		}

		$table_name  = $wpdb->prefix . 'icl_translation_batches';
		$found_table = (string) $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $table_name ) );
		if ( 0 !== strcasecmp( $found_table, $table_name ) ) {
			if ( ! CreateTable::create(
				$wpdb,
				$wpdb->prefix . 'icl_translation_batches',
				"
	                 CREATE TABLE IF NOT EXISTS {$wpdb->prefix}icl_translation_batches (
                  `id` int(11) NOT NULL AUTO_INCREMENT,
                  `batch_name` text NOT NULL,
                  `tp_id` int NULL,
                  `ts_url` text NULL,
                  `last_update` DATETIME NULL,
                  PRIMARY KEY (`id`)
	                )"
			) ) {
				throw new Exception( $wpdb->last_error );
			}
		}

		$table_name  = $wpdb->prefix . 'icl_locale_map';
		$found_table = (string) $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $table_name ) );
		if ( 0 !== strcasecmp( $found_table, $table_name ) ) {
			if ( ! CreateTable::create(
				$wpdb,
				$wpdb->prefix . 'icl_locale_map',
				"
	                 CREATE TABLE IF NOT EXISTS `{$wpdb->prefix}icl_locale_map` (
	                    `code` VARCHAR( 7 ) NOT NULL ,
	                    `locale` VARCHAR( 35 ) NOT NULL ,
	                    PRIMARY KEY (`code` ,`locale`)
	                )"
			) ) {
				throw new Exception( $wpdb->last_error );
			}
		}

		StringTablesSchema::createMissing( $wpdb );

		if ( ! Repository::createDatabaseTables() ) {
			throw new Exception( 'Failed to create database tables for links translations.' );
		}

		$icl_background_task = CreateBackgroundTaskTable::create_table_if_not_exists( $wpdb );
		if ( ! $icl_background_task ) {
			throw new Exception( $wpdb->last_error );
		}

		// loads - never on a Blog licence - so activation creates it as well.
		$icl_translation_downloads = CreateAteDownloadQueueTable::create_table_if_not_exists( $wpdb );
		if ( ! $icl_translation_downloads ) {
			throw new Exception( $wpdb->last_error );
		}

		if ( ! \WPML\TM\ATE\Handshake\Schema::createTables( $wpdb ) ) {
			throw new Exception( $wpdb->last_error );
		}

		if ( ! CreateLanguagePresetsTable::create_table_if_not_exists( $wpdb ) ) {
			throw new Exception( $wpdb->last_error );
		}

		if ( ! CreateCountriesTable::create_table_if_not_exists( $wpdb ) ) {
			throw new Exception( $wpdb->last_error );
		}
		if ( ! CreateLanguagePresetCountriesTable::create_table_if_not_exists( $wpdb ) ) {
			throw new Exception( $wpdb->last_error );
		}

		\WPML\LanguageEditor\Presets\CountriesSeeder::create()->seed();

		\WPML\LanguageEditor\Presets\PresetsSeeder::create()->seed();

		if ( false === CreateUrlResolutionCacheTable::create_table_if_not_exists( $wpdb ) ) {
			throw new Exception( $wpdb->last_error );
		}

		$icl_translation_jobs_errors_table_task = CreateUnsolvableJobsTable::create_table_if_not_exists( $wpdb );
		if ( ! $icl_translation_jobs_errors_table_task ) {
			throw new Exception( $wpdb->last_error );
		}

		if ( ! CreateTermBatchMembersTable::create_table_if_not_exists( $wpdb ) ) {
			throw new Exception( $wpdb->last_error );
		}

		$icl_media_url_lookup = CreateMediaUrlLookupTable::create_table_if_not_exists( $wpdb );
		if ( ! $icl_media_url_lookup ) {
			throw new Exception( $wpdb->last_error );
		}

		if ( false === CreateElementKnowledgeTable::create_table_if_not_exists( $wpdb ) ) {
			throw new Exception( $wpdb->last_error );
		}

		if ( false === CreateAppearancesTable::create_table_if_not_exists( $wpdb ) ) {
			error_log(
				sprintf(
					'WPML: could not create %s (%s). Activation continues; the appearance registry answers "unknown" until the table exists, and the upgrade will retry on the next request.',
					$wpdb->prefix . AppearanceRepository::TABLE,
					$wpdb->last_error
				)
			);
		}
	} catch ( Exception $e ) {
		trigger_error( esc_html( icl_sitepress_activation_error_message( $e ) ), E_USER_ERROR );
		exit;
	}

	if ( ! defined( 'ICL_MULTI_STEP_UPGRADE' ) ) {
		delete_option( 'icl_sitepress_version' );
		add_option( 'icl_sitepress_version', ICL_SITEPRESS_VERSION, '', true );
	}

	$iclsettings = get_option( 'icl_sitepress_settings' );
	if ( $iclsettings === false ) {
		$settings = array(
			'translated_document_status_sync' => 1,
		);
		add_option( 'icl_sitepress_settings', $settings, '', true );
	} else {
		$iclsettings['ajx_health_checked'] = 0;
		$iclsettings['just_reactivated']   = 1;
		update_option( 'icl_sitepress_settings', $iclsettings );
	}

	wpml_enable_capabilities();

	repair_el_type_collate();

	do_action( 'wpml_activated' );
}

function icl_sitepress_activation_error_message( Exception $e ) {
	$refusal = CreateTable::lastRefusal();
	if ( null === $refusal ) {
		return $e->getMessage();
	}

	return sprintf(
		/* translators: %1$s: the full database table name, %2$s: the error the database answered, verbatim. */
		__( 'WPML could not create the database table %1$s. The database answered: %2$s', 'sitepress' ),
		$refusal['table'],
		$refusal['error']
	);
}

function wpml_maybe_activate_network_site( array $sitewide_plugins ) {
	if (
		\WPML_Settings_Failsafe_Loader::isUnrecoverable()
		|| false !== get_option( 'icl_sitepress_version', false )
		|| ! isset( $sitewide_plugins[ WPML_PLUGIN_BASENAME ] )
		|| \WPML\Troubleshooting\ResetWindow::isOpenNow()
	) {
		return false;
	}

	icl_sitepress_activate();

	return true;
}

function icl_sitepress_deactivate() {
	wp_clear_scheduled_hook( 'update_wpml_config_index' );
	require_once WPML_PLUGIN_PATH . '/inc/cache.php';
	icl_cache_clear();
	do_action( 'wpml_deactivated' );
}

function icl_enable_capabilities() {
	global $wp_roles;

	if ( ! isset( $wp_roles ) || ! is_object( $wp_roles ) ) {
		$wp_roles = new WP_Roles();
	}

	$iclsettings      = get_option( 'icl_sitepress_settings' );
	$icl_capabilities = icl_sitepress_get_capabilities();

	$roles = $wp_roles->get_names();
	foreach ( $roles as $current_role => $role_name ) {
		if ( isset( $wp_roles->roles[ $current_role ]['capabilities']['manage_options'] ) ) {
			$role = get_role( $current_role );
			if ( isset( $role ) && is_object( $role ) ) {
				for ( $i = 0, $caps_limit = count( $icl_capabilities ); $i < $caps_limit; $i ++ ) {
					if ( ! isset( $wp_roles->roles[ $current_role ]['capabilities'][ $icl_capabilities[ $i ] ] ) ) {
						$role->add_cap( $icl_capabilities[ $i ] );
					}
				}
			}
		}
	}

	$user_admins = get_users(
		array(
			'role' => 'administrator',
		)
	);

	if ( is_multisite() ) {
		$super_admins = get_super_admins();

		foreach ( $super_admins as $admin ) {
			$super_admin = new WP_User( $admin );

			if ( ! in_array( $super_admin, $user_admins, true ) ) {
				$user_admins[] = $super_admin;
			}
		}
	}

	\WPML\TranslationRoles\Service\SyncBatch::run(
		function () use ( $user_admins, $icl_capabilities ) {
			foreach ( $user_admins as $user ) {
				if ( $user->exists() ) {
					for ( $i = 0, $caps_limit = count( $icl_capabilities ); $i < $caps_limit; $i ++ ) {
						( new WP_User( $user->ID ) )->add_cap( $icl_capabilities[ $i ] );
					}
				}
			}
		}
	);

	$iclsettings['icl_capabilities_verified'] = true;
	update_option( 'icl_sitepress_settings', $iclsettings );
}

function wpml_enable_capabilities() {
	global $sitepress_settings;

	if ( ! did_action( 'plugins_loaded' ) ) {
		add_action( 'plugins_loaded', 'wpml_enable_capabilities' );
	} else {
		if ( ! function_exists( 'get_user_by' ) ) {
			require ABSPATH . WPINC . '/pluggable.php';
		}
		icl_enable_capabilities();
		$sitepress_settings = get_option( 'icl_sitepress_settings' );
	}
}
