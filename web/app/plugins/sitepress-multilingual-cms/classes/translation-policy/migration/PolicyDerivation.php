<?php

namespace WPML\TranslationPolicy\Migration;

use WPML\Core\SharedKernel\Component\Post\Domain\TranslateEverythingScope;
use WPML\Core\SharedKernel\Component\Post\Domain\TranslationEditorPreference as Keys;
use WPML\Setup\Option;
use WPML\TM\PostEditScreen\Endpoints\SetEditorMode;
use WPML\TranslationPolicy\Policy\PolicyTable;

class PolicyDerivation {

	const SOURCE_WCML = 'wcml';

	private $wpmlEditor;

	public function __construct( string $wpmlEditor ) {
		$this->wpmlEditor = $wpmlEditor;
	}

	public function siteAndTypeRows( array $tm, array $sinceDates, bool $isTranslateEverythingOn, array $types ): array {
		$rows = [];

		$globalEditor = $this->validEditor( $tm[ Keys::TM_KEY_GLOBAL_EDITOR ] ?? null, PolicyTable::SCOPE_SITE );
		if ( $globalEditor ) {
			$this->add( $rows, PolicyTable::SCOPE_SITE, '', PolicyTable::STRENGTH_CHOICE, '', $globalEditor, null );
		} else {
			list( $legacyEditor ) = \WPML_TM_Post_Edit_TM_Editor_Mode::derive_from_legacy_with_scope(
				null,
				null,
				null,
				null,
				$tm[ Keys::TM_KEY_GLOBAL_USE_NATIVE ] ?? null,
				$tm[ Keys::TM_KEY_GLOBAL_USE_WPML ] ?? null,
				$tm['doc_translation_method'] ?? ''
			);
			$this->add( $rows, PolicyTable::SCOPE_SITE, '', PolicyTable::STRENGTH_DEFAULT, '', $this->editor( $legacyEditor, PolicyTable::SCOPE_SITE ), null );
		}

		$typeEditors = self::map( $tm, Keys::TM_KEY_FOR_POST_TYPE_EDITOR );
		foreach ( $typeEditors as $type => $value ) {
			$editor = $this->validEditor( $value, PolicyTable::SCOPE_TYPE );
			if ( $editor && self::isType( $type ) ) {
				$this->add( $rows, PolicyTable::SCOPE_TYPE, (string) $type, PolicyTable::STRENGTH_CHOICE, '', $editor, null );
			}
		}

		$legacyNative = self::map( $tm, Keys::TM_KEY_FOR_POST_TYPE_USE_NATIVE );
		$legacyWpml   = self::map( $tm, Keys::TM_KEY_FOR_POST_TYPE_USE_WPML );
		if ( ! $globalEditor ) {
			foreach ( array_keys( $legacyNative + $legacyWpml ) as $type ) {
				if ( ! self::isType( $type ) || $this->validEditor( $typeEditors[ $type ] ?? null, PolicyTable::SCOPE_TYPE ) ) {
					continue;
				}
				list( $legacyEditor, $scope ) = \WPML_TM_Post_Edit_TM_Editor_Mode::derive_from_legacy_with_scope(
					null,
					null,
					$legacyNative[ $type ] ?? null,
					$legacyWpml[ $type ] ?? null,
					null,
					null,
					''
				);
				if ( SetEditorMode::MODE_FOR_POST_TYPE === $scope ) {
					$this->add( $rows, PolicyTable::SCOPE_TYPE, (string) $type, PolicyTable::STRENGTH_DEFAULT, '', $this->editor( $legacyEditor, PolicyTable::SCOPE_TYPE ), null );
				}
			}
		}

		if ( false === ( $legacyNative['product'] ?? null ) ) {
			$this->add( $rows, PolicyTable::SCOPE_TYPE, 'product', PolicyTable::STRENGTH_DEFAULT, self::SOURCE_WCML, PolicyTable::EDITOR_WPML, null );
		}

		foreach ( $types as $type ) {
			if ( self::isType( $type ) ) {
				$this->add( $rows, PolicyTable::SCOPE_TYPE, (string) $type, PolicyTable::STRENGTH_DEFAULT, '', null, self::method( (string) $type, $sinceDates, $isTranslateEverythingOn ) );
			}
		}

		return $rows;
	}

