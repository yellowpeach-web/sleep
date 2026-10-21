<?php

namespace WPML\WPSEO\YoastSEO\Terms\Meta;

use SitePress;
use WPML\WPSEO\YoastSEO\Utils;
use WPSEO_Taxonomy_Meta;
use WPML\LIB\WP\Hooks as WPHooks;
use WPML\FP\Fns;
use WPML\Utils\DebugBackTrace;

use function WPML\FP\spreadArgs;

class Hooks implements \IWPML_Frontend_Action, \IWPML_Backend_Action, \IWPML_DIC_Action {

	const PACKAGE = [
		'kind'      => 'Yoast SEO',
		'kind_slug' => 'yoast-seo',
		'name'      => 'term-meta',
		'title'     => 'Term Meta',
	];

	const FIELDS = [
		Utils::KEY_META_TITLE       => 'SEO Title',
		Utils::KEY_META_DESC        => 'Meta Description',
		Utils::KEY_BREADCRUMB_TITLE => 'Breadcrumb Title',
		Utils::KEY_FOCUS_KEYWORD    => 'Focus Keyword',
		Utils::KEY_SOCIAL_TITLE     => 'Social Title',
		Utils::KEY_SOCIAL_DESC      => 'Social Description',
		Utils::KEY_X_TITLE          => 'X Title',
		Utils::KEY_X_DESC           => 'X Description',
	];

	const TERM_BUILDER_TRACE_LIMIT = 50;

	const FIELDS_SERVED_ONLY_WHEN_TRANSLATED = [
		Utils::KEY_SOCIAL_TITLE,
		Utils::KEY_SOCIAL_DESC,
		Utils::KEY_X_TITLE,
		Utils::KEY_X_DESC,
	];

	private $wpSeoOptionName;

	private $sitepress;

	private $hasCachedTermMetaValue = false;

	private $cachedTermMetaValue;

	private $pinTermIdsCallback;

	private static $forceTranslateStrings = false;

	private static $translateOnAdminScreen = false;

	private static $shouldReadStoredValues = false;

	private $stringLanguage;

	private $debugBackTraceFunction;

	public function __construct( SitePress $sitepress, StringLanguage $stringLanguage, $debugBackTraceFunction = null ) {
		$this->sitepress              = $sitepress;
		$this->stringLanguage         = $stringLanguage;
		$this->debugBackTraceFunction = $debugBackTraceFunction;
	}

	public static function withForcedTranslation( callable $callback ) {
		self::$forceTranslateStrings = true;
		try {
			return $callback();
		} finally {
			self::$forceTranslateStrings = false;
		}
	}

	public static function withStoredValues( callable $callback ) {
		$wasReadingStoredValues       = self::$shouldReadStoredValues;
		self::$shouldReadStoredValues = true;
		try {
			return $callback();
		} finally {
			self::$shouldReadStoredValues = $wasReadingStoredValues;
		}
	}

	public static function enableAdminScreenTranslation() {
		self::$translateOnAdminScreen = true;
	}

	public function add_hooks() {
		WPHooks::onFilter( 'wpml_active_string_package_kinds' )
			->then( spreadArgs( [ $this, 'addPackageKind' ] ) );

		WPHooks::onFilter( 'option_' . $this->getWpSeoOptionName() )
			->then( spreadArgs( [ $this, 'maybeTranslateStrings' ] ) );

		WPHooks::onFilter( 'pre_update_option_' . $this->getWpSeoOptionName(), 10, 2 )
			->then( spreadArgs( [ $this, 'removeServedValues' ] ) );

		WPHooks::onAction( 'add_option_' . $this->getWpSeoOptionName() )
			->then( [ $this, 'forgetServedValue' ] );
		WPHooks::onAction( 'update_option_' . $this->getWpSeoOptionName() )
			->then( [ $this, 'forgetServedValue' ] );

		if ( is_admin() ) {
			WPHooks::onAction( 'add_option', 10, 2 )
				->then( spreadArgs( [ $this, 'registerStrings' ] ) );
			WPHooks::onAction( 'update_option', 10, 3 )
				->then( spreadArgs( [ $this, 'registerUpdatedStrings' ] ) );
			if ( defined( 'WPML_ST_VERSION' ) ) {
				WPHooks::onAction( 'edited_term', 10, 3 )
					->then( spreadArgs( [ $this, 'setOriginalTermStringsLanguage' ] ) );
			}
		}

		if ( defined( 'WP_CLI' ) && WP_CLI ) {
			\WP_CLI::add_hook( 'before_invoke:yoast', [ $this, 'pinTermIds' ] );
		}

		add_action( 'wpseo_indexable_index_batch', [ $this, 'pinTermIds' ], 0 );
		add_action( 'wpseo_indexable_index_batch', [ $this, 'unpinTermIds' ], PHP_INT_MAX );
	}

