<?php

namespace WPML\Compatibility\FusionBuilder\Hooks;

use WPML\FP\Obj;
use WPML\LIB\WP\Hooks;
use function WPML\FP\spreadArgs;

class MultilingualOptions implements \IWPML_Backend_Action, \IWPML_Frontend_Action, \IWPML_AJAX_Action, \IWPML_REST_Action, \IWPML_DIC_Action {

	use OptionNameOfLanguage;

	const OPTIONS_SCREEN_ID = 'appearance_page_avada_options';
	const NOTICE_GROUP      = 'wpml-multilingual-options';

	const OPTION_NAME = 'fusion_options';
	const CONTEXT     = 'admin_texts_fusion_options';

	const LANGUAGE_OF_UNSUFFIXED_OPTION = 'en';

	const PRIORITY_AFTER_ORIGINAL_STRINGS_SYNC = 11;

	private $pbStringTranslation;

	private $updatedTranslations = [];

	private $addedOptionsSync;

	private $canLoadAdminTexts;

	public function __construct( \WPML_PB_String_Translation $pbStringTranslation ) {
		$this->pbStringTranslation = $pbStringTranslation;
		$this->canLoadAdminTexts   = function_exists( 'wpml_st_load_admin_texts' );
	}

	public function add_hooks() {
		Hooks::onAction( 'current_screen' )->then( spreadArgs( [ $this, 'multilingualOptionsNotice' ] ) );

		add_action( 'wpml_st_add_string_translation', [ $this, 'syncStringsToOptions' ], 10, 4 );

		$currentLanguage = apply_filters( 'wpml_current_language', null );
		$optionName      = $this->getOptionName( $currentLanguage );
		add_action( 'update_option_' . $optionName, [ $this, 'syncOptionsToStrings' ], self::PRIORITY_AFTER_ORIGINAL_STRINGS_SYNC, 2 );
		add_action( 'add_option_' . $optionName, $this->getAddedOptionsSync(), self::PRIORITY_AFTER_ORIGINAL_STRINGS_SYNC, 2 );

		if ( class_exists( 'WPML_Admin_Texts' ) ) {
			add_action(
				'update_option_' . \WPML_Admin_Texts::TRANSLATABLE_NAMES_SETTING,
				function () {
					$this->syncEnglishOptionsToListedStrings();
				}
			);
		}
	}

	private function getAddedOptionsSync() {
		if ( null === $this->addedOptionsSync ) {
			$this->addedOptionsSync = function ( $optionName, $options ) {
				$this->syncOptionsToStrings( [], $options );
			};
		}

		return $this->addedOptionsSync;
	}

	public function multilingualOptionsNotice( $screen ) {
		if ( self::OPTIONS_SCREEN_ID !== $screen->id ) {
			return;
		}

		$text     = $this->getNoticeContent();
		$noticeId = md5( self::OPTIONS_SCREEN_ID );
		$notice   = new \WPML_Notice( $noticeId, $text, self::NOTICE_GROUP );
		$notice->set_css_class_types( $this->getNoticeClass() );
		$notice->set_restrict_to_screen_ids( [ self::OPTIONS_SCREEN_ID ] );
		$notice->set_dismissible( true );

		$adminNotices = wpml_get_admin_notices();
		$adminNotices->remove_notice( self::NOTICE_GROUP, $noticeId );
		$adminNotices->add_notice( $notice, true );
	}

	private function getNoticeClass() {
		if ( 'all' === filter_input( INPUT_GET, 'lang' ) ) {
			return 'info mashup';
		}
		return 'info';
	}

	private function getNoticeContent() {
		if ( 'all' === filter_input( INPUT_GET, 'lang' ) ) {
			return $this->getNoticeContentForAllLanguages();
		}

		global $sitepress;
		$dashboardUrl               = $this->getTranslationDashboardUrl();
		$currentLanguage            = apply_filters( 'wpml_current_language', null );
		$currentLanguageDisplayName = Obj::prop( 'display_name', Obj::prop( $currentLanguage, $sitepress->get_active_languages() ) );
		$text                       = '<h4>' . sprintf(
			// Translators: %s is the display name of a language.
			__( 'You\'re editing theme options for %s', 'sitepress' ),
			$currentLanguageDisplayName
		) . '</h4>'
		. '<ul>'
		/* translators: First line of the notice on the Avada theme options screen, shown while one language is being edited. */
		. '<li>' . __( 'Avada stores a separate set of options for each language.', 'sitepress' ) . '</li>'
		/* translators: Second line of that notice; the switcher is WPML's language switcher in the WordPress admin bar. */
		. '<li>' . __( 'To edit other languages, use the switcher in the top admin bar.', 'sitepress' ) . '</li>';
		if ( $dashboardUrl ) {
			$text .= '<li>' . sprintf(
				// Translators: %1$s and %2$s are bold HTML tags, and %3$s and %4$s are HTML anchor tags.
				__( 'To translate text-based options, use %1$sOther texts (Strings)%2$s in the %3$sTranslation Dashboard%4$s.', 'sitepress' ),
				'<strong>',
				'</strong>',
				'<a href="' . $dashboardUrl . '">',
				'</a>'
			) . '</li>';
		}
		$text .= '</ul>';

		return $text;
	}

