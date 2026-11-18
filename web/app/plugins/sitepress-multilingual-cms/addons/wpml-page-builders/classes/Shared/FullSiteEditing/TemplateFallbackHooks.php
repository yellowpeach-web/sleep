<?php

namespace WPML\PB\FullSiteEditing;

use WPML\PB\Gutenberg\Integration;

class TemplateFallbackHooks implements \IWPML_Frontend_Action, Integration {

	private $fallbacks = [];

	public function add_hooks() {
		add_filter( 'category_template_hierarchy', [ $this, 'extendCategoryHierarchy' ] );
		add_filter( 'tag_template_hierarchy', [ $this, 'extendTagHierarchy' ] );
		add_filter( 'taxonomy_template_hierarchy', [ $this, 'extendTaxonomyHierarchy' ] );
		add_filter( 'page_template_hierarchy', [ $this, 'extendPageHierarchy' ] );
	}

	public function extendCategoryHierarchy( $templates ) {
		return $this->extendTermHierarchy( $templates, 'category' );
	}

	public function extendTagHierarchy( $templates ) {
		return $this->extendTermHierarchy( $templates, 'tag' );
	}

	public function extendTaxonomyHierarchy( $templates ) {
		return $this->extendTermHierarchy( $templates, 'taxonomy' );
	}

	public function extendPageHierarchy( $templates ) {
		$language = $this->visitorLanguage();
		if ( null === $language || ! is_array( $templates ) ) {
			return $templates;
		}

		$id = (int) get_queried_object_id();
		if ( ! $id ) {
			return $templates;
		}

		$assigned = get_page_template_slug( $id );
		if ( is_string( $assigned ) && '' !== $assigned && 'default' !== $assigned ) {
			$localized = TemplateLocalizer::getLocalizedTemplateSlug( $assigned, $language );
			if ( $localized !== $assigned ) {
				$templates = $this->insertBefore( $templates, $assigned, [ $localized ] );
			}
		}

		$templates = $this->localizePinnedPageEntries( $templates, $language, is_string( $assigned ) ? $assigned : '' );

		$fallbacks = $this->pageFallbacks( $id );
		if ( ! $fallbacks ) {
			return $templates;
		}

		$pagename = get_query_var( 'pagename' );
		if ( ! $pagename ) {
			$page     = get_queried_object();
			$pagename = is_object( $page ) && ! empty( $page->post_name ) ? $page->post_name : '';
		}

		$templates = $this->insertAfter( $templates, "page-{$pagename}.php", $this->entries( 'page-', array_filter( $fallbacks ) ) );

		return $this->insertAfter( $templates, "page-{$id}.php", $this->entries( 'page-', array_keys( $fallbacks ) ) );
	}

	private function localizePinnedPageEntries( array $templates, $language, $assigned ) {
		foreach ( $templates as $entry ) {
			if ( ! is_string( $entry ) || $entry === $assigned || ! preg_match( '/^page-.+$/', $entry ) || '.php' === substr( $entry, -4 ) ) {
				continue;
			}

			$localized = TemplateLocalizer::getLocalizedTemplateSlug( $entry, $language );
			if ( $localized !== $entry ) {
				$templates = $this->insertBefore( $templates, $entry, [ $localized ] );
			}
		}

		return $templates;
	}

	private function extendTermHierarchy( $templates, $type ) {
		$language = $this->visitorLanguage();
		if ( null === $language || ! is_array( $templates ) ) {
			return $templates;
		}

		$term = get_queried_object();
		if ( ! is_object( $term ) || empty( $term->term_id ) || empty( $term->slug ) || empty( $term->taxonomy ) ) {
			return $templates;
		}

		$fallbacks = $this->termFallbacks( $term );
		if ( ! $fallbacks ) {
			return $templates;
		}

		$prefix    = 'taxonomy' === $type ? "taxonomy-{$term->taxonomy}-" : "{$type}-";
		$templates = $this->insertAfter( $templates, "{$prefix}{$term->slug}.php", $this->entries( $prefix, array_filter( $fallbacks ) ) );

		return $this->insertAfter( $templates, "{$prefix}{$term->term_id}.php", $this->entries( $prefix, array_keys( $fallbacks ) ) );
	}

