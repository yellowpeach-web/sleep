<?php

namespace WPML\WPSEO\YoastSEO\Indexable;

use WPML\LIB\WP\WPDB;
use WPML\WPSEO\YoastSEO\Terms\Meta\Hooks as TermMetaHooks;
use Yoast\WP\SEO\Main;
use Yoast\WP\SEO\Models\Indexable;
use Yoast\WP\SEO\Repositories\Indexable_Repository;
use Yoast\WP\SEO\Surfaces\Classes_Surface;

class Hooks implements \IWPML_Backend_Action, \IWPML_Frontend_Action, \IWPML_DIC_Action {

	const YOAST_UNINDEXED_TERMS_TRANSIENTS = [ 'wpseo_total_unindexed_terms', 'wpseo_total_unindexed_terms_limited' ];

	private $wpdb;

	private $pendingStringTranslations = [];

	public function __construct( \wpdb $wpdb ) {
		$this->wpdb = $wpdb;
	}

	public function add_hooks() {
		add_action( 'icl_pro_translation_completed', [ $this, 'invalidateIndexables' ], 10, 3 );
		add_action( 'wpml_st_add_string_translation', [ $this, 'invalidateTermIndexableOnStringTranslation' ], 10, 4 );
		add_action( 'update_option_page_on_front', [ $this, 'invalidateStaticFrontPageIndexables' ], 10, 2 );
		add_action( 'update_option_show_on_front', [ $this, 'invalidateCurrentStaticFrontPageIndexables' ] );
		add_action( 'wpml_st_language_of_strings_changed', [ $this, 'invalidateTranslationsOfChangedStrings' ] );
		add_action( 'icl_make_duplicate', [ $this, 'invalidateDuplicateIndexable' ], 10, 4 );
	}

	public function invalidateDuplicateIndexable( $masterPostId, $language, $postArray, $duplicateId ) {
		$this->invalidatePostIndexable( (int) $duplicateId );
	}

	public function invalidateCurrentStaticFrontPageIndexables() {
		$this->invalidateStaticFrontPageIndexables( 0, get_option( 'page_on_front' ) );
	}

	public function invalidateStaticFrontPageIndexables( $oldValue, $value ) {
		$pageIds = array_unique( array_filter( [ (int) $oldValue, (int) $value ] ) );

		foreach ( $pageIds as $pageId ) {
			foreach ( $this->getPageTranslationIds( $pageId ) as $translationId ) {
				$this->invalidatePostIndexable( $translationId );
			}
		}
	}

	private function getPageTranslationIds( int $pageId ): array {
		$trid = apply_filters( 'wpml_element_trid', null, $pageId, 'post_page' );
		if ( ! $trid ) {
			return [];
		}

		$translations = (array) apply_filters( 'wpml_get_element_translations', [], $trid, 'post_page' );

		return array_filter( array_map( 'intval', array_column( $translations, 'element_id' ) ) );
	}

	public function invalidateTranslationsOfChangedStrings( array $stringIds ) {
		if ( ! $stringIds ) {
			return;
		}

		$names = $this->getTermMetaStringNames( array_map( 'intval', $stringIds ) );
		if ( ! $names ) {
			return;
		}

		$languages = $this->getActiveLanguageCodes();

		$termIds = [];
		foreach ( $names as $name ) {
			$parsedName = $this->parseTermMetaStringName( $name );
			if ( ! $parsedName ) {
				continue;
			}

			list( $taxonomy, $sourceTermId ) = $parsedName;

			$termIds += array_fill_keys( $this->getTranslatedTermIds( $taxonomy, $sourceTermId, $languages ), true );
		}

		if ( $termIds ) {
			$this->invalidateTermIndexables( array_keys( $termIds ) );
		}
	}

	public function invalidateTranslatedTermIndexables( string $taxonomy, int $sourceTermId ) {
		$termIds = $this->getTranslatedTermIds( $taxonomy, $sourceTermId, $this->getActiveLanguageCodes() );

		if ( $termIds ) {
			$this->invalidateTermIndexables( $termIds );
		}
	}

	private function getActiveLanguageCodes(): array {
		return array_keys( (array) apply_filters( 'wpml_active_languages', [], [ 'skip_missing' => 0 ] ) );
	}

