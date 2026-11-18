<?php

namespace WPML\TranslationPolicy\Policy;

use WPML\Collect\Support\Collection;
use WPML\FP\Right;
use WPML\TM\PostEditScreen\Endpoints\SetEditorMode;

class PolicyEditorMode {

	const REASON = 'editor-switcher';

	private $sitepress;

	private $sql;

	private $writer;

	public function __construct( \SitePress $sitepress, PolicySql $sql, PolicyWriter $writer ) {
		$this->sitepress = $sitepress;
		$this->sql       = $sql;
		$this->writer    = $writer;
	}

	public static function create( \SitePress $sitepress ): self {
		global $wpdb;

		$sql = new PolicySql( $wpdb );

		return new self( $sitepress, $sql, new PolicyWriter( $wpdb, new PolicyTable( $wpdb ), $sql ) );
	}

	public function run( Collection $data ) {
		$editorModeFor = $data->get( 'editorModeFor' );
		$editor        = $this->editor( $data->get( 'enabledEditor' ), SetEditorMode::MODE_FOR_GLOBAL === $editorModeFor );
		if ( ! $editor ) {
			return Right::of( false );
		}

		$postId  = (int) $data->get( 'postId' );
		$isReset = (bool) $data->get( 'isSwitchingWpmlNative' );

		switch ( $editorModeFor ) {
			case SetEditorMode::MODE_FOR_GLOBAL:
				if ( $isReset ) {
					\WPML_TM_Post_Edit_TM_Editor_Mode::delete_all_posts_option();
					$this->deleteOldTypeKeys();
					$this->sql->resetTypeAndPostEditors();
				}
				$this->writer->set( PolicyTable::SCOPE_SITE, '', $editor, $this->keptMethod( PolicyTable::SCOPE_SITE, '' ), PolicyTable::STRENGTH_CHOICE, '', self::REASON );
				break;

			case SetEditorMode::MODE_FOR_POST_TYPE:
				$postType = (string) get_post_type( $postId );
				if ( '' !== $postType ) {
					if ( $isReset ) {
						\WPML_TM_Post_Edit_TM_Editor_Mode::delete_all_posts_option( $postType );
						$this->sql->resetPostEditorsOfType( $postType );
					}
					$this->writer->set( PolicyTable::SCOPE_TYPE, $postType, $editor, $this->keptMethod( PolicyTable::SCOPE_TYPE, $postType ), PolicyTable::STRENGTH_CHOICE, '', self::REASON );
				}
				break;

			case SetEditorMode::MODE_FOR_THIS_POST:
				if ( $postId > 0 ) {
					$this->writer->set( PolicyTable::SCOPE_POST, (string) $postId, $editor, $this->keptMethod( PolicyTable::SCOPE_POST, (string) $postId ), PolicyTable::STRENGTH_CHOICE, '', self::REASON );
				}
				break;
		}

		Policy::resetRequestCache();

		return Right::of( true );
	}

	private function keptMethod( string $scope, string $scopeId ) {
		$choice = $this->sql->choiceRow( $scope, $scopeId );
		$method = $choice['method'] ?? null;

		return in_array( $method, PolicyTable::METHODS, true ) ? $method : null;
	}

	private function deleteOldTypeKeys() {
		$tmSettings = $this->sitepress->get_setting( 'translation-management' );
		if ( ! is_array( $tmSettings ) ) {
			return;
		}

		unset(
			$tmSettings[ \WPML_TM_Post_Edit_TM_Editor_Mode::TM_KEY_FOR_POST_TYPE_EDITOR ],
			$tmSettings[ \WPML_TM_Post_Edit_TM_Editor_Mode::TM_KEY_FOR_POST_TYPE_USE_NATIVE ],
			$tmSettings[ \WPML_TM_Post_Edit_TM_Editor_Mode::TM_KEY_FOR_POST_TYPE_USE_WPML ]
		);
		$this->sitepress->set_setting( 'translation-management', $tmSettings, true );
	}

	private function editor( $value, bool $isSite ) {
		if ( SetEditorMode::TRANSLATION_EDITOR_NATIVE === $value ) {
			return PolicyTable::EDITOR_NATIVE;
		}

		if ( $isSite && SetEditorMode::TRANSLATION_EDITOR_DASHBOARD === $value ) {
			return PolicyTable::fallbackEditor();
		}

		if ( in_array( $value, [ SetEditorMode::TRANSLATION_EDITOR_WPML, SetEditorMode::TRANSLATION_EDITOR_DASHBOARD ], true ) ) {
			return PolicyTable::EDITOR_WPML;
		}

		return null;
	}
}
