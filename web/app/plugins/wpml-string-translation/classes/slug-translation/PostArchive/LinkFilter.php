<?php

namespace WPML\ST\SlugTranslation\PostArchive;

use IWPML_Action;
use SitePress;
use WPML_Slug_Translation_Records;
use WPML_ST_Slug_Translation_Settings;
use WPML_ST_Slug_Translations;

class LinkFilter implements IWPML_Action {

	const PRIORITY = 11;

	private $records;

	private $settings;

	private $sitepress;

	private $slug_translations;

	public function __construct(
		WPML_Slug_Translation_Records $records,
		WPML_ST_Slug_Translation_Settings $settings,
		SitePress $sitepress,
		WPML_ST_Slug_Translations $slug_translations
	) {
		$this->records           = $records;
		$this->settings          = $settings;
		$this->sitepress         = $sitepress;
		$this->slug_translations = $slug_translations;
	}

	public function add_hooks() {
		add_filter( 'post_type_archive_link', array( $this, 'translate_archive_slug_in_link' ), self::PRIORITY, 2 );
	}

	public function translate_archive_slug_in_link( $link, $post_type ) {
		if ( ! is_string( $link ) || '' === $link
			|| ! $this->settings->is_translated( $post_type )
			|| ! $this->sitepress->is_translated_post_type( $post_type )
		) {
			return $link;
		}

		$archive_slug = Records::get_registered_archive_slug( get_post_type_object( $post_type ) );
		if ( '' === $archive_slug ) {
			return $link;
		}

		$slug = $this->records->get_slug( $post_type );
		if ( ! $slug->get_original_id() ) {
			return $link;
		}

		$translation = $this->slug_translations->get(
			$slug,
			$this->sitepress->is_display_as_translated_post_type( $post_type )
		);

		if ( '' === $translation || $translation === $archive_slug ) {
			return $link;
		}

		return $this->replace_last_path_segment( $link, $archive_slug, $translation );
	}

	private function replace_last_path_segment( string $link, string $segment, string $replacement ): string {
		$link_parts = explode( '?', $link, 2 );
		$path       = preg_replace_callback(
			'#/' . preg_quote( $segment, '#' ) . '(/?)$#',
			function ( $matches ) use ( $replacement ) {
				return '/' . $replacement . $matches[1];
			},
			$link_parts[0],
			1
		);

		return isset( $link_parts[1] ) ? $path . '?' . $link_parts[1] : $path;
	}
}
