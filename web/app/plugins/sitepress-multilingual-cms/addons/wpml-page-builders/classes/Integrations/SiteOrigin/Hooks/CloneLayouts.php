<?php

namespace WPML\PB\SiteOrigin\Hooks;

use WPML\FP\Obj;
use WPML\Language\RequestedLanguage;

class CloneLayouts implements \IWPML_AJAX_Action, \IWPML_DIC_Action {

	const LAYOUTS_QUERY_ACTION = 'so_panels_layouts_query';
	const CLONE_TYPE_PREFIX    = 'clone_';
	const CLONE_SOURCES_SELECT = 'SQL_CALC_FOUND_ROWS DISTINCT ID, post_title, meta.meta_value';

	private $wpdb;

	private $clonedPostType = '';

	public function __construct( \wpdb $wpdb ) {
		$this->wpdb = $wpdb;
	}

	public function add_hooks() {
		if ( self::LAYOUTS_QUERY_ACTION !== Obj::prop( 'action', $_REQUEST ) ) {
			return;
		}

		$this->clonedPostType = self::getClonedPostType();

		if ( $this->clonedPostType ) {
			add_filter( 'query', [ $this, 'keepCurrentLanguageCloneSources' ] );
		}
	}

	public function keepCurrentLanguageCloneSources( $query ) {
		$wpdb            = $this->wpdb;
		$postMetaJoin    = "JOIN {$wpdb->postmeta} AS meta ON posts.ID = meta.post_id";
		$currentLanguage = apply_filters( 'wpml_current_language', null );

		if (
			false === strpos( $query, self::CLONE_SOURCES_SELECT )
			|| false === strpos( $query, $postMetaJoin )
			|| ! $currentLanguage
			|| RequestedLanguage::ALL === $currentLanguage
			|| ! apply_filters( 'wpml_is_translated_post_type', false, $this->clonedPostType )
		) {
			return $query;
		}

		$languageJoin = $wpdb->prepare(
			" JOIN {$wpdb->prefix}icl_translations AS translations ON translations.element_id = posts.ID AND translations.element_type = %s AND translations.language_code = %s",
			'post_' . $this->clonedPostType,
			$currentLanguage
		);

		return str_replace( $postMetaJoin, $postMetaJoin . $languageJoin, $query );
	}

	private static function getClonedPostType() {
		$type = sanitize_key( (string) Obj::prop( 'type', $_REQUEST ) );

		return 0 === strpos( $type, self::CLONE_TYPE_PREFIX ) ? substr( $type, strlen( self::CLONE_TYPE_PREFIX ) ) : '';
	}
}
