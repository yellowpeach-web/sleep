<?php

namespace WPML\WPSEO\YoastSEO\Presentation;

use WPML\FP\Logic;
use WPML\FP\Maybe;
use WPML\FP\Obj;
use WPML\Settings\LanguageNegotiation;
use WPML\WPSEO\YoastSEO\Utils;
use Yoast\WP\SEO\Presentations\Indexable_Presentation;
use Yoast\WP\SEO\Models\Indexable;
use function WPML\FP\pipe;

class Hooks implements \IWPML_Frontend_Action {

	const OPTION_KEY = 'wpseo_titles';

	const OPEN_GRAPH_PRIORITY = 10;

	const AUTHOR_META_KEYS = [
		'title'    => Utils::KEY_META_TITLE,
		'metadesc' => Utils::KEY_USER_META_DESC,
	];

	public function add_hooks() {
		add_filter( 'wpseo_title', [ $this, 'translateTitle' ], 10, 2 );
		add_filter( 'wpseo_metadesc', [ $this, 'translateDescription' ], 10, 2 );

		add_filter( 'wpseo_opengraph_title', [ $this, 'translateOpenGraphTitle' ], self::OPEN_GRAPH_PRIORITY, 2 );
		add_filter( 'wpseo_opengraph_desc', [ $this, 'translateOpenGraphDescription' ], self::OPEN_GRAPH_PRIORITY, 2 );

		add_filter( 'wpseo_breadcrumb_indexables', [ $this, 'translateBreadcrumbs' ] );

		add_filter( 'wpseo_frontend_presentation', [ $this, 'translatePermalinks' ] );

		add_filter( 'wpseo_frontend_presentation', [ $this, 'setSchemaGraphData' ], 10, 2 );
	}

	public function translateOpenGraphTitle( $title, $presentation ) {
		return $this->isPostTypeArchiveWithSocialTemplate( 'title', $presentation )
			? $this->translate( 'social-title', $title, $presentation )
			: $this->translateTitle( $title, $presentation );
	}

	public function translateOpenGraphDescription( $description, $presentation ) {
		return $this->isPostTypeArchiveWithSocialTemplate( 'description', $presentation )
			? $this->translate( 'social-description', $description, $presentation )
			: $this->translateDescription( $description, $presentation );
	}

	private function isPostTypeArchiveWithSocialTemplate( $field, $presentation ) {
		$postTypeArchive = 'post-type-archive';

		return $postTypeArchive === Obj::path( [ 'model', 'object_type' ], $presentation )
			&& (bool) apply_filters( 'Yoast\\WP\\SEO\\open_graph_' . $field . '_' . $postTypeArchive, '', Obj::path( [ 'model', 'object_sub_type' ], $presentation ) );
	}

	public function translateTitle( $title, $presentation ) {
		return $this->translate( 'title', $title, $presentation );
	}

	public function translateDescription( $description, $presentation ) {
		return $this->translate( 'metadesc', $description, $presentation );
	}

	public function translateBreadcrumbTitle( $title, $presentation ) {
		return $this->translate( 'bctitle', $title, $presentation );
	}

	private function translate( $type, $text, $presentation ) {
		if ( 'user' === Obj::path( [ 'model', 'object_type' ], $presentation ) ) {
			return $this->translateAuthorMeta( $type, $text, $presentation );
		}

		$translation = Obj::prop( $this->getOptionKey( $type, $presentation ), get_option( self::OPTION_KEY, [] ) );

		if ( $translation ) {
			$text = wpseo_replace_vars( $translation, $presentation );
		}

		return $text;
	}

	private function translateAuthorMeta( $type, $text, $presentation ) {
		$metaKey = Obj::prop( $type, self::AUTHOR_META_KEYS );

		if ( ! $metaKey ) {
			return $text;
		}

		$value = get_the_author_meta( $metaKey, $presentation->model->object_id );

		return $value ? wpseo_replace_vars( $value, $presentation ) : $text;
	}