	private function getNoticeContentForAllLanguages() {
		/* translators: Heading of the notice on the Avada theme options screen, shown while the all-languages view is being edited. */
		$text  = '<h4>' . __( 'You\'re editing theme options for all languages', 'sitepress' ) . '</h4>'
			/* translators: Body of that notice. */
			. '<p>' . __( 'Your edits will be synchronized to all languages on your site.', 'sitepress' ) . '</p>';
		$text .= '<div class="otgs-notice warning">';
		if ( defined( 'WPML_ST_FOLDER' ) ) {
			$text .= sprintf(
				// Translators: %1$s and %2$s are bold HTML tags.
				__( 'This will %1$soverwrite any existing language-specific settings%2$s, including translated texts created with WPML.', 'sitepress' ),
				'<strong>',
				'</strong>'
			);
		} else {
			$text .= sprintf(
				// Translators: %1$s and %2$s are bold HTML tags.
				__( 'This will %1$soverwrite any existing language-specific settings%2$s.', 'sitepress' ),
				'<strong>',
				'</strong>'
			);
		}
		$text .= '</div>';

		return $text;
	}

	private function getTranslationDashboardUrl() {
		if ( ! defined( 'WPML_ST_FOLDER' ) ) {
			return null;
		}

		return admin_url( 'admin.php?page=tm/menu/main.php&sm=dashboard&sections=string' );
	}

	public function syncStringsToOptions( $translationId, $translationData = [], $language = '', $stringId = null ) {
		if ( ! $stringId ) {
			return;
		}

		$defaultLanguage = apply_filters( 'wpml_default_language', null );
		if ( ! isset( $this->updatedTranslations[ $stringId ] ) ) {
			$this->updatedTranslations[ $stringId ] = [];
		}

		$this->updatedTranslations[ $stringId ][ $language ] = Obj::prop( 'value', $translationData );
		$this->addShutdownAction();
	}

	private function addShutdownAction() {
		if ( ! has_action( 'shutdown', array( $this, 'processUpdateQueueAction' ) ) ) {
			add_action( 'shutdown', array( $this, 'processUpdateQueueAction' ) );
		}
	}

	public function processUpdateQueueAction() {
		remove_action( 'shutdown', array( $this, 'processUpdateQueueAction' ) );

		$this->processUpdateQueue();
	}

	private function processUpdateQueue() {
		$updatedStringIds = array_keys( $this->updatedTranslations );
		if ( empty( $updatedStringIds ) ) {
			return;
		}

		$conditions      = 'AND id IN (' . wpml_prepare_in( $updatedStringIds, '%d' ) . ')';
		$stringsInDomain = $this->pbStringTranslation->getStringsInContext( self::CONTEXT, [ 'id', 'name' ], $conditions );
		$registeredNames = wp_list_pluck( $stringsInDomain, 'name', 'id' );
		if ( empty( $registeredNames ) ) {
			return;
		}

		$namesToOptionPaths = $this->getNamesToOptionPaths();
		if ( empty( $namesToOptionPaths ) ) {
			return;
		}

		$optionsPerLanguage = [];
		foreach ( $this->updatedTranslations as $stringId => $stringData ) {
			$stringName = Obj::prop( $stringId, $registeredNames );
			if ( ! $stringName ) {
				continue;
			}

			$optionPath = Obj::prop( $stringName, $namesToOptionPaths );
			if ( ! $optionPath ) {
				continue;
			}

			foreach ( $stringData as $language => $value ) {
				if ( is_null( $value ) ) {
					continue;
				}
				$option                          = $optionsPerLanguage[ $language ] ?? get_option( $this->getOptionName( $language ), [] );
				$optionsPerLanguage[ $language ] = Obj::assocPath( $optionPath, $value, $option );
			}
		}

		$currentLanguage   = apply_filters( 'wpml_current_language', null );
		$currentOptionName = $this->getOptionName( $currentLanguage );
		remove_action( 'update_option_' . $currentOptionName, [ $this, 'syncOptionsToStrings' ], self::PRIORITY_AFTER_ORIGINAL_STRINGS_SYNC );
		remove_action( 'add_option_' . $currentOptionName, $this->getAddedOptionsSync(), self::PRIORITY_AFTER_ORIGINAL_STRINGS_SYNC );

		foreach ( $optionsPerLanguage as $language => $options ) {
			if ( self::LANGUAGE_OF_UNSUFFIXED_OPTION === $language ) {
				$this->updateUnsuffixedOptionAndKeepOriginalStrings( $options );
			} else {
				update_option( $this->getOptionName( (string) $language ), $options, false );
			}
		}
	}

