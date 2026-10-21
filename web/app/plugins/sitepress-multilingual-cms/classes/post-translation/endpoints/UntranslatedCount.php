<?php

namespace WPML\Posts;

use WPML\API\PostTypes;
use WPML\Collect\Support\Collection;
use WPML\Element\API\Languages;
use WPML\FP\Fns;
use WPML\FP\Lst;
use WPML\FP\Str;
use WPML\LIB\WP\PostType;

class UntranslatedCount {

	public function run( Collection $data, \wpdb $wpdb ) {
		return wpml_collect( $this->runForPosts( $data, $wpdb ) )
			->merge( $this->runForPackages( $data, $wpdb ) )
			->toArray();
	}

	public function countTotal( Collection $data, \wpdb $wpdb ): int {
		return (int) array_sum( array_values( (array) $this->run( $data, $wpdb ) ) );
	}

	private function runForPosts( Collection $data, \wpdb $wpdb ) {
		$postTypes = $data->get( 'postTypes', PostTypes::getAutomaticTranslatable() );

		$postIn   = wpml_prepare_in( Fns::map( Str::concat( 'post_' ), $postTypes ) );
		$statuses = wpml_prepare_in( [ ICL_TM_NOT_TRANSLATED, ICL_TM_ATE_CANCELLED ] );

		$editorCondition = $this->buildEditorCondition();

		$query = "
			SELECT translations.post_type, COUNT(translations.ID)
			FROM (
	            SELECT RIGHT(element_type, LENGTH(element_type) - 5) as post_type, posts.ID
	            FROM {$wpdb->prefix}icl_translations
	            INNER JOIN {$wpdb->prefix}posts posts ON element_id = ID
                
                LEFT JOIN {$wpdb->postmeta} postmeta_editor ON postmeta_editor.post_id = posts.ID AND postmeta_editor.meta_key = %s
                LEFT JOIN {$wpdb->postmeta} postmeta ON postmeta.post_id = posts.ID AND postmeta.meta_key = %s
                LEFT JOIN {$wpdb->postmeta} postmeta_wpml ON postmeta_wpml.post_id = posts.ID AND postmeta_wpml.meta_key = %s
	                                        
	            WHERE element_type IN ({$postIn})
		           AND post_status = 'publish'
		           AND source_language_code IS NULL
		           AND language_code = %s
		           AND (
	                   SELECT COUNT(trid)
	                   FROM {$wpdb->prefix}icl_translations icl_translations_inner
	                   INNER JOIN {$wpdb->prefix}icl_translation_status icl_translations_status
	                                       on icl_translations_inner.translation_id = icl_translations_status.translation_id
	                   WHERE icl_translations_inner.trid = {$wpdb->prefix}icl_translations.trid
	                     AND icl_translations_status.status NOT IN ({$statuses})
	                     AND icl_translations_status.needs_update != 1
	               ) < %d
	               AND {$editorCondition}
	         ) as translations
			GROUP BY translations.post_type;
		";

		$untranslatedPosts = Lst::length( $postTypes ) ? $wpdb->get_results(
			$wpdb->prepare( $query, \WPML_TM_Post_Edit_TM_Editor_Mode::POST_META_KEY_EDITOR, \WPML_TM_Post_Edit_TM_Editor_Mode::POST_META_KEY_USE_NATIVE, \WPML_TM_Post_Edit_TM_Editor_Mode::POST_META_KEY_USE_WPML, Languages::getDefaultCode(), Lst::length( Languages::getSecondaries() ) ),
			ARRAY_N
		) : [];

		$setPluralPostName = function ( $postType ) {
			return [ PostType::getPluralName( $postType[0] )->getOrElse( $postType[0] ) => (int) $postType[1] ];
		};

		$setCountToZero = Lst::makePair( Fns::__, 0 );


		return wpml_collect( $postTypes )
			->map( $setCountToZero )
			->merge( $untranslatedPosts )
			->mapWithKeys( $setPluralPostName )
			->toArray();
	}

	private function buildEditorCondition() {
		$inheritFallback = $this->useNativeEditorGlobally()
			? $this->getGlobalNativeEditorFallback()
			: $this->getPerPostTypeNativeEditorFallback();

		return 'COALESCE( ' . $this->getPerPostEditorCase() . ', ' . $inheritFallback . ' ) = 0';
	}

	private function useNativeEditorGlobally() {
		$editor = wpml_get_tm_sub_setting( \WPML_TM_Post_Edit_TM_Editor_Mode::TM_KEY_GLOBAL_EDITOR, null );
		if ( $this->isValidEditor( $editor ) ) {
			return \WPML_TM_Post_Edit_TM_Editor_Mode::EDITOR_NATIVE === $editor;
		}

		return true === wpml_get_tm_sub_setting( \WPML_TM_Post_Edit_TM_Editor_Mode::TM_KEY_GLOBAL_USE_NATIVE, false );
	}

