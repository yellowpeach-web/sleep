<?php

namespace WPML\TM\PostEditScreen\Endpoints;

use WPML\Ajax\Authorization\Authorized;
use WPML\Ajax\IHandler;
use WPML\Collect\Support\Collection;
use WPML\FP\Right;
use WPML\LIB\WP\User;
use WPML_TM_Post_Edit_TM_Editor_Mode;

class SetEditorMode implements IHandler, Authorized {

	const TRANSLATION_EDITOR_DASHBOARD = 'dashboard';
	const TRANSLATION_EDITOR_WPML      = 'wpml';
	const TRANSLATION_EDITOR_NATIVE    = 'native';

	const MODE_FOR_GLOBAL    = 'global';
	const MODE_FOR_POST_TYPE = 'all_posts_of_type';
	const MODE_FOR_THIS_POST = 'this_post';

	private $sitepress;

	public function __construct( \SitePress $sitepress ) {
		$this->sitepress = $sitepress;
	}

	public function authorize( Collection $data ) {
		if ( self::MODE_FOR_THIS_POST === $data->get( 'editorModeFor' ) ) {
			$postId = (int) $data->get( 'postId' );
			if ( $postId <= 0 ) {
				return false;
			}

			return User::canManageTranslations() || current_user_can( 'edit_post', $postId );
		}

		return User::canManageTranslations();
	}

	public function run( Collection $data ) {
		$tmSettings = $this->sitepress->get_setting( 'translation-management' );

		$enabledEditor = $data->get( 'enabledEditor' );
		$postId        = $data->get( 'postId' );
		$editorModeFor = $data->get( 'editorModeFor' );

		$isSwitchingWpmlNative = $data->get( 'isSwitchingWpmlNative' );

		switch ( $editorModeFor ) {
			case self::MODE_FOR_GLOBAL:
				$tmSettings[ WPML_TM_Post_Edit_TM_Editor_Mode::TM_KEY_GLOBAL_EDITOR ] = $enabledEditor;

				if ( $isSwitchingWpmlNative ) {
					unset( $tmSettings[ WPML_TM_Post_Edit_TM_Editor_Mode::TM_KEY_FOR_POST_TYPE_EDITOR ] );
					unset( $tmSettings[ WPML_TM_Post_Edit_TM_Editor_Mode::TM_KEY_FOR_POST_TYPE_USE_NATIVE ] );
					unset( $tmSettings[ WPML_TM_Post_Edit_TM_Editor_Mode::TM_KEY_FOR_POST_TYPE_USE_WPML ] );

					WPML_TM_Post_Edit_TM_Editor_Mode::delete_all_posts_option();
				}

				$this->sitepress->set_setting( 'translation-management', $tmSettings, true );
				break;

			case self::MODE_FOR_POST_TYPE:
				$post_type = get_post_type( $postId );

				if ( $post_type ) {
					$tmSettings[ WPML_TM_Post_Edit_TM_Editor_Mode::TM_KEY_FOR_POST_TYPE_EDITOR ][ $post_type ] = $enabledEditor;

					if ( $isSwitchingWpmlNative ) {
						WPML_TM_Post_Edit_TM_Editor_Mode::delete_all_posts_option( $post_type );
					}

					$this->sitepress->set_setting( 'translation-management', $tmSettings, true );
				}
				break;

			case self::MODE_FOR_THIS_POST:
				update_post_meta(
					$postId,
					WPML_TM_Post_Edit_TM_Editor_Mode::POST_META_KEY_EDITOR,
					$enabledEditor
				);
				break;
		}

		return Right::of( true );
	}
}
