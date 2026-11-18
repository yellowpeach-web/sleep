<?php

namespace WPML\Compatibility\Divi\Hooks;

class AppearedOnSite implements \IWPML_Frontend_Action, \IWPML_Backend_Action {

	const POST_TYPES = [
		'et_header_layout',
		'et_body_layout',
		'et_footer_layout',
	];

	const PRIORITY_AFTER_DIVI = 999;

	public function add_hooks() {
		add_filter( 'wpml_appearance_part_of_types', [ $this, 'declareTypes' ] );
		add_filter( 'et_theme_builder_template_layouts', [ $this, 'markLayouts' ], self::PRIORITY_AFTER_DIVI );
		add_filter( 'wpml_appearance_surface_labels', [ $this, 'labelTypes' ] );
	}

	public function labelTypes( $labels ) {
		$labels = is_array( $labels ) ? $labels : [];
		$label  = __( 'Divi Theme Builder layouts', 'sitepress' );

		foreach ( self::POST_TYPES as $postType ) {
			$labels[ self::elementType( $postType ) ] = $label;
		}

		return $labels;
	}

	public function declareTypes( $types ) {
		$types = is_array( $types ) ? $types : [];

		foreach ( self::POST_TYPES as $postType ) {
			$types[] = self::elementType( $postType );
		}

		return array_values( array_unique( $types ) );
	}

	public function markLayouts( $layouts ) {
		if ( ! is_array( $layouts ) || ! $layouts || self::isDiviBuilder() ) {
			return $layouts;
		}

		foreach ( self::POST_TYPES as $area ) {
			$layout = isset( $layouts[ $area ] ) && is_array( $layouts[ $area ] ) ? $layouts[ $area ] : [];
			if ( empty( $layout['override'] ) || empty( $layout['enabled'] ) || empty( $layout['id'] ) ) {
				continue;
			}

			self::markShown( $layout['id'] );
		}

		return $layouts;
	}

	public static function markShown( $postId ) {
		$postId = is_numeric( $postId ) ? (int) $postId : 0;
		if ( $postId <= 0 ) {
			return false;
		}

		$postType = get_post_type( $postId );
		if ( ! in_array( $postType, self::POST_TYPES, true ) ) {
			return false;
		}

		do_action( 'wpml_appearance_mark', self::elementType( $postType ), $postId );

		return true;
	}

	private static function elementType( $postType ) {
		return 'post_' . $postType;
	}

	private static function isDiviBuilder() {
		return ! empty( $_GET['et_fb'] ) || isset( $_GET['et_tb_preview'] );
	}
}
