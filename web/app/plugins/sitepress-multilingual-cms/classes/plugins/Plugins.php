<?php

namespace WPML;

use WPML\API\Settings;
use WPML\FP\Fns;
use WPML\FP\Lst;
use WPML\FP\Obj;
use WPML\FP\Relation;
use WPML\Setup\Option;

class Plugins {
	const WPML_TM_PLUGIN              = 'wpml-translation-management/plugin.php';
	const WPML_CORE_PLUGIN            = 'sitepress-multilingual-cms/sitepress.php';
	const WPML_SUBSCRIPTION_TYPE_BLOG = 6718;
	const AFTER_INSTALLER             = 999;

	public static function loadCoreFirst() {
		$plugins = get_option( 'active_plugins' );

		$isSitePress = function ( $value ) {
			return $value === WPML_PLUGIN_BASENAME;
		};

		$newOrder = wpml_collect( $plugins )
			->prioritize( $isSitePress )
			->values()
			->toArray();

		if ( $newOrder !== $plugins ) {
			update_option( 'active_plugins', $newOrder );
		}
	}

	/**
	 * The verdict `inc/functions-load-tm.php` reached at plugin boot: did this
	 * request load Translation Management's module functions?
	 *
	 * `is-tm-allowed` is not stable within one request. The product itself
	 * rewrites it from the live installer subscription on
	 * `otgs_installer_initialized` and on
	 * `otgs_installer_subscription_refreshed`, both of which fire after plugin
	 * boot - so a license upgrade, or any drift between the stored option and
	 * the installer's subscription record, lands mid-request. A gate that read
	 * the option again later could decide to use TM code that boot had declined
	 * to define, and the request died on an undefined function (POST-16a).
	 *
	 * Whatever the option says later, the code this request can call was
	 * settled at boot. This is where that one answer lives.
	 *
	 * @var bool|null
	 */
	private static $isTMLoadedForRequest = null;

	public static function latchTMLoadedForRequest( $isLoaded ) {
		if ( self::$isTMLoadedForRequest === null ) {
			self::$isTMLoadedForRequest = (bool) $isLoaded;
		}

		return self::$isTMLoadedForRequest;
	}

	public static function isTMLoadedForRequest() {
		return self::$isTMLoadedForRequest === null
			? function_exists( 'wpml_tm_load_element_translations' )
			: self::$isTMLoadedForRequest;
	}

	public static function isTMAllowed() {
		$isTMAllowed = true;

		if ( function_exists( 'OTGS_Installer' ) ) {
			$subscriptionType = OTGS_Installer()->get_subscription( 'wpml' )->get_type();
			if ( $subscriptionType && $subscriptionType === self::WPML_SUBSCRIPTION_TYPE_BLOG ) {
				$isTMAllowed = false;
			}
		}

		return $isTMAllowed;
	}

	public static function updateTMAllowedOption() {
		$isTMAllowed = self::isTMAllowed();
		Option::setTMAllowed( $isTMAllowed );
		return $isTMAllowed;
	}

	public static function updateTMAllowedAndTranslateEverythingOnSubscriptionChange() {
		if ( function_exists( 'OTGS_Installer' ) ) {
			$type = OTGS_Installer()->get_subscription( 'wpml' )->get_type();
			if ( $type ) {
				Option::setTMAllowed( $type !== self::WPML_SUBSCRIPTION_TYPE_BLOG );
				if ( self::WPML_SUBSCRIPTION_TYPE_BLOG === $type ) {
					Option::setTranslateEverything( false );
				}
				self::selectTranslationEditorIfMissing( $type );
			}
		}
	}

