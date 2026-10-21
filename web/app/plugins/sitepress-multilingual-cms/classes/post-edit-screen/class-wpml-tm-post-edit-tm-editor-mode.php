<?php

use WPML\API\Settings;
use WPML\Core\SharedKernel\Component\Post\Domain\TranslationEditorPreference;
use WPML\Element\API\Translations;
use WPML\TM\PostEditScreen\Endpoints\SetEditorMode;

class WPML_TM_Post_Edit_TM_Editor_Mode {

	const POST_META_KEY_USE_NATIVE        = TranslationEditorPreference::POST_META_KEY_USE_NATIVE;
	const TM_KEY_FOR_POST_TYPE_USE_NATIVE = TranslationEditorPreference::TM_KEY_FOR_POST_TYPE_USE_NATIVE;
	const TM_KEY_GLOBAL_USE_NATIVE        = TranslationEditorPreference::TM_KEY_GLOBAL_USE_NATIVE;

	const POST_META_KEY_USE_WPML        = TranslationEditorPreference::POST_META_KEY_USE_WPML;
	const TM_KEY_FOR_POST_TYPE_USE_WPML = TranslationEditorPreference::TM_KEY_FOR_POST_TYPE_USE_WPML;
	const TM_KEY_GLOBAL_USE_WPML        = TranslationEditorPreference::TM_KEY_GLOBAL_USE_WPML;

	const POST_META_KEY_EDITOR        = TranslationEditorPreference::POST_META_KEY_EDITOR;
	const TM_KEY_FOR_POST_TYPE_EDITOR = TranslationEditorPreference::TM_KEY_FOR_POST_TYPE_EDITOR;
	const TM_KEY_GLOBAL_EDITOR        = TranslationEditorPreference::TM_KEY_GLOBAL_EDITOR;

	const EDITOR_NATIVE    = TranslationEditorPreference::EDITOR_NATIVE;
	const EDITOR_WPML      = TranslationEditorPreference::EDITOR_WPML;
	const EDITOR_DASHBOARD = TranslationEditorPreference::EDITOR_DASHBOARD;

	public static function is_using_tm_editor( $deprecated, $post_id, $should_find_original_id = true ) {
		return self::get_translation_editor( $deprecated, $post_id, $should_find_original_id ) !== self::EDITOR_NATIVE;
	}

	public static function get_translation_editor( $deprecated, $post_id, $should_find_original_id = true ) {
		return self::resolve_editor( $post_id, $should_find_original_id )[0];
	}

	public static function get_translation_editor_with_scope( $deprecated, $post_id ) {
		return self::resolve_editor( $post_id, true );
	}

	private static function resolve_editor( $post_id, $should_find_original_id ) {
		if ( $should_find_original_id ) {
			$original_id = (int) Translations::getOriginalId( $post_id, 'post_' . get_post_type( $post_id ) );
			$post_id     = $original_id ?: $post_id;
		}

		$post_type = get_post_type( $post_id );

		$editor_meta = get_post_meta( $post_id, self::POST_META_KEY_EDITOR, true );
		if ( self::is_valid_editor( $editor_meta ) ) {
			return [ $editor_meta, SetEditorMode::MODE_FOR_THIS_POST ];
		}

		$tm_settings = self::init_settings();

		$type_editor = $tm_settings[ self::TM_KEY_FOR_POST_TYPE_EDITOR ][ $post_type ] ?? null;
		if ( self::is_valid_editor( $type_editor ) ) {
			return [ $type_editor, SetEditorMode::MODE_FOR_POST_TYPE ];
		}

		if ( self::is_valid_editor( $tm_settings[ self::TM_KEY_GLOBAL_EDITOR ] ) ) {
			return [ $tm_settings[ self::TM_KEY_GLOBAL_EDITOR ], SetEditorMode::MODE_FOR_GLOBAL ];
		}

		return self::derive_from_legacy_with_scope(
			get_post_meta( $post_id, self::POST_META_KEY_USE_NATIVE, true ),
			get_post_meta( $post_id, self::POST_META_KEY_USE_WPML, true ),
			$tm_settings[ self::TM_KEY_FOR_POST_TYPE_USE_NATIVE ][ $post_type ] ?? null,
			$tm_settings[ self::TM_KEY_FOR_POST_TYPE_USE_WPML ][ $post_type ] ?? null,
			$tm_settings[ self::TM_KEY_GLOBAL_USE_NATIVE ],
			$tm_settings[ self::TM_KEY_GLOBAL_USE_WPML ],
			$tm_settings['doc_translation_method']
		);
	}

