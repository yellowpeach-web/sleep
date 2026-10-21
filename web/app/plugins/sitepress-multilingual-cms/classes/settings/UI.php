<?php


namespace WPML\Settings;

use WPML\API\PostTypes;
use WPML\Core\WP\App\Resources;
use WPML\FP\Either;
use WPML\FP\Fns;
use WPML\FP\Logic;
use WPML\LIB\WP\Hooks;
use WPML\Setup\Option;
use WPML\TM\ATE\AutomaticTranslationCapabilities;
use WPML\TM\ATE\AutoTranslate\Endpoint\GetNumberOfPosts;
use WPML\TM\ATE\AutoTranslate\Endpoint\SetForPostType;
use WPML\TM\Settings\CountPageUrlSlugChanges;
use WPML\TM\Settings\CountSettingsChange;
use WPML\TM\Settings\EstimateReapplyCost;
use WPML\TM\Settings\CustomFieldChangeDetector;
use WPML\TM\Settings\CountAffectedPostsForCustomField;
use WPML\TM\Settings\GetNumberOfTermsForCustomField;
use WPML\TM\Settings\GetNumberOfTermsForTaxonomy;
use WPML\TM\Settings\ReapplyPreferenceChanges;
use WPML\TM\Settings\SchedulePreferenceReapply;
use WPML\UIPage;
use WPML\Setup\Endpoint\ATEDashboardScript;

class UI implements \IWPML_Backend_Action {

	private static $requestQuery = [];

	public function add_hooks() {
		$query = $_GET;

		Hooks::onAction( 'admin_enqueue_scripts' )
		     ->then( Fns::always( $query ) )
		     ->then( Logic::anyPass( [ [ UIPage::class, 'isMainSettingsTab' ], [ UIPage::class, 'isTroubleshooting' ] ] ) )
		     ->then( Either::fromBool() )
		     ->then( [ self::class, 'getData' ] )
		     ->then( Resources::enqueueApp( 'settings' ) );

		self::$requestQuery = $query;
		add_action( 'admin_enqueue_scripts', [ self::class, 'enqueueSettingsStylesForRequest' ] );
		add_action( 'admin_enqueue_scripts', [ self::class, 'enqueueSettingsFormBindingsForRequest' ] );
	}

	/**
	 * With TM loaded, TranslationManagement::admin_enqueue_scripts enqueues the
	 * Settings hub stylesheet exactly as it always did (including the legacy
	 * page aliases it sees); this path only covers the licenses that skip tm.php.
	 *
	 * @return void
	 */
	public static function enqueueSettingsStylesForRequest() {
		if ( self::tmOwnsTheSettingsScreens() ) {
			return;
		}

		self::maybeEnqueueSettingsStyles( self::$requestQuery );
	}

	/**
	 * The Settings hub is a core screen on every license, so its stylesheet must
	 * not depend on tm.php: TranslationManagement enqueued it behind a
	 * "TM is loaded" guard, which a Blog license never passes (wpmldev-8160: the
	 * Post Types / Taxonomies grid, .wpml-flex-table, collapsed into stacked
	 * radios without it).
	 *
	 * @param array $get The request query, as captured by add_hooks().
	 *
	 * @return void
	 */
	public static function maybeEnqueueSettingsStyles( array $get ) {
		if ( UIPage::isSettings( $get ) ) {
			self::enqueueSettingsStyles();
		}
	}

	/**
	 * With TM loaded, res/js/scripts-tm.js (enqueued by
	 * TranslationManagement::admin_enqueue_scripts) binds the hub's option forms
	 * itself; this path only covers the licenses that skip tm.php. It is the
	 * exact negation of that guard, so no request binds a form twice.
	 *
	 * @return void
	 */
	public static function enqueueSettingsFormBindingsForRequest() {
		if ( self::tmOwnsTheSettingsScreens() ) {
			return;
		}

		self::maybeEnqueueSettingsFormBindings( self::$requestQuery );
	}

	/**
	 * The hub's legacy option forms (Posts and Pages Synchronization, Post
	 * Types / Taxonomies Translation, Custom Fields, ...) are declared with an
	 * empty action and no method: they save over admin-ajax only through the
	 * iclSaveForm() submit binding. That binding lived solely in scripts-tm.js,
	 * so on a Blog license Save was a native GET to a bare admin.php and landed
	 * on a blank page (wpmldev-8391). Same seam as the stylesheet above.
	 *
	 * @param array $get The request query, as captured by add_hooks().
	 *
	 * @return void
	 */
	public static function maybeEnqueueSettingsFormBindings( array $get ) {
		if ( UIPage::isSettings( $get ) ) {
			self::enqueueSettingsFormBindings();
		}
	}

	public static function enqueueSettingsFormBindings() {
		wp_register_script( 'sitepress-translation-options', ICL_PLUGIN_URL . '/res/js/translation-options.js', [ 'jquery', 'sitepress-scripts' ], ICL_SITEPRESS_SCRIPT_VERSION, false );
		wp_enqueue_script( 'sitepress-translation-options' );
	}

	private static function tmOwnsTheSettingsScreens() {
		return \WPML\Plugins::isTMLoadedForRequest() && defined( 'WPML_TM_VERSION' ) && defined( 'WPML_TM_URL' );
	}

	public static function enqueueSettingsStyles() {
		wp_register_style( 'sitepress-translation-options', ICL_PLUGIN_URL . '/res/css/translation-options.css', [], ICL_SITEPRESS_SCRIPT_VERSION );
		wp_enqueue_style( 'sitepress-translation-options' );
	}

	public static function getData() {
		return [
			'name' => 'wpmlSettingsUI',
			'data' => [
				'endpoints'                 => [
					'getCount'               => GetNumberOfPosts::class,
					'setAutomatic'           => SetForPostType::class,
					'ateDashboardScript'     => ATEDashboardScript::class,
					'getCountForCustomField' => CountAffectedPostsForCustomField::class,
					'getCountForTermMeta'    => GetNumberOfTermsForCustomField::class,
					'getCountForTaxonomy'    => GetNumberOfTermsForTaxonomy::class,
					'countPageUrlSlugChanges' => CountPageUrlSlugChanges::class,
					'estimateReapplyCost'    => EstimateReapplyCost::class,
					'scheduleReapply'        => SchedulePreferenceReapply::class,
					'runReapply'             => ReapplyPreferenceChanges::class,
					'runCount'               => CountSettingsChange::class,
				],
				'shouldTranslateEverything' => AutomaticTranslationCapabilities::shouldTranslateEverything(),
				'pendingPreferenceRulings'  => CustomFieldChangeDetector::pending(),
				'settingsUrl'               => admin_url( UIPage::getSettings() ),
				'existingPostTypes'         => PostTypes::getOnlyTranslatable(),
				'isTMLoaded'                => ! wpml_is_setup_complete() || Option::isTMAllowed(),
			]
		];
	}
}
