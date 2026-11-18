<?php

namespace WPML\Compatibility\Divi\V5;

use WPML\PB\Gutenberg\StringsInBlock\StringsInBlock;
use WPML\PB\TranslationJob\Groups;

class LocalCanvasStrings implements \IWPML_Backend_Action, \IWPML_Frontend_Action {

	const PACKAGE_KIND = \WPML_Gutenberg_Integration::PACKAGE_ID;

	const STRING_NAME_PREFIX = 'divi-canvas-';

	const PRIORITY_AFTER_PAGE_REBUILD = \WPML_Page_Builders_Integration::STRINGS_TRANSLATED_PRIORITY + 1;

	private $finders;

	private $gutenberg;

	private $canvases;

	private $strings;

	public function __construct(
		StringsInBlock $finders,
		\WPML_Gutenberg_Integration $gutenberg,
		LocalCanvasRepository $canvases,
		\WPML_ST_String_Factory $strings
	) {
		$this->finders   = $finders;
		$this->gutenberg = $gutenberg;
		$this->canvases  = $canvases;
		$this->strings   = $strings;
	}

	public function add_hooks() {
		add_filter( 'wpml_pb_gutenberg_register_more_strings', [ $this, 'registerCanvasStrings' ], 10, 3 );
		add_action( 'wpml_page_builder_string_translated', [ $this, 'translateCanvases' ], self::PRIORITY_AFTER_PAGE_REBUILD, 5 );
		add_filter( 'wpml_tm_dashboard_exclude_posts_with_meta_keys', [ $this, 'excludeFromDashboard' ], 10, 2 );
		add_filter( 'wpml_exclude_post_from_auto_translate', [ $this, 'excludeFromAutomaticTranslation' ], 10, 2 );
	}

	public function excludeFromDashboard( $metaKeys, $postType ) {
		if ( is_array( $metaKeys ) && CanvasHooks::POST_TYPE === $postType ) {
			$metaKeys[] = CanvasHooks::META_KEY;
		}

		return $metaKeys;
	}

	public function excludeFromAutomaticTranslation( $isExcluded, $postId ) {
		if ( $isExcluded || ! is_numeric( $postId ) ) {
			return $isExcluded;
		}

		return $this->canvases->isLocalCanvas( (int) $postId ) ? true : $isExcluded;
	}

	public function registerCanvasStrings( $values, $post, $packageData ) {
		if ( ! is_array( $values ) || ! $post instanceof \WP_Post || self::PACKAGE_KIND !== ( $packageData['kind'] ?? null ) || ! $this->canvases->isCanvasTranslatable() ) {
			return $values;
		}

		$stringIds = [];

		foreach ( $this->canvases->getLocalCanvases( $post->ID ) as $canvas ) {
			$canvasId = $this->canvases->getCanvasId( $canvas );

			foreach ( $this->findStrings( $canvas ) as $string ) {
				$name = $this->getStringName( $canvasId, $string->id );

				do_action(
					'wpml_register_string',
					$string->value,
					$name,
					$packageData,
					Groups::buildGroupLabel( [ self::STRING_NAME_PREFIX . $canvasId ], $string->name ),
					$string->type
				);

				$stringIds[] = (int) apply_filters( 'wpml_string_id_from_package', 0, $packageData, $name, $string->value );
				$values[]    = $string->value;
			}
		}

		$this->placeAfterPageStrings( array_values( array_filter( $stringIds ) ) );

		return $values;
	}

	private function placeAfterPageStrings( array $stringIds ) {
		if ( ! $stringIds ) {
			return;
		}

		$location = $this->canvases->getLastLocationOfOtherStrings( $stringIds );

		foreach ( $stringIds as $stringId ) {
			$this->strings->find_by_id( $stringId )->set_location( ++$location );
		}
	}

