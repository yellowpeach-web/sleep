<?php

namespace WPML\Compatibility\Divi\V5;

use WPML\PB\Integrations\Divi\Helper;
use SitePress;

class CopyContent implements \IWPML_DIC_Action, \IWPML_Backend_Action, \IWPML_REST_Action {

	private const BUILDER_META_KEYS = [ '_et_pb_use_builder', '_et_pb_use_divi_5' ];

	private const COPY_FLAG_PREFIX = 'wpml_pb_divi5_copied_';
	private const COPY_FLAG_TTL    = HOUR_IN_SECONDS;

	private const COLLAPSED_PLACEHOLDER = '#^<!--\s*wp:divi/placeholder(?:\s+\{.*\})?\s*/-->$#s';

	private const DIVI_MODULE = '#<!--\s+wp:divi/(?!placeholder)#';

	private $sitepress;

	private $httpReferer;

	private $restoredOriginals = [];

	public function __construct( SitePress $sitepress, \WPML_URL_HTTP_Referer $httpReferer ) {
		$this->sitepress   = $sitepress;
		$this->httpReferer = $httpReferer;
	}

	public function add_hooks() {
		add_action( 'icl_copy_from_original', [ $this, 'flagCopiedOriginal' ] );
		add_filter( 'wp_insert_post_data', [ $this, 'restoreCollapsedLayout' ], 10, 2 );
		add_action( 'save_post', [ $this, 'copyBuilderMeta' ], PHP_INT_MAX );
	}

	public function flagCopiedOriginal( $originalId ) {
		$originalId = (int) $originalId;
		$trid       = (int) filter_input( INPUT_POST, 'trid' );

		if ( $originalId && $trid && Helper::isPostUsingDivi5( $originalId ) ) {
			set_transient( self::flagKey( $trid ), $originalId, self::COPY_FLAG_TTL );
		}
	}

	public function restoreCollapsedLayout( $data, $postarr ) {
		if ( ! self::isCollapsedLayout( wp_unslash( $data['post_content'] ?? '' ) ) ) {
			return $data;
		}

		$postId   = (int) ( $postarr['ID'] ?? 0 );
		$original = $postId ? $this->getFlaggedOriginal( $postId, (string) ( $data['post_type'] ?? '' ) ) : null;

		if ( ! $original ) {
			return $data;
		}

		$data['post_content']               = wp_slash( $original->post_content );
		$this->restoredOriginals[ $postId ] = $original->ID;

		return $data;
	}

	public function copyBuilderMeta( $postId ) {
		$originalId = $this->restoredOriginals[ $postId ] ?? 0;

		if ( ! $originalId ) {
			return;
		}

		foreach ( self::BUILDER_META_KEYS as $key ) {
			update_post_meta( $postId, $key, get_post_meta( $originalId, $key, true ) );
		}

		unset( $this->restoredOriginals[ $postId ] );
	}

	private function getFlaggedOriginal( $postId, $postType ) {
		$trid = $this->getTrid( $postId, $postType );

		if ( ! $trid ) {
			return null;
		}

		$originalId = (int) get_transient( self::flagKey( $trid ) );

		if ( ! $originalId || $originalId === $postId ) {
			return null;
		}

		$original = get_post( $originalId );

		if ( ! $original || ! preg_match( self::DIVI_MODULE, $original->post_content ) ) {
			return null;
		}

		if ( (int) $this->sitepress->get_element_trid( $original->ID, 'post_' . $original->post_type ) !== $trid ) {
			return null;
		}

		return $original;
	}

	private function getTrid( $postId, $postType ) {
		$postType = get_post_type( $postId ) ?: $postType;
		$trid     = (int) $this->sitepress->get_element_trid( $postId, 'post_' . $postType );

		return $trid ?: $this->getTridOfUnsavedTranslation();
	}

	private function getTridOfUnsavedTranslation() {
		return (int) $this->httpReferer->get_trid();
	}

	private static function isCollapsedLayout( $content ) {
		return (bool) preg_match( self::COLLAPSED_PLACEHOLDER, trim( $content ) );
	}

	private static function flagKey( $trid ) {
		return self::COPY_FLAG_PREFIX . $trid . '_' . get_current_user_id();
	}
}