	private function getTranslatedTermIds( string $taxonomy, int $sourceTermId, array $languages ): array {
		$translatedTermIds = [];
		foreach ( $languages as $language ) {
			$targetTermId = (int) apply_filters( 'wpml_object_id', $sourceTermId, $taxonomy, false, $language );
			if ( $targetTermId && $sourceTermId !== $targetTermId ) {
				$translatedTermIds[] = $targetTermId;
			}
		}

		return $translatedTermIds;
	}

	public function invalidateTermIndexableOnStringTranslation( $stId, $translationData, $language, $stringId ) {
		if ( ! $this->pendingStringTranslations ) {
			add_action( 'shutdown', [ $this, 'invalidateTermIndexablesForSavedStrings' ] );
		}

		$this->pendingStringTranslations[] = [ (int) $stringId, (string) $language ];
	}

	public function invalidateTermIndexablesForSavedStrings() {
		$pending                         = $this->pendingStringTranslations;
		$this->pendingStringTranslations = [];

		if ( ! $pending ) {
			return;
		}

		$names = $this->getTermMetaStringNames( array_unique( array_column( $pending, 0 ) ) );

		$termIds = [];
		foreach ( $pending as $stringTranslation ) {
			list( $stringId, $language ) = $stringTranslation;

			if ( ! isset( $names[ $stringId ] ) ) {
				continue;
			}

			$parsedName = $this->parseTermMetaStringName( $names[ $stringId ] );
			if ( ! $parsedName ) {
				continue;
			}

			list( $taxonomy, $sourceTermId ) = $parsedName;

			$targetTermId = (int) apply_filters( 'wpml_object_id', $sourceTermId, $taxonomy, false, $language );

			if ( $targetTermId && $sourceTermId !== $targetTermId ) {
				$termIds[ $targetTermId ] = true;
			}
		}

		if ( $termIds ) {
			$this->invalidateTermIndexables( array_keys( $termIds ) );
		}
	}

	private function getTermMetaStringNames( array $stringIds ) {
		$in = wpml_prepare_in( $stringIds, '%d' );

		$rows = $this->wpdb->get_results(
			$this->wpdb->prepare(
				"SELECT id, name FROM {$this->wpdb->prefix}icl_strings WHERE context = %s AND id IN ($in)",
				TermMetaHooks::stringContext()
			)
		);

		return array_column( (array) $rows, 'name', 'id' );
	}

	private function parseTermMetaStringName( $name ) {
		if ( ! preg_match( '/^(.+)-(\d+)-(wpseo_[\w-]+)$/', $name, $matches )
			|| ! array_key_exists( $matches[3], TermMetaHooks::FIELDS )
		) {
			return null;
		}

		return [ $matches[1], (int) $matches[2], $matches[3] ];
	}

	public function invalidateIndexables( $postId, $fields, $job ) {
		if ( $postId ) {
			$this->invalidatePostIndexable( $postId );
		} elseif ( 'package_yoast-seo' === $job->original_post_type ) {
			$this->invalidateTermIndexables();
		}
	}

	private function invalidatePostIndexable( $postId ) {
		$yoastSEO = YoastSEO();

		$classes = $yoastSEO->classes;

		$indexable_repository = $classes->get( Indexable_Repository::class );

		$indexable = $indexable_repository->find_by_id_and_type( $postId, 'post', false );
		if ( $indexable ) {
			$indexable->version = 0;
			$indexable->save();
		}
	}

	public function invalidateTermIndexables( $termIds = null ) {
		WPDB::withoutError(
			function () use ( $termIds ) {
				$sql = "UPDATE {$this->wpdb->prefix}yoast_indexable SET version = 0 WHERE object_type = 'term'";

				if ( $termIds ) {
					$sql .= ' AND object_id IN (' . wpml_prepare_in( (array) $termIds, '%d' ) . ')';
				}

				$this->wpdb->query( $sql );
			}
		);

		if ( ! $termIds ) {
			foreach ( self::YOAST_UNINDEXED_TERMS_TRANSIENTS as $transient ) {
				delete_transient( $transient );
			}
		}
	}
}