	public function pinTermIds() {
		add_filter( 'wpml_disable_term_adjust_id', $this->getPinTermIdsCallback() );
	}

	public function unpinTermIds() {
		remove_filter( 'wpml_disable_term_adjust_id', $this->getPinTermIdsCallback() );
	}

	private function getPinTermIdsCallback() {
		if ( null === $this->pinTermIdsCallback ) {
			$this->pinTermIdsCallback = Fns::always( true );
		}

		return $this->pinTermIdsCallback;
	}

	public static function withoutTermAdjustId( callable $callback ) {
		$disable = Fns::always( true );
		add_filter( 'wpml_disable_term_adjust_id', $disable );
		try {
			return $callback();
		} finally {
			remove_filter( 'wpml_disable_term_adjust_id', $disable );
		}
	}

	public function maybeTranslateStrings( $value ) {
		if ( self::$shouldReadStoredValues ) {
			return $value;
		}

		if ( self::$forceTranslateStrings ) {
			return $this->translateStrings( $value );
		}

		if ( $this->isTranslatingInContext() ) {
			if ( ! $this->hasCachedTermMetaValue ) {
				$this->cachedTermMetaValue    = $this->translateStrings( $value );
				$this->hasCachedTermMetaValue = true;
			}

			return $this->cachedTermMetaValue;
		}

		return $value;
	}

	private function isServingTranslatedValue() {
		return self::$forceTranslateStrings || $this->isTranslatingInContext();
	}

	private function isTranslatingInContext() {
		$shouldTranslateInContext = ! is_admin() || self::$translateOnAdminScreen || $this->isYoastBuildingTermIndexable();

		return $shouldTranslateInContext && ! Utils::isSitemapRequest();
	}

	public function removeServedValues( $writtenValue, $servedValue ) {
		if ( ! is_array( $writtenValue ) || ! is_array( $servedValue ) || ! $this->isServingTranslatedValue() ) {
			return $writtenValue;
		}

		$storedValue = $this->getStoredValue();
		if ( ! is_array( $storedValue ) ) {
			return $writtenValue;
		}

		foreach ( $servedValue as $taxonomy => $terms ) {
			foreach ( (array) $terms as $termId => $servedTermMeta ) {
				$writtenValue = $this->removeServedTermValues( $writtenValue, $storedValue, $taxonomy, $termId, (array) $servedTermMeta );
			}
		}

		return $writtenValue;
	}

	private function removeServedTermValues( $writtenValue, $storedValue, $taxonomy, $termId, $servedTermMeta ) {
		$storedTermMeta = $storedValue[ $taxonomy ][ $termId ] ?? null;

		foreach ( array_keys( self::FIELDS ) as $field ) {
			$servedFieldValue       = $servedTermMeta[ $field ] ?? null;
			$isAddedOnRead          = null !== $servedFieldValue && $servedFieldValue !== ( $storedTermMeta[ $field ] ?? null );
			$isWrittenBackUnchanged = $isAddedOnRead && ( $writtenValue[ $taxonomy ][ $termId ][ $field ] ?? null ) === $servedFieldValue;

			if ( ! $isWrittenBackUnchanged ) {
				continue;
			}

			if ( is_array( $storedTermMeta ) && array_key_exists( $field, $storedTermMeta ) ) {
				$writtenValue[ $taxonomy ][ $termId ][ $field ] = $storedTermMeta[ $field ];
			} else {
				unset( $writtenValue[ $taxonomy ][ $termId ][ $field ] );
			}
		}

		if ( null === $storedTermMeta && isset( $writtenValue[ $taxonomy ][ $termId ] ) && [] === $writtenValue[ $taxonomy ][ $termId ] ) {
			unset( $writtenValue[ $taxonomy ][ $termId ] );
		}

		return $writtenValue;
	}

