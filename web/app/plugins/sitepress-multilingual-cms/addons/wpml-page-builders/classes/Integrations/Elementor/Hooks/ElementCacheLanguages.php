<?php

namespace WPML\PB\Elementor\Hooks;

class ElementCacheLanguages implements \IWPML_Frontend_Action, \IWPML_Backend_Action {

	const LANGUAGE_PREFIX = 'wpml_language:';

	private $isRaw = false;

	public function add_hooks() {
		add_filter( 'get_post_metadata', [ $this, 'getForCurrentLanguage' ], 10, 4 );
		add_filter( 'update_post_metadata', [ $this, 'updateForCurrentLanguage' ], 10, 4 );
	}

	public function getForCurrentLanguage( $value, $postId, $metaKey, $single ) {
		if ( null !== $value || ! $this->isCacheKey( $metaKey ) ) {
			return $value;
		}

		$rows     = $this->getRows( (int) $postId );
		$language = $this->getLanguage();

		if ( ! $language ) {
			return $this->hasLanguageRows( $rows ) ? $this->miss( $single ) : $value;
		}

		$prefix = self::LANGUAGE_PREFIX . $language . ':';

		foreach ( $rows as $row ) {
			if ( $this->hasPrefix( $row, $prefix ) ) {
				return [ substr( $row, strlen( $prefix ) ) ];
			}
		}

		foreach ( $rows as $row ) {
			if ( $this->isOtherValue( $row ) ) {
				return $value;
			}
		}

		return $this->miss( $single );
	}

	public function updateForCurrentLanguage( $check, $postId, $metaKey, $metaValue ) {
		if ( null !== $check || ! $this->isCacheKey( $metaKey ) || ! is_string( $metaValue ) || ! $this->isJson( $metaValue ) ) {
			return $check;
		}

		wp_cache_delete( $postId, 'post_meta' );

		$rows     = $this->getRows( (int) $postId );
		$language = $this->getLanguage();

		if ( ! $language ) {
			return $this->hasLanguageRows( $rows ) ? true : $check;
		}

		$prefix = self::LANGUAGE_PREFIX . $language . ':';

		$this->isRaw = true;
		foreach ( $rows as $row ) {
			if ( $this->hasPrefix( $row, $prefix ) || $this->isCacheWithoutLanguage( $row ) ) {
				delete_metadata( 'post', $postId, Cache::ELEMENT_CACHE_KEY, wp_slash( $row ) );
			}
		}
		add_metadata( 'post', $postId, Cache::ELEMENT_CACHE_KEY, wp_slash( $prefix . $metaValue ) );
		$this->isRaw = false;

		return true;
	}

	private function isCacheKey( $metaKey ): bool {
		return ! $this->isRaw && Cache::ELEMENT_CACHE_KEY === $metaKey;
	}

	private function getLanguage() {
		$language = apply_filters( 'wpml_current_language', null );

		return is_string( $language ) && '' !== $language && 'all' !== $language ? $language : null;
	}

	private function getRows( int $postId ): array {
		$isRaw       = $this->isRaw;
		$this->isRaw = true;
		$rows        = get_metadata( 'post', $postId, Cache::ELEMENT_CACHE_KEY, false );
		$this->isRaw = $isRaw;

		return is_array( $rows ) ? $rows : [];
	}

	private function hasLanguageRows( array $rows ): bool {
		foreach ( $rows as $row ) {
			if ( $this->hasPrefix( $row, self::LANGUAGE_PREFIX ) ) {
				return true;
			}
		}

		return false;
	}

	private function miss( $single ): array {
		return $single ? [ '' ] : [];
	}

	private function hasPrefix( $row, string $prefix ): bool {
		return is_string( $row ) && 0 === strncmp( $row, $prefix, strlen( $prefix ) );
	}

	private function isOtherValue( $row ): bool {
		return is_string( $row ) && ! $this->hasPrefix( $row, self::LANGUAGE_PREFIX ) && ! $this->isJson( $row );
	}

	private function isCacheWithoutLanguage( $row ): bool {
		return ! $this->hasPrefix( $row, self::LANGUAGE_PREFIX ) && ! $this->isOtherValue( $row );
	}

	private function isJson( string $value ): bool {
		return is_array( json_decode( $value, true ) );
	}
}
