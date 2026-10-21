<?php

namespace WPML\TM\Dashboard\Taxonomy;

use WPML\Element\API\Languages;

class TaxonomyLabelStatus {

	const SUBSTRINGS = [ 'general', 'singular', 'slug' ];

	private $labelsTranslation = null;

	private $labelJobDispatcher = false;

	public function isStringTranslationActive(): bool {
		return defined( 'WPML_ST_VERSION' )
			&& class_exists( '\\WPML_ST_Taxonomy_Labels_Translation' );
	}

	public function getRow( string $taxonomy, bool $isTeaActive = false ) {
		if ( ! $this->isStringTranslationActive() ) {
			return null;
		}

		$data = $this->getLabelTranslationData( $taxonomy );

		$sourceLang = $this->getSourceLang( $data );
		$original   = is_array( $data ) && isset( $data[ $sourceLang ] ) ? $data[ $sourceLang ] : [];

		$displayLang = apply_filters( 'wpml_current_language', null );
		$display     = ( $displayLang && is_array( $data ) && isset( $data[ $displayLang ] ) )
			? $data[ $displayLang ]
			: $original;

		$singular = (string) ( $display['singular'] ?? '' ) ?: (string) ( $original['singular'] ?? '' );
		$plural   = (string) ( $display['general'] ?? '' ) ?: (string) ( $original['general'] ?? '' );
		$slug     = (string) ( $display['slug'] ?? '' ) ?: (string) ( $original['slug'] ?? '' );

		$labelsRegistered = is_array( $data )
			&& ( '' !== (string) ( $original['singular'] ?? '' ) || '' !== (string) ( $original['general'] ?? '' ) );

		$languages     = $this->getLanguageStates( $data, $sourceLang, $isTeaActive, $taxonomy );
		$wordCounts    = [];
		$wordCountsAll = [];
		foreach ( $languages as $state ) {
			$wordCounts[ $state['code'] ]    = $state['words'];
			$wordCountsAll[ $state['code'] ] = $state['wordsAll'] ?? $state['words'];
		}

		return [
			'taxonomy'             => $taxonomy,
			'singular'             => $singular,
			'plural'               => $plural,
			'slug'                 => $slug,
			'languages'            => $languages,
			'wordCounts'           => $wordCounts,
			'wordCountsAll'        => $wordCountsAll,
			'labelsRegistered'     => $labelsRegistered,
			'stringTranslationUrl' => $this->getStringTranslationUrl( $plural ?: $taxonomy ),
		];
	}

	public function rescan( string $taxonomy ): array {
		$this->registerLabelStrings( $taxonomy );

		$row = $this->getRow( $taxonomy );

		if ( is_array( $row ) ) {
			return [
				'labelsRegistered'     => (bool) $row['labelsRegistered'],
				'stringTranslationUrl' => (string) $row['stringTranslationUrl'],
			];
		}

		return [
			'labelsRegistered'     => false,
			'stringTranslationUrl' => $this->getStringTranslationUrl( $taxonomy ),
		];
	}