	private function getStoredValue() {
		return self::withStoredValues(
			function () {
				return get_option( $this->getWpSeoOptionName() );
			}
		);
	}

	public function forgetServedValue() {
		$this->hasCachedTermMetaValue = false;
		$this->cachedTermMetaValue    = null;
	}

	private function isYoastBuildingTermIndexable() {
		return $this->isYoastTermBuilderOnTheStack();
	}

	private function isYoastTermBuilderOnTheStack() {
		return ( new DebugBackTrace( self::TERM_BUILDER_TRACE_LIMIT, false, true, $this->debugBackTraceFunction ) )
			->is_class_function_in_call_stack( \Yoast\WP\SEO\Builders\Indexable_Term_Builder::class, 'build' );
	}

	public function addPackageKind( $kinds ) {
		$kinds[ self::PACKAGE['kind_slug'] ] = [
			'title'  => self::PACKAGE['kind'],
			'slug'   => self::PACKAGE['kind_slug'],
			'plural' => self::PACKAGE['kind'],
		];

		return $kinds;
	}

	private function getPackage() {
		return [
			'kind'      => self::PACKAGE['kind'],
			'kind_slug' => self::PACKAGE['kind_slug'],
			'name'      => self::PACKAGE['name'],
			'title'     => self::PACKAGE['title'],
		];
	}

	public function registerStrings( $option, $value, $oldValue = [] ) {
		if ( $option !== $this->getWpSeoOptionName() || ! is_array( $value ) ) {
			return;
		}

		$package              = $this->getPackage();
		$languageByStringName = [];

		do_action( 'wpml_start_string_package_registration', $package );

		foreach ( $value as $taxonomy => $terms ) {
			if ( ! is_array( $terms ) ) {
				continue;
			}

			if ( ! $this->sitepress->is_translated_taxonomy( $taxonomy ) ) {
				continue;
			}

			foreach ( $terms as $termId => $termMeta ) {
				$languageByStringName += $this->registerTermStrings( $package, $taxonomy, (int) $termId, $termMeta, $oldValue[ $taxonomy ][ $termId ] ?? null );
			}
		}

		do_action( 'wpml_delete_unused_package_strings', $package );

		$this->stringLanguage->apply( $languageByStringName );
	}

	private function registerTermStrings( $package, $taxonomy, $termId, $termMeta, $oldTermMeta ) {
		if ( ! is_array( $termMeta ) ) {
			return [];
		}

		$term = $this->getUnadjustedTerm( $termId, $taxonomy );
		if ( ! $term || ! $this->sitepress->is_original_content_filter( false, (int) $term->term_taxonomy_id, 'tax_' . $taxonomy ) ) {
			return [];
		}

		foreach ( self::FIELDS as $field => $fieldTitle ) {
			if ( ! empty( $termMeta[ $field ] ) ) {
				do_action(
					'wpml_register_string',
					$termMeta[ $field ],
					self::buildStringName( $taxonomy, $termId, $field ),
					$package,
					$fieldTitle . ': ' . $term->name,
					'LINE'
				);
			}
		}

		if ( $oldTermMeta === $termMeta ) {
			return [];
		}

		$termLanguage = $this->stringLanguage->getTermLanguage( $term->term_taxonomy_id, $taxonomy );

		return $termLanguage ? $this->getLanguageByStringName( $taxonomy, $termId, $termMeta, $termLanguage ) : [];
	}

	public function registerUpdatedStrings( $option, $oldValue, $value ) {
		if ( $oldValue !== $value ) {
			$this->registerStrings( $option, $value, $oldValue );
		}
	}

	public function setOriginalTermStringsLanguage( $termId, $termTaxonomyId, $taxonomy ) {
		if ( ! $this->sitepress->is_translated_taxonomy( $taxonomy ) ) {
			return;
		}

		$source = $this->getOriginalTranslation( (int) $termTaxonomyId, $taxonomy );
		if ( ! $source || empty( $source->language_code ) ) {
			return;
		}

		$sourceTermId = $this->getTermIdOfTranslation( $source, $taxonomy );
		if ( ! $sourceTermId ) {
			return;
		}

		$option = get_option( $this->getWpSeoOptionName() );

		$this->stringLanguage->apply(
			$this->getLanguageByStringName( $taxonomy, $sourceTermId, $option[ $taxonomy ][ $sourceTermId ] ?? [], $source->language_code )
		);
	}