	private static function selectTranslationEditorIfMissing( $subscriptionType ) {
		global $sitepress;

		if (
			self::WPML_SUBSCRIPTION_TYPE_BLOG === $subscriptionType
			|| true !== Option::isTMAllowed()
			|| ! is_object( $sitepress )
			|| ! function_exists( 'wpml_is_setup_complete' )
			|| ! function_exists( 'wpml_get_tm_sub_setting' )
			|| ! wpml_is_setup_complete()
		) {
			return;
		}

		$editor = wpml_get_tm_sub_setting( 'doc_translation_method', null );
		if ( null !== $editor && (string) ICL_TM_TMETHOD_MANUAL !== (string) $editor ) {
			return;
		}

		$newSettings = [];

		$hasExplicitGlobalEditorMode = in_array(
			wpml_get_tm_sub_setting( \WPML_TM_Post_Edit_TM_Editor_Mode::TM_KEY_GLOBAL_EDITOR, null ),
			[
				\WPML_TM_Post_Edit_TM_Editor_Mode::EDITOR_NATIVE,
				\WPML_TM_Post_Edit_TM_Editor_Mode::EDITOR_WPML,
				\WPML_TM_Post_Edit_TM_Editor_Mode::EDITOR_DASHBOARD,
			],
			true
		)
			|| null !== wpml_get_tm_sub_setting( \WPML_TM_Post_Edit_TM_Editor_Mode::TM_KEY_GLOBAL_USE_NATIVE, null )
			|| null !== wpml_get_tm_sub_setting( \WPML_TM_Post_Edit_TM_Editor_Mode::TM_KEY_GLOBAL_USE_WPML, null );

		if ( ! $hasExplicitGlobalEditorMode ) {
			$newSettings[ \WPML_TM_Post_Edit_TM_Editor_Mode::TM_KEY_GLOBAL_EDITOR ] = \WPML_TM_Post_Edit_TM_Editor_Mode::EDITOR_NATIVE;
		}
		$newSettings['doc_translation_method'] = ICL_TM_TMETHOD_ATE;

		$tmSettings = Settings::getOr( [], 'translation-management' );
		$tmSettings = is_array( $tmSettings ) ? $tmSettings : [];

		Settings::setAndSave( 'translation-management', array_merge( $tmSettings, $newSettings ) );

		global $iclTranslationManagement;
		if ( is_object( $iclTranslationManagement ) ) {
			foreach ( $newSettings as $key => $value ) {
				$iclTranslationManagement->settings[ $key ] = $value;
			}
		}

		if ( function_exists( 'wpml_get_cache' ) && class_exists( \WPML_Translation_Roles_Records::class ) ) {
			wpml_get_cache( \WPML_Translation_Roles_Records::CACHE_GROUP )->flush_group_cache();
		}
	}

	public static function loadEmbeddedTM( $isSetupComplete ) {
		$tmSlug = 'wpml-translation-management/plugin.php';

		self::stopPluginActivation( self::WPML_TM_PLUGIN );
		add_action( 'otgs_installer_subscription_refreshed', [ self::class, 'updateTMAllowedOption' ] );
		add_action( 'otgs_installer_site_key_update', [ self::class, 'updateTMAllowedAndTranslateEverythingOnSubscriptionChange' ] );

		if ( ! self::deactivateTm() ) {

			add_action( "after_plugin_row_$tmSlug", [ self::class, 'showEmbeddedTMNotice' ] );
			add_action(
                'otgs_installer_initialized',
                [
					self::class,
					'updateTMAllowedAndTranslateEverythingOnSubscriptionChange',
				]
            );

			$isTMAllowed = Option::isTMAllowed();
			if ( $isTMAllowed === null ) {
				add_action( 'after_setup_theme', [ self::class, 'updateTMAllowedOption' ], self::AFTER_INSTALLER );
			}
			if ( ! $isSetupComplete || $isTMAllowed ) {
				require_once WPML_PLUGIN_PATH . '/tm.php';
			} else {
				// Blog license: tm.php is not loaded, but a few options
				// core reads WPML_TM_URL and its neighbours on every license
				require_once WPML_PLUGIN_PATH . '/inc/constants-tm.php';
				self::loadBlogLicenseSettingsSaving();
				self::loadBlogLicenseTranslationStatus();
			}
		}
	}

	/**
	 * Keep translation status moving on a Blog license (wpmldev-3902).
	 *
	 * tm.php holds the only listener on `wpml_tm_save_post`, the action core
	 * fires on every post save. Without it the "needs update" row is never
	 * written and the status icon never leaves the pencil, although the read
	 * side (WPML_Post_Status, WPML_Post_Status_Display) works without TM.
	 * The listener registered here writes that row and nothing else - see
	 * WPML_Blog_License_Translation_Status.
	 *
	 * @return void
	 */
	private static function loadBlogLicenseTranslationStatus() {
		add_action( 'wpml_tm_save_post', [ \WPML_Blog_License_Translation_Status::class, 'on_save_post' ], 10, 3 );
	}

