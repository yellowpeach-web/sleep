<?php

namespace ACFML\FieldGroup;

use ACFML\Helper\FieldGroup;
use WPML\FP\Obj;

class SetCptNotTranslatable implements \IWPML_Backend_Action {

	const ACTION = 'acfml_set_field_groups_not_translatable';
	const NONCE  = 'acfml_set_field_groups_not_translatable';

	const MODE_DO_NOT_TRANSLATE = 'do_not_translate';

	const READONLY_SETTING     = 'translation-management';
	const READONLY_SUB_SETTING = 'custom-types_readonly_config';

	public function add_hooks() {
		add_action( 'admin_post_' . self::ACTION, [ $this, 'handle' ] );
	}

	public static function getUrl() {
		return wp_nonce_url(
			add_query_arg( [ 'action' => self::ACTION ], admin_url( 'admin-post.php' ) ),
			self::NONCE,
			self::NONCE
		);
	}

	public static function isConfigLocked() {
		$readonly = apply_filters( 'wpml_sub_setting', [], self::READONLY_SETTING, self::READONLY_SUB_SETTING );

		return is_array( $readonly ) && null !== Obj::prop( FieldGroup::CPT, $readonly );
	}

	public function handle() {
		if ( ! current_user_can( 'manage_options' ) ) {
			$this->refuse();

			return;
		}

		check_admin_referer( self::NONCE, self::NONCE );

		if ( ! self::isConfigLocked() ) {
			do_action( 'wpml_set_translation_mode_for_post_type', FieldGroup::CPT, self::MODE_DO_NOT_TRANSLATE );
			delete_transient( \WPML_ACF_Translatable_Groups_Checker::TRANSIENT_KEY );
		}

		$this->redirect( $this->getReturnUrl() );
	}

	private function getReturnUrl() {
		$referer = wp_get_referer();

		return $referer ? $referer : \ACFML\Tools\AdminUrl::getFieldGroupsList();
	}

	protected function refuse() {
		wp_die(
			/* translators: Error page shown when the logged-in user may not change WPML's translation settings. */
			esc_html__( 'You are not allowed to change the WPML translation settings on this site.', 'acfml' ),
			'',
			[ 'response' => 403 ]
		);
	}

	protected function redirect( $url ) {
		wp_safe_redirect( $url );
		exit;
	}
}