	private function getOptionKey( $type, $presentation ) {
		$systemPageSubType = wpml_collect(
			[
				'search-result' => 'search',
			]
		)->get( $presentation->model->object_sub_type, $presentation->model->object_sub_type );

		return wpml_collect(
			[
				'post-type-archive'          => $type . '-ptarchive-' . $presentation->model->object_sub_type,
				'system-page'                => $type . '-' . $systemPageSubType . '-wpseo',
				Utils::OBJECT_TYPE_HOME_PAGE => $type . '-home-wpseo',
			]
		)->get( $presentation->model->object_type, '' );
	}

	public function translateBreadcrumbs( $indexables ) {
		foreach ( $indexables as &$indexable ) {
			if ( 'post-type-archive' === $indexable->object_type ) {
				$getDefaultLabel = function ( $indexable ) {
					$post_object = get_post_type_object( $indexable->object_sub_type );
					return $post_object ? $post_object->labels->name : $indexable->breadcrumb_title;
				};

				$getYoastLabel = function ( $indexable ) {
					return $this->translateBreadcrumbTitle(
						$indexable->breadcrumb_title,
						(object) [ 'model' => $indexable ]
					);
				};

				$indexable->breadcrumb_title = $getDefaultLabel( $indexable );
				$indexable->breadcrumb_title = $getYoastLabel( $indexable );
				$indexable->permalink        = self::getPostTypeArchiveLink( $indexable->object_sub_type, $indexable->permalink );
			} elseif ( 'term' === $indexable->object_type ) {
				$term                        = apply_filters( 'wpml_object_id', $indexable->object_id, $indexable->object_sub_type, true );
				$indexable->permalink        = self::getTermLink( $term, $indexable->permalink );
				$indexable->breadcrumb_title = Obj::prop( 'name', get_term( $term ) ) ?: $indexable->breadcrumb_title;
			} elseif ( $indexable->permalink ) {
				$indexable->permalink = apply_filters( 'wpml_permalink', $indexable->permalink );
			}
		}

		return $indexables;
	}

	public function translatePermalinks( $presentation ) {
		$newLink      = null;
		$originalLink = Obj::path( [ 'model', 'permalink' ], $presentation );
		$objectType   = Obj::path( [ 'model', 'object_type' ], $presentation );

		if ( 'post' === $objectType ) {
			$newLink = self::getPermalink( $presentation->model->object_id, $originalLink );
		} elseif ( 'term' === $objectType ) {
			$newLink = self::getTermLink( $presentation->model->object_id, $originalLink );
		} elseif ( 'post-type-archive' === $objectType ) {
			$newLink = self::getPostTypeArchiveLink( $presentation->model->object_sub_type, $originalLink );
		} elseif ( 'user' === $objectType ) {
			$newLink = self::getAuthorLink( $presentation->model->object_id, $originalLink );
		}

		if ( $newLink ) {
			return Obj::assocPath( [ 'model', 'permalink' ], $newLink, $presentation );
		}

		return $presentation;
	}

	public function setSchemaGraphData( $presentation, $context ) {
		$context->site_url = apply_filters( 'wpml_permalink', $context->site_url );

		if ( LanguageNegotiation::isDomain() && ! empty( $context->company_logo_meta ) ) {
			$translateImg                      = wp_get_attachment_image_src( $context->company_logo_meta['id'], 'full' );
			$context->company_logo_meta['url'] = ! empty( $translateImg[0] ) ? $translateImg[0] : $context->company_logo_meta['url'];
		}

		return $presentation;
	}

	private static function getPermalink( $post, $fallback ) {
		return Maybe::fromNullable( get_permalink( $post ) )
			->getOrElse( $fallback );
	}

	private static function getTermLink( $term, $fallback ) {
		return Maybe::fromNullable( get_term_link( $term ) )
			->filter( pipe( 'is_wp_error', Logic::not() ) )
			->getOrElse( $fallback );
	}

	private static function getAuthorLink( $userId, $fallback ) {
		return Maybe::fromNullable( get_author_posts_url( $userId ) )
			->getOrElse( $fallback );
	}

	private static function getPostTypeArchiveLink( $postType, $fallback ) {
		return Maybe::fromNullable( get_post_type_archive_link( $postType ) )
			->getOrElse( $fallback );
	}
}
