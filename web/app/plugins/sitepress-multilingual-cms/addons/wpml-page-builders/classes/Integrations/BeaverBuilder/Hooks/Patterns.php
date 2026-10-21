<?php

namespace WPML\PB\BeaverBuilder\Hooks;

use FLBuilderModel;

class Patterns implements \IWPML_Frontend_Action {

	const POST_TYPE   = 'wp_block';
	const MODULE_TYPE = 'reusable-block';

	public function add_hooks() {
		add_action( 'pre_get_posts', [ $this, 'filterByLanguage' ] );
		add_filter( 'fl_builder_layout_data', [ $this, 'translateSelectedPatterns' ] );
	}

	public function filterByLanguage( $query ) {
		if ( self::POST_TYPE === ( $query->query_vars['post_type'] ?? null ) && $this->isBuilderActive() ) {
			$query->query_vars['suppress_filters'] = false;
		}
	}

	public function translateSelectedPatterns( $data ) {
		if ( ! is_array( $data ) || ! $data || ! $this->isBuilderActive() ) {
			return $data;
		}

		foreach ( $data as $nodeId => $node ) {
			$patternId = $this->getPatternId( $node );

			if ( ! $patternId ) {
				continue;
			}

			$translatedId = (int) apply_filters( 'wpml_object_id', $patternId, self::POST_TYPE, true );

			if ( $translatedId && $translatedId !== $patternId ) {
				$node                     = clone $node;
				$node->settings           = clone $node->settings;
				$node->settings->block_id = 'block-' . $translatedId;
				$data[ $nodeId ]          = $node;
			}
		}

		return $data;
	}

	private function getPatternId( $node ) {
		if (
			! is_object( $node )
			|| ! isset( $node->settings )
			|| ! is_object( $node->settings )
			|| self::MODULE_TYPE !== ( $node->settings->type ?? null )
			|| ! is_string( $node->settings->block_id ?? null )
			|| ! preg_match( '/^block-(\d+)$/', $node->settings->block_id, $matches )
		) {
			return 0;
		}

		return (int) $matches[1];
	}

	private function isBuilderActive() {
		return class_exists( FLBuilderModel::class )
			&& method_exists( FLBuilderModel::class, 'is_builder_active' )
			&& FLBuilderModel::is_builder_active();
	}
}
