<?php

namespace ACFML\Options;

use ACFML\Field\CompositeValue;
use ACFML\Helper\BoldNames;
use ACFML\Helper\Fields;
use ACFML\Helper\PhpFunctions;
use ACFML\Post\NativeEditorTranslationHooks;
use ACFML\Strings\Factory;
use ACFML\Strings\HooksFactory;
use ACFML\Strings\Package;
use ACFML\Tools\AdminUrl;
use WPML\Element\API\Languages;
use WPML\FP\Fns;
use WPML\FP\Obj;

class EditorHooks implements \IWPML_Backend_Action, \IWPML_DIC_Action {

	const NOTICE_PRIORITY = 9;
	const NOTICE_GROUP    = 'acfml';
	const NOTICE_ID       = 'acfml-editing-translated-options-notice';

	const PRIORITY_AFTER_ROW_SYNC = 20;

	private $sitepress;

	private $factory;

	private $acfWorker;

	private $valueCopy;

	private $valueRegistration;

	private $optionsPageId;

	private $storageLanguage;

	private $translationsQueue = [];

	private $copyQueue = [];

	public function __construct(
		\SitePress $sitepress,
		Factory $factory,
		\WPML_ACF_Worker $acfWorker,
		ValueCopy $valueCopy,
		ValueRegistration $valueRegistration,
		StorageLanguage $storageLanguage
	) {
		$this->sitepress         = $sitepress;
		$this->factory           = $factory;
		$this->acfWorker         = $acfWorker;
		$this->valueCopy         = $valueCopy;
		$this->valueRegistration = $valueRegistration;
		$this->storageLanguage   = $storageLanguage;
	}

	public function add_hooks() {
		add_action( 'admin_init', [ $this, 'setCurrentOptionsPage' ] );
		add_filter( 'acf/pre_render_fields', [ $this, 'preRenderOnTranslatedOptionsPage' ], 11, 2 );
		add_filter( 'acf/update_value', [ $this, 'onUpdateValue' ], 10, 3 );
		add_action( 'acf/save_post', [ $this, 'copyQueuedValuesToTranslations' ], self::PRIORITY_AFTER_ROW_SYNC );
		add_action( 'acf/options_page/save', [ $this, 'onUpdateOptionsPage' ], 10, 2 );
		add_action( 'admin_enqueue_scripts', [ $this, 'enqueueFieldLockAssets' ] );
		add_action( 'admin_notices', [ $this, 'editingTranslatedOptionsNotice' ], self::NOTICE_PRIORITY );
	}

	private function isMainLanguage() {
		return $this->sitepress->get_current_language() === $this->storageLanguage->get();
	}

	public function setCurrentOptionsPage() {
		global $plugin_page;
		$optionsPage = acf_get_options_page( $plugin_page );
		if ( ! (bool) $optionsPage ) {
			return;
		}

		if ( ! isset( $_GET['lang'] ) ) {
			$lang = apply_filters( 'wpml_current_language', null );
			$url  = add_query_arg( 'lang', $lang );

			wp_safe_redirect( $url );
			PhpFunctions::phpExit();
		}

		$this->optionsPageId = $optionsPage['post_id'];
	}

	public function preRenderOnTranslatedOptionsPage( $fields, $postId ) {
		if ( ! $this->optionsPageId ) {
			return $fields;
		}

		if ( $this->isMainLanguage() ) {
			return $fields;
		}

		NativeEditorTranslationHooks::loadFieldLockFilters();
		add_filter( 'acf/load_value', Fns::withNamedLock( self::class, Fns::identity(), function( $value, $fieldPostId, $field ) use ( $postId ) {
			if ( ! $this->optionsPageId ) {
				return $value;
			}
			if ( $postId !== $fieldPostId ) {
				return $value;
			}
			if ( $this->optionsPageId === $fieldPostId ) {
				return $value;
			}
			if ( Fields::isWrapperOrGroup( $field ) ) {
				return $value;
			}

			$currentLanguage = $this->sitepress->get_current_language();
			if ( WPML_COPY_CUSTOM_FIELD === Obj::prop( 'wpml_cf_preferences', $field ) ) {
				return $this->convertRelationshipField( $this->getFieldValue( $this->optionsPageId, $field ), $field, $currentLanguage );
			}

			if ( WPML_COPY_ONCE_CUSTOM_FIELD === Obj::prop( 'wpml_cf_preferences', $field ) ) {
				$storedValue = $this->getFieldValue( $fieldPostId, $field );
				if (
					false === $storedValue
					|| null === $storedValue
				) {
					$value = $this->convertRelationshipField( $this->getFieldValue( $this->optionsPageId, $field ), $field, $currentLanguage );
				}
				return $value;
			}

			return $value;
		} ), 10, 3 );
		return $fields;
	}

	public function onUpdateOptionsPage( $postId, $menuSlug ) {
		if ( $this->isMainLanguage() ) {
			$this->valueRegistration->registerAll( $postId );

			return;
		}

		$this->processTranslationsQueue();
	}