	private static function loadBlogLicenseSettingsSaving() {
		if ( defined( 'DOING_AJAX' ) && DOING_AJAX ) {
			global $sitepress;
			if ( $sitepress instanceof \SitePress ) {
				( new \WPML_TM_Options_Ajax( $sitepress ) )->ajax_hooks();
			}
			return;
		}

		add_action( 'admin_enqueue_scripts', [ self::class, 'registerBlogLicenseMcsScript' ] );
	}

	/**
	 * Register the legacy MCS save script on a blog license. Same handle,
	 * file and version as the TM registration in `inc/js-tm-scripts.php`,
	 * but with only the `jquery` dependency — the translation-pickup
	 * polling chain that registration adds is TM-specific and unused by
	 * the blog-eligible save buttons. The existing
	 * `wp_enqueue_script( 'wpml-tm-mcs' )` call in the MCS settings render
	 * then resolves (wpmldev-7163).
	 *
	 * @return void
	 */
	public static function registerBlogLicenseMcsScript() {
		if ( wp_script_is( 'wpml-tm-mcs', 'registered' ) ) {
			return;
		}
		wp_register_script(
			'wpml-tm-mcs',
			ICL_PLUGIN_URL . '/res/js/mcs/wpml-tm-mcs.js',
			[ 'jquery' ],
			ICL_SITEPRESS_SCRIPT_VERSION,
			true
		);
	}

	private static function deactivateTm() {
		if ( ! self::isTMActive() ) {
			return false;
		}

		require_once ABSPATH . 'wp-admin/includes/plugin.php';
		require_once ABSPATH . 'wp-includes/pluggable.php';

		deactivate_plugins( self::WPML_TM_PLUGIN );

		if ( ! wpml_is_cli() && ! wpml_is_ajax() && wp_redirect( $_SERVER['REQUEST_URI'], 302, 'WPML' ) ) {
			exit;
		}

		return true;
	}

	public static function isTMActive() {
		$hasTM = function ( $plugins ) {
			return is_array( $plugins ) && (
					Lst::includes( self::WPML_TM_PLUGIN, $plugins ) ||
					array_key_exists( self::WPML_TM_PLUGIN, $plugins )
				);
		};

		if ( \is_multisite() && $hasTM( \get_site_option( 'active_sitewide_plugins', [] ) ) ) {
			return true;
		}

        return $hasTM( \get_option( 'active_plugins', [] ) );
	}

	private static function stopPluginActivation( $pluginSlug ) {
		if ( Relation::propEq( 'action', 'activate', $_GET ) && Relation::propEq( 'plugin', $pluginSlug, $_GET ) ) {
			unset( $_GET['plugin'], $_GET['action'] );
		}

		if ( wpml_is_cli() ) {
			if ( Lst::includesAll( [ 'plugin', 'activate', 'wpml-translation-management' ], $_SERVER['argv'] ) ) {
				\WP_CLI::warning(
					__( 'WPML Translation Management is now included in WPML Multilingual CMS.', 'sitepress' )
				);
			}
		}

		if (
			Relation::propEq( 'action', 'activate-selected', $_POST )
			&& Lst::includes( $pluginSlug, Obj::propOr( [], 'checked', $_POST ) )
		) {
			$_POST['checked'] = Fns::reject( Relation::equals( $pluginSlug ), $_POST['checked'] );
		}
	}

	public static function showEmbeddedTMNotice() {
		$wpListTable = _get_list_table( 'WP_Plugins_List_Table' );
		?>

		<tr class="plugin-update-tr">
			<td colspan="<?php echo $wpListTable->get_column_count(); ?>" class="plugin-update colspanchange">
				<div class="update-message inline notice notice-error notice-alt">
					<p>
						<?php
						echo _e(
							'This plugin has been deactivated as it is now part of the WPML Multilingual CMS plugin. You can safely delete it.',
							'sitepress'
						);
						?>
					</p>
				</div>
		</tr>
		<?php
	}
}
