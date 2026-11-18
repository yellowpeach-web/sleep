<?php

namespace WPML\PB\BeaverBuilder\Hooks;

use FLBuilderModel;
use WPML\LIB\WP\Hooks;
use WPML_Beaver_Builder_Data_Settings;
use function WPML\FP\spreadArgs;

class Cache implements \IWPML_Frontend_Action, \IWPML_Backend_Action {

	public function add_hooks() {
		Hooks::onAction( 'wpml_pro_translation_completed' )
			->then( spreadArgs( [ $this, 'flush' ] ) );
		Hooks::onAction( 'wpml_pb_duplicate_links_converted' )
			->then( spreadArgs( [ $this, 'flush' ] ) );
		Hooks::onAction( [ 'added_post_meta', 'updated_post_meta' ], 10, 3 )
			->then( spreadArgs( [ $this, 'flushOnLayoutDataSaved' ] ) );
	}

	public function flush( $postId ) {
		if ( class_exists( FLBuilderModel::class ) && method_exists( FLBuilderModel::class, 'delete_all_asset_cache' ) ) {
			FLBuilderModel::delete_all_asset_cache( $postId );
		}
	}

	public function flushOnLayoutDataSaved( $metaId, $postId, $metaKey ) {
		if ( WPML_Beaver_Builder_Data_Settings::META_FIELD_KEY === $metaKey ) {
			$this->flush( $postId );
		}
	}
}
