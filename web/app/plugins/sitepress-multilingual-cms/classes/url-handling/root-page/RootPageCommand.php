<?php

namespace WPML\UrlHandling\RootPage;

use WPML\Core\Component\PostHog\Application\Service\Event\EventInstanceService;
use WPML\Request\Policy\Authenticity;
use WPML\Request\Policy\Policy;
use WPML\Request\Policy\Registry;

final class RootPageCommand {

	const ROUTE        = 'root-page-assign';
	const REST_ROUTE   = 'root-page-assign-rest';
	const CAPABILITY   = 'wpml_manage_languages';
	const FLAG_FIELD   = '_wpml_root_page';
	const NONCE_ACTION = 'wpml_root_page_assign';
	const NONCE_FIELD  = '_wpml_root_page_nonce';

	const PENDING_KEY = 'root_page_pending';

	public static function policy() {
		return Registry::declare(
			Registry::PSEUDO_ROUTE,
			self::ROUTE,
			Policy::capability(
				self::CAPABILITY,
				Authenticity::actionNonce( self::NONCE_ACTION, self::NONCE_FIELD )
			)
		);
	}

	public static function restPolicy() {
		return Registry::declare(
			Registry::PSEUDO_ROUTE,
			self::REST_ROUTE,
			Policy::capability( self::CAPABILITY, Authenticity::restTransport() )
		);
	}

	public static function isRequested() {
		return isset( $_POST[ self::FLAG_FIELD ] ) && (bool) filter_var( wp_unslash( $_POST[ self::FLAG_FIELD ] ), FILTER_VALIDATE_BOOLEAN );
	}

	public static function fields() {
		return '<input type="hidden" name="' . self::FLAG_FIELD . '" value="1" />'
			. wp_nonce_field( self::NONCE_ACTION, self::NONCE_FIELD, true, false );
	}

	public function assign( $post ) {
		global $sitepress, $iclTranslationManagement;

		$iclsettings['urls']['root_page']        = $post->ID;
		$iclsettings['urls'][ self::PENDING_KEY ] = 1;
		$sitepress->save_settings( $iclsettings );

		remove_action( 'save_post', array( $sitepress, 'save_post_actions' ), 10 );

		if ( ! is_null( $iclTranslationManagement ) ) {
			remove_action( 'save_post', array( $iclTranslationManagement, 'save_post_actions' ), 11 );
		}

		$update_args = array(
			'element_id'   => $post->ID,
			'element_type' => 'post_page',
			'context'      => 'post',
		);

		do_action( 'wpml_translation_update', array_merge( $update_args, array( 'type' => 'before_delete' ) ) );

		\WPML_Translation_Records_Delete::translations_where(
			"element_type = 'post_page' AND element_id = %d",
			array( $post->ID )
		);

		do_action( 'wpml_translation_update', array_merge( $update_args, array( 'type' => 'after_delete' ) ) );

		\WPML\PostHog\Event\CaptureEvent::capture(
			( new EventInstanceService() )->getRootPageSavedEvent( [
				'root_page_id'     => $post->ID,
				'root_page_status' => $post->post_status,
			] )
		);
	}
}
