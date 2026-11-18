<?php

namespace WPML\Compatibility\Divi\V5;

use SitePress;

class ContextCanvases {

	const META_KEY = '_divi_canvas_parent_context';

	const TERM_KEY_PATTERN = '/^term:([a-z0-9_\-]+):(\d+)$/';

	private $sitepress;

	public function __construct( SitePress $sitepress ) {
		$this->sitepress = $sitepress;
	}

	public function getAllKeys( string $key ) {
		return array_values( array_unique( array_merge( [ $key ], $this->getTranslatedKeys( $key ) ) ) );
	}

	public function getLanguage( string $key ) {
		$language = array_search( $key, $this->getTranslatedKeys( $key ), true );

		return false === $language ? null : (string) $language;
	}

	public function translate( string $key, string $language ) {
		return $this->getTranslatedKeys( $key )[ $language ] ?? $key;
	}

	public function findOriginalToConnect( int $canvasPostId, string $canvasId, string $key, string $language ) {
		$wpdb = $this->sitepress->get_wpdb();
		$keys = $this->getAllKeys( $key );

		$row = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT t.trid, t.language_code
				FROM {$wpdb->posts} p
				JOIN {$wpdb->postmeta} cid ON cid.post_id = p.ID AND cid.meta_key = %s AND cid.meta_value = %s
				JOIN {$wpdb->postmeta} ctx ON ctx.post_id = p.ID AND ctx.meta_key = %s
					AND ctx.meta_value IN ( " . implode( ',', array_fill( 0, count( $keys ), '%s' ) ) . " )
				JOIN {$wpdb->prefix}icl_translations t ON t.element_id = p.ID AND t.element_type = %s
				WHERE p.ID <> %d
					AND p.post_type = %s
					AND p.post_status NOT IN ( 'trash', 'auto-draft', 'inherit' )
					AND t.source_language_code IS NULL
					AND t.language_code <> %s
					AND NOT EXISTS (
						SELECT 1 FROM {$wpdb->prefix}icl_translations other
						WHERE other.trid = t.trid AND other.language_code = %s
					)
				ORDER BY p.ID
				LIMIT 1",
				array_merge(
					[ LocalCanvasRepository::META_CANVAS_ID, $canvasId, self::META_KEY ],
					$keys,
					[ LocalCanvasRepository::CANVAS_ELEMENT_TYPE, $canvasPostId, CanvasHooks::POST_TYPE, $language, $language ]
				)
			)
		);

		return $row ?: null;
	}

	private function getTranslatedKeys( string $key ) {
		if ( 1 !== preg_match( self::TERM_KEY_PATTERN, $key, $matches ) || ! $this->sitepress->is_translated_taxonomy( $matches[1] ) ) {
			return [];
		}

		list( , $taxonomy, $termId ) = $matches;

		$keys = [];
		foreach ( array_keys( $this->sitepress->get_active_languages() ) as $language ) {
			$translatedId = (int) apply_filters( 'wpml_object_id', (int) $termId, $taxonomy, false, $language );
			if ( $translatedId ) {
				$keys[ $language ] = sprintf( 'term:%s:%d', $taxonomy, $translatedId );
			}
		}

		return $keys;
	}
}
