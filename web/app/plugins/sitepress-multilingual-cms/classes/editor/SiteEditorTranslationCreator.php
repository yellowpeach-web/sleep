<?php

namespace WPML\TM\Editor;

use WPML\Core\Component\Post\Application\LanguagePanel\SiteEditorEntityTypes;

class SiteEditorTranslationCreator {

	const TYPES = [ 'wp_template', 'wp_template_part' ];

	const PAGE_META_COPIED = [ '_wp_page_template' ];

	const META_NOT_COPIED = '/^(_icl_|_wpml_|_edit_lock$|_edit_last$|_wp_old_slug$|_wp_trash_meta_|_wp_desired_post_slug$)/';

	private $localizeSlug;

	public function __construct( ?callable $localizeSlug = null ) {
		$this->localizeSlug = $localizeSlug ?: function ( $slug, $language ) {
			if ( class_exists( \WPML\PB\FullSiteEditing\TemplateLocalizer::class ) ) {
				return (string) \WPML\PB\FullSiteEditing\TemplateLocalizer::getLocalizedTemplateSlug( $slug, $language );
			}

			return $slug;
		};
	}

	public static function isSiteEditorType( $type ) {
		$type = (string) $type;
		if ( 0 === strpos( $type, 'post_' ) ) {
			$type = substr( $type, 5 );
		}

		return in_array( $type, self::TYPES, true );
	}

	public static function isPageType( $type ) {
		$type = (string) $type;
		if ( 0 === strpos( $type, 'post_' ) ) {
			$type = substr( $type, 5 );
		}

		return SiteEditorEntityTypes::PAGE === $type;
	}

	public function findOrCreate( $originalPostId, $trid, $targetLanguage, $sourceLanguage ) {
		global $sitepress;

		$original = get_post( (int) $originalPostId );
		if ( ! $original ) {
			return null;
		}

		$isPage = self::isPageType( $original->post_type );
		if ( ! $isPage && ! self::isSiteEditorType( $original->post_type ) ) {
			return null;
		}

		$existing = (int) $sitepress->post_translations()->get_element_id( $targetLanguage, (int) $trid );
		if ( $existing ) {
			return $existing;
		}

		if ( $isPage ) {
			$slug    = '';
			$postarr = $this->pageCopyOf( $original, $targetLanguage );
		} else {
			$slug    = (string) call_user_func( $this->localizeSlug, (string) $original->post_name, $targetLanguage );
			$postarr = $this->copyOf( $original, $slug );
		}

		$copyId = $this->insertUnlinked( $postarr, $targetLanguage );

		if ( ! $copyId ) {
			return null;
		}

		$this->link( $copyId, $original->post_type, (int) $trid, $targetLanguage, $sourceLanguage );
		if ( ! $isPage ) {
			$this->copyTerms( (int) $original->ID, $copyId, $original->post_type );
		}
		$this->copyMeta( (int) $original->ID, $copyId, $isPage ? self::PAGE_META_COPIED : null );
		$this->enforceSlug( $copyId, $slug );
		$this->tellTranslationManagement( $copyId );

		return $copyId;
	}

	public function siteEditorUrl( $postId, $language ) {
		$post = get_post( (int) $postId );
		if ( $post && self::isPageType( $post->post_type ) ) {
			return admin_url( SiteEditorEntityTypes::pageEditorPath( (int) $postId, (string) $language ) );
		}

		$url = (string) get_edit_post_link( (int) $postId, 'raw' );

		if ( '' !== $url && ! preg_match( '/[?&]lang=/', $url ) ) {
			$url = add_query_arg( 'lang', $language, $url );
		}

		return $url;
	}

	private function copyOf( $original, $slug ) {
		return [
			'post_type'      => $original->post_type,
			'post_status'    => $original->post_status,
			'post_title'     => $original->post_title,
			'post_content'   => $original->post_content,
			'post_excerpt'   => $original->post_excerpt,
			'post_author'    => $original->post_author,
			'post_name'      => $slug,
			'post_password'  => isset( $original->post_password ) ? (string) $original->post_password : '',
			'comment_status' => $original->comment_status,
			'ping_status'    => $original->ping_status,
			'menu_order'     => $original->menu_order,
		];
	}

	private function pageCopyOf( $original, $targetLanguage ) {
		global $sitepress;

		$postarr = $this->copyOf( $original, '' );
		unset( $postarr['post_name'] );

		$postarr['post_status'] = 'draft';
		$postarr['post_parent'] = (int) $original->post_parent > 0
			? (int) $sitepress->post_translations()->element_id_in( (int) $original->post_parent, $targetLanguage )
			: 0;

		return $postarr;
	}

	private function insertUnlinked( array $postarr, $targetLanguage ) {
		\WPML_Post_Translation::suspend_save_post_actions();

		try {
			$id = wpml_get_create_post_helper()->insert_post( $postarr, $targetLanguage, true );
		} finally {
			\WPML_Post_Translation::resume_save_post_actions();
		}

		return is_wp_error( $id ) ? 0 : (int) $id;
	}

	private function link( $copyId, $postType, $trid, $targetLanguage, $sourceLanguage ) {
		global $sitepress;

		$sitepress->set_element_language_details( $copyId, 'post_' . $postType, $trid, $targetLanguage, $sourceLanguage );
	}

	/**
	 * TM's own recording of a translation saved in the WordPress editor
	 * (`wpml_tm_save_post` -> WPML_TM_Post_Actions: the status row complete, an
	 * `editor=wp` job - what a native save of a template translation records,
	 * journey 20), told once the copy is whole: linked, its terms, meta and
	 * slug in place. On a Blog licence the action's listener is
	 * `WPML_Blog_License_Translation_Status::on_save_post` (wpmldev-3902,
	 * `WPML\Plugins::loadBlogLicenseTranslationStatus()`), which writes the
	 * status row and no job - the same recording a native save gets there
	 * (D-31 P3-6).
	 *
	 * @param int $copyId
	 */
	private function tellTranslationManagement( $copyId ) {
		do_action( 'wpml_tm_save_post', $copyId, get_post( $copyId ), false );
	}

	private function copyTerms( $originalId, $copyId, $postType ) {
		foreach ( array_map( 'strval', (array) get_object_taxonomies( $postType ) ) as $taxonomy ) {
			$slugs = wp_get_object_terms( $originalId, $taxonomy, [ 'fields' => 'slugs' ] );
			if ( ! is_array( $slugs ) || ! $slugs ) {
				continue;
			}

			wp_set_object_terms( $copyId, $slugs, $taxonomy );
		}
	}

	private function copyMeta( $originalId, $copyId, ?array $only = null ) {
		foreach ( (array) get_post_custom( $originalId ) as $key => $values ) {
			if ( preg_match( self::META_NOT_COPIED, (string) $key ) ) {
				continue;
			}
			if ( null !== $only && ! in_array( (string) $key, $only, true ) ) {
				continue;
			}
			if ( metadata_exists( 'post', $copyId, (string) $key ) ) {
				continue;
			}

			foreach ( (array) $values as $value ) {
				add_post_meta( $copyId, $key, wp_slash( \WPML\Utils\Unserialize::maybe( $value ) ) );
			}
		}
	}

	private function enforceSlug( $copyId, $slug ) {
		if ( '' === $slug || (string) get_post_field( 'post_name', $copyId ) === $slug ) {
			return;
		}

		global $wpdb;

		$wpdb->update( $wpdb->posts, [ 'post_name' => $slug ], [ 'ID' => $copyId ] );
		clean_post_cache( $copyId );
	}
}
