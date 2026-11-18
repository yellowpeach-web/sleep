<?php

namespace WPML\WPSEO\YoastSEO\Meta;

class DuplicateCanonicalHooks implements \IWPML_Backend_Action, \IWPML_Frontend_Action, \IWPML_DIC_Action {

	const KEY_CANONICAL = '_yoast_wpseo_canonical';

	const KEY_WRITTEN_CANONICAL = '_wpml_wpseo_duplicate_canonical';

	const KEY_DUPLICATE_OF = '_icl_lang_duplicate_of';

	private $sitepress;

	public function __construct( \SitePress $sitepress ) {
		$this->sitepress = $sitepress;
	}

	public function add_hooks() {
		add_filter( 'wpml_duplicate_custom_fields_exceptions', [ $this, 'keepWrittenCanonical' ] );
		add_action( 'icl_make_duplicate', [ $this, 'writeCanonical' ], 10, 4 );
		add_action( 'delete_post_meta', [ $this, 'removeCanonicalOfIndependentPosts' ], 10, 3 );
	}

	public function keepWrittenCanonical( $exceptions ) {
		$exceptions[] = self::KEY_WRITTEN_CANONICAL;

		return $exceptions;
	}

	public function writeCanonical( $masterPostId, $language, $postArray, $duplicateId ) {
		$duplicateId = (int) $duplicateId;
		if ( ! $duplicateId ) {
			return;
		}

		$current = (string) get_post_meta( $duplicateId, self::KEY_CANONICAL, true );
		$written = (string) get_post_meta( $duplicateId, self::KEY_WRITTEN_CANONICAL, true );

		if ( '' !== $current && $current !== $written ) {
			delete_post_meta( $duplicateId, self::KEY_WRITTEN_CANONICAL );

			return;
		}

		$canonical = $this->getCanonicalOfOriginal( (int) $masterPostId );
		if ( ! $canonical ) {
			return;
		}

		if ( $current !== $canonical ) {
			update_post_meta( $duplicateId, self::KEY_CANONICAL, $canonical );
		}
		update_post_meta( $duplicateId, self::KEY_WRITTEN_CANONICAL, $canonical );
	}

	public function removeCanonicalOfIndependentPosts( $metaIds, $postId, $metaKey ) {
		if ( self::KEY_DUPLICATE_OF !== $metaKey ) {
			return;
		}

		foreach ( (array) $metaIds as $metaId ) {
			$meta = get_metadata_by_mid( 'post', (int) $metaId );
			if ( $meta ) {
				$this->removeWrittenCanonical( (int) $meta->post_id );
			}
		}
	}

	private function removeWrittenCanonical( int $postId ) {
		$written = (string) get_post_meta( $postId, self::KEY_WRITTEN_CANONICAL, true );
		if ( '' === $written ) {
			return;
		}

		if ( get_post_meta( $postId, self::KEY_CANONICAL, true ) === $written ) {
			delete_post_meta( $postId, self::KEY_CANONICAL );
		}
		delete_post_meta( $postId, self::KEY_WRITTEN_CANONICAL );
	}

	private function getCanonicalOfOriginal( int $masterPostId ): string {
		$language = apply_filters( 'wpml_element_language_code', null, [
			'element_id'   => $masterPostId,
			'element_type' => get_post_type( $masterPostId ),
		] );

		$this->sitepress->switch_lang( $language );
		try {
			$canonical = get_permalink( $masterPostId );
		} finally {
			$this->sitepress->switch_lang();
		}

		return (string) $canonical;
	}
}
