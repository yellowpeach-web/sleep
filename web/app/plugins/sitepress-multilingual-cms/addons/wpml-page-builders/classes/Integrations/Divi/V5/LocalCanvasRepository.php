<?php

namespace WPML\Compatibility\Divi\V5;

use WPML\PB\Helper\TranslationContent;
use SitePress;

class LocalCanvasRepository {

	const META_CANVAS_ID = '_divi_canvas_id';

	const CANVAS_ELEMENT_TYPE = 'post_' . CanvasHooks::POST_TYPE;

	const COPIED_META_KEYS = [
		self::META_CANVAS_ID,
		'_divi_canvas_created_at',
		'_divi_canvas_append_to_main',
		'_divi_canvas_z_index',
	];

	private $sitepress;

	private $wpdb;

	public function __construct( SitePress $sitepress ) {
		$this->sitepress = $sitepress;
		$this->wpdb      = $sitepress->get_wpdb();
	}

	public function isCanvasTranslatable() {
		return (bool) $this->sitepress->is_translated_post_type( CanvasHooks::POST_TYPE );
	}

	public function getLocalCanvases( int $postId ) {
		$postType = get_post_type( $postId );
		$postLang = $postType ? $this->sitepress->get_language_for_element( $postId, 'post_' . $postType ) : null;
		if ( ! $postLang ) {
			return [];
		}

		$originalId = (int) $this->sitepress->get_original_element_id( $postId, 'post_' . $postType, false, true, false, true ) ?: $postId;

		$wpdb = $this->wpdb;

		$ids = $wpdb->get_col(
			$wpdb->prepare(
				"SELECT p.ID
				FROM {$wpdb->posts} p
				JOIN {$wpdb->postmeta} parent ON parent.post_id = p.ID AND parent.meta_key = %s AND parent.meta_value IN ( %s, %s )
				JOIN {$wpdb->prefix}icl_translations t ON t.element_id = p.ID AND t.element_type = %s
				WHERE p.post_type = %s
					AND p.post_status NOT IN ( 'trash', 'auto-draft', 'inherit' )
					AND t.language_code = %s
				ORDER BY parent.meta_value = %s DESC, p.ID",
				CanvasHooks::META_KEY,
				(string) $postId,
				(string) $originalId,
				self::CANVAS_ELEMENT_TYPE,
				CanvasHooks::POST_TYPE,
				$postLang,
				(string) $originalId
			)
		);

		$canvases = [];
		foreach ( $ids as $id ) {
			$canvas   = get_post( (int) $id );
			$canvasId = $canvas ? $this->getCanvasId( $canvas ) : '';
			if ( $canvasId && ! isset( $canvases[ $canvasId ] ) ) {
				$canvases[ $canvasId ] = $canvas;
			}
		}

		return array_values( $canvases );
	}

	public function isLocalCanvas( int $postId ) {
		return CanvasHooks::POST_TYPE === get_post_type( $postId ) && $this->getStoredParent( $postId ) > 0;
	}

	public function getCanvasId( \WP_Post $canvas ) {
		return (string) get_post_meta( $canvas->ID, self::META_CANVAS_ID, true );
	}

	public function getTranslation( \WP_Post $canvas, string $lang ) {
		$trid         = $this->sitepress->get_element_trid( $canvas->ID, self::CANVAS_ELEMENT_TYPE );
		$translations = $trid ? $this->sitepress->get_element_translations( $trid, self::CANVAS_ELEMENT_TYPE, false, true ) : [];

		return isset( $translations[ $lang ] ) ? (int) $translations[ $lang ]->element_id : 0;
	}

	public function findUnconnectedCanvas( \WP_Post $canvas, string $lang, int $translatedPostId ) {
		$wpdb = $this->wpdb;

		return (int) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT p.ID
				FROM {$wpdb->posts} p
				JOIN {$wpdb->postmeta} cid ON cid.post_id = p.ID AND cid.meta_key = %s AND cid.meta_value = %s
				JOIN {$wpdb->postmeta} parent ON parent.post_id = p.ID AND parent.meta_key = %s
				JOIN {$wpdb->prefix}icl_translations t ON t.element_id = p.ID AND t.element_type = %s
				WHERE p.post_type = %s
					AND t.language_code = %s
					AND parent.meta_value IN ( %d, %d )
					AND NOT EXISTS (
						SELECT 1 FROM {$wpdb->prefix}icl_translations other
						WHERE other.trid = t.trid AND other.translation_id <> t.translation_id
					)
				ORDER BY p.ID
				LIMIT 1",
				self::META_CANVAS_ID,
				$this->getCanvasId( $canvas ),
				CanvasHooks::META_KEY,
				self::CANVAS_ELEMENT_TYPE,
				CanvasHooks::POST_TYPE,
				$lang,
				$this->getStoredParent( $canvas->ID ),
				$translatedPostId
			)
		);
	}

	public function connect( int $translationId, \WP_Post $canvas, string $lang ) {
		$sourceLang = $this->sitepress->get_language_for_element( $canvas->ID, self::CANVAS_ELEMENT_TYPE );
		$trid       = $this->sitepress->get_element_trid( $canvas->ID, self::CANVAS_ELEMENT_TYPE );

		if ( ! $sourceLang || ! $trid ) {
			return false;
		}

		$this->sitepress->set_element_language_details( $translationId, self::CANVAS_ELEMENT_TYPE, $trid, $lang, $sourceLang );

		return true;
	}

	public function create( \WP_Post $canvas, string $lang ) {
		$translationId = (int) wpml_update_escaped_post(
			[
				'post_type'    => CanvasHooks::POST_TYPE,
				'post_status'  => $canvas->post_status,
				'post_title'   => $canvas->post_title,
				'post_content' => $canvas->post_content,
			],
			$lang
		);

		if ( ! $translationId ) {
			return 0;
		}

		foreach ( self::COPIED_META_KEYS as $key ) {
			$value = get_post_meta( $canvas->ID, $key, true );
			if ( '' !== $value ) {
				update_post_meta( $translationId, $key, $value );
			}
		}

		update_post_meta( $translationId, CanvasHooks::META_KEY, $this->getStoredParent( $canvas->ID ) );

		return $translationId;
	}

	public function updateContent( int $canvasId, string $content, string $lang ) {
		return TranslationContent::update(
			[
				'ID'           => $canvasId,
				'post_content' => $content,
			],
			$lang
		);
	}

	public function getLastLocationOfOtherStrings( array $stringIds ) {
		$wpdb = $this->wpdb;

		return (int) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT MAX( s.location ) FROM {$wpdb->prefix}icl_strings s
				JOIN {$wpdb->prefix}icl_strings canvas ON canvas.string_package_id = s.string_package_id
				WHERE canvas.id = %d AND s.id NOT IN ( " . implode( ',', array_map( 'intval', $stringIds ) ) . ' )',
				$stringIds[0]
			)
		);
	}

	private function getStoredParent( int $canvasPostId ) {
		$cache = update_meta_cache( 'post', [ $canvasPostId ] );

		return (int) ( $cache[ $canvasPostId ][ CanvasHooks::META_KEY ][0] ?? 0 );
	}
}
