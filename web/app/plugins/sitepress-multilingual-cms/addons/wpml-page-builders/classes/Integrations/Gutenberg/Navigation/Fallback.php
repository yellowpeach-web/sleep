<?php

namespace WPML\PB\Gutenberg\Navigation;

use WPML\Convert\Ids;

class Fallback implements \WPML\PB\Gutenberg\Integration {

	private $sitepress;

	private $wpdb;

	private $picks = [];

	private $armed = null;

	private $foreign = false;

	private $inRoute = false;

	private $switched = false;

	public function __construct( \SitePress $sitepress, \wpdb $wpdb ) {
		$this->sitepress = $sitepress;
		$this->wpdb      = $wpdb;
	}

	public function add_hooks() {
		add_filter( 'wp_navigation_should_create_fallback', [ $this, 'onLookup' ] );
		add_filter( 'rest_request_before_callbacks', [ $this, 'enterRoute' ], 10, 3 );
		add_filter( 'rest_request_after_callbacks', [ $this, 'leaveRoute' ], 10, 3 );
		add_filter( 'block_core_navigation_render_fallback', [ $this, 'translateLinks' ] );
	}

	public function onLookup( $create ) {
		$this->foreign = false;
		if ( ! $this->sitepress->is_translated_post_type( 'wp_navigation' ) ) {
			return $create;
		}

		$lang = $this->sitepress->get_current_language();
		if ( $this->inRoute || ! array_key_exists( $lang, $this->picks ) ) {
			$this->picks[ $lang ] = $this->pick( $lang );
		}
		$row           = $this->picks[ $lang ];
		$this->armed   = $row ? (int) $row->ID : null;
		$this->foreign = $row && $row->language_code !== $lang;
		add_filter( 'posts_pre_query', [ $this, 'answerLookup' ], 10, 2 );

		if ( ! $this->inRoute ) {
			return false;
		}

		if ( null === $this->armed && $create ) {
			$this->sitepress->switch_lang( $this->sitepress->get_default_language() );
			$this->switched = true;
		}

		return $create;
	}

	public function answerLookup( $posts, $query ) {
		if ( 'wp_navigation' !== $query->get( 'post_type' ) ) {
			return $posts;
		}
		remove_filter( 'posts_pre_query', [ $this, 'answerLookup' ] );

		return $this->armed ? [ $this->armed ] : [];
	}

	public function translateLinks( $blocks ) {
		if ( ! $this->foreign || ! is_array( $blocks ) ) {
			return $blocks;
		}
		foreach ( $blocks as &$block ) {
			$attrs = isset( $block['attrs'] ) ? $block['attrs'] : [];
			if ( ! empty( $attrs['id'] ) && in_array( $block['blockName'], [ 'core/navigation-link', 'core/navigation-submenu' ], true ) ) {
				$url = $this->translatedUrl( (int) $attrs['id'], isset( $attrs['kind'] ) ? $attrs['kind'] : '' );
				if ( $url ) {
					$block['attrs']['url'] = $url;
				}
			}
			if ( ! empty( $block['innerBlocks'] ) ) {
				$block['innerBlocks'] = $this->translateLinks( $block['innerBlocks'] );
			}
		}

		return $blocks;
	}

	private function translatedUrl( $id, $kind ) {
		if ( 'post-type' === $kind ) {
			$converted = (int) Ids::convert( $id, 'any_post', true );
			$url       = $converted && $converted !== $id ? get_permalink( $converted ) : null;
		} elseif ( 'taxonomy' === $kind ) {
			$converted = (int) Ids::convert( $id, 'any_term', true );
			$url       = $converted && $converted !== $id ? get_term_link( $converted ) : null;
		} else {
			return null;
		}

		return $url && ! is_wp_error( $url ) ? $url : null;
	}

	public function enterRoute( $response, $handler, $request ) {
		if ( $this->isFallbackRoute( $handler ) ) {
			$this->inRoute = true;
		}

		return $response;
	}

	public function leaveRoute( $response, $handler, $request ) {
		if ( $this->isFallbackRoute( $handler ) ) {
			$this->inRoute = false;
			if ( $this->switched ) {
				$this->sitepress->switch_lang();
				$this->switched = false;
			}
		}

		return $response;
	}

	private function isFallbackRoute( $handler ) {
		return is_array( $handler )
			&& isset( $handler['callback'] ) && is_array( $handler['callback'] )
			&& isset( $handler['callback'][0] )
			&& is_a( $handler['callback'][0], 'WP_REST_Navigation_Fallback_Controller' );
	}

	private function pick( $lang ) {
		$default = $this->sitepress->get_default_language();

		$sql = $this->wpdb->prepare(
			"SELECT p.ID, t.trid, t.language_code, t.source_language_code
			 FROM {$this->wpdb->posts} p
			 INNER JOIN {$this->wpdb->prefix}icl_translations t
			   ON t.element_id = p.ID AND t.element_type = 'post_wp_navigation'
			 WHERE p.post_type = 'wp_navigation' AND p.post_status = 'publish'
			   AND t.language_code IN ( %s, %s )
			 ORDER BY p.post_date DESC, p.ID DESC",
			$lang,
			$default
		);
		$rows = (array) $this->wpdb->get_results( $sql );

		$first = function ( callable $test ) use ( $rows ) {
			foreach ( $rows as $row ) {
				if ( $test( $row ) ) {
					return $row;
				}
			}

			return null;
		};
		$isOriginal = function ( $row ) {
			return empty( $row->source_language_code );
		};

		$defaultPick = $first( function ( $row ) use ( $default, $isOriginal ) {
			return $default === $row->language_code && $isOriginal( $row );
		} ) ?: $first( function ( $row ) use ( $default ) {
			return $default === $row->language_code;
		} );

		$current = null;
		if ( $lang !== $default ) {
			$current = ( $defaultPick ? $first( function ( $row ) use ( $lang, $defaultPick ) {
				return $lang === $row->language_code && $row->trid === $defaultPick->trid;
			} ) : null ) ?: $first( function ( $row ) use ( $lang, $isOriginal ) {
				return $lang === $row->language_code && $isOriginal( $row );
			} );
		}

		return $current ?: $defaultPick;
	}
}
