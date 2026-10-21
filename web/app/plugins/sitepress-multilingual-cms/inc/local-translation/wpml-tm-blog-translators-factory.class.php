<?php

/**
 * Builds the blog translators service from the globals it needs.
 *
 * The one place for that wiring. wpml_tm_load_blog_translators() - defined
 * only when Translation Management is loaded - keeps its per-request
 * singleton on top of it, and WPML_Blog_License_Translation_Status builds
 * its own instance on a Blog license, where that loader does not exist
 * (wpmldev-3902). The boot verdict in inc/functions-load-tm.php stays the
 * only thing deciding which loader functions a request can call.
 */
class WPML_TM_Blog_Translators_Factory {

	public function create() {
		global $wpdb, $sitepress, $wpml_post_translations, $wpml_term_translations, $wpml_cache_factory;

		$tm_records         = new WPML_TM_Records( $wpdb, $wpml_post_translations, $wpml_term_translations );
		$translator_records = new WPML_Translator_Records(
			$wpdb,
			new WPML_WP_User_Query_Factory(),
			wp_roles(),
			new \WPML\TranslationRoles\Service\AdministratorRoleManager()
		);

		return new WPML_TM_Blog_Translators( $sitepress, $tm_records, $translator_records, $wpml_cache_factory );
	}
}
