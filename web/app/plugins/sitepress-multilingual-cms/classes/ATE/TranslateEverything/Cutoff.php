<?php

namespace WPML\TM\ATE\TranslateEverything;

use WPML\Setup\Option;

class Cutoff {

	public static function postDate( $post ): string {
		$published = (string) ( $post->post_date ?? '' );

		return substr( $published ?: (string) ( $post->post_modified ?? '' ), 0, 10 );
	}

	public static function isPostWithin( $post, string $sinceDate ): bool {
		return self::postDate( $post ) >= $sinceDate;
	}

	public static function isSkip( $sinceDate ): bool {
		return Option::SINCE_DATE_SKIP_TYPE === $sinceDate;
	}

	public static function postSql( string $alias ): string {
		return "{$alias}.post_date >= %s";
	}

	public static function ownerPostSql( string $alias ): string {
		return "( {$alias}.ID IS NULL OR " . self::postSql( $alias ) . ' )';
	}

	public static function isPackageWithin( string $kindSlug, $ownerPostId ): bool {
		$sinceDate = Option::getTranslateEverythingPackageKindSinceDate( $kindSlug );

		if ( self::isSkip( $sinceDate ) ) {
			return false;
		}

		if ( ! $ownerPostId ) {
			return true;
		}

		$post = get_post( (int) $ownerPostId );

		if ( ! $post ) {
			return true;
		}

		return self::isPostWithin( $post, (string) $sinceDate );
	}
}
