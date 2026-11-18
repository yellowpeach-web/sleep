<?php

namespace WPML\PB\FullSiteEditing;

class TemplateRestRoute {

	const TYPES = [
		'templates'      => 'wp_template',
		'template-parts' => 'wp_template_part',
	];

	public static function parse( $route ) {
		if ( ! is_string( $route ) || '' === $route ) {
			return null;
		}

		if ( ! preg_match( '#/wp/v2/(templates|template-parts)/([^?\#]+)#', $route, $m ) ) {
			return null;
		}

		if ( ! preg_match( '#^([^/]+//[^/]+)#', rawurldecode( $m[2] ), $id ) ) {
			return null;
		}

		return [ self::TYPES[ $m[1] ], $id[1] ];
	}

	public static function savedWpId( $route ) {
		$parsed = self::parse( $route );
		if ( ! $parsed || ! function_exists( 'get_block_template' ) ) {
			return null;
		}

		list( $type, $id ) = $parsed;
		$template          = get_block_template( $id, $type );

		if ( ! is_object( $template ) || empty( $template->wp_id ) ) {
			return null;
		}

		return (int) $template->wp_id;
	}
}