	public function postRows( array $tm, array $metaByPost ): array {
		$rows        = [];
		$hasGlobal   = (bool) $this->validEditor( $tm[ Keys::TM_KEY_GLOBAL_EDITOR ] ?? null, PolicyTable::SCOPE_SITE );
		$typeEditors = self::map( $tm, Keys::TM_KEY_FOR_POST_TYPE_EDITOR );

		foreach ( $metaByPost as $postId => $meta ) {
			$postId = (string) (int) $postId;
			if ( '0' === $postId ) {
				continue;
			}

			$editor = $this->validEditor( $meta['editor'] ?? null, PolicyTable::SCOPE_POST );
			if ( $editor ) {
				$this->add( $rows, PolicyTable::SCOPE_POST, $postId, PolicyTable::STRENGTH_CHOICE, '', $editor, null );
				continue;
			}

			if ( $hasGlobal || $this->validEditor( $typeEditors[ $meta['type'] ?? '' ] ?? null, PolicyTable::SCOPE_TYPE ) ) {
				continue;
			}

			list( $legacyEditor, $scope ) = \WPML_TM_Post_Edit_TM_Editor_Mode::derive_from_legacy_with_scope(
				$meta['native'] ?? null,
				$meta['wpml'] ?? null,
				null,
				null,
				null,
				null,
				''
			);
			if ( SetEditorMode::MODE_FOR_THIS_POST === $scope ) {
				$this->add( $rows, PolicyTable::SCOPE_POST, $postId, PolicyTable::STRENGTH_DEFAULT, '', $this->editor( $legacyEditor, PolicyTable::SCOPE_POST ), null );
			}
		}

		return $rows;
	}

	public static function key( array $row ): string {
		return implode( "\0", [ $row['scope'], (string) $row['scope_id'], $row['strength'], (string) $row['source'] ] );
	}

	private static function method( string $type, array $sinceDates, bool $isTranslateEverythingOn ): string {
		if ( ! $isTranslateEverythingOn || ! TranslateEverythingScope::isOffered( $type ) ) {
			return PolicyTable::METHOD_SELF;
		}

		$since = isset( $sinceDates[ $type ] ) ? (string) $sinceDates[ $type ] : ( $sinceDates ? Option::SINCE_DATE_SKIP_TYPE : Option::SINCE_DATE_ALL );

		return Option::SINCE_DATE_SKIP_TYPE === $since ? PolicyTable::METHOD_SELF : PolicyTable::METHOD_PTC;
	}

	private function add( array &$rows, string $scope, string $scopeId, string $strength, string $source, $editor, $method ) {
		$row = [
			'scope'    => $scope,
			'scope_id' => $scopeId,
			'strength' => $strength,
			'source'   => $source,
			'editor'   => $editor,
			'method'   => $method,
		];
		$key = self::key( $row );

		if ( isset( $rows[ $key ] ) ) {
			$rows[ $key ]['editor'] = $rows[ $key ]['editor'] ?? $editor;
			$rows[ $key ]['method'] = $rows[ $key ]['method'] ?? $method;

			return;
		}

		$rows[ $key ] = $row;
	}

	private function validEditor( $value, string $scope ) {
		return in_array( $value, [ Keys::EDITOR_NATIVE, Keys::EDITOR_WPML, Keys::EDITOR_DASHBOARD ], true ) ? $this->editor( $value, $scope ) : null;
	}

	private function editor( string $legacyEditor, string $scope ): string {
		if ( Keys::EDITOR_NATIVE === $legacyEditor ) {
			return PolicyTable::EDITOR_NATIVE;
		}

		if ( PolicyTable::SCOPE_SITE === $scope && Keys::EDITOR_DASHBOARD === $legacyEditor ) {
			return $this->wpmlEditor;
		}

		return PolicyTable::EDITOR_WPML;
	}

	private static function map( array $tm, string $key ): array {
		return is_array( $tm[ $key ] ?? null ) ? $tm[ $key ] : [];
	}

	private static function isType( $type ): bool {
		return is_string( $type ) && '' !== $type && strlen( $type ) <= 191;
	}
}
