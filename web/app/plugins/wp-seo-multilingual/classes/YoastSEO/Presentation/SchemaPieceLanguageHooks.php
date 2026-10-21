<?php

namespace WPML\WPSEO\YoastSEO\Presentation;

class SchemaPieceLanguageHooks implements \IWPML_Frontend_Action, \IWPML_DIC_Action {

	const SCHEMA_AGGREGATOR_ROUTE = '/yoast/v1/schema-aggregator/';

	const SCHEMA_AGGREGATOR_COMMAND = 'yoast aggregate_site_schema';

	const SITE_WIDE_PIECE_TYPE = 'WebSite';

	const PIECE_LANGUAGE_FILTER = 'wpseo_schema_piece_language';

	private $sitepress;

	public function __construct( \SitePress $sitepress ) {
		$this->sitepress = $sitepress;
	}

	public function add_hooks() {
		if ( wpml_is_cli() ) {
			\WP_CLI::add_hook( 'before_invoke:' . self::SCHEMA_AGGREGATOR_COMMAND, [ $this, 'addPieceLanguageFilter' ] );
		} elseif ( wpml_is_rest_request() ) {
			add_filter( 'rest_request_before_callbacks', [ $this, 'addPieceLanguageFilterOnSchemaAggregatorRoute' ], 10, 3 );
			add_filter( 'rest_request_after_callbacks', [ $this, 'removePieceLanguageFilterAfterRequest' ] );
		}
	}

	public function addPieceLanguageFilterOnSchemaAggregatorRoute( $response, $handler, $request ) {
		if ( 0 === strpos( $request->get_route(), self::SCHEMA_AGGREGATOR_ROUTE ) ) {
			$this->addPieceLanguageFilter();
		}

		return $response;
	}

	public function removePieceLanguageFilterAfterRequest( $response ) {
		remove_filter( self::PIECE_LANGUAGE_FILTER, [ $this, 'setPieceLanguageFromPost' ] );

		return $response;
	}

	public function addPieceLanguageFilter() {
		add_filter( self::PIECE_LANGUAGE_FILTER, [ $this, 'setPieceLanguageFromPost' ], 10, 2 );
	}

	public function setPieceLanguageFromPost( $language, $piece ) {
		if ( $this->isSiteWidePiece( $piece ) ) {
			return $language;
		}

		$locale = $this->getCurrentPostLocale();

		return $locale ? str_replace( '_', '-', $locale ) : $language;
	}

	private function getCurrentPostLocale() {
		$post = get_post();
		if ( ! $post ) {
			return null;
		}

		$postLanguage = $this->sitepress->get_language_for_element( $post->ID, 'post_' . $post->post_type );

		return $postLanguage ? $this->sitepress->get_locale( $postLanguage ) : null;
	}

	private function isSiteWidePiece( array $piece ) {
		return in_array( self::SITE_WIDE_PIECE_TYPE, (array) ( $piece['@type'] ?? [] ), true );
	}
}
