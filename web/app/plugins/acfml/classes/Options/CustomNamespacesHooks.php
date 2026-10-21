<?php

namespace ACFML\Options;

use WPML\FP\Relation;
use WPML\FP\Str;

class CustomNamespacesHooks implements \IWPML_Action {

	public function add_hooks() {
		add_filter( 'acf/validate_post_id', [ $this, 'appendLanguageToCustomNamespace' ] );
	}

	public function appendLanguageToCustomNamespace( $postId ) {
		if ( is_string( $postId )
			&& ! is_numeric( $postId )
			&& ! self::isRestrictedNamespace( $postId )
			&& ! self::isCommonNamespace( $postId )
			&& self::isValidOptionPagePostId( $postId )
		) {
			$currentLanguage = acf_get_setting( CurrentLanguageHooks::ACF_CURRENT_LANGUAGE_SETTING );
			$defaultLanguage = acf_get_setting( 'default_language' );

			if ( $currentLanguage && ! $this->hasLanguageAppended( $postId, $currentLanguage ) && $currentLanguage !== $defaultLanguage ) {
				$postId .= '_' . $currentLanguage;
			}
		}

		return $postId;
	}

	private static function isRestrictedNamespace( $postId ) {
		$restricted = [
			'new_post' => 'The post id is new_post so it is fake id used in acf_form function when creating new post with ACF fields.',
		];
		return array_key_exists( $postId, $restricted );
	}

	private static function isCommonNamespace( $postId ) {
		if ( Str::startsWith( 'options', $postId ) ) {
			return true;
		} elseif ( Str::startsWith( 'term_', $postId ) ) {
			return true;
		} elseif ( Str::startsWith( 'block_', $postId ) ) {
			return true;
		} elseif ( Str::startsWith( 'user_', $postId ) ) {
			return true;
		} elseif ( Str::startsWith( 'widget_', $postId ) ) {
			return true;
		}

		$taxonomies = get_taxonomies( [], 'names' );
		foreach ( $taxonomies as $taxonomy_name ) {
			if ( (bool) Str::startsWith( sprintf( '%s_', $taxonomy_name ), $postId ) ) {
				return true;
			}
		}

		return false;
	}

	private static function isValidOptionPagePostId( $postId ) {
		if ( function_exists( 'acf_get_options_pages' ) ) {
			$optionPages = acf_get_options_pages();

			if ( $optionPages ) {
				return wpml_collect( acf_get_options_pages() )->first( Relation::propEq( 'post_id', $postId ) );
			}
		}

		return false;
	}

	private function hasLanguageAppended( $postId, $language ) {
		$suffix = '_' . $language;
		return Str::endsWith( $suffix, $postId );  
	}

}
