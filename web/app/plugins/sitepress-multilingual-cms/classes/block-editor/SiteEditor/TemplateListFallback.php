<?php

namespace WPML\BlockEditor\SiteEditor;

use WPML\Core\Component\Post\Application\LanguagePanel\SiteEditorEntityTypes;

class TemplateListFallback implements \IWPML_Backend_Action, \IWPML_Frontend_Action, \IWPML_REST_Action {

	const ROUTE_PATTERN = '#^/wp/v2/(?:templates|template-parts)(?:/|$)#';

	const POST_EDITOR_REFERER_PATTERN = '#/wp-admin/post(?:-new)?\.php(?:[?\#]|$)#';

	const POST_EDITOR_PAGES = [ 'post.php', 'post-new.php' ];

	private $requests = [];

	private $wpdb;

	public function __construct( $wpdb = null ) {
		$this->wpdb = $wpdb;
	}

	public function add_hooks() {
		add_filter( 'rest_request_before_callbacks', [ $this, 'enter' ], 8, 3 );
		add_filter( 'rest_request_after_callbacks', [ $this, 'leave' ], 20 );
		add_filter( 'wpml_should_force_display_as_translated_snippet', [ $this, 'forceFallbackChain' ], 10, 2 );
		add_filter( 'wpml_forced_display_as_translated_snippet', [ $this, 'addOriginalsStep' ], 10, 4 );
	}

	public function enter( $response, $handler, $request ) {
		$this->requests[] = $request instanceof \WP_REST_Request
			&& ( $this->isSiteEditorTemplatesRequest( $request ) || $this->isPostEditorRequest() );

		return $response;
	}

	public function leave( $response ) {
		array_pop( $this->requests );

		return $response;
	}

	public function forceFallbackChain( $force, $query = null ) {
		if ( $force ) {
			return true;
		}

		return $this->isTemplateQuery( $query ) && $this->inRuledScope();
	}

