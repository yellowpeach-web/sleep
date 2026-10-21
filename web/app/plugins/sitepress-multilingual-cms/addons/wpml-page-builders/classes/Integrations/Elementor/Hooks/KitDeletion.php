<?php

namespace WPML\PB\Elementor\Hooks;

use WPML\LIB\WP\Hooks;
use function WPML\FP\spreadArgs;

class KitDeletion implements \IWPML_Backend_Action, \IWPML_DIC_Action {

	const TEMPLATES_POST_TYPE = 'elementor_library';

	const FORCE_DELETE_ARG = 'force_delete_kit';

	const PRIORITY_BEFORE_ELEMENTOR = 9;

	const PRIORITY_AFTER_ELEMENTOR = 11;

	private const KIT_OPTIONS = [ 'elementor_active_kit', 'elementor_previous_kit' ];

	private $forcedPostId = null;

	private $sitepress;

	public function __construct( \SitePress $sitepress ) {
		$this->sitepress = $sitepress;
	}

	public function add_hooks() {
		Hooks::onAction( 'before_delete_post', self::PRIORITY_BEFORE_ELEMENTOR )
			->then( spreadArgs( [ $this, 'maybeSkipConfirmation' ] ) );

		Hooks::onAction( 'before_delete_post', self::PRIORITY_AFTER_ELEMENTOR )
			->then( spreadArgs( [ $this, 'restoreConfirmation' ] ) );

		Hooks::onAction( 'deleted_post' )
			->then( spreadArgs( [ $this, 'discardStaleKitReferences' ] ) );
	}

	public function maybeSkipConfirmation( $postId ) {
		if ( isset( $_GET[ self::FORCE_DELETE_ARG ] ) || ! $this->isKitInInactiveLanguage( $postId ) ) {
			return;
		}

		$_GET[ self::FORCE_DELETE_ARG ] = '1';
		$this->forcedPostId             = (int) $postId;
	}

	public function restoreConfirmation( $postId ) {
		if ( $this->forcedPostId === (int) $postId ) {
			unset( $_GET[ self::FORCE_DELETE_ARG ] );
		}
	}

	private function isKitInInactiveLanguage( $postId ) {
		if ( self::TEMPLATES_POST_TYPE !== get_post_type( $postId ) ) {
			return false;
		}

		$language = $this->sitepress->get_language_for_element( $postId, 'post_' . self::TEMPLATES_POST_TYPE );

		return (bool) $language
			&& ! isset( $this->sitepress->get_active_languages()[ $language ] )
			&& $this->isKit( $postId );
	}

	private function isKit( $postId ) {
		try {
			$kitsManager = \Elementor\Plugin::instance()->kits_manager;

			return $kitsManager && $kitsManager->is_kit( $postId );
		} catch ( \Throwable $e ) {
			return false;
		}
	}

	public function discardStaleKitReferences( $postId ) {
		if ( $this->forcedPostId !== (int) $postId ) {
			return;
		}

		$this->forcedPostId = null;

		foreach ( self::KIT_OPTIONS as $option ) {
			if ( (int) get_option( $option ) === (int) $postId ) {
				delete_option( $option );
			}
		}
	}
}
