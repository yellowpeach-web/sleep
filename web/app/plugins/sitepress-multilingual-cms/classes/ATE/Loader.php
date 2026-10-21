<?php

namespace WPML\TM\ATE;

use WPML\Element\API\Languages;
use WPML\TM\ATE\AutomaticTranslationCapabilities;
use WPML\Core\BackgroundTask\Model\BackgroundTask;
use WPML\Core\BackgroundTask\Repository\BackgroundTaskRepository;
use WPML\FP\Fns;
use WPML\FP\Logic;
use WPML\FP\Lst;
use WPML\FP\Obj;
use WPML\FP\Relation;
use WPML\LIB\WP\Hooks;
use WPML\LIB\WP\User;
use WPML\Posts\UntranslatedCount;
use WPML\Setup\Option;
use WPML\TM\ATE\AutoTranslate\Endpoint\AutoTranslate;
use WPML\TM\ATE\AutoTranslate\Endpoint\CancelJobs;
use WPML\TM\ATE\AutoTranslate\Endpoint\CountJobsInProgress;
use WPML\TM\ATE\AutoTranslate\Endpoint\EnableATE;
use WPML\TM\ATE\AutoTranslate\Endpoint\GetAccountBalances;
use WPML\TM\ATE\AutoTranslate\Endpoint\GetATEJobsToSync;
use WPML\TM\ATE\AutoTranslate\Endpoint\GetCredits;
use WPML\TM\ATE\AutoTranslate\Endpoint\GetJobsCount;
use WPML\TM\ATE\AutoTranslate\Endpoint\GetJobsInfo;
use WPML\TM\ATE\AutoTranslate\Endpoint\GetStatus;
use WPML\TM\ATE\AutoTranslate\Endpoint\RefreshJobsStatus;
use WPML\TM\ATE\AutoTranslate\Endpoint\Resume;
use WPML\TM\ATE\AutoTranslate\Endpoint\SyncLock;
use WPML\TM\ATE\AutoTranslate\Endpoint\TranslationAction;
use WPML\TM\ATE\AutoTranslate\Endpoint\Languages as EndpointLanguages;
use WPML\TM\ATE\Download\Queue;
use WPML\TM\ATE\LanguageMapping\InvalidateCacheEndpoint;
use WPML\TM\ATE\Loader\MarkPreviouslyUnsupportedContentAsCompletedInTEA;
use WPML\TM\ATE\Retranslation\Endpoint as RetranslationEndpoint;
use WPML\TM\ATE\Retranslation\InfoEndpoint as RetranslationInfoEndpoint;
use WPML\TM\ATE\Sync\Trigger;
use WPML\TM\Menu\TranslationQueue\TranslationQueuePage;
use WPML\Core\WP\App\Resources;
use WPML\UIPage;
use WPML\TM\ATE\Retranslation\Scheduler;
use WPML\Troubleshooting\ResetWindow;
use function WPML\Container\make;
use function WPML\FP\invoke;
use function WPML\FP\pipe;

class Loader implements \IWPML_Backend_Action, \IWPML_DIC_Action {

	const JOB_ID_PLACEHOLDER = '###';

	private $backgroundTaskRepository;

	private $translateEverything;

	private $markPreviouslyUnsupportedContentAsCompletedInTEA;


	public function __construct(
		BackgroundTaskRepository $backgroundTaskRepository,
		TranslateEverything $translateEverything,
		MarkPreviouslyUnsupportedContentAsCompletedInTEA $markDisplayedAsTranslatedPostTypesAsCompletedInTEA
	) {
		$this->backgroundTaskRepository = $backgroundTaskRepository;
		$this->translateEverything = $translateEverything;
		$this->markPreviouslyUnsupportedContentAsCompletedInTEA = $markDisplayedAsTranslatedPostTypesAsCompletedInTEA;
	}

