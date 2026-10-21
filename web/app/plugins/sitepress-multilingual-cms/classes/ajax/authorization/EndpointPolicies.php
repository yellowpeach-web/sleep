<?php

namespace WPML\Ajax\Authorization;

class EndpointPolicies {

	const MANAGE_TRANSLATIONS   = 'manage_translations';
	const MANAGE_OPTIONS        = 'manage_options';
	const MANAGE_LANGUAGES      = 'wpml_manage_languages';
	const MANAGE_STRING_TRANS   = 'wpml_manage_string_translation';
	const MANAGE_MEDIA_TRANS    = 'wpml_manage_media_translation';
	const MANAGE_THEME_L10N     = 'wpml_manage_theme_and_plugin_localization';
	const TRANSLATE             = 'translate';

	public static function map() {
		$mt          = self::MANAGE_TRANSLATIONS;
		$mo          = self::MANAGE_OPTIONS;
		$ml          = self::MANAGE_LANGUAGES;
		$mst         = [ self::MANAGE_STRING_TRANS, self::MANAGE_TRANSLATIONS ];
		$mmed        = [ self::MANAGE_MEDIA_TRANS, self::MANAGE_TRANSLATIONS ];
		$translator  = [ self::TRANSLATE, self::MANAGE_TRANSLATIONS ];

		return [
			'WPML\TM\ATE\AutoTranslate\Endpoint\AutoTranslate'        => $mt,
			'WPML\TM\ATE\AutoTranslate\Endpoint\CancelJobs'           => $mt,
			'WPML\TM\ATE\AutoTranslate\Endpoint\GetATEJobsToSync'     => $mt,
			'WPML\TM\ATE\AutoTranslate\Endpoint\GetAccountBalances'   => $mt,
			'WPML\TM\ATE\AutoTranslate\Endpoint\GetCredits'           => $mt,
			'WPML\TM\ATE\AutoTranslate\Endpoint\GetJobsCount'         => $mt,
			'WPML\TM\ATE\AutoTranslate\Endpoint\GetJobsInfo'          => $mt,
			'WPML\TM\ATE\AutoTranslate\Endpoint\Languages'            => $mt,
			'WPML\TM\ATE\AutoTranslate\Endpoint\Resume'               => $mt,
			'WPML\TM\ATE\AutoTranslate\Endpoint\SyncLock'             => $translator,
			'WPML\TM\ATE\AutoTranslate\Endpoint\TranslationAction'    => $translator,
			'WPML\TM\ATE\AutoTranslate\Endpoint\ActivateLanguage'     => $mt,
			'WPML\TM\ATE\AutoTranslate\Endpoint\CheckLanguageSupport' => $mt,
			'WPML\TM\ATE\AutoTranslate\Endpoint\GetNumberOfPosts'     => $mt,
			'WPML\TM\ATE\AutoTranslate\Endpoint\SetForPostType'       => $mt,

			'WPML\TM\ATE\Review\AcceptTranslation'                    => $translator,
			'WPML\TM\ATE\Review\ApproveTranslations'                  => $translator,
			'WPML\TM\ATE\Review\Cancel'                               => $translator,

			'WPML\TM\ATE\TranslateEverything'                         => $mt,
			'WPML\TM\ATE\UpdateTranslation\UpdateTranslation'         => $translator,
			'WPML\TM\ATE\Retranslation\Endpoint'                      => $mt,
			'WPML\TM\ATE\Retranslation\InfoEndpoint'                  => $mt,
			'WPML\TM\ATE\LanguageMapping\InvalidateCacheEndpoint'     => $mt,

			'WPML\TM\ATE\PullDelivery\AjaxPing'                       => $translator,
			'WPML\TM\ATE\ClonedSites\AutoMigration\Endpoints\Connect'    => $mo,
			'WPML\TM\ATE\ClonedSites\AutoMigration\Endpoints\Disconnect' => $mo,
			'WPML\TM\ATE\ClonedSites\AutoMigration\Endpoints\Dismiss'    => $mo,

			'WPML\TM\Jobs\Endpoint\Resign'                            => $translator,
			'WPML\TM\Jobs\TakeOver\Reassign'                          => $translator,

			'WPML\TM\Menu\TranslationServices\Endpoints\Activate'     => $mt,
			'WPML\TM\Menu\TranslationServices\Endpoints\Deactivate'   => $mt,
			'WPML\TM\Menu\TranslationServices\Endpoints\Select'       => $mt,

			'WPML\TranslationMode\Endpoint\SetAiSkipped'              => $mt,
			'WPML\TranslationMode\Endpoint\SetReviewMode'             => $mt,
			'WPML\TranslationMode\Endpoint\SetTranslateEverything'    => $mt,

			'WPML\TM\ATE\AutoTranslate\Endpoint\EnableATE'            => $mt,
			'WPML\TM\ATE\Network\Endpoints\UseSeparateAccount'        => $mt,
			'WPML\TM\ATE\Network\Endpoints\DismissSuggestion'         => $mt,

			'WPML\TranslationRoles\SaveTranslator'                    => $mt,
			'WPML\TranslationRoles\RemoveTranslator'                  => $mt,
			'WPML\TranslationRoles\FindAvailableByRole'               => $mt,
			'WPML\TranslationRoles\GetManagerRecords'                 => $mt,
			'WPML\TranslationRoles\GetTranslatorRecords'              => $mt,
			'WPML\TranslationRoles\SaveManager'                       => $mo,
			'WPML\TranslationRoles\RemoveManager'                     => $mo,

			'WPML\Setup\Endpoint\AITranslationStep'                   => $ml,
			'WPML\Setup\Endpoint\ATEDashboardScript'                  => $ml,
			'WPML\Setup\Endpoint\AddLanguages'                        => $ml,
			'WPML\Setup\Endpoint\AddressStep'                         => $ml,
			'WPML\Setup\Endpoint\CheckTMAllowed'                      => $ml,
			'WPML\Setup\Endpoint\CurrentStep'                         => $ml,
			'WPML\Setup\Endpoint\EnableAte'                           => $ml,
			'WPML\Setup\Endpoint\FinishStep'                          => $ml,
			'WPML\Setup\Endpoint\FinishMediaSetup'                    => $ml,
			'WPML\Setup\Endpoint\FinishDeferredUpgrade'               => $ml,
			'WPML\Setup\Endpoint\GetLanguagesAutomaticSupport'        => $ml,
			'WPML\Setup\Endpoint\GetParametersForAteDashboard'        => $ml,
			'WPML\Setup\Endpoint\LicenseStep'                         => $ml,
			'WPML\Setup\Endpoint\RecommendedPlugins'                  => $ml,
			'WPML\Setup\Endpoint\SetOriginalLanguage'                 => $ml,
			'WPML\Setup\Endpoint\SetSecondaryLanguages'               => $ml,
			'WPML\Setup\Endpoint\SetSupport'                          => $ml,
			'WPML\Setup\Endpoint\ShouldShowWCMLMessages'              => $ml,
			'WPML\Setup\Endpoint\TranslationServices'                 => $ml,
			'WPML\Setup\Endpoint\TranslationStep'                     => $ml,
			'WPML\Setup\Endpoint\VerifyUrlRewrite'                    => $ml,

			'WPML\LanguageEditor\Endpoint\ActivateLanguageTranslation' => $mo,
			'WPML\LanguageEditor\Endpoint\AddLanguageTranslationInfo'  => $mo,
			'WPML\LanguageEditor\Endpoint\CountLanguageContent'        => $mo,
			'WPML\LanguageEditor\Endpoint\GetAutomaticTranslationInfo' => $mo,
			'WPML\LanguageEditor\Endpoint\GetLanguageLabels'           => $mo,
			'WPML\LanguageEditor\Endpoint\InvalidateCache'             => $mo,
			'WPML\LanguageEditor\Endpoint\SaveLanguages'               => $mo,
			'WPML\LanguageEditor\Endpoint\UploadFlag'                  => $mo,
			'WPML\LanguageEditor\Save\Endpoint\AdvanceSave'            => $mo,
			'WPML\LanguageEditor\Save\Endpoint\EstimateImpact'         => $mo,
			'WPML\LanguageEditor\Save\Endpoint\ResumeSave'             => $mo,
			'WPML\LanguageEditor\Save\Endpoint\SaveStatus'             => $mo,
			'WPML\LanguageEditor\Save\Endpoint\StartSave'              => $mo,
			'WPML\LanguageEditor\Endpoint\ListLanguageContent'       => $mo,
			'WPML\LanguageEditor\Save\Endpoint\ListRemovedLanguages' => $mo,
			'WPML\LanguageEditor\Save\Endpoint\MoveRemovedLanguage'  => $mo,
			'WPML\LanguageEditor\Save\Endpoint\PauseTranslation'     => $mo,
			'WPML\LanguageEditor\Save\Endpoint\RecordLanguageRemoval' => $mo,
			'WPML\LanguageEditor\Save\Endpoint\ProbeLanguageDefaults' => $mo,
			'WPML\LanguageEditor\Save\Endpoint\ResetLanguageDefaults' => $mo,
			'WPML\LanguageEditor\Save\Endpoint\ResumeTranslation'    => $mo,
			'WPML\LanguageEditor\Endpoint\CheckCatalogueFreshness'   => $mo,
			'WPML\LanguageEditor\Endpoint\GetAteLanguages'           => $mo,
			'WPML\LanguageEditor\Endpoint\SyncCatalogue'             => $mo,

			'WPML\ContentDeletion\Endpoint\PreflightDeletion'        => 'edit_posts',
			'WPML\ContentDeletion\Endpoint\PreflightRestore'         => 'edit_posts',
			'WPML\ContentDeletion\Endpoint\UndoItemDelete'           => 'edit_posts',
			'WPML\ContentDeletion\Endpoint\SaveSettings'             => $ml,

			'WPML\Posts\CountPerPostType'                             => $mt,
			'WPML\TM\Settings\Flags\Endpoints\SetFormat'              => $ml,
			'WPML\TM\Settings\GetNumberOfPostsForCustomField'         => $mt,
			'WPML\TM\Settings\CountAffectedPostsForCustomField'       => $mt,
			'WPML\TM\Settings\GetNumberOfTermsForCustomField'         => $mt,
			'WPML\TM\Settings\CountPageUrlSlugChanges'               => $mt,
			'WPML\TM\Settings\EstimateReapplyCost'                    => $mt,
			'WPML\TM\Settings\SchedulePreferenceReapply'              => $mt,

			'WPML\Posts\UntranslatedCount'                            => $mt,
			'WPML\TM\Settings\GetNumberOfTermsForTaxonomy'            => $mt,

			'WPML\Media\Setup\Endpoint\PerformSetup'                  => $mmed,
			'WPML\Media\Setup\Endpoint\PrepareSetup'                  => $mmed,
			'WPML\Media\Translate\Endpoint\DuplicateFeaturedImages'   => $mmed,
			'WPML\Media\Translate\Endpoint\FinishMediaTranslation'    => $mmed,
			'WPML\Media\Translate\Endpoint\PrepareForTranslation'     => $mmed,
			'WPML\Media\Translate\Endpoint\TranslateExistingMedia'    => $mmed,

			'WPML\Ajax\Endpoint\Upload'                               => $mo,

			'WPML\BackgroundTask\BackgroundTaskLoader'                => $mt,

			'WPML\ST\BatchAction\ChangeLanguageOfStringsInDomain'             => $mst,
			'WPML\ST\BatchAction\CountStringsInDomain'                        => $mst,
			'WPML\ST\BatchAction\DeleteStringsInDomain'                       => $mst,
			'WPML\ST\BatchAction\InitChangeStringLangOfDomain'                => $mst,
			'WPML\ST\Main\Ajax\FetchCompletedStrings'                         => $mst,
			'WPML\ST\Main\Ajax\FetchTranslationMemory'                        => $mst,
			'WPML\ST\Main\Ajax\SaveTranslation'                               => $mst,
			'WPML\ST\StringsCleanup\Ajax\InitStringsRemoving'                 => $mst,
			'WPML\ST\StringsCleanup\Ajax\RemoveStringsFromDomains'            => $mst,
			'WPML\ST\StringsScanning\UpdateStats'                             => self::MANAGE_THEME_L10N,
			'WPML\Ajax\ST\AdminText\Register'                                 => $mo,


			'WPML\Import\UI\Endpoints\Command'                                => self::MANAGE_LANGUAGES,

			'ACFML\FieldGroup\Endpoints\DismissTranslateCptModal'             => 'edit_posts',
		];
	}
}
