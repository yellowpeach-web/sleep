<?php
namespace WPML\PB\Elementor\Modules;

class Reviews extends \WPML_Elementor_Module_With_Items {

    protected function get_title( $field ) {
        switch ( $field ) {
            case 'content':
				/* translators: Field label in WPML's translation editor for a page built with Elementor. Before the colon is the name Elementor gives the widget on its own canvas, after it the field inside that widget; keep both halves and the colon. */
                return esc_html__( 'Reviews: Comment Contents', 'sitepress' );
            case 'name':
				/* translators: Field label in WPML's translation editor for a page built with Elementor. Before the colon is the name Elementor gives the widget on its own canvas, after it the field inside that widget; keep both halves and the colon. */
                return esc_html__( 'Reviews: Commenter Name', 'sitepress' );
            case 'title':
				/* translators: Field label in WPML's translation editor for a page built with Elementor. Before the colon is the name Elementor gives the widget on its own canvas, after it the field inside that widget; keep both halves and the colon. */
                return esc_html__( 'Reviews: Comment Title', 'sitepress' );
            case 'image':
				/* translators: Field label in WPML's translation editor for a page built with Elementor. Before the colon is the name Elementor gives the widget on its own canvas, after it the field inside that widget; keep both halves and the colon. */
                return esc_html__( 'Reviews: Comment Image', 'sitepress' );
            case 'url':
				/* translators: Field label in WPML's translation editor for a page built with Elementor. Before the colon is the name Elementor gives the widget on its own canvas, after it the field inside that widget; keep both halves and the colon. */
                return esc_html__( 'Reviews: Comment Link', 'sitepress' );
            default:
                return '';
        }
    }

    public function get_fields() {
        return [ 'content', 'name', 'title', 'link' => [ 'field' => 'url' ] ];
    }

    protected function get_editor_type( $field ) {
        if ( 'content' === $field ) {
            return 'LINE';
        }
        if ( 'name' === $field ) {
            return 'LINE';
        }
        if ( 'title' === $field ) {
            return 'LINE';
        }
        if ( 'url' === $field ) {
            return 'LINK';
        }

		return 'LINE';
    }

    public function get_items_field() {
        return 'slides';
    }
}