	public function add_hooks() {
		if ( wpml_is_ajax() ) {
			return;
		}

		if ( $this->isPreviewAction() ) {
			return;
		}

		$maybeLoadStatusBarAndATEConsole = Fns::tap( function () {
			StatusBar::add_hooks();
			Hooks::onAction( 'in_admin_header' )
				 ->then( [ self::class, 'showAteConsoleContainer' ] );
		} );

		Hooks::onAction( 'wp_loaded' )
				 ->then( [ $this->markPreviouslyUnsupportedContentAsCompletedInTEA, 'run' ] )
		     ->then( [ $this, 'getData' ] )
		     ->then( $maybeLoadStatusBarAndATEConsole )
		     ->then( [ self::class, 'enqueueJobsSyncApp' ] );
	}

	public static function enqueueJobsSyncApp( $data ) {
		if ( ! self::canBootJobsSyncApp() || ResetWindow::isResetPage() ) {
			return;
		}

		$enqueue = Resources::enqueueApp( 'ate-jobs-sync' );
		$enqueue( $data );

		if ( ! UIPage::isTMJobs( $_GET ) && ! UIPage::isTranslationQueue( $_GET ) ) {
			make( \WPML_TM_Scripts_Factory::class )->localize_script( 'wpml-ate-jobs-sync-ui' );
		}
	}

	private static function canBootJobsSyncApp() {
		return current_user_can( 'edit_posts' )
			|| self::canRetryAteJobs()
			|| User::canManageTranslations()
			|| User::isTranslator();
	}

	private static function canRetryAteJobs() {
		return current_user_can( 'manage_options' )
			|| current_user_can( 'manage_translations' )
			|| current_user_can( 'translate' );
	}

	private function isPreviewAction() {
		return isset( $_POST['wp-preview'] ) && 'dopreview' === $_POST['wp-preview'];
	}

	public function getData() {
		$ateTab = admin_url( UIPage::getTMATE() );

		$isAteActive = \WPML_TM_ATE_Status::is_enabled_and_activated();

		$defaultLanguage = Languages::getDefaultCode();
		$getLanguages    = pipe(
			Languages::class . '::getActive',
			AutomaticTranslationCapabilities::withCapabilityInfo(),
			\WPML\TM\API\ATE\CachedLanguageMappings::withMapping(),
			Fns::map( Obj::over( Obj::lensProp( 'mapping' ), Obj::prop( 'targetCode' ) ) ),
			Fns::map(
				Obj::addProp(
					'is_default',
					Relation::propEq( 'code', $defaultLanguage )
				)
			),
			Obj::values()
		);

		$jobs = make( Jobs::class );

		$scheduler = make( Scheduler::class );

		$data = [
			'name' => 'ate_jobs_sync',
			'data' => [
				'endpoints'            => self::getEndpoints(),
				'urls'                 => self::getUrls( $ateTab ),
				'jobIdPlaceHolder'     => self::JOB_ID_PLACEHOLDER,
				'languages'            => $isAteActive ? $getLanguages() : [],
				'isTranslationManager' => User::canManageTranslations(),
				'isTranslatorOrHigher'        => User::isTranslator() || User::canManageTranslations(),

				'canRetryAteJobs'             => self::canRetryAteJobs(),

				'shouldTranslateEverything'   =>
					AutomaticTranslationCapabilities::shouldTranslateEverything()
					&& ! $this->translateEverything->isEverythingProcessed( true ),

				'isAutomaticTranslations' => Option::shouldTranslateEverything(),

				'pendingPreferenceRulings' => [
					'fields' => \WPML\TM\Settings\CustomFieldChangeDetector::pending(),
					'url'    => \admin_url( 'admin.php?page=' . WPML_TM_FOLDER . '/menu/settings&section=custom-fields' ),
				],

				'notEnoughCreditPopup' => self::getNotEnoughCreditPopup(),
				'ateConsole'           => self::getAteData(),
				'isAteActive'          => $isAteActive,
				'editorMode'           => wpml_get_tm_sub_setting( 'doc_translation_method', false ),
				'shouldCheckForRetranslation' => $scheduler->shouldRun(),
				'ateCallbacks' => [],

					'pullDelivery' => [
						'enabled'      => self::canRetryAteJobs(),
						'restUrl'      => \WPML\TM\ATE\REST\PullPing::url(),
						'restNonce'    => wp_create_nonce( 'wp_rest' ),
						'heartbeatKey' => \WPML\TM\ATE\PullDelivery\Hooks::HEARTBEAT_KEY,
						'minPing'      => \WPML\TM\ATE\PullDelivery\Cadence::minPing(),
						'snapshot'     => \WPML\TM\ATE\PullDelivery\State::snapshot(),
					],

				'settings' => [
					'numberOfParallelDownloads' => defined('WPML_ATE_MAX_PARALLEL_DOWNLOADS') ? WPML_ATE_MAX_PARALLEL_DOWNLOADS : 2,
					'downloadBatchSize'         => (int) apply_filters(
						'wpml_ate_jobs_download_batch_size',
						defined( 'WPML_ATE_JOBS_DOWNLOAD_BATCH_SIZE' ) ? WPML_ATE_JOBS_DOWNLOAD_BATCH_SIZE : 5
					),
				],
			],
		];

		if ( UIPage::isTMDashboard( $_GET ) ) {
			$data['data']['anyJobsExist'] = $jobs->hasAny();
		}

		return $data;
	}