	public function copyQueuedValuesToTranslations() {
		$queue           = $this->copyQueue;
		$this->copyQueue = [];

		foreach ( $queue as $copy ) {
			$copy();
		}
	}

	public function onUpdateValue( $value, $postId, $field ) {
		if ( ! $this->optionsPageId ) {
			return $value;
		}

		if ( $this->isMainLanguage() ) {
			return $this->onUpdateMainValue( $value, $postId, $field );
		}
		return $this->onUpdateTranslationValue( $value, $postId, $field );
	}

	private function onUpdateMainValue( $value, $postId, $field ) {
		if ( Fields::isWrapperOrGroup( $field ) ) {
			$this->copyQueue[] = fn() => $this->maybeCopyWrapperToTranslations( $value, $field );
			return $value;
		}

		if ( WPML_COPY_CUSTOM_FIELD === Obj::prop( 'wpml_cf_preferences', $field ) ) {
			$this->copyQueue[] = fn() => $this->copyValueToTranslations( $value, $field );
			return $value;
		}

		if ( WPML_COPY_ONCE_CUSTOM_FIELD === Obj::prop( 'wpml_cf_preferences', $field ) ) {
			$this->copyQueue[] = fn() => $this->copyValueToTranslations( $value, $field, false );
			return $value;
		}

		if ( WPML_TRANSLATE_CUSTOM_FIELD === Obj::prop( 'wpml_cf_preferences', $field ) ) {
			$this->registerString( $value, $field );
		}

		return $value;
	}

	private function registerString( $value, $field ) {
		$package = $this->factory->createPackage( $this->optionsPageId, Package::OPTION_PACKAGE_KIND_SLUG );

		$compositeTexts = CompositeValue::getTexts( $field, $value );

		if ( $compositeTexts ) {
			foreach ( $compositeTexts as $subKey => $text ) {
				$compositeField = CompositeValue::asStringField( $field, Obj::prop( 'name', $field ), $subKey );
				$package->register( $text, FieldStringData::of( $compositeField, $text ) );
			}
			return;
		}

		if ( is_scalar( $value ) ) {
			$package->register( (string) $value, FieldStringData::of( $field, $value ) );
		}
	}

	private function maybeCopyWrapperToTranslations( $value, $field ) {
		if ( WPML_COPY_CUSTOM_FIELD !== (int) Obj::prop( 'wpml_cf_preferences', $field ) ) {
			return;
		}

		$this->valueCopy->fromSave(
			$this->optionsPageId,
			$this->sitepress->get_current_language(),
			$field,
			Obj::prop( 'name', $field ),
			$value,
			true,
			false
		);
	}

	private function copyValueToTranslations( $value, $field, $overrideExisting = true ) {
		$this->valueCopy->fromSave(
			$this->optionsPageId,
			$this->sitepress->get_current_language(),
			$field,
			Obj::prop( 'name', $field ),
			$value,
			$overrideExisting
		);
	}

	private function onUpdateTranslationValue( $value, $postId, $field ) {
		if ( $this->optionsPageId === $postId ) {
			return $value;
		}
		if ( Fields::isWrapperOrGroup( $field ) ) {
			return $value;
		}

		if ( WPML_COPY_CUSTOM_FIELD === Obj::prop( 'wpml_cf_preferences', $field ) ) {
			return $this->convertRelationshipField( $this->getFieldValue( $this->optionsPageId, $field ), $field, $this->sitepress->get_current_language() );
		}

		if ( WPML_TRANSLATE_CUSTOM_FIELD === Obj::prop( 'wpml_cf_preferences', $field ) ) {
			$compositeTexts = CompositeValue::getTexts( $field, $value );

			if ( $compositeTexts ) {
				$this->registerCompositeTranslations( $compositeTexts, $field, $this->sitepress->get_current_language() );
			} elseif ( is_scalar( $value ) ) {
				$this->registerFieldTranslation( (string) $value, $field, $this->sitepress->get_current_language() );
			}
		}

		return $value;
	}

	private function registerFieldTranslation( $value, $field, $language ) {
		if ( ! HooksFactory::isStActivated() ) {
			return;
		}
		$originalValue = $this->getFieldValue( $this->optionsPageId, $field );

		if ( null === $originalValue || false === $originalValue || ! is_scalar( $originalValue ) ) {
			return;
		}
		$stringName = Package::getStringName( $originalValue, FieldStringData::of( $field, $originalValue ) );
		$this->addToTranslationsQueue( $stringName, $language, $value );
	}