	private function visitorLanguage() {
		if ( is_admin() || wpml_is_rest_request() ) {
			return null;
		}

		$current = apply_filters( 'wpml_current_language', null );

		return is_string( $current ) && '' !== $current ? $current : null;
	}

	private function termFallbacks( $term ) {
		$key = "{$term->taxonomy}:{$term->term_id}";
		if ( isset( $this->fallbacks[ $key ] ) ) {
			return $this->fallbacks[ $key ];
		}

		$rows = [];
		$elementId = ! empty( $term->term_taxonomy_id ) ? (int) $term->term_taxonomy_id : (int) $term->term_id;
		foreach ( $this->fallbackLanguages( $elementId, (string) $term->taxonomy ) as $language ) {
			$id = (int) apply_filters( 'wpml_object_id', (int) $term->term_id, $term->taxonomy, false, $language );
			if ( ! $id || $id === (int) $term->term_id || isset( $rows[ $id ] ) ) {
				continue;
			}
			$rows[ $id ] = TemplateLocalizer::readTermSlug( $id, (string) $term->taxonomy );
		}

		$this->fallbacks[ $key ] = $rows;

		return $rows;
	}

	private function pageFallbacks( $id ) {
		$key = "page:{$id}";
		if ( isset( $this->fallbacks[ $key ] ) ) {
			return $this->fallbacks[ $key ];
		}

		$rows = [];
		foreach ( $this->fallbackLanguages( $id, 'page' ) as $language ) {
			$translated = (int) apply_filters( 'wpml_object_id', $id, 'page', false, $language );
			if ( ! $translated || $translated === $id || isset( $rows[ $translated ] ) ) {
				continue;
			}
			$slug               = get_post_field( 'post_name', $translated );
			$rows[ $translated ] = is_string( $slug ) && '' !== $slug ? $slug : null;
		}

		$this->fallbacks[ $key ] = $rows;

		return $rows;
	}

	private function fallbackLanguages( $elementId, $elementType ) {
		$current   = (string) apply_filters( 'wpml_current_language', null );
		$default   = (string) apply_filters( 'wpml_default_language', null );
		$languages = '' !== $default && $default !== $current ? [ $default ] : [];

		$details = apply_filters(
			'wpml_element_language_details',
			null,
			[
				'element_id'   => $elementId,
				'element_type' => $elementType,
			]
		);
		$source  = is_object( $details ) && ! empty( $details->source_language_code ) ? (string) $details->source_language_code : '';
		if ( '' !== $source && $source !== $current && ! in_array( $source, $languages, true ) ) {
			$languages[] = $source;
		}

		return $languages;
	}

	private function entries( $prefix, array $slugsOrIds ) {
		return array_map(
			function ( $slugOrId ) use ( $prefix ) {
				return "{$prefix}{$slugOrId}.php";
			},
			array_values( $slugsOrIds )
		);
	}

	private function insertAfter( array $templates, $anchor, array $entries ) {
		$entries = array_values( array_diff( array_unique( $entries ), $templates ) );
		if ( ! $entries ) {
			return $templates;
		}

		$at = array_search( $anchor, $templates, true );
		$at = false === $at ? max( 0, count( $templates ) - 1 ) : $at + 1;
		array_splice( $templates, $at, 0, $entries );

		return $templates;
	}

	private function insertBefore( array $templates, $anchor, array $entries ) {
		$entries = array_values( array_diff( array_unique( $entries ), $templates ) );
		$at      = array_search( $anchor, $templates, true );
		if ( ! $entries || false === $at ) {
			return $templates;
		}

		array_splice( $templates, $at, 0, $entries );

		return $templates;
	}
}
