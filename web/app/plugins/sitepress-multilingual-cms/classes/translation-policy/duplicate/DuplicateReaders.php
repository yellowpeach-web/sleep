<?php

namespace WPML\TranslationPolicy\Duplicate;

use WPML\TranslationPolicy\FeatureSwitch;

class DuplicateReaders {

	const META = '_icl_lang_duplicate_of';

	public static function keepsInStep(): bool {
		return ! FeatureSwitch::isOn();
	}

	public static function isKeptInStep( int $postId ): bool {
		return self::keepsInStep() && (bool) get_post_meta( $postId, self::META, true );
	}

	public static function isCopy( int $postId ): bool {
		if ( self::keepsInStep() ) {
			return (bool) get_post_meta( $postId, self::META, true );
		}

		return CopyReader::create()->isCopy( $postId );
	}

	public static function afterCopy( int $postId, string $language, int $originalId ): bool {
		if ( self::keepsInStep() || CopyProvenance::create()->write( $postId, $language, $originalId ) ) {
			return true;
		}

		error_log( sprintf( 'WPML: the copy of post %d into %s has no copy entry in its edit history.', $originalId, $language ) );

		return false;
	}
}
