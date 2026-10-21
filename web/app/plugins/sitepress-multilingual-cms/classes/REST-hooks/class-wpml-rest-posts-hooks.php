<?php

use \WPML\FP\Fns;
use WPML\UrlHandling\PreviewHost;

class WPML_REST_Posts_Hooks implements IWPML_Action {

	const UNPUBLISHED_PREVIEW_STATUSES = [ 'draft', 'auto-draft', 'pending', 'future' ];

	private $sitepress;

	private $term_translations;

	public function __construct(
		SitePress $sitepress,
		WPML_Term_Translation $term_translations
	) {
		$this->sitepress         = $sitepress;
		$this->term_translations = $term_translations;
	}

	private $hooked_post_types = array();

	public function add_hooks() {
		$post_types = $this->sitepress->get_translatable_documents();

		foreach ( $post_types as $post_type => $post_object ) {
			$this->add_prepare_filter( (string) $post_type );
		}

		if ( did_action( 'rest_api_init' ) ) {
			add_action( 'registered_post_type', array( $this, 'add_hooks_for_post_type' ), 10, 1 );

			if ( $this->is_domain_mode() ) {
				add_filter( 'preview_post_link', array( $this, 'preview_link_on_request_host' ), 10, 2 );
			}
		} else {
			add_action( 'rest_api_init', array( $this, 'add_hooks_for_translatable_post_types' ) );
		}

		add_filter( 'rest_request_before_callbacks', array( $this, 'reload_wpml_post_translation' ), 10, 3 );
	}

	public function add_hooks_for_translatable_post_types() {
		foreach ( $this->sitepress->get_translatable_documents() as $post_type => $post_object ) {
			$this->add_prepare_filter( (string) $post_type );
		}
	}

	public function preview_link_on_request_host( $link, $post = null ) {
		if ( ! is_string( $link ) || ! $this->query_has_any_of( $link, array( 'preview_id', 'p', 'page_id' ) ) ) {
			return $link;
		}

		return PreviewHost::toRequestHost( $link );
	}

	public function add_hooks_for_post_type( $post_type ) {
		$post_type = (string) $post_type;

		if ( isset( $this->hooked_post_types[ $post_type ] ) ) {
			return;
		}

		if ( ! array_key_exists( $post_type, $this->sitepress->get_translatable_documents() ) ) {
			return;
		}

		$this->add_prepare_filter( $post_type );
	}

	private function add_prepare_filter( $post_type ) {
		if ( isset( $this->hooked_post_types[ $post_type ] ) ) {
			return;
		}

		$this->hooked_post_types[ $post_type ] = true;

		add_filter( "rest_prepare_$post_type", array( $this, 'prepare_post' ), 10, 3 );
	}

	public function prepare_post( $response, $post, $request = null ) {
		if ( $this->sitepress->get_setting( 'sync_post_taxonomies' ) ) {
			$response = $this->preset_terms_in_new_translation( $response, $post );
		}

		$response = $this->adjust_sample_links( $response, $post );
		$response = $this->move_unpublished_link_to_request_host( $response, $post, $request );

		return $response;
	}

	private function move_unpublished_link_to_request_host( $response, $post, $request ) {
		if ( ! $request instanceof WP_REST_Request
			 || 'edit' !== $request->get_param( 'context' )
			 || ! in_array( $post->post_status, self::UNPUBLISHED_PREVIEW_STATUSES, true )
			 || ! $this->is_domain_mode()
			 || ! current_user_can( 'edit_post', $post->ID )
		) {
			return $response;
		}

		$data = $response->get_data();
		$link = isset( $data['link'] ) ? $data['link'] : '';

		if ( ! is_string( $link ) || '' === $link || ! $this->query_has_any_of( $link, array( 'p', 'page_id' ) ) ) {
			return $response;
		}

		$link_on_request_host = PreviewHost::toRequestHost( $link );

		if ( $link_on_request_host === $link ) {
			return $response;
		}

		$data['link'] = add_query_arg( 'preview', 'true', $link_on_request_host );
		$response->set_data( $data );

		return $response;
	}

	private function is_domain_mode() {
		return WPML_LANGUAGE_NEGOTIATION_TYPE_DOMAIN === (int) $this->sitepress->get_setting( 'language_negotiation_type' );
	}

	private function query_has_any_of( $url, array $keys ) {
		$query = wpml_parse_url( $url, PHP_URL_QUERY );

		if ( ! is_string( $query ) || '' === $query ) {
			return false;
		}

		parse_str( $query, $args );

		foreach ( $keys as $key ) {
			if ( isset( $args[ $key ] ) && '' !== $args[ $key ] ) {
				return true;
			}
		}

		return false;
	}

