<?php

namespace WPML\WPSEO\YoastSEO\Meta;

class CopyFieldsSyncHooks implements \IWPML_Backend_Action, \IWPML_DIC_Action {

	const META_KEY_PREFIX = '_yoast_wpseo_';

	private $sitepress;

	private $isYoastSaved = false;

	public function __construct( \SitePress $sitepress ) {
		$this->sitepress = $sitepress;
	}

	public function add_hooks() {
		add_action( 'wpseo_saved_postdata', [ $this, 'markYoastSaved' ] );
		add_action( 'wp_insert_post', [ $this, 'syncYoastCopyFields' ], PHP_INT_MAX );
	}

	public function markYoastSaved() {
		$this->isYoastSaved = true;
	}

	public function syncYoastCopyFields( $postId ) {
		if ( ! $this->isYoastSaved ) {
			return;
		}
		$this->isYoastSaved = false;

		$postId = (int) $postId;
		foreach ( $this->getTranslationIds( $postId ) as $translationId ) {
			$changedKeys = $this->getChangedYoastKeys( $postId, $translationId );
			if ( $changedKeys ) {
				$this->sitepress->copy_custom_fields( $postId, $translationId, $changedKeys );
			}
		}
	}

	private function getTranslationIds( int $postId ): array {
		$elementType = 'post_' . get_post_type( $postId );
		$trid        = $this->sitepress->get_element_trid( $postId, $elementType );
		if ( ! $trid ) {
			return [];
		}

		$isOriginal     = false;
		$translationIds = [];
		foreach ( $this->sitepress->get_element_translations( $trid, $elementType ) as $translation ) {
			if ( (int) $translation->element_id === $postId ) {
				$isOriginal = (bool) $translation->original;
			} else {
				$translationIds[] = (int) $translation->element_id;
			}
		}

		return $isOriginal ? $translationIds : [];
	}

	private function getChangedYoastKeys( int $postId, int $translationId ): array {
		$originalMeta    = $this->getYoastMeta( $postId );
		$translationMeta = $this->getYoastMeta( $translationId );

		$changedKeys = [];
		foreach ( array_keys( $originalMeta + $translationMeta ) as $metaKey ) {
			$originalValues    = isset( $originalMeta[ $metaKey ] ) ? $originalMeta[ $metaKey ] : [];
			$translationValues = isset( $translationMeta[ $metaKey ] ) ? $translationMeta[ $metaKey ] : [];
			if ( $originalValues !== $translationValues ) {
				$changedKeys[] = (string) $metaKey;
			}
		}

		return $changedKeys;
	}

	private function getYoastMeta( int $postId ): array {
		$meta = get_post_meta( $postId );
		if ( ! is_array( $meta ) ) {
			return [];
		}

		$yoastMeta = [];
		foreach ( $meta as $metaKey => $values ) {
			if ( 0 === strpos( (string) $metaKey, self::META_KEY_PREFIX ) ) {
				$yoastMeta[ $metaKey ] = (array) $values;
			}
		}

		return $yoastMeta;
	}
}
