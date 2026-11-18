<?php

namespace WPML\ST\SlugTranslation\PostArchive;

use WPML_Slug_Translation_Factory;
use WPML_Slug_Translation_Records;

class Records extends WPML_Slug_Translation_Records {

	const STRING_NAME_PREFIX = 'URL archive slug: ';
	const STRING_NAME        = self::STRING_NAME_PREFIX . '%s';

	protected function get_string_name( $slug ) {
		return sprintf( self::STRING_NAME, $slug );
	}

	protected function get_element_type() {
		return WPML_Slug_Translation_Factory::POST_ARCHIVE;
	}

	public static function get_registered_archive_slug( $post_type_object ): string {
		if ( ! is_object( $post_type_object )
			|| empty( $post_type_object->has_archive )
			|| ! is_string( $post_type_object->has_archive )
			|| empty( $post_type_object->rewrite )
		) {
			return '';
		}

		$archive_slug = trim( $post_type_object->has_archive, '/' );
		$rewrite_slug = trim( (string) ( $post_type_object->rewrite['slug'] ?? '' ), '/' );

		return $archive_slug === $rewrite_slug ? '' : $archive_slug;
	}
}