	private function preset_terms_in_new_translation( $response, $post ) {
		if ( ! isset( $_GET['trid'] ) ) {
			return $response;
		}

		if ( ! current_user_can( 'edit_post', $post->ID ) ) {
			return $response;
		}

		$trid        = filter_var( $_GET['trid'], FILTER_SANITIZE_NUMBER_INT );
		$source_lang = isset( $_GET['source_lang'] )
			? \WPML\Language\RequestedLanguage::forPrivileged( sanitize_text_field( wp_unslash( $_GET['source_lang'] ) ) )
			: $this->sitepress->get_default_language();
		if ( null === $source_lang ) {
			return $response;
		}

		$element_type = 'post_' . $post->post_type;

		if ( $this->sitepress->get_element_trid( $post->ID, $element_type ) ) {
			return $response;
		}

		$translations = $this->sitepress->get_element_translations( $trid, $element_type );

		if ( ! isset( $translations[ $source_lang ] ) ) {
			return $response;
		}

		$current_lang      = $this->sitepress->get_current_language();
		$translatable_taxs = $this->sitepress->get_translatable_taxonomies( true, $post->post_type );
		$all_taxs          = wp_list_filter( get_object_taxonomies( $post->post_type, 'objects' ), array( 'show_in_rest' => true ) );
		$data              = $response->get_data();

		$this->sitepress->switch_lang( $source_lang );

		try {
			foreach ( $all_taxs as $tax ) {
				$tax_rest_base = ! empty( $tax->rest_base ) ? $tax->rest_base : $tax->name;

				if ( ! isset( $data[ $tax_rest_base ] ) ) {
					continue;
				}

				$terms = get_the_terms( $translations[ $source_lang ]->element_id, $tax->name );

				if ( ! is_array( $terms ) ) {
					continue;
				}

				$term_ids = $this->get_translated_term_ids( $terms, $tax, $translatable_taxs, $current_lang );
				wp_set_object_terms( $post->ID, $term_ids, $tax->name );
				$data[ $tax_rest_base ] = $term_ids;
			}
		} finally {
			$this->sitepress->switch_lang();
		}

		$response->set_data( $data );

		return $response;
	}

	private function get_translated_term_ids( array $terms, $tax, array $translatable_taxs, $current_lang ) {
		$term_ids = array();

		if ( in_array( $tax->name, $translatable_taxs ) ) {
			$this->prefetch_term_translations( $terms );
		}

		foreach ( $terms as $term ) {
			if ( in_array( $tax->name, $translatable_taxs ) ) {
				$term_ids[] = $this->term_translations->term_id_in( $term->term_id, $current_lang, false );
			} else {
				$term_ids[] = $term->term_id;
			}
		}

		return wpml_collect( $term_ids )
			->filter()
			->values()
			->map( Fns::unary( 'intval' ) )
			->toArray();
	}

	private function prefetch_term_translations( array $terms ) {
		$ttids = array();
		foreach ( $terms as $term ) {
			if ( ! empty( $term->term_taxonomy_id ) ) {
				$ttids[] = (int) $term->term_taxonomy_id;
			}
		}

		if ( count( $ttids ) > 1 ) {
			$this->term_translations->prefetch_ids( $ttids );
		}
	}

	private function adjust_sample_links( $response, $post ) {
		$data = $response->get_data();

		if ( ! isset( $data['link'], $data['permalink_template'] ) ) {
			return $response;
		}

		$lang_details = $this->sitepress->get_element_language_details( $post->ID, 'post_' . $post->post_type );

		if ( empty( $lang_details->language_code ) ) {
			$lang                       = $this->sitepress->get_current_language();
			$data['link']               = $this->sitepress->convert_url_string( $data['link'], $lang );
			$data['permalink_template'] = $this->sitepress->convert_url_string( $data['permalink_template'], $lang );

			$response->set_data( $data );
		}

		return $response;
	}

	public function reload_wpml_post_translation( $response, ?array $handler, WP_REST_Request $request ) {
		if ( ! is_wp_error( $response )
		     && ( $this->isRestSavingBlockResources( $request ) || $this->isRestSavingPostFromEditor( $handler ) )
		) {
			wpml_load_post_translation( is_admin(), $this->sitepress->get_settings() );
		}

		return $response;
	}

	private function isRestSavingPostFromEditor( ?array $handler ) {
		if ( empty( $handler['callback'] ) || ! is_array( $handler['callback'] ) ) {
			return false;
		}

		$is_post_save = isset( $handler['callback'][0], $handler['callback'][1] )
		                && $handler['callback'][0] instanceof \WP_REST_Posts_Controller
		                && in_array( $handler['callback'][1], array( 'create_item', 'update_item' ), true );

		return $is_post_save && WPML_URL_HTTP_Referer::is_post_edit_page();
	}

	private function isRestSavingBlockResources( WP_REST_Request $request ) {
		$methods = array( 'POST', 'PUT', 'PATCH' );
		$route = $request->get_route();
		$pattern = '#\/wp\/v2\/(?:blocks|templates|template-parts)(?:\/\d+)*#';
		return in_array( $request->get_method(), $methods ) && preg_match( $pattern, $route );
	}
}