	public function dispatchLabels( array $taxonomies, array $languages, bool $overwrite = false ): int {
		$taxonomies = array_values( array_filter( array_map( 'strval', $taxonomies ) ) );

		if ( empty( $taxonomies ) || ! $this->isStringTranslationActive() ) {
			return 0;
		}

		$sourceLang = Languages::getDefaultCode();
		$languages  = array_values(
			array_filter(
				array_map( 'strval', $languages ),
				static function ( $lang ) use ( $sourceLang ) {
					return '' !== $lang && $lang !== $sourceLang;
				}
			)
		);

		if ( empty( $languages ) ) {
			$languages = array_values(
				array_filter(
					$this->getSecondaryLanguages(),
					static function ( $lang ) use ( $sourceLang ) {
						return $lang !== $sourceLang;
					}
				)
			);
		}

		if ( empty( $languages ) || '' === $sourceLang ) {
			return 0;
		}

		$actionsClass = '\\WPML\\TM\\AutomaticTranslation\\Actions\\Actions';

		if ( ! class_exists( $actionsClass ) ) {
			return 0;
		}

		try {
			$dispatcher = $this->buildLabelJobDispatcher();
			if ( ! $dispatcher ) {
				return 0;
			}

			$stringIds = $dispatcher->collectLabelStringIds( $taxonomies );
			if ( empty( $stringIds ) ) {
				return 0;
			}

			$dispatcher->applyCopyEncodedSlugs( $taxonomies, $languages, $sourceLang );
			$dispatcher->enablePerTaxonomySlugSetting( $taxonomies );

			$elements = $dispatcher->getUntranslatedElements( $stringIds, $languages, $this->labelQueueSize(), $overwrite );
			if ( empty( $elements ) ) {
				return 0;
			}

			$actions = \WPML\Container\make( $actionsClass );
			$jobs    = $dispatcher->createJobs( $actions, $elements, $sourceLang );

			return is_array( $jobs ) ? count( $jobs ) : 0;
		} catch ( \Throwable $e ) {
			if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
				error_log( '[wpml taxonomies] label dispatch failed: ' . $e->getMessage() );
			}

			return 0;
		}
	}

	public function resolveLabelStringIds( array $taxonomies ): array {
		$taxonomies = array_values( array_unique( array_filter( array_map( 'sanitize_key', $taxonomies ) ) ) );
		if ( ! $taxonomies || ! $this->isStringTranslationActive() ) {
			return [];
		}

		try {
			$dispatcher = $this->buildLabelJobDispatcher();
			if ( ! $dispatcher || ! method_exists( $dispatcher, 'collectLabelStringIds' ) ) {
				return [];
			}

			$ids = $dispatcher->collectLabelStringIds( $taxonomies );

			return array_values(
				array_unique(
					array_filter( array_map( 'intval', is_array( $ids ) ? $ids : [] ) )
				)
			);
		} catch ( \Throwable $e ) {
			return [];
		}
	}

	private function labelQueueSize(): int {
		return 150;
	}

	private function registerLabelStrings( string $taxonomy ) {
		$factoryClass = '\\WPML\\StringTranslation\\Infrastructure\\TranslateEverything\\UntranslatedTaxonomyLabelStringsFactory';

		if ( ! class_exists( $factoryClass ) ) {
			return;
		}

		try {
			$factory    = new $factoryClass();
			$reflection = new \ReflectionMethod( $factoryClass, 'create' );
			$instance   = $reflection->invoke( $factory );

			$dispatcherProp = new \ReflectionProperty( $instance, 'dispatcher' );
			if ( PHP_VERSION_ID < 80100 ) {
				$dispatcherProp->setAccessible( true );
			}
			$dispatcher = $dispatcherProp->getValue( $instance );

			if ( $dispatcher && method_exists( $dispatcher, 'collectLabelStringIds' ) ) {
				$dispatcher->collectLabelStringIds( [ $taxonomy ] );
			}
		} catch ( \Throwable $e ) {
			return;
		}
	}

	private function getLanguageStates( $data, string $sourceLang, bool $isTeaActive = false, string $taxonomy = '' ): array {
		$states = [];

		if ( ! is_array( $data ) ) {
			foreach ( $this->getSecondaryLanguages() as $lang ) {
				$states[] = [
					'code'         => $lang,
					'status'       => $isTeaActive ? 'preparing' : 'missing',
					'untranslated' => 0,
					'words'        => 0,
				];
			}

			return $states;
		}

		$original = $data[ $sourceLang ] ?? [];
		$hasSlug  = isset( $original['slug'] ) && '' !== (string) $original['slug'];
		$requireSlug = $hasSlug && $this->isSlugTranslatable( $taxonomy );
		$expected    = $requireSlug ? self::SUBSTRINGS : [ 'general', 'singular' ];
		$totalParts  = count( $expected );

		$wpmlDefault = Languages::getDefaultCode();

		foreach ( $this->getSecondaryLanguages() as $lang ) {
			if ( $lang === $wpmlDefault ) {
				continue;
			}

			$langData   = isset( $data[ $lang ] ) && is_array( $data[ $lang ] ) ? $data[ $lang ] : [];
			$translated = 0;

			foreach ( $expected as $part ) {
				if ( ! empty( $langData[ $part ] ) ) {
					++$translated;
				}
			}

			$untranslated = max( $totalParts - $translated, 0 );
			$inProgress   = ! empty( $langData['inProgress'] );

			if ( 0 === $totalParts || $translated === $totalParts ) {
				$status = 'complete';
			} elseif ( $inProgress ) {
				$status = 'in_progress';
			} elseif ( $isTeaActive ) {
				$status = 'preparing';
			} elseif ( 0 === $translated ) {
				$status = 'missing';
			} else {
				$status = 'partial';
			}

			$allWords = $this->wordCount( $original );
			$states[] = [
				'code'         => $lang,
				'status'       => $status,
				'untranslated' => $untranslated,
				'inProgress'   => $inProgress,
				'words'        => $untranslated > 0 ? $allWords : 0,
				'wordsAll'     => $allWords,
			];
		}

		return $states;
	}

	private function wordCount( array $original ): int {
		$text = trim(
			wp_strip_all_tags(
				(string) ( $original['singular'] ?? '' ) . ' ' . (string) ( $original['general'] ?? '' )
			)
		);

		if ( '' === $text ) {
			return 0;
		}

		return count( preg_split( '/\s+/u', $text ) ?: [] );
	}

	private function getSourceLang( $data ): string {
		if ( is_array( $data ) && ! empty( $data['st_default_lang'] ) ) {
			return (string) $data['st_default_lang'];
		}

		return Languages::getDefaultCode();
	}

	private function getLabelTranslationData( string $taxonomy ) {
		try {
			$data = $this->buildLabelTranslations( $taxonomy );
		} catch ( \Throwable $e ) {
			return null;
		}

		return is_array( $data ) ? $data : null;
	}

	protected function buildLabelTranslations( string $taxonomy ) {
		global $sitepress;

		if ( null === $this->labelsTranslation ) {
			if ( ! defined( 'WPML_ST_VERSION' )
				|| ! $sitepress
				|| ! class_exists( '\\WPML_Slug_Translation_Records_Factory' )
				|| ! class_exists( '\\WPML_ST_Taxonomy_Strings' )
				|| ! class_exists( '\\WPML_ST_String_Factory' )
				|| ! class_exists( '\\WPML_ST_Taxonomy_Labels_Translation' )
				|| ! class_exists( '\\WPML_ST_Tax_Slug_Translation_Settings' )
				|| ! class_exists( '\\WPML_Super_Globals_Validation' )
			) {
				return null;
			}

			$records_factory  = new \WPML_Slug_Translation_Records_Factory();
			$taxonomy_strings = new \WPML_ST_Taxonomy_Strings(
				$records_factory->createTaxRecords(),
				\WPML\Container\make( \WPML_ST_String_Factory::class )
			);

			$this->labelsTranslation = new \WPML_ST_Taxonomy_Labels_Translation(
				$taxonomy_strings,
				new \WPML_ST_Tax_Slug_Translation_Settings(),
				new \WPML_Super_Globals_Validation(),
				$sitepress->get_active_languages( true )
			);
		}

		return $this->labelsTranslation->get_label_translations( false, $taxonomy, false );
	}

	protected function isSlugTranslatable( string $taxonomy ): bool {
		global $sitepress;

		$dispatcherClass = '\\WPML\\StringTranslation\\Infrastructure\\TranslateEverything\\TaxonomyLabelJobDispatcher';

		if ( '' === $taxonomy
			|| ! defined( 'WPML_ST_VERSION' )
			|| ! $sitepress
			|| ! class_exists( $dispatcherClass )
			|| ! method_exists( $dispatcherClass, 'shouldTranslateSlug' )
			|| ! class_exists( '\\WPML\\StringTranslation\\Infrastructure\\TranslateEverything\\UntranslatedTaxonomyLabelStringsFactory' )
		) {
			return false;
		}

		try {
			$dispatcher = $this->buildLabelJobDispatcher();

			return $dispatcher && $dispatcher->shouldTranslateSlug( $taxonomy );
		} catch ( \Throwable $e ) {
			return false;
		}
	}

	protected function buildLabelJobDispatcher() {
		if ( false !== $this->labelJobDispatcher ) {
			return $this->labelJobDispatcher;
		}
		$this->labelJobDispatcher = null;

		$factoryClass = '\\WPML\\StringTranslation\\Infrastructure\\TranslateEverything\\UntranslatedTaxonomyLabelStringsFactory';

		if ( ! class_exists( $factoryClass ) ) {
			return null;
		}

		$factory  = new $factoryClass();
		$instance = $factory->create();

		$dispatcherProp = new \ReflectionProperty( $instance, 'dispatcher' );
		if ( PHP_VERSION_ID < 80100 ) {
			$dispatcherProp->setAccessible( true );
		}

		$dispatcher = $dispatcherProp->getValue( $instance );

		$this->labelJobDispatcher = $dispatcher instanceof \WPML\StringTranslation\Infrastructure\TranslateEverything\TaxonomyLabelJobDispatcher
			? $dispatcher
			: null;

		return $this->labelJobDispatcher;
	}

	private function getSecondaryLanguages(): array {
		return \WPML\LanguageEditor\TranslationPause::filterTranslatable( Languages::getSecondaryCodes() );
	}

	private function getStringTranslationUrl( string $search ): string {
		if ( ! function_exists( 'admin_url' ) ) {
			return '';
		}

		$menu = defined( 'WPML_ST_MENU_URL' )
			? WPML_ST_MENU_URL
			: 'wpml-string-translation/menu/string-translation.php';

		return admin_url(
			'admin.php?page=' . $menu . '&search=' . rawurlencode( $search )
		);
	}
}