	private function getGlobalNativeEditorFallback() {
		$typesNotUsingNativeEditor = $this->getPostTypesUsingNativeEditor( false );

		return $typesNotUsingNativeEditor
			? 'CASE WHEN posts.post_type IN (' . wpml_prepare_in( $typesNotUsingNativeEditor ) . ') THEN 0 ELSE 1 END'
			: '1';
	}

	private function getPerPostTypeNativeEditorFallback() {
		$typesUsingNativeEditor = $this->getPostTypesUsingNativeEditor( true );

		return $typesUsingNativeEditor
			? 'CASE WHEN posts.post_type NOT IN (' . wpml_prepare_in( $typesUsingNativeEditor ) . ') THEN 0 ELSE 1 END'
			: '0';
	}

	private function getPostTypesUsingNativeEditor( $usingNativeEditor ) {
		$perPostType = (array) wpml_get_tm_sub_setting( \WPML_TM_Post_Edit_TM_Editor_Mode::TM_KEY_FOR_POST_TYPE_USE_NATIVE, [] );

		$consolidated = (array) wpml_get_tm_sub_setting( \WPML_TM_Post_Edit_TM_Editor_Mode::TM_KEY_FOR_POST_TYPE_EDITOR, [] );
		foreach ( $consolidated as $postType => $editor ) {
			if ( $this->isValidEditor( $editor ) ) {
				$perPostType[ (string) $postType ] = \WPML_TM_Post_Edit_TM_Editor_Mode::EDITOR_NATIVE === $editor;
			}
		}

		return array_keys( array_filter( $perPostType, function ( $value ) use ( $usingNativeEditor ) {
			return $value === $usingNativeEditor;
		} ) );
	}

	private function isValidEditor( $value ) {
		return in_array(
			$value,
			[
				\WPML_TM_Post_Edit_TM_Editor_Mode::EDITOR_NATIVE,
				\WPML_TM_Post_Edit_TM_Editor_Mode::EDITOR_WPML,
				\WPML_TM_Post_Edit_TM_Editor_Mode::EDITOR_DASHBOARD,
			],
			true
		);
	}

	private function getPerPostEditorCase() {
		$editorNative = \WPML_TM_Post_Edit_TM_Editor_Mode::EDITOR_NATIVE;

		return "CASE
			WHEN postmeta_editor.meta_value = '{$editorNative}' THEN 1
			WHEN postmeta_editor.meta_value IS NOT NULL THEN 0
			WHEN postmeta.meta_value = 'yes' AND postmeta_wpml.meta_value IS NULL THEN 1
			WHEN postmeta.meta_value = 'no' THEN 0
			ELSE NULL
		END";
	}

	private function runForPackages( Collection $data, \wpdb $wpdb ) {
		if ( ! wpml_is_st_loaded() ) {
			return [];
		}

		$kinds = apply_filters( 'wpml_active_string_package_kinds', [] );
		if ( ! is_array( $kinds ) || ! $kinds ) {
			return [];
		}

		$fullTypes = Fns::map( Str::concat( 'package_' ), array_keys( $kinds ) );
		$statuses  = [ ICL_TM_NOT_TRANSLATED, ICL_TM_ATE_CANCELLED ];

		$untranslatedPackages = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT translations.kind, COUNT(translations.ID)
				FROM (
	            SELECT RIGHT(element_type, LENGTH(element_type) - 8) as kind, packages.ID
	            FROM {$wpdb->prefix}icl_translations
	            INNER JOIN {$wpdb->prefix}icl_string_packages packages ON element_id = ID
                
				WHERE element_type IN (" . implode( ', ', array_fill( 0, count( $fullTypes ), '%s' ) ) . ")
				   AND source_language_code IS NULL
				   AND language_code = %s
		           AND (
	                   SELECT COUNT(trid)
	                   FROM {$wpdb->prefix}icl_translations icl_translations_inner
	                   INNER JOIN {$wpdb->prefix}icl_translation_status icl_translations_status
	                                       on icl_translations_inner.translation_id = icl_translations_status.translation_id
	                   WHERE icl_translations_inner.trid = {$wpdb->prefix}icl_translations.trid
				     AND icl_translations_status.status NOT IN (" . implode( ', ', array_fill( 0, count( $statuses ), '%d' ) ) . ")
				     AND icl_translations_status.needs_update != 1
	               ) < %d
	         ) as translations
				GROUP BY translations.kind",
				array_merge(
					$fullTypes,
					array( Languages::getDefaultCode() ),
					$statuses,
					array( Lst::length( Languages::getSecondaries() ) )
				)
			),
			ARRAY_N
		);

		return wpml_collect( $untranslatedPackages )
			->mapWithKeys( function( $packageType ) {
				return [ $packageType[0] => (int) $packageType[1] ];
			} )
			->toArray();
	}
}