	private function getOriginalTranslation( $termTaxonomyId, $taxonomy ) {
		$trid = $this->sitepress->get_element_trid( $termTaxonomyId, 'tax_' . $taxonomy );
		if ( ! $trid ) {
			return null;
		}

		foreach ( (array) $this->sitepress->get_element_translations( $trid, 'tax_' . $taxonomy ) as $translation ) {
			if ( ! empty( $translation->original ) ) {
				return $translation;
			}
		}

		return null;
	}

	private function getLanguageByStringName( $taxonomy, $termId, $termMeta, $language ) {
		$languageByStringName = [];
		foreach ( array_keys( self::FIELDS ) as $field ) {
			if ( ! empty( $termMeta[ $field ] ) ) {
				$languageByStringName[ self::buildStringName( $taxonomy, $termId, $field ) ] = $language;
			}
		}

		return $languageByStringName;
	}

	private function getWpSeoOptionName() {
		if ( null === $this->wpSeoOptionName ) {
			$this->wpSeoOptionName = WPSEO_Taxonomy_Meta::get_instance()->option_name;
		}

		return $this->wpSeoOptionName;
	}

	public function translateStrings( $value ) {
		if ( ! is_array( $value ) ) {
			return $value;
		}

		$package = $this->getPackage();

		foreach ( $value as $taxonomy => $terms ) {
			if ( ! is_array( $terms ) ) {
				continue;
			}

			if ( ! $this->sitepress->is_translated_taxonomy( $taxonomy ) ) {
				continue;
			}

			foreach ( $terms as $termId => $termMeta ) {
				if ( ! is_array( $termMeta ) ) {
					continue;
				}

				$term = $this->getUnadjustedTerm( $termId, $taxonomy );
				if ( ! $term ) {
					continue;
				}

				$ttId = (int) $term->term_taxonomy_id;

				if ( ! $this->sitepress->is_original_content_filter( false, $ttId, 'tax_' . $taxonomy ) ) {
					continue;
				}

				$trid         = $this->sitepress->get_element_trid( $ttId, 'tax_' . $taxonomy );
				$translations = $this->sitepress->get_element_translations( $trid, 'tax_' . $taxonomy );
				foreach ( $translations as $translation ) {
					if ( $ttId !== (int) $translation->element_id ) {
						$targetTermId = $this->getTermIdOfTranslation( $translation, $taxonomy );
						if ( ! $targetTermId ) {
							continue;
						}

						$this->sitepress->switch_lang( $translation->language_code );
						try {
							foreach ( self::FIELDS as $field => $fieldTitle ) {
								if ( ! empty( $value[ $taxonomy ][ $targetTermId ][ $field ] ) ) {
									continue;
								}

								if ( ! empty( $termMeta[ $field ] ) ) {
									$stringName = self::buildStringName( $taxonomy, $termId, $field );

									if ( self::isServedOnlyWhenTranslated( $field ) ) {
										$translatedValue = $this->getServedTranslation( $stringName, $termMeta[ $field ], $translation->language_code );
										if ( null !== $translatedValue ) {
											$value[ $taxonomy ][ $targetTermId ][ $field ] = $translatedValue;
										}
										continue;
									}

									$translatedValue = self::$forceTranslateStrings
										? $this->getStoredStringTranslation( $stringName, $translation->language_code )
										: null;

									if ( null === $translatedValue ) {
										$translatedValue = apply_filters(
											'wpml_translate_string',
											$termMeta[ $field ],
											$stringName,
											$package
										);
									}

									if ( $translatedValue ) {
										$value[ $taxonomy ][ $targetTermId ][ $field ] = $translatedValue;
									}
								}
							}
						} finally {
							$this->sitepress->switch_lang();
						}
					}
				}
			}
		}

		return $value;
	}