	public static function getNotEnoughCreditPopup() {
		$isTranslationManager = User::canManageTranslations();

		$primaryButton = $isTranslationManager
			? '<button class="wpml-antd-button wpml-antd-button-primary" onclick="BUTTON_ACTION">BUTTON_TEXT</button>'
			: '';

		return '<div class="wpml-not-enough-credit-popup">' .
		       '<p>MESSAGE_TEXT</p>' .
		       $primaryButton .
		       '</div>';
	}

	public static function showAteConsoleContainer() {
		echo '<div id="wpml-ate-console-container"></div>';
	}

	private static function getAteData() {
		if ( User::canManageTranslations() ) {
			$noCreditPopup = make( NoCreditPopup::class );

			return $noCreditPopup->getData();
		}

		return false;
	}

	private static function getEndpoints() {
		return [
			'auto-translate'           => AutoTranslate::class,
			'translation-action'       => TranslationAction::class,
			'translate-everything'     => TranslateEverything::class,
			'getCredits'               => GetCredits::class,
			'getAccountBalances'       => GetAccountBalances::class,
			'resume'                   => Resume::class,
			'enableATE'                => EnableATE::class,
			'getATEJobsToSync'         => GetATEJobsToSync::class,
			'syncLock'                 => SyncLock::class,
			'untranslatedCount'        => UntranslatedCount::class,
			'getJobsCount'             => GetJobsCount::class,
				'getJobsInfo'              => GetJobsInfo::class,
				'languages'                => EndpointLanguages::class,
				'pullPing'                     => \WPML\TM\ATE\PullDelivery\AjaxPing::class,
				'assignToTranslation'          => RetranslationEndpoint::class,
				'retranslationInfo'            => RetranslationInfoEndpoint::class,
				'invalidateLangMappingCache'   => InvalidateCacheEndpoint::class,
			];
	}

	private static function getUrls( $ateTab ) {
		return [
			'editor'                    => \WPML_TM_Translation_Status_Display::get_link_for_existing_job( self::JOB_ID_PLACEHOLDER ),
			'ateams'                    => $ateTab,
			'automaticSettings'         => \admin_url( UIPage::getSettings() ),
			'ateConsole'                => make( NoCreditPopup::class )->getUrl(),
			'translationQueue'          => \add_query_arg(
				[ 'status' => ICL_TM_NEEDS_REVIEW ],
				\admin_url( TranslationQueuePage::base() )
			),
			'currentUrl'                => \WPML\TM\API\Jobs::getCurrentUrl(),
			'editLanguages'             => \admin_url( 'admin.php?page=' . WPML_TM_FOLDER . '/menu/settings&section=languages' ),
			'translationDashboard'      => \admin_url( UIPage::getTMDashboard() ),
		];
	}
}