	public static function is_post_type_using_wp_editor( $post_type = '' ) : bool {
		$tm_settings = self::init_settings();

		if ( $post_type ) {
			$type_editor = $tm_settings[ self::TM_KEY_FOR_POST_TYPE_EDITOR ][ $post_type ] ?? null;
			if ( self::is_valid_editor( $type_editor ) ) {
				return self::EDITOR_NATIVE === $type_editor;
			}
		}

		return self::EDITOR_NATIVE === self::derive_from_legacy(
			null,
			null,
			$post_type ? ( $tm_settings[ self::TM_KEY_FOR_POST_TYPE_USE_NATIVE ][ $post_type ] ?? null ) : null,
			$post_type ? ( $tm_settings[ self::TM_KEY_FOR_POST_TYPE_USE_WPML ][ $post_type ] ?? null ) : null,
			$tm_settings[ self::TM_KEY_GLOBAL_USE_NATIVE ],
			$tm_settings[ self::TM_KEY_GLOBAL_USE_WPML ],
			$tm_settings['doc_translation_method']
		);
	}

	public static function get_editor_settings( $deprecated, $postId ) {
		$useTmEditor = \WPML_TM_Post_Edit_TM_Editor_Mode::is_using_tm_editor( null, $postId );
		$useTmEditor = apply_filters( 'wpml_use_tm_editor', $useTmEditor, $postId );

		$result = self::get_blocked_posts( [ $postId ] );

		if ( isset($result[$postId]) ) {
			$isWpmlEditorBlocked = true;
			$reason              = $result[$postId];
		} else {
			$isWpmlEditorBlocked = false;
			$reason              = '';
		}

		return [
			$useTmEditor,
			$isWpmlEditorBlocked,
			$reason,
		];
	}

	public static function uses_native_editor( $post_id ) {
		list( $use_tm_editor, $is_wpml_editor_blocked ) = self::get_editor_settings( null, $post_id );

		return ! $use_tm_editor || $is_wpml_editor_blocked;
	}

	public static function get_blocked_posts( $postIds ) {
		return apply_filters( 'wpml_tm_editor_exclude_posts', [], $postIds );
	}

	private static function init_settings() {
		$get = fn( $key, $default = [] ) => wpml_get_tm_sub_setting( $key, $default );

		return [
			self::TM_KEY_GLOBAL_EDITOR            => $get( self::TM_KEY_GLOBAL_EDITOR, null ),
			self::TM_KEY_FOR_POST_TYPE_EDITOR     => $get( self::TM_KEY_FOR_POST_TYPE_EDITOR, [] ),
			self::TM_KEY_GLOBAL_USE_NATIVE        => $get( self::TM_KEY_GLOBAL_USE_NATIVE, null ),
			self::TM_KEY_FOR_POST_TYPE_USE_NATIVE => $get( self::TM_KEY_FOR_POST_TYPE_USE_NATIVE, [] ),
			self::TM_KEY_GLOBAL_USE_WPML          => $get( self::TM_KEY_GLOBAL_USE_WPML, null ),
			self::TM_KEY_FOR_POST_TYPE_USE_WPML   => $get( self::TM_KEY_FOR_POST_TYPE_USE_WPML, [] ),
			'doc_translation_method'              => $get( 'doc_translation_method', '' ),
		];
	}

	private static function is_valid_editor( $value ) {
		return in_array( $value, [ self::EDITOR_NATIVE, self::EDITOR_WPML, self::EDITOR_DASHBOARD ], true );
	}