	public function addOriginalsStep( $snippet, $currentLanguage, $fallbackLanguage, $query = null ) {
		if ( ! $this->isTemplateQuery( $query ) ) {
			return $snippet;
		}

		$wpdb      = $this->wpdb ?: $GLOBALS['wpdb'];
		$languages = array_unique( array_filter( [ (string) $currentLanguage, (string) $fallbackLanguage ] ) );
		$literals  = $this->literals( $wpdb, $languages );
		$current   = $this->literals( $wpdb, array_filter( [ (string) $currentLanguage ] ) ) ?: $literals;

		return "( ( {$snippet} ) AND NOT EXISTS ( {$this->sameSlugRow( $wpdb, $current )} ) ) OR (
					wpml_translations.source_language_code IS NULL
					AND NOT EXISTS (
						SELECT 1
						FROM {$wpdb->prefix}icl_translations t3
						JOIN {$wpdb->posts} p3 ON p3.ID = t3.element_id
						WHERE t3.trid = wpml_translations.trid
						AND t3.language_code IN ({$literals})
						AND ( p3.post_status = 'publish' OR p3.post_status = 'private' )
					)
					AND NOT EXISTS ( {$this->sameSlugRow( $wpdb, $literals )} )
					AND NOT EXISTS ( {$this->olderSameSlugOriginal( $wpdb )} )
				)";
	}

	private function sameSlugRow( $wpdb, $literals ) {
		return "SELECT 1
						FROM {$wpdb->posts} p4
						JOIN {$wpdb->prefix}icl_translations t4 ON t4.element_id = p4.ID AND t4.element_type = wpml_translations.element_type
						JOIN {$wpdb->term_relationships} tr4 ON tr4.object_id = p4.ID
						JOIN {$wpdb->term_taxonomy} tt4 ON tt4.term_taxonomy_id = tr4.term_taxonomy_id AND tt4.taxonomy = 'wp_theme'
						JOIN {$wpdb->term_relationships} tr0 ON tr0.object_id = {$wpdb->posts}.ID AND tr0.term_taxonomy_id = tr4.term_taxonomy_id
						WHERE p4.post_name = {$wpdb->posts}.post_name
						AND p4.post_type = {$wpdb->posts}.post_type
						AND p4.ID <> {$wpdb->posts}.ID
						AND t4.language_code IN ({$literals})
						AND ( p4.post_status = 'publish' OR p4.post_status = 'private' )";
	}

	private function olderSameSlugOriginal( $wpdb ) {
		return "SELECT 1
						FROM {$wpdb->posts} p5
						JOIN {$wpdb->prefix}icl_translations t5 ON t5.element_id = p5.ID AND t5.element_type = wpml_translations.element_type
						JOIN {$wpdb->term_relationships} tr5 ON tr5.object_id = p5.ID
						JOIN {$wpdb->term_taxonomy} tt5 ON tt5.term_taxonomy_id = tr5.term_taxonomy_id AND tt5.taxonomy = 'wp_theme'
						JOIN {$wpdb->term_relationships} tr6 ON tr6.object_id = {$wpdb->posts}.ID AND tr6.term_taxonomy_id = tr5.term_taxonomy_id
						WHERE p5.post_name = {$wpdb->posts}.post_name
						AND p5.post_type = {$wpdb->posts}.post_type
						AND p5.ID < {$wpdb->posts}.ID
						AND t5.source_language_code IS NULL
						AND ( p5.post_status = 'publish' OR p5.post_status = 'private' )";
	}

	private function literals( $wpdb, array $languages ) {
		return implode( ',', array_map( function ( $language ) use ( $wpdb ) {
			return $wpdb->prepare( '%s', $language );
		}, $languages ) );
	}

	private function inRuledScope() {
		if ( $this->requests ) {
			return true === end( $this->requests );
		}

		if ( wpml_is_rest_request() ) {
			return $this->isPostEditorRequest();
		}

		if ( is_admin() ) {
			return $this->isPostEditorPage();
		}

		return $this->isFrontEndPageView();
	}

	private function isFrontEndPageView() {
		return ! wpml_is_rest_request()
			&& ! wp_doing_cron()
			&& ! wpml_is_cli()
			&& ! wp_doing_ajax();
	}

	private function isTemplateQuery( $query ) {
		if ( ! $query instanceof \WP_Query || ! isset( $query->query_vars['post_type'] ) ) {
			return false;
		}

		$types = (array) $query->query_vars['post_type'];
		if ( ! $types ) {
			return false;
		}

		foreach ( $types as $type ) {
			if ( ! is_string( $type ) || ! SiteEditorEntityTypes::isTemplateType( $type ) ) {
				return false;
			}
		}

		return true;
	}

	private function isSiteEditorTemplatesRequest( \WP_REST_Request $request ) {
		if ( ! preg_match( self::ROUTE_PATTERN, (string) $request->get_route() ) ) {
			return false;
		}

		return $this->isSiteEditorReferer() || $this->isSiteEditorPage();
	}

	private function isPostEditorRequest() {
		$referer = wp_get_raw_referer();

		return ( is_string( $referer ) && preg_match( self::POST_EDITOR_REFERER_PATTERN, $referer ) )
			|| $this->isPostEditorPage();
	}

	private function isSiteEditorReferer() {
		$referer = wp_get_raw_referer();

		return is_string( $referer ) && false !== strpos( $referer, 'wp-admin/site-editor.php' );
	}

	private function isSiteEditorPage() {
		return is_admin() && isset( $GLOBALS['pagenow'] ) && 'site-editor.php' === $GLOBALS['pagenow'];
	}

	private function isPostEditorPage() {
		return is_admin() && isset( $GLOBALS['pagenow'] ) && in_array( $GLOBALS['pagenow'], self::POST_EDITOR_PAGES, true );
	}
}
