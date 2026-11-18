<?php

namespace WPML\PB\Elementor\Hooks;

use WPML\FP\Obj;
use WPML\FP\Relation;
use WPML\LIB\WP\Hooks;
use WPML\UrlHandling\UrlBuilder;
use function WPML\FP\spreadArgs;
use function WPML\FP\pipe;

class LandingPages implements \IWPML_Frontend_Action, \IWPML_Backend_Action, \IWPML_DIC_Action {

	const POST_TYPE            = 'e-landing-page';
	const BACKUP_HOOK_PRIORITY = 11;

	const DEFAULT_WPML_LINK_PRIORITY = 2;

	private $sitepress;

	private $lastLink;

	private $translationLinks = [];

	public function __construct( \SitePress $sitepress ) {
		$this->sitepress = $sitepress;
	}

	public function add_hooks() {
		if ( get_option( 'permalink_structure' ) ) {
			$wpmlLinkPriority = $this->getWpmlLinkPriority();
			add_filter( 'post_type_link', [ $this, 'beforeWpmlLink' ], $wpmlLinkPriority - 1, 2 );
			add_filter( 'post_type_link', [ $this, 'afterWpmlLink' ], $wpmlLinkPriority + 1, 2 );
			add_filter( 'post_type_link', [ $this, 'adjustLink' ], PHP_INT_MAX, 2 );
			Hooks::onFilter( 'pre_handle_404', self::BACKUP_HOOK_PRIORITY, 2 )
				->then( spreadArgs( [ $this, 'backupQuery' ] ) );
		}
	}

	public function beforeWpmlLink( $postUrl, $post ) {
		if ( $this->isPublishedLandingPage( $post ) ) {
			$this->lastLink = null;
		}

		return $postUrl;
	}

	public function afterWpmlLink( $postUrl, $post ) {
		if ( $this->isPublishedLandingPage( $post ) ) {
			if ( $this->lastLink && $this->lastLink[0] !== (int) $post->ID ) {
				$this->translationLinks[ (int) $post->ID ] = $this->lastLink[1];
			} else {
				unset( $this->translationLinks[ (int) $post->ID ] );
			}
		}

		return $postUrl;
	}

	public function adjustLink( $postUrl, $post ) {
		if ( ! $this->isPublishedLandingPage( $post ) ) {
			return $postUrl;
		}

		$postId = (int) $post->ID;

		if ( isset( $this->translationLinks[ $postId ] ) ) {
			$newPostUrl = $this->translationLinks[ $postId ];
			unset( $this->translationLinks[ $postId ] );
		} else {
			$homeUrl          = get_home_url();
			$urlParts         = wp_parse_url( $homeUrl );
			$urlParts['path'] = trailingslashit( Obj::prop( 'path', $urlParts ) ) . $post->post_name . '/';
			$postLangCode     = $this->sitepress->get_language_for_element( $post->ID, 'post_' . self::POST_TYPE );
			$newPostUrl       = $this->sitepress->convert_url( UrlBuilder::build( '', $urlParts ), $postLangCode );
		}

		$this->lastLink = [ $postId, $newPostUrl ];

		return $newPostUrl;
	}

	private function getWpmlLinkPriority() {
		global $wpml_url_filters;

		$priority = $wpml_url_filters
			? has_filter( 'post_type_link', [ $wpml_url_filters, 'post_type_link_filter' ] )
			: false;

		return false === $priority ? self::DEFAULT_WPML_LINK_PRIORITY : (int) $priority;
	}

	private function isPublishedLandingPage( $post ) {
		return self::POST_TYPE === $post->post_type && 'publish' === $post->post_status;
	}

	public function backupQuery( $value, $query ) {
		global $wp_query;

		if ( $value ) {
			return $value;
		}

		$wasModifiedByElementor = pipe(
			Obj::prop( 'query' ),
			Relation::propEq( 'post_type', self::POST_TYPE )
		);

		$hasPosts = Obj::prop( 'posts' );

		if (
			$query->is_main_query()
			&& empty( $query->posts )
			&& ! empty( $query->query['category_name'] )
			&& empty( $query->tax_query->table_aliases )
			&& $wasModifiedByElementor( $wp_query )
			&& $hasPosts( $wp_query )
		) {
			if ( apply_filters( 'wpml_sub_setting', 1, 'seo', 'head_langs' ) ) {
				$wpQueryBackup = clone $wp_query;
				$priority      = (int) apply_filters( 'wpml_sub_setting', 1, 'seo', 'head_langs_priority' );
				Hooks::onAction( 'wp_head', $priority + 1 )
					->then(
						function () use ( $wpQueryBackup ) {
							global $wp_query;

							$wp_query = $wpQueryBackup;
						}
					);
			}
		}

		return false;
	}
}
