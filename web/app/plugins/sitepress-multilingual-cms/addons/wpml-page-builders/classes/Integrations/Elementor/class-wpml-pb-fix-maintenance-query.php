<?php

class WPML_PB_Fix_Maintenance_Query implements IWPML_Frontend_Action {

	const AFTER_TEMPLATE_APPLY = 12;

	const TEMPLATE_POST_TYPE = 'elementor_library';

	public function add_hooks() {
		add_action( 'template_redirect', array( $this, 'fix_global_query' ), self::AFTER_TEMPLATE_APPLY );
		add_action( 'pre_get_posts', array( $this, 'show_original_template_until_translated' ) );
	}

	public function fix_global_query() {
		if (
			class_exists( '\Elementor\Maintenance_Mode' ) &&
			isset( $GLOBALS['post']->ID ) &&
			(int) \Elementor\Maintenance_Mode::get( 'template_id' ) === $GLOBALS['post']->ID
		) {
			$GLOBALS['wp_the_query'] = $GLOBALS['wp_query'];
		}
	}

	public function show_original_template_until_translated( $query ) {
		if (
			! $query instanceof WP_Query ||
			! doing_action( 'template_redirect' ) ||
			! class_exists( '\Elementor\Maintenance_Mode' )
		) {
			return;
		}

		$args = (array) $query->query;
		if ( self::TEMPLATE_POST_TYPE !== ( $args['post_type'] ?? null ) || empty( $args['p'] ) ) {
			return;
		}

		$templateId = (int) \Elementor\Maintenance_Mode::get( 'template_id' );
		if ( ! $templateId || (int) $args['p'] !== $templateId || $this->hasPublishedCopy( $templateId ) ) {
			return;
		}

		$query->query_vars['p'] = $templateId;

		$args['suppress_wpml_where_and_join_filter'] = true;
		$query->query                                = $args;
	}

	private function hasPublishedCopy( $templateId ) {
		$copyId = apply_filters( 'wpml_object_id', $templateId, self::TEMPLATE_POST_TYPE, false );

		return $copyId && 'publish' === get_post_status( (int) $copyId );
	}
}
