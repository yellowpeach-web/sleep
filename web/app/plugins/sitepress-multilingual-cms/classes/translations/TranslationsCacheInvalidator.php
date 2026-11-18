<?php

namespace WPML\Translation;

class TranslationsCacheInvalidator implements \IWPML_Action, \IWPML_Frontend_Action_Loader, \IWPML_Backend_Action_Loader {

	private $wpdb;

	private $sitepress;

	private $cache;

	private $postTranslations;

	private $deleting = [];

	public function __construct( ?\wpdb $wpdb = null, ?\SitePress $sitepress = null, ?\WPML_WP_Cache $cache = null, ?\WPML_Post_Translation $postTranslations = null ) {
		$this->wpdb             = $wpdb;
		$this->sitepress        = $sitepress;
		$this->cache            = $cache;
		$this->postTranslations = $postTranslations;
	}

	public function create() {
		return $this;
	}

	public function add_hooks() {
		add_action( 'wpml_translation_update', [ $this, 'translationUpdated' ], 10, 1 );
		add_action( 'icl_set_element_language', [ $this, 'elementLanguageSet' ], 10, 4 );
		add_action( 'clean_post_cache', [ $this, 'postCacheCleaned' ], 10, 2 );
	}

	public function translationUpdated( $args ) {
		if ( ! is_array( $args ) ) {
			return;
		}

		if ( ! empty( $args['trid'] ) ) {
			$this->flushTrids( [ $args['trid'] ] );

			return;
		}

		$type = isset( $args['type'] ) && is_string( $args['type'] ) ? $args['type'] : '';
		$row  = $this->rowKey( $args );

		if ( 'after_delete' === $type ) {
			if ( null !== $row && isset( $this->deleting[ $row ] ) ) {
				$this->flushTrids( [ $this->deleting[ $row ] ] );
				unset( $this->deleting[ $row ] );
			}

			return;
		}

		if ( null === $row ) {
			\WPML_Translations::flush_cache_for_all_trids( $this->cache() );

			return;
		}

		$trid = $this->tridOfRow( $args );
		$this->flushTrids( [ $trid ] );
		if ( 'before_delete' === $type && $trid ) {
			$this->deleting[ $row ] = $trid;
		}
	}

	public function termCacheCleaned( $ids, $taxonomy ) {
		$ids = array_values( array_filter( array_map( 'intval', (array) $ids ) ) );
		if ( ! $ids || ! is_string( $taxonomy ) || '' === $taxonomy ) {
			$this->cache()->flush_group_cache();

			return;
		}

		$wpdb = $this->wpdb();
		$this->flushTrids(
			$wpdb->get_col(
				$wpdb->prepare(
					"SELECT t.trid FROM {$wpdb->prefix}icl_translations t
					JOIN {$wpdb->term_taxonomy} tt ON tt.term_taxonomy_id = t.element_id
					WHERE t.element_type = %s AND tt.taxonomy = %s AND tt.term_id IN (" . implode( ',', array_fill( 0, count( $ids ), '%d' ) ) . ')',
					array_merge( [ 'tax_' . $taxonomy, $taxonomy ], $ids )
				)
			)
		);
	}

	public function elementLanguageSet( $translationId, $elementId, $languageCode, $trid ) {
		$this->flushTrids( [ $trid ] );
	}

	public function postCacheCleaned( $postId, $post = null ) {
		$sitepress = $this->sitepress();
		if ( ! $post instanceof \WP_Post || ! $sitepress instanceof \SitePress || ! $sitepress->is_translated_post_type( $post->post_type ) ) {
			return;
		}

		$trid = $this->postTranslations() ? $this->postTranslations()->get_cached_trid( (int) $postId ) : null;
		if ( null === $trid ) {
			$trid = $this->tridOfRow(
				[
					'element_id'   => (int) $postId,
					'element_type' => 'post_' . $post->post_type,
				]
			);
		}

		$this->flushTrids( [ $trid ] );
	}

	private function rowKey( array $args ) {
		if ( ! empty( $args['translation_id'] ) ) {
			return 'translation:' . (int) $args['translation_id'];
		}
		if ( empty( $args['element_id'] ) ) {
			return null;
		}
		if ( ! empty( $args['element_type'] ) && is_string( $args['element_type'] ) ) {
			return 'element:' . $args['element_type'] . ':' . (int) $args['element_id'];
		}
		if ( ! empty( $args['context'] ) && is_string( $args['context'] ) ) {
			return 'context:' . $args['context'] . ':' . (int) $args['element_id'];
		}

		return null;
	}

	private function tridOfRow( array $args ) {
		$wpdb = $this->wpdb();

		if ( ! empty( $args['translation_id'] ) ) {
			return (int) $wpdb->get_var(
				$wpdb->prepare(
					"SELECT trid FROM {$wpdb->prefix}icl_translations WHERE translation_id = %d",
					(int) $args['translation_id']
				)
			);
		}

		if ( ! empty( $args['element_type'] ) && is_string( $args['element_type'] ) ) {
			return (int) $wpdb->get_var(
				$wpdb->prepare(
					"SELECT trid FROM {$wpdb->prefix}icl_translations WHERE element_id = %d AND element_type = %s",
					(int) $args['element_id'],
					$args['element_type']
				)
			);
		}

		return (int) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT trid FROM {$wpdb->prefix}icl_translations WHERE element_id = %d AND element_type LIKE %s LIMIT 1",
				(int) $args['element_id'],
				$wpdb->esc_like( $args['context'] . '_' ) . '%'
			)
		);
	}

	private function flushTrids( array $trids ) {
		\WPML_Translations::flush_cache_for_trids( $trids, $this->cache() );
	}

	private function wpdb() {
		if ( ! $this->wpdb ) {
			global $wpdb;
			$this->wpdb = $wpdb;
		}

		return $this->wpdb;
	}

	private function postTranslations() {
		if ( ! $this->postTranslations ) {
			global $wpml_post_translations;
			$this->postTranslations = $wpml_post_translations instanceof \WPML_Post_Translation ? $wpml_post_translations : null;
		}

		return $this->postTranslations;
	}

	private function sitepress() {
		if ( ! $this->sitepress ) {
			global $sitepress;
			$this->sitepress = $sitepress;
		}

		return $this->sitepress;
	}

	private function cache() {
		if ( ! $this->cache ) {
			$this->cache = new \WPML_WP_Cache( WPML_ELEMENT_TRANSLATIONS_CACHE_GROUP );
		}

		return $this->cache;
	}
}