	private function registerCompositeTranslations( array $texts, $field, $language ) {
		if ( ! HooksFactory::isStActivated() ) {
			return;
		}

		$originalTexts = CompositeValue::getTexts( $field, $this->getFieldValue( $this->optionsPageId, $field ) );

		foreach ( $texts as $subKey => $text ) {
			if ( ! isset( $originalTexts[ $subKey ] ) ) {
				continue;
			}

			$compositeField = CompositeValue::asStringField( $field, Obj::prop( 'name', $field ), $subKey );
			$stringName     = Package::getStringName( $originalTexts[ $subKey ], FieldStringData::of( $compositeField, $originalTexts[ $subKey ] ) );
			$this->addToTranslationsQueue( $stringName, $language, $text );
		}
	}

	private function addToTranslationsQueue( $stringName, $language, $value ) {
		if ( ! array_key_exists( $stringName, $this->translationsQueue ) ) {
			$this->translationsQueue[ $stringName ] = [];
		}
		$this->translationsQueue[ $stringName ][ $language ] = [
			'value'  => $value,
			'status' => ICL_STRING_TRANSLATION_COMPLETE,
		];
	}

	private function processTranslationsQueue() {
		if ( empty( $this->translationsQueue ) ) {
			return;
		}
		$package = $this->factory->createPackage( $this->optionsPageId, Package::OPTION_PACKAGE_KIND_SLUG );
		$package->setStringTranslations( $this->translationsQueue );
		$package->flushCache();
	}

	private function convertRelationshipField( $value, $field, $language ) {
		return $this->acfWorker->convertMetaValue( $value, $field['name'], $field['type'], 'post', $this->optionsPageId, $this->optionsPageId . '_' . $language, $language );
	}

	public function enqueueFieldLockAssets() {
		if ( ! $this->optionsPageId ) {
			return;
		}

		if ( $this->isMainLanguage() ) {
			return;
		}

		NativeEditorTranslationHooks::enqueueAssets();
	}

	private function getFieldValue( $postId, $field ) {
		if ( function_exists( 'acf_get_metadata_by_field' ) ) {
			return acf_get_metadata_by_field( $postId, $field );
		}
		return acf_get_value( $postId, $field );
	}

	public function editingTranslatedOptionsNotice() {
		if ( ! $this->optionsPageId ) {
			return;
		}
		if ( ! function_exists( 'wpml_get_admin_notices' ) ) {
			return;
		}

		$screen = get_current_screen();
		if ( ! $screen ) {
			return;
		}

		$noticeId = md5( self::NOTICE_ID );
		$notices  = wpml_get_admin_notices();
		$notices->remove_notice( self::NOTICE_GROUP, $noticeId );

		if ( $this->isMainLanguage() ) {
			return;
		}

		$tmDashboardUrl = AdminUrl::getWPMLTMDashboardPackageSection( Package::OPTION_PACKAGE_KIND_SLUG );
		$tmDashboardUrl = add_query_arg(
			[
				'lang'      => $this->storageLanguage->get(),
				'admin_bar' => 1,
			],
			$tmDashboardUrl
		);

		/* translators: Heading of the admin notice on an ACF options page. Keep the bold tags around the WPML screen name; "Options page" is ACF's own name for that screen. Verb, imperative. */
		$text  = '<h2>' . BoldNames::render( __( 'Translate this Options page from the <b>Translation Dashboard</b>', 'acfml' ) ) . '</h2>';
		$text .= '<p>' . sprintf(
			/* translators: Text in the notice on an ACF options page in a secondary language. %1$s: opening link tag, %2$s: closing link tag; the link opens WPML's Translation Dashboard. */
			esc_html__( 'You no longer need to switch the admin language to translate options manually. Translate all your options from the %1$sTranslation Dashboard%2$s.', 'acfml' ),
			/* translators: Tooltip of the link in that notice; it opens WPML's Translation Dashboard. */
			'<a href="' . esc_url( $tmDashboardUrl ) . '" title="' . esc_attr__( 'Go to the Translation Dashboard', 'acfml' ) . '">',
			'</a>'
		) . '</p>';
		$text .= '<p>' . sprintf(
			/* translators: Text in the notice on an ACF options page in a secondary language. %1$s: opening link tag, %2$s: closing link tag; the link opens WPML's Translation Jobs list. "Edit Translation" is the tooltip of the pencil icon in that list: use the same words as WPML's own translation of that tooltip. */
			esc_html__( 'To change a finished translation, go to %1$sTranslation Jobs%2$s and click on the Edit Translation icon.', 'acfml' ),
			/* translators: Tooltip of the link in that notice; it opens WPML's Translation Jobs list. */
			'<a href="' . esc_url( AdminUrl::getTranslationJobs( Package::OPTION_PACKAGE_ELEMENT_TYPE ) ) . '" title="' . esc_attr__( 'Go to Translation Jobs', 'acfml' ) . '">',
			'</a>'
		) . '</p>';

		$notice = $notices->create_notice( $noticeId, $text, self::NOTICE_GROUP );
		$notice->set_css_class_types( 'info' );
		$notice->set_dismissible( true );
		$notice->set_restrict_to_screen_ids( [ $screen->id ] );
		$notices->add_notice( $notice );
	}

}
