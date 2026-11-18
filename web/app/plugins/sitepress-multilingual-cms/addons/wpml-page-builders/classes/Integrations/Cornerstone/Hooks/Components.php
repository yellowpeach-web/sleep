<?php

namespace WPML\PB\Cornerstone\Hooks;

use WPML\LIB\WP\Hooks;
use function WPML\FP\spreadArgs;

class Components implements \IWPML_Frontend_Action, \IWPML_Backend_Action, \IWPML_DIC_Action {

	const POST_TYPE                     = TranslationGuiLabels::POST_TYPE_COMPONENT;
	const CORNERSTONE_CACHE_OPTION      = 'cs_component_cache';
	const CACHE_BY_LANGUAGE_OPTION      = 'wpml_cs_component_cache';
	const CORNERSTONE_ALL_LANGUAGES_VAR = 'cs_all_wpml';

	const CORNERSTONE_CACHE_PURGE_ACTIONS = [
		'cs_save_component',
		'cs_delete_component',
		'cs_purge_tmp',
		'cs_purge_cache',
	];

	private $wpdb;

	public function __construct( \wpdb $wpdb ) {
		$this->wpdb = $wpdb;
	}

	public function add_hooks() {
		Hooks::onAction( 'pre_get_posts' )
			->then( spreadArgs( [ $this, 'keepOneComponentPerTranslationGroup' ] ) );

		Hooks::onFilter( 'pre_option_' . self::CORNERSTONE_CACHE_OPTION )
			->then( [ $this, 'getCacheOfCurrentLanguage' ] );

		Hooks::onFilter( 'pre_update_option_' . self::CORNERSTONE_CACHE_OPTION, 10, 2 )
			->then( spreadArgs( [ $this, 'saveCacheOfCurrentLanguage' ] ) );

		foreach ( self::CORNERSTONE_CACHE_PURGE_ACTIONS as $action ) {
			Hooks::onAction( $action )->then( [ $this, 'deleteCacheOfAllLanguages' ] );
		}

		Hooks::onAction( 'save_post_' . self::POST_TYPE )->then( [ $this, 'deleteCacheOfAllLanguages' ] );

		Hooks::onAction( 'deleted_post', 10, 2 )
			->then( spreadArgs( [ $this, 'deleteCacheOfAllLanguagesForComponent' ] ) );
	}

	public function keepOneComponentPerTranslationGroup( $query ) {
		if ( ! $query instanceof \WP_Query || ! $this->isCornerstoneAllLanguagesComponentQuery( $query ) ) {
			return;
		}

		$hiddenComponentIds = $this->getComponentIdsHiddenInLanguage( $this->getCurrentLanguage() );

		if ( $hiddenComponentIds ) {
			$query->set( 'post__not_in', array_merge( (array) $query->get( 'post__not_in' ), $hiddenComponentIds ) );
		}
	}

	public function getCacheOfCurrentLanguage() {
		$cacheByLanguage = get_option( self::CACHE_BY_LANGUAGE_OPTION, [] );
		$currentLanguage = $this->getCurrentLanguage();

		return is_array( $cacheByLanguage ) && isset( $cacheByLanguage[ $currentLanguage ] )
			? $cacheByLanguage[ $currentLanguage ]
			: '';
	}

	public function saveCacheOfCurrentLanguage( $cache, $previousCache ) {
		$cacheByLanguage = get_option( self::CACHE_BY_LANGUAGE_OPTION, [] );
		$cacheByLanguage = is_array( $cacheByLanguage ) ? $cacheByLanguage : [];

		$cacheByLanguage[ $this->getCurrentLanguage() ] = $cache;
		update_option( self::CACHE_BY_LANGUAGE_OPTION, $cacheByLanguage, false );

		return $previousCache;
	}

	public function deleteCacheOfAllLanguages() {
		delete_option( self::CACHE_BY_LANGUAGE_OPTION );
	}

	public function deleteCacheOfAllLanguagesForComponent( $postId, $post = null ) {
		$postType = $post instanceof \WP_Post ? $post->post_type : get_post_type( $postId );

		if ( self::POST_TYPE === $postType ) {
			$this->deleteCacheOfAllLanguages();
		}
	}

	private function isCornerstoneAllLanguagesComponentQuery( \WP_Query $query ) {
		return $query->get( self::CORNERSTONE_ALL_LANGUAGES_VAR ) && self::POST_TYPE === $query->get( 'post_type' );
	}

	private function getComponentIdsHiddenInLanguage( $language ) {
		$wpdb        = $this->wpdb;
		$elementType = 'post_' . self::POST_TYPE;

		$hiddenIds = $wpdb->get_col(
			$wpdb->prepare(
				"SELECT component.element_id
				FROM {$wpdb->prefix}icl_translations component
				WHERE component.element_type = %s
					AND component.language_code <> %s
					AND (
						component.source_language_code IS NOT NULL
						OR EXISTS (
							SELECT 1 FROM {$wpdb->prefix}icl_translations translation
							WHERE translation.trid = component.trid
								AND translation.element_type = %s
								AND translation.language_code = %s
						)
					)",
				$elementType,
				$language,
				$elementType,
				$language
			)
		);

		return array_map( 'intval', (array) $hiddenIds );
	}

	private function getCurrentLanguage() {
		return (string) apply_filters( 'wpml_current_language', null );
	}
}
