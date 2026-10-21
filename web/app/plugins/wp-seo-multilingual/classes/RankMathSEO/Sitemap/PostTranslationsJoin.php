<?php

namespace WPML\WPSEO\RankMathSEO\Sitemap;

class PostTranslationsJoin {

	public static function sql( \wpdb $wpdb ): string {
		return "
			JOIN {$wpdb->prefix}icl_translations AS wpml_translations
				ON p.ID = wpml_translations.element_id
				AND wpml_translations.element_type = CONCAT('post_', p.post_type)
		";
	}
}
