<?php

namespace WPML\WPSEO\Shared\PrimaryCategory;

abstract class BaseHooks implements \IWPML_Frontend_Action, \IWPML_Backend_Action {

	const PRIORITY_TRANSLATE_TERM_ID = 20;

	abstract protected function getPrimaryTermMetaKeyPrefix();

	public function add_hooks() {
		$this->addTranslateTermIdFilter();
		add_filter( 'wpml_resolve_custom_field_preferences', [ $this, 'resolvePrimaryTermPreferences' ], 10, 3 );
	}

	public function remove_hooks() {
		$this->removeTranslateTermIdFilter();
	}

	public function translateTermId( $value, $postId, $key, $single ) {
		$taxonomy = $this->getTaxonomyOfMetaKey( $key );
		if ( $taxonomy ) {
			$value = $this->getStoredTermId( $postId, $key );

			$args     = [
				'element_id'   => $postId,
				'element_type' => get_post_type( $postId ),
			];
			$language = apply_filters( 'wpml_element_language_code', false, $args );
			$value    = apply_filters( 'wpml_object_id', $value, $taxonomy, true, $language );

			if ( ! $single ) {
				$value = [ $value ];
			}
		}

		return $value;
	}

	public function resolvePrimaryTermPreferences( $preferences, $metaKeys, $elementType = 'post' ) {
		if ( ! is_array( $preferences ) || 'post' !== $elementType ) {
			return $preferences;
		}

		foreach ( $metaKeys as $metaKey ) {
			if ( $this->getTaxonomyOfMetaKey( $metaKey ) ) {
				$preferences[ $metaKey ] = WPML_COPY_CUSTOM_FIELD;
			}
		}

		return $preferences;
	}

	private function getTaxonomyOfMetaKey( $metaKey ) {
		$prefix = $this->getPrimaryTermMetaKeyPrefix();
		if ( ! is_string( $metaKey ) || 0 !== strpos( $metaKey, $prefix ) ) {
			return null;
		}

		$taxonomy = substr( $metaKey, strlen( $prefix ) );

		return taxonomy_exists( $taxonomy ) ? $taxonomy : null;
	}

	private function getStoredTermId( $postId, $key ) {
		$this->removeTranslateTermIdFilter();
		$termId = get_post_meta( $postId, $key, true );
		$this->addTranslateTermIdFilter();

		return $termId;
	}

	private function addTranslateTermIdFilter() {
		add_filter( 'get_post_metadata', [ $this, 'translateTermId' ], self::PRIORITY_TRANSLATE_TERM_ID, 4 );
	}

	private function removeTranslateTermIdFilter() {
		remove_filter( 'get_post_metadata', [ $this, 'translateTermId' ], self::PRIORITY_TRANSLATE_TERM_ID );
	}
}