	private function getUnadjustedTerm( $termId, $taxonomy ) {
		$term = self::withoutTermAdjustId(
			function () use ( $termId, $taxonomy ) {
				return get_term( (int) $termId, $taxonomy );
			}
		);

		return ( $term instanceof \WP_Term ) ? $term : null;
	}

	private function getTermIdOfTranslation( $translation, $taxonomy ) {
		if ( isset( $translation->term_id ) ) {
			return (int) $translation->term_id;
		}

		$term = self::withoutTermAdjustId(
			function () use ( $translation, $taxonomy ) {
				return get_term_by( 'term_taxonomy_id', (int) $translation->element_id, $taxonomy );
			}
		);

		return ( $term instanceof \WP_Term ) ? (int) $term->term_id : 0;
	}

	private function getStoredStringTranslation( $stringName, $languageCode ) {
		global $wpdb;

		if ( ! is_object( $wpdb ) || ! defined( 'ICL_TM_COMPLETE' ) ) {
			return null;
		}

		$value = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT st.value
					FROM {$wpdb->prefix}icl_strings s
					INNER JOIN {$wpdb->prefix}icl_string_translations st ON st.string_id = s.id
					WHERE s.name = %s
						AND s.context = %s
						AND st.language = %s
						AND st.status = %d
						AND st.value <> ''",
				$stringName,
				self::stringContext(),
				$languageCode,
				ICL_TM_COMPLETE
			)
		);

		return is_string( $value ) && '' !== $value ? $value : null;
	}

	public static function buildStringName( $taxonomy, $termId, $field ) {
		return $taxonomy . '-' . $termId . '-' . $field;
	}

	public static function stringContext() {
		return self::PACKAGE['kind_slug'] . '-' . self::PACKAGE['name'];
	}

	public function getServedValues( $taxonomy, $sourceTermId, $field, $languageCode ) {
		$option      = get_option( $this->getWpSeoOptionName() );
		$sourceValue = $option[ $taxonomy ][ $sourceTermId ][ $field ] ?? '';

		if ( '' === $sourceValue || ! is_string( $sourceValue ) ) {
			return [];
		}

		$stringName = self::buildStringName( $taxonomy, $sourceTermId, $field );

		if ( self::isServedOnlyWhenTranslated( $field ) ) {
			return $this->getTranslations( $stringName, $sourceValue, $languageCode );
		}

		$this->sitepress->switch_lang( $languageCode );
		try {
			$dictionaryValue = apply_filters( 'wpml_translate_string', $sourceValue, $stringName, $this->getPackage() );
		} finally {
			$this->sitepress->switch_lang();
		}

		$values      = [];
		$storedValue = $this->getStoredStringTranslation( $stringName, $languageCode );
		if ( null !== $storedValue ) {
			$values[] = $storedValue;
		}
		$values[] = is_string( $dictionaryValue ) && '' !== $dictionaryValue ? $dictionaryValue : $sourceValue;

		return array_values( array_unique( $values ) );
	}

	public static function isServedOnlyWhenTranslated( $field ) {
		return in_array( $field, self::FIELDS_SERVED_ONLY_WHEN_TRANSLATED, true );
	}

	private function getServedTranslation( $stringName, $sourceValue, $languageCode ) {
		if ( self::$forceTranslateStrings ) {
			return $this->getStoredStringTranslation( $stringName, $languageCode );
		}

		return $this->getDictionaryTranslation( $stringName, $sourceValue, $languageCode );
	}

	private function getTranslations( $stringName, $sourceValue, $languageCode ) {
		$translations = array_filter(
			[
				$this->getStoredStringTranslation( $stringName, $languageCode ),
				$this->getDictionaryTranslation( $stringName, $sourceValue, $languageCode ),
			],
			'is_string'
		);

		return array_values( array_unique( $translations ) );
	}

	private function getDictionaryTranslation( $stringName, $sourceValue, $languageCode ) {
		if ( ! function_exists( 'icl_translate' ) ) {
			return null;
		}

		$hasTranslation  = false;
		$translatedValue = icl_translate( self::stringContext(), $stringName, $sourceValue, false, $hasTranslation, $languageCode );

		return $hasTranslation && is_string( $translatedValue ) && '' !== $translatedValue ? $translatedValue : null;
	}
}
