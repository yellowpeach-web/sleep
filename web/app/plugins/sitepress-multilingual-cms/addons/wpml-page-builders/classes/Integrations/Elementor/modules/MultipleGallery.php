<?php
namespace WPML\PB\Elementor\Modules;

class MultipleGallery extends \WPML_Elementor_Module_With_Items {

    protected function get_title( $field ) {
        switch ( $field ) {
            case 'gallery_title':
				/* translators: Field label in WPML's translation editor for a page built with Elementor. Before the colon is the name Elementor gives the widget on its own canvas, after it the field inside that widget; keep both halves and the colon. */
                return esc_html__( 'Galleries: Gallery Title', 'sitepress' );
            default:
                return '';
        }
    }

    public function get_fields() {
        return [ 'gallery_title' ];
    }

    protected function get_editor_type( $field ) {
        if ( 'gallery_title' === $field ) {
            return 'LINE';
        }

		return 'LINE';
    }

    public function get_items_field() {
        return 'galleries';
    }
}