	public function translateCanvases( $kind, $translatedPostId, $originalPost, $stringTranslations, $lang ) {
		if ( self::PACKAGE_KIND !== $kind || ! $originalPost instanceof \WP_Post || ! $this->canvases->isCanvasTranslatable() ) {
			return;
		}

		$isCanvasWritten = false;

		foreach ( $this->canvases->getLocalCanvases( $originalPost->ID ) as $canvas ) {
			$translations = $this->getCanvasTranslations( $canvas, (array) $stringTranslations );

			if ( $this->hasTranslation( $translations, $lang ) ) {
				$isCanvasWritten = $this->saveCanvasTranslation( $canvas, $translations, $lang, (int) $translatedPostId ) || $isCanvasWritten;
			}
		}

		if ( $isCanvasWritten ) {
			$this->clearCanvasCaches( (int) $translatedPostId );
		}
	}

	private function getCanvasTranslations( \WP_Post $canvas, array $stringTranslations ) {
		$canvasId     = $this->canvases->getCanvasId( $canvas );
		$translations = [];

		foreach ( $this->findStrings( $canvas ) as $string ) {
			$name = $this->getStringName( $canvasId, $string->id );
			if ( isset( $stringTranslations[ $name ] ) ) {
				$translations[ $string->id ] = $stringTranslations[ $name ];
			}
		}

		return $translations;
	}

	private function hasTranslation( array $translations, string $lang ) {
		foreach ( $translations as $translation ) {
			if ( isset( $translation[ $lang ] ) ) {
				return true;
			}
		}

		return false;
	}

	private function findStrings( \WP_Post $canvas ) {
		return $this->findStringsInBlocks( \WPML_Gutenberg_Integration::parse_blocks( $canvas->post_content ) );
	}

	private function findStringsInBlocks( array $blocks ) {
		$strings = [];

		foreach ( $blocks as $block ) {
			$block = \WPML_Gutenberg_Integration::sanitize_block( $block );

			foreach ( $this->finders->find( $block ) as $string ) {
				$strings[ $string->id ] = $string;
			}

			if ( ! empty( $block->innerBlocks ) ) {
				$strings += $this->findStringsInBlocks( $block->innerBlocks );
			}
		}

		return $strings;
	}

	private function saveCanvasTranslation( \WP_Post $canvas, array $translations, string $lang, int $translatedPostId ) {
		$translationId = $this->canvases->getTranslation( $canvas, $lang );

		if ( ! $translationId ) {
			$translationId = $this->canvases->findUnconnectedCanvas( $canvas, $lang, $translatedPostId )
				?: $this->canvases->create( $canvas, $lang );

			if ( ! $translationId || ! $this->canvases->connect( $translationId, $canvas, $lang ) ) {
				return false;
			}
		}

		$content = $this->gutenberg->replace_strings_in_blocks( $canvas->post_content, $translations, $lang );

		$content = apply_filters( 'wpml_pb_gutenberg_translated_content_before_save', $content, $translationId, $lang, $canvas );

		$isUpdated = $this->canvases->updateContent( $translationId, $content, $lang );

		if ( $isUpdated ) {
			$this->fixLinks( $translationId, $lang );
		}

		return $isUpdated;
	}

	private function fixLinks( int $translationId, string $lang ) {
		$proTranslation = $GLOBALS['ICL_Pro_Translation'] ?? null;

		if ( is_object( $proTranslation ) && method_exists( $proTranslation, 'fix_links_to_translated_content' ) ) {
			$proTranslation->fix_links_to_translated_content( $translationId, $lang, 'post' );
		}
	}

	private function getStringName( string $canvasId, string $stringId ) {
		return md5( self::STRING_NAME_PREFIX . $canvasId . '-' . $stringId );
	}

	private function clearCanvasCaches( int $postId ) {
		if ( class_exists( '\ET_Core_PageResource' ) && method_exists( '\ET_Core_PageResource', 'clear_post_meta_caches' ) ) {
			\ET_Core_PageResource::clear_post_meta_caches( $postId );
		}

		$clearCanvasIdsCache = [ '\ET\Builder\FrontEnd\Assets\DynamicAssetsUtils', 'clear_canvas_ids_cache' ];
		if ( is_callable( $clearCanvasIdsCache ) ) {
			call_user_func( $clearCanvasIdsCache, $postId );
		}
	}
}
