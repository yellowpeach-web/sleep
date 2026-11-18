<?php

namespace WPML\PB\Cornerstone;

class Utils {

	const MODULE_TYPE_PREFIX = 'classic:';
	const LAYOUT_TYPES       = [
		'bar',
		'container',
		'section',
		'row',
		'column',
		'layout-row',
		'layout-column',
		'layout-grid',
		'layout-cell',
		'layout-div',
		'layout-modal',
		'layout-off-canvas',
		'layout-slide-container',
		'layout-slide',
		'layout-dropdown',
		'layout-table',
		'layout-table-section',
		'layout-table-row',
	];

	const NODES_WITH_MODULES = [
		'accordion',
		'accordion-item-elements',
		'tabs',
		'tab-elements',
		'nav-inline',
		'layout-table-cell',
	];

	const LAYOUT_POST_TYPES = [
		'cs_layout_single',
		'cs_layout_archive',
		'cs_layout_single_wc',
		'cs_layout_archive_wc',
		'cs_header',
		'cs_footer',
		'cs_global_block',
	];

	public static function getNodeId( $data ) {
		return md5( serialize( $data ) );
	}

	public static function getLayoutNodeId( $data ) {
		unset( $data[ \WPML_Cornerstone_Translatable_Nodes::SETTINGS_FIELD ] );

		return self::getNodeId( $data );
	}

	public static function typeIsLayout( $type ) {
		$type = preg_replace( '/^' . self::MODULE_TYPE_PREFIX . '/', '', $type );

		return in_array( $type, self::getLayoutTypes(), true );
	}

	public static function isLayoutPostType( $postType ) {
		return in_array( $postType, self::getLayoutPostTypes(), true );
	}

	public static function getLayoutPostTypes() {
		return (array) apply_filters( 'wpml_cornerstone_layout_post_types', self::LAYOUT_POST_TYPES );
	}

	public static function getLayoutTypes() {
		return (array) apply_filters( 'wpml_cornerstone_layout_types', self::LAYOUT_TYPES );
	}

	public static function getNodesWithModules() {
		return (array) apply_filters( 'wpml_cornerstone_nodes_with_modules', self::NODES_WITH_MODULES );
	}

	public static function shouldCheckForSubmodules( $type ) {
		$shouldCheckForSubmodules = in_array( $type, self::getNodesWithModules(), true );

		return apply_filters( 'wpml_cornerstone_should_check_for_submodules', $shouldCheckForSubmodules, $type );
	}
}