	public static function derive_from_legacy(
		$meta_native,
		$meta_wpml,
		$type_native,
		$type_wpml,
		$global_native,
		$global_wpml,
		$doc_translation_method
	): string {
		return self::derive_from_legacy_with_scope(
			$meta_native,
			$meta_wpml,
			$type_native,
			$type_wpml,
			$global_native,
			$global_wpml,
			$doc_translation_method
		)[0];
	}

	private static function derive_from_legacy_with_scope(
		$meta_native,
		$meta_wpml,
		$type_native,
		$type_wpml,
		$global_native,
		$global_wpml,
		$doc_translation_method
	) {
		$meta_native = 'yes' === $meta_native ? 'Y' : ( 'no' === $meta_native ? 'N' : null );
		$meta_wpml   = self::normalize_wpml_value( $meta_wpml );

		if ( 'Y' === $meta_native && null === $meta_wpml ) {
			return [ self::EDITOR_NATIVE, SetEditorMode::MODE_FOR_THIS_POST ];
		}
		if ( null === $meta_native && null !== $meta_wpml ) {
			return [ $meta_wpml, SetEditorMode::MODE_FOR_THIS_POST ];
		}
		if ( 'N' === $meta_native ) {
			return [ null === $meta_wpml ? self::EDITOR_WPML : $meta_wpml, SetEditorMode::MODE_FOR_THIS_POST ];
		}

		$type_native = true === $type_native;
		$type_wpml   = self::normalize_wpml_value( $type_wpml );

		if ( $type_native && null === $type_wpml ) {
			return [ self::EDITOR_NATIVE, SetEditorMode::MODE_FOR_POST_TYPE ];
		}
		if ( ! $type_native && null !== $type_wpml ) {
			return [ $type_wpml, SetEditorMode::MODE_FOR_POST_TYPE ];
		}

		$global_native = true === $global_native ? 'Y' : ( false === $global_native ? 'N' : null );
		$global_wpml   = self::normalize_wpml_value( $global_wpml );

		if ( 'Y' === $global_native && null === $global_wpml ) {
			return [ self::EDITOR_NATIVE, SetEditorMode::MODE_FOR_GLOBAL ];
		}
		if ( 'N' === $global_native ) {
			return [ null === $global_wpml ? self::EDITOR_DASHBOARD : $global_wpml, SetEditorMode::MODE_FOR_GLOBAL ];
		}
		if ( null === $global_native && null !== $global_wpml ) {
			return [ $global_wpml, SetEditorMode::MODE_FOR_GLOBAL ];
		}

		return [ '0' === (string) $doc_translation_method ? self::EDITOR_NATIVE : self::EDITOR_DASHBOARD, SetEditorMode::MODE_FOR_GLOBAL ];
	}

	private static function normalize_wpml_value( $value ) {
		if ( self::EDITOR_WPML === $value ) {
			return self::EDITOR_WPML;
		}

		return self::EDITOR_DASHBOARD === $value ? self::EDITOR_DASHBOARD : null;
	}

	public static function delete_all_posts_option( $post_type = null ) {
		global $wpdb;

		if ( $post_type ) {
			$meta_keys = [ self::POST_META_KEY_EDITOR, self::POST_META_KEY_USE_NATIVE, self::POST_META_KEY_USE_WPML ];

			$wpdb->query(
				$wpdb->prepare(
					"DELETE postmeta FROM {$wpdb->postmeta} AS postmeta
					 INNER JOIN {$wpdb->posts} AS posts ON posts.ID = postmeta.post_id
					 WHERE posts.post_type = %s AND postmeta.meta_key IN (" . implode( ', ', array_fill( 0, count( $meta_keys ), '%s' ) ) . ')',
					array_merge( [ $post_type ], $meta_keys )
				)
			);
		} else {
			delete_post_meta_by_key( self::POST_META_KEY_EDITOR );
			delete_post_meta_by_key( self::POST_META_KEY_USE_NATIVE );
			delete_post_meta_by_key( self::POST_META_KEY_USE_WPML );
		}
	}
}
