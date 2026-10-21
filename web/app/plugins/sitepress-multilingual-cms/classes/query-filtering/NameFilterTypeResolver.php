<?php

namespace WPML\QueryFiltering;

use WP_Query;

class NameFilterTypeResolver {

	public function resolve( $type, WP_Query $q, string $nameInQ ) {
		if ( $this->pagenameTargetsThePostsPage( $q, $nameInQ ) ) {
			return 'page';
		}

		$type = $type ?: 'page';
		if ( is_scalar( $type ) ) {
			return $type;
		}

		return count( $type ) === 1 ? end( $type ) : false;
	}

	private function pagenameTargetsThePostsPage( WP_Query $q, string $nameInQ ): bool {
		if ( ! $this->nameCameFromPagename( $q, $nameInQ ) ) {
			return false;
		}
		if ( ! $this->homepageIsAStaticPage() ) {
			return false;
		}
		$postsPageId = $this->currentLanguagePostsPageId();

		return $postsPageId && $this->slugOf( $postsPageId ) === $this->lastSegmentOf( $nameInQ );
	}

	private function nameCameFromPagename( WP_Query $q, string $nameInQ ): bool {
		return ! $q->get( 'name' )
			&& (bool) $q->get( 'pagename' )
			&& $nameInQ === $q->get( 'pagename' );
	}

	private function homepageIsAStaticPage(): bool {
		return 'page' === get_option( 'show_on_front' );
	}

	private function currentLanguagePostsPageId(): int {
		return (int) get_option( 'page_for_posts' );
	}

	private function slugOf( int $pageId ): string {
		return (string) get_post_field( 'post_name', $pageId );
	}

	private function lastSegmentOf( string $pagename ): string {
		$slugs = explode( '/', trim( $pagename, '/' ) );

		return (string) end( $slugs );
	}
}
