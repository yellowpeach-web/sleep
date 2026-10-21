<?php

namespace WPML\Compatibility\FusionBuilder\Frontend;

use WPML\API\Sanitize;
use WPML\Compatibility\FusionBuilder\BaseHooks;
use WPML\FP\Obj;

class Hooks extends BaseHooks implements \IWPML_Frontend_Action, \IWPML_DIC_Action {

	const LAYOUT_POST_TYPE = 'fusion_tb_layout';

	private $sitepress;

	public function __construct(
		\SitePress $sitepress
	) {
		$this->sitepress = $sitepress;
	}

	public function add_hooks() {
		add_filter( 'nav_menu_link_attributes', [ $this, 'addMenuLinkCssClass' ], 10, 2 );
		add_filter( 'fusion_get_all_meta', [ $this, 'translateOffCanvasConditionId' ] );
		add_action( 'pre_get_posts', [ $this, 'enableFiltersForThemeBuilderQuery' ] );
		add_filter( 'the_posts', [ $this, 'translateThemeBuilderConditionIds' ], 10, 2 );
	}

	public function addMenuLinkCssClass( $atts, $item ) {
		if ( 'wpml_ls_menu_item' === $item->type ) {
			$class         = Obj::prop( 'class', $atts );
			$atts['class'] = $class ? "$class wpml-ls-link" : 'wpml-ls-link';
		}

		return $atts;
	}

	public function translateOffCanvasConditionId( $data ) {
		if ( is_array( $data ) && Obj::prop( 'layout_conditions', $data ) ) {
			$conditions = json_decode( Obj::prop( 'layout_conditions', $data ), true );
			if ( ! is_array( $conditions ) ) {
				return $data;
			}
			$result = [];
			foreach ( $conditions as $key => $condition ) {
				$result[ $this->translateConditionKey( $key ) ] = $condition;
			}
			$data = Obj::assoc( 'layout_conditions', wp_json_encode( $result ), $data );
		}

		return $data;
	}

	public function enableFiltersForThemeBuilderQuery( $query ) {
		if (
			$query instanceof \WP_Query
			&& [ self::LAYOUT_POST_TYPE ] === (array) $query->get( 'post_type' )
			&& $query->get( 'suppress_filters' )
		) {
			$query->set( 'suppress_filters', false );
		}
	}

	public function translateThemeBuilderConditionIds( $posts, $query ) {
		if ( ! is_array( $posts ) || ! $query instanceof \WP_Query ) {
			return $posts;
		}

		if ( ! in_array( self::LAYOUT_POST_TYPE, (array) $query->get( 'post_type' ), true ) ) {
			return $posts;
		}

		foreach ( $posts as $post ) {
			if ( is_object( $post ) && isset( $post->post_type, $post->post_content ) && self::LAYOUT_POST_TYPE === $post->post_type ) {
				$post->post_content = $this->translateConditionIds( $post->post_content );
			}
		}

		return $posts;
	}

	private function translateConditionIds( string $post_content ): string {
		if ( false === strpos( $post_content, 'specific_' ) && false === strpos( $post_content, 'children_of_' ) ) {
			return $post_content;
		}

		$translated = preg_replace_callback(
			'/(?:specific_|children_of_)[a-z0-9_-]+\|\d+/',
			function ( $matches ) {
				return $this->translateConditionKey( $matches[0] );
			},
			$post_content
		);

		return null === $translated ? $post_content : $translated;
	}

	private function translateConditionKey( string $key ): string {
		foreach ( [ 'specific_', 'children_of_' ] as $prefix ) {
			if ( 0 === strpos( $key, $prefix ) && false !== strpos( $key, '|' ) ) {
				list( $pattern, $id ) = explode( '|', $key, 2 );
				$post_type            = substr( $pattern, strlen( $prefix ) );

				return $pattern . '|' . $this->sitepress->get_object_id( (int) $id, $post_type, true );
			}
		}

		return $key;
	}
}