	private function updateUnsuffixedOptionAndKeepOriginalStrings( $options ) {
		$updateAction        = 'update_option_' . self::OPTION_NAME;
		$adminTexts          = $this->canLoadAdminTexts ? wpml_st_load_admin_texts() : null;
		$originalStringsSync = [ $adminTexts, 'on_update_original_value' ];
		$priority            = $adminTexts ? has_action( $updateAction, $originalStringsSync ) : false;

		if ( false !== $priority ) {
			remove_action( $updateAction, $originalStringsSync, $priority );
		}

		update_option( self::OPTION_NAME, $options, false );

		if ( false !== $priority ) {
			add_action( $updateAction, $originalStringsSync, $priority, 3 );
		}
	}

	private function getNamesToOptionPaths() {
		$translatableNamesSetting = get_option( \WPML_Admin_Texts::TRANSLATABLE_NAMES_SETTING, [] );
		$translatableAvadaOptions = Obj::prop( self::OPTION_NAME, $translatableNamesSetting );
		if ( empty( $translatableAvadaOptions ) ) {
			return [];
		}

		$namesToPaths = function( $options, $key = '', $path = [], $paths = [] ) use ( &$namesToPaths ) {
			foreach ( $options as $optionName => $optionPath ) {
				$iterationPath   = $path;
				$iterationPath[] = $optionName;
				if ( is_array( $optionPath ) ) {
					$paths = $namesToPaths( $optionPath, $key . '[' . $optionName . ']', $iterationPath, $paths );
				} else {
					$paths[ $key . $optionName ] = $iterationPath;
				}
			}
			return $paths;
		};

		return $namesToPaths( $translatableAvadaOptions, '[' . self::OPTION_NAME . ']' );
	}

	public function syncOptionsToStrings( $oldOptions, $newOptions ) {
		$defaultLanguage = apply_filters( 'wpml_default_language', null );
		$currentLanguage = apply_filters( 'wpml_current_language', null );
		if ( $defaultLanguage === $currentLanguage ) {
			return;
		}

		$this->registerTranslations( $newOptions, $currentLanguage, true );
	}

	private function syncEnglishOptionsToListedStrings() {
		if ( self::LANGUAGE_OF_UNSUFFIXED_OPTION === apply_filters( 'wpml_default_language', null ) ) {
			return;
		}

		$englishOptions = \WPML\Utils\Unserialize::maybe( apply_filters( 'wpml_unfiltered_admin_string', false, self::OPTION_NAME ) );
		if ( is_array( $englishOptions ) ) {
			$this->registerTranslations( $englishOptions, self::LANGUAGE_OF_UNSUFFIXED_OPTION, false );
		}
	}

	private function registerTranslations( $options, $language, $shouldReplaceExistingTranslations ) {
		if ( ! class_exists( 'WPML_Admin_Texts' ) ) {
			return;
		}
		if ( ! function_exists( 'icl_add_string_translation' ) || ! function_exists( 'icl_register_string' ) ) {
			return;
		}
		if ( ! defined( 'ICL_TM_COMPLETE' ) ) {
			return;
		}

		$translatableNamesSetting = get_option( \WPML_Admin_Texts::TRANSLATABLE_NAMES_SETTING, [] );
		$translatableAvadaOptions = Obj::prop( self::OPTION_NAME, $translatableNamesSetting );
		if ( empty( $translatableAvadaOptions ) ) {
			return;
		}

		$defaultLanguage   = apply_filters( 'wpml_default_language', null );
		$stringsInDomain   = $this->pbStringTranslation->getStringsInContext( self::CONTEXT, [ 'id', 'name' ] );
		$registeredStrings = wp_list_pluck( $stringsInDomain, 'id', 'name' );

		$registerTranslations = function ( $optionsList, $translatableNames, $stringNamePrefix = '' ) use ( &$registerTranslations, $language, $defaultLanguage, $registeredStrings, $shouldReplaceExistingTranslations ) {
			foreach ( $optionsList as $optionKey => $optionValue ) {
				if ( ! isset( $translatableNames[ $optionKey ] ) ) {
					continue;
				}

				if ( is_array( $optionValue ) ) {
					$registerTranslations( $optionValue, $translatableNames[ $optionKey ], $stringNamePrefix . '[' . $optionKey . ']' );
					continue;
				}

				$optionStringName = $stringNamePrefix . $optionKey;
				$optionStringId   = Obj::prop( $optionStringName, $registeredStrings );
				if ( ! $optionStringId ) {
					$originalValue  = self::LANGUAGE_OF_UNSUFFIXED_OPTION === $language ? $optionValue : '';
					$optionStringId = icl_register_string( self::CONTEXT, $optionStringName, $originalValue, true, $defaultLanguage );
				} elseif ( ! $shouldReplaceExistingTranslations && ( '' === (string) $optionValue || $this->hasTranslation( $optionStringId, $language ) ) ) {
					continue;
				}

				icl_add_string_translation( $optionStringId, $language, $optionValue, ICL_TM_COMPLETE );
			}
		};

		remove_action( 'wpml_st_add_string_translation', [ $this, 'syncStringsToOptions' ] );
		$registerTranslations( $options, $translatableAvadaOptions, '[' . self::OPTION_NAME . ']' );
	}

	private function hasTranslation( $stringId, $language ) {
		return array_key_exists( $language, (array) icl_get_string_translations_by_id( $stringId ) );
	}
}
