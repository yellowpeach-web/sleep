<?php

namespace WPML\PB\Elementor\Hooks;

use Elementor\Plugin;

class AppearedOnSite implements \IWPML_Frontend_Action, \IWPML_Backend_Action {

	const POST_TYPE = 'elementor_library';

	const ELEMENT_TYPE = 'post_' . self::POST_TYPE;

	public function add_hooks() {
		add_filter( 'wpml_appearance_part_of_types', [ $this, 'declareTypes' ] );
		add_action( 'elementor/frontend/get_builder_content', [ $this, 'onDocumentRendered' ], 10, 1 );
		add_filter( 'wpml_appearance_surface_labels', [ $this, 'labelTypes' ] );
	}

	public function labelTypes( $labels ) {
		$labels = is_array( $labels ) ? $labels : [];

		$labels[ self::ELEMENT_TYPE ] = __( 'Elementor templates', 'sitepress' );

		return $labels;
	}

	public function declareTypes( $types ) {
		$types   = is_array( $types ) ? $types : [];
		$types[] = self::ELEMENT_TYPE;

		return array_values( array_unique( $types ) );
	}

	public function onDocumentRendered( $document ) {
		$this->markDocument( $document );
	}

	public function markDocument( $document ) {
		if ( ! is_object( $document ) || ! is_callable( [ $document, 'get_main_id' ] ) ) {
			return false;
		}

		return self::markShown( (int) $document->get_main_id() );
	}

	public static function markShown( $postId ) {
		$postId = (int) $postId;
		if ( $postId <= 0 || self::isElementorPreview() ) {
			return false;
		}

		if ( self::POST_TYPE !== get_post_type( $postId ) ) {
			return false;
		}

		do_action( 'wpml_appearance_mark', self::ELEMENT_TYPE, $postId );

		return true;
	}

	private static function isElementorPreview() {
		if ( ! isset( $_GET['elementor-preview'] ) || ! class_exists( Plugin::class ) ) {
			return false;
		}

		$elementor = Plugin::instance();

		if ( isset( $elementor->preview ) && $elementor->preview->is_preview_mode() ) {
			return true;
		}

		return isset( $elementor->editor ) && $elementor->editor->is_edit_mode();
	}
}
