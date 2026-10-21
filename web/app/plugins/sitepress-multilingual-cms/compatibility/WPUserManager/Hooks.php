<?php

namespace WPML\Compatibility\WPUserManager;

class Hooks implements \IWPML_Frontend_Action {

	public function add_hooks() {
		add_filter( 'wpum_profile_page_ids', [ $this, 'addPageTranslations' ] );
		add_filter( 'wpum_account_page_ids', [ $this, 'addPageTranslations' ] );
		add_filter( 'icl_ls_languages', [ $this, 'keepRoutePath' ], PHP_INT_MAX );
	}

	public function addPageTranslations( $pageIds ) {
		$ids = [];

		foreach ( (array) $pageIds as $pageId ) {
			$ids[] = (int) $pageId;
			$ids[] = (int) apply_filters( 'wpml_object_id', $pageId, 'page', false );
		}

		return array_values( array_unique( array_filter( $ids ) ) );
	}

	public function keepRoutePath( $languages ) {
		$path = $this->getRoutePath();

		if ( ! $path ) {
			return $languages;
		}

		$pageId = get_queried_object_id();

		foreach ( (array) $languages as $code => $language ) {
			$languages[ $code ]['url'] = $this->appendPath( $language['url'], $this->getPageSlugs( $pageId, $code ), $path );
		}

		return $languages;
	}

	private function getRoutePath() {
		if ( ! is_page() ) {
			return '';
		}

		$pageId = get_queried_object_id();

		$user = $this->isTranslationOf( $pageId, $this->getWpumPageId( 'profile' ) ) ? (string) get_query_var( 'profile' ) : '';

		if ( '' !== $user ) {
			$path = rawurlencode( $user );
			$tab  = (string) get_query_var( 'tab' );
			if ( '' !== $tab ) {
				$path .= '/' . rawurlencode( $tab );

				$paged = (int) get_query_var( 'paged' );
				if ( $paged > 0 ) {
					$path .= '/page/' . $paged;
				}
			}

			return $path;
		}

		if ( $this->isTranslationOf( $pageId, $this->getWpumPageId( 'account' ) ) ) {
			$tab = (string) get_query_var( 'tab' );

			return '' === $tab ? '' : rawurlencode( $tab );
		}

		return '';
	}

	private function getWpumPageId( $page ) {
		return function_exists( 'wpum_get_core_page_id' ) ? wpum_get_core_page_id( $page ) : false;
	}

	private function isTranslationOf( $pageId, $wpumPageId ) {
		if ( ! $pageId || ! $wpumPageId ) {
			return false;
		}

		$trid = apply_filters( 'wpml_element_trid', null, $pageId, 'post_page' );

		return $trid && (int) $trid === (int) apply_filters( 'wpml_element_trid', null, (int) $wpumPageId, 'post_page' );
	}

	private function getPageSlugs( $pageId, $language ) {
		$slugs = [];

		foreach ( [ $language, apply_filters( 'wpml_default_language', null ) ] as $code ) {
			$id = (int) apply_filters( 'wpml_object_id', $pageId, 'page', true, $code );
			if ( $id ) {
				$slugs[] = (string) get_post_field( 'post_name', $id );
			}
		}

		return array_values( array_unique( array_filter( $slugs ) ) );
	}

	private function appendPath( $url, $pageSlugs, $path ) {
		$parts = explode( '?', $url, 2 );
		$base  = rtrim( $parts[0], '/' );

		if ( isset( $parts[1] ) && $this->endsWith( rtrim( $url, '/' ), '/' . $path ) ) {
			return $url;
		}

		foreach ( $pageSlugs as $pageSlug ) {
			if ( $this->endsWith( $base, '/' . $pageSlug . '/' . $path ) ) {
				return $url;
			}
		}

		foreach ( $pageSlugs as $pageSlug ) {
			if ( $this->endsWith( $base, '/' . $pageSlug ) ) {
				$trailingSlash = '/' === substr( $parts[0], -1 ) ? '/' : '';

				return $base . '/' . $path . $trailingSlash . ( isset( $parts[1] ) ? '?' . $parts[1] : '' );
			}
		}

		return $url;
	}

	private function endsWith( $haystack, $needle ) {
		return substr( $haystack, - strlen( $needle ) ) === $needle;
	}
}
