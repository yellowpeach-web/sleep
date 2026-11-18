<?php

namespace WPML\PB\Gutenberg\Widgets\Block;

use WPML\LIB\WP\Hooks;
use function WPML\FP\spreadArgs;

class RegisterPackageKind implements \WPML\PB\Gutenberg\Integration {

	const PRIORITY_AFTER_LEGACY_ST_REGISTRATION = 11;

	public function add_hooks() {
		Hooks::onFilter( 'wpml_active_string_package_kinds', self::PRIORITY_AFTER_LEGACY_ST_REGISTRATION )
			->then( spreadArgs( [ self::class, 'registerKind' ] ) );
	}

	public static function registerKind( $kinds ) {
		if ( ! is_array( $kinds ) ) {
			$kinds = [];
		}

		$kinds[ Strings::PACKAGE_KIND ] = [
			/* translators: Name of the kind of content made of block widgets (the Widgets screen), on the WPML translation dashboard. Singular. */
			'title'  => __( 'Widget', 'sitepress' ),
			'slug'   => Strings::PACKAGE_KIND_SLUG,
			/* translators: Name of the kind of content made of block widgets (the Widgets screen), on the WPML translation dashboard. Plural. */
			'plural' => __( 'Widgets', 'sitepress' ),
		];

		return $kinds;
	}
}
