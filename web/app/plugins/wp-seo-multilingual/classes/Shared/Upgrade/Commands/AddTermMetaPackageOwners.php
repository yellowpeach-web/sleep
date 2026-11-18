<?php

namespace WPML\WPSEO\Shared\Upgrade\Commands;

use WPML\WPSEO\YoastSEO\Loaders;
use WPML\WPSEO\YoastSEO\Terms\Meta\Hooks as TermMetaHooks;

class AddTermMetaPackageOwners implements Command {

	public static function run() {
		global $wpdb;

		if ( ! Loaders::supportsPerTermPackages() ) {
			return;
		}

		$packages = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT ID, name FROM {$wpdb->prefix}icl_string_packages
				WHERE kind_slug = %s AND name LIKE %s AND ( owner_kind IS NULL OR owner_kind = '' )",
				TermMetaHooks::PACKAGE['kind_slug'],
				'term-%'
			)
		);
		foreach ( (array) $packages as $package ) {
			$term = self::ownerTerm( $package->name );
			if ( ! $term ) {
				continue;
			}
			$updated = $wpdb->update(
				$wpdb->prefix . 'icl_string_packages',
				[ 'owner_kind' => 'term', 'owner_element_id' => (int) $term->term_taxonomy_id ],
				[ 'ID' => (int) $package->ID ]
			);
			if ( $updated ) {
				wp_cache_delete( 'name-kind:' . TermMetaHooks::PACKAGE['kind_slug'] . $package->name, 'WPML_Package' );
				do_action( 'wpml_string_package_owner_changed', 'tax_' . $term->taxonomy, (int) $term->term_taxonomy_id, (int) $package->ID );
			}
		}
		if ( $packages ) {
			wp_cache_delete( 'get_all_packages', 'string_package' );
		}
	}

	private static function ownerTerm( string $packageName ) {
		if ( ! preg_match( '/^term-(\d+)$/', $packageName, $matches ) ) {
			return null;
		}
		$term = TermMetaHooks::withoutTermAdjustId( function() use ( $matches ) {
			return get_term_by( 'term_taxonomy_id', (int) $matches[1] );
		} );

		return $term instanceof \WP_Term ? $term : null;
	}
}
