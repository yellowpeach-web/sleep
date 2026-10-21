<?php

namespace WPML\TranslationRoles;

class CacheRefresh {

	public static function execute() {
		\WPML_Translation_Roles_Records::delete_cache();

		$wp_cache_groups = [
			'WPML_TM_Blog_Translators::get_raw_blog_translators',
			'WPML_TM_Blog_Translators::has_translators',
		];
		foreach ( $wp_cache_groups as $group ) {
			$cache = new \WPML_WP_Cache( $group );
			$cache->flush_group_cache();
		}
	}
}
