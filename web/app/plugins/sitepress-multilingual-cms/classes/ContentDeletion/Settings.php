<?php

namespace WPML\ContentDeletion;

class Settings {

	const KEY = 'content_deletion';

	const ORIGINALS = 'originals';

	const TRANSLATIONS = 'translations';

	const ASK  = 'ask';
	const ONLY = 'only';
	const ALL  = 'all';

	const ORIGINAL_ACTIONS = [ self::ASK, self::ONLY, self::ALL ];

	const TRANSLATION_ACTIONS = [ self::ASK, self::ONLY ];

	const POST_PREFIX = 'post_';
	const TAX_PREFIX  = 'tax_';

	const LEGACY_POSTS = 'sync_delete';

	const LEGACY_TERMS = 'sync_delete_tax';

	const FORCED_CASCADE_POST_TYPES = [ 'wp_template', 'wp_template_part' ];

	private $reader;

	public function __construct( ?callable $reader = null ) {
		$this->reader = $reader;
	}

	public function originalAction( $post_type ) {
		$post_type = (string) $post_type;

		if ( self::isForcedCascade( $post_type ) ) {
			return self::ALL;
		}

		$stored = $this->stored( self::ORIGINALS, self::postKey( $post_type ) );
		if ( in_array( $stored, self::ORIGINAL_ACTIONS, true ) ) {
			return $stored;
		}

		return $this->legacyOriginalAction( self::LEGACY_POSTS );
	}

	public function termOriginalAction( $taxonomy ) {
		$stored = $this->stored( self::ORIGINALS, self::termKey( (string) $taxonomy ) );
		if ( in_array( $stored, self::ORIGINAL_ACTIONS, true ) ) {
			return $stored;
		}

		return $this->legacyOriginalAction( self::LEGACY_TERMS );
	}

	public function translationAction( $post_type ) {
		$stored = $this->stored( self::TRANSLATIONS, self::postKey( (string) $post_type ) );

		return in_array( $stored, self::TRANSLATION_ACTIONS, true ) ? $stored : self::ONLY;
	}

	public function anyPostTypeDeletesAllLanguages() {
		$stored  = $this->storedColumn( self::ORIGINALS );
		$written = false;

		foreach ( $stored as $key => $value ) {
			$key = (string) $key;
			if ( 0 !== strpos( $key, self::POST_PREFIX ) ) {
				continue;
			}
			if ( self::isForcedCascade( substr( $key, strlen( self::POST_PREFIX ) ) ) ) {
				continue;
			}
			if ( ! in_array( $value, self::ORIGINAL_ACTIONS, true ) ) {
				continue;
			}
			$written = true;
			if ( self::ALL === $value ) {
				return true;
			}
		}

		return $written ? false : self::ALL === $this->legacyOriginalAction( self::LEGACY_POSTS );
	}

	public function effectiveFor( $element_key ) {
		$element_key = (string) $element_key;

		if ( 0 === strpos( $element_key, self::TAX_PREFIX ) ) {
			$taxonomy = substr( $element_key, strlen( self::TAX_PREFIX ) );

			return [
				'original'    => $this->termOriginalAction( $taxonomy ),
				'translation' => $this->storedTranslation( $element_key ),
			];
		}

		$post_type = 0 === strpos( $element_key, self::POST_PREFIX )
			? substr( $element_key, strlen( self::POST_PREFIX ) )
			: $element_key;

		return [
			'original'    => $this->originalAction( $post_type ),
			'translation' => $this->translationAction( $post_type ),
		];
	}

	public static function isForcedCascade( $post_type ) {
		return in_array( (string) $post_type, self::FORCED_CASCADE_POST_TYPES, true );
	}

	public static function postKey( $post_type ) {
		return self::POST_PREFIX . (string) $post_type;
	}

	public static function termKey( $taxonomy ) {
		return self::TAX_PREFIX . (string) $taxonomy;
	}

	public static function sanitize( $raw ) {
		$raw = is_array( $raw ) ? $raw : [];

		return [
			self::ORIGINALS    => self::sanitizeColumn(
				isset( $raw[ self::ORIGINALS ] ) ? $raw[ self::ORIGINALS ] : null,
				self::ORIGINAL_ACTIONS
			),
			self::TRANSLATIONS => self::sanitizeColumn(
				isset( $raw[ self::TRANSLATIONS ] ) ? $raw[ self::TRANSLATIONS ] : null,
				self::TRANSLATION_ACTIONS
			),
		];
	}

	private static function sanitizeColumn( $column, array $domain ) {
		$out = [];
		if ( ! is_array( $column ) ) {
			return $out;
		}

		foreach ( $column as $key => $value ) {
			$key = (string) $key;
			if ( 1 !== preg_match( '/^(post|tax)_[A-Za-z0-9_-]+$/', $key ) ) {
				continue;
			}
			if ( ! is_string( $value ) || ! in_array( $value, $domain, true ) ) {
				continue;
			}
			$out[ $key ] = $value;
		}

		return $out;
	}

	private function legacyOriginalAction( $legacy_key ) {
		return $this->setting( $legacy_key, false ) ? self::ALL : self::ONLY;
	}

	private function storedTranslation( $element_key ) {
		$stored = $this->stored( self::TRANSLATIONS, $element_key );

		return in_array( $stored, self::TRANSLATION_ACTIONS, true ) ? $stored : self::ONLY;
	}

	private function stored( $column, $key ) {
		$stored = $this->storedColumn( $column );

		return isset( $stored[ $key ] ) && is_string( $stored[ $key ] ) ? $stored[ $key ] : null;
	}

	private function storedColumn( $column ) {
		$table = $this->setting( self::KEY, [] );
		if ( ! is_array( $table ) || ! isset( $table[ $column ] ) || ! is_array( $table[ $column ] ) ) {
			return [];
		}

		return $table[ $column ];
	}

	private function setting( $key, $default = false ) {
		if ( null !== $this->reader ) {
			return call_user_func( $this->reader, $key, $default );
		}

		global $sitepress;

		if ( ! is_object( $sitepress ) || ! method_exists( $sitepress, 'get_setting' ) ) {
			return $default;
		}

		return $sitepress->get_setting( $key, $default );
	}
}
