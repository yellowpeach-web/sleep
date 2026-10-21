<?php

use WPML\WPSEO\YoastSEO\Utils;
use WPML\Collect\Support\Collection;

class WPML_WPSEO_Should_Create_Redirect implements IWPML_Action {

	private $filter_hooks;

	private $unfiltered_url;

	public function __construct() {
		$this->filter_hooks = wpml_collect( [ 'post_link', 'page_link', 'post_type_link' ] );
	}

	public function add_hooks() {
		Utils::add_filter( 'wpseo_premium_post_redirect_slug_change', [ $this, 'dont_convert_url' ], 10, 4 );
	}

	public function dont_convert_url( $result, $post_id, $post, $post_before ) {
		$status = get_post_status( $post_before );
		if ( in_array( $status, [ 'draft', 'auto-draft' ], true ) ) {

			$this->filter_hooks->each(
				function ( $filter_hook ) {
					add_filter( $filter_hook, [ $this, 'save_unfiltered_url' ], 0 );
					add_filter( $filter_hook, [ $this, 'restore_unfiltered_url' ], 20 );
				}
			);

		}

		return $result;
	}

	public function save_unfiltered_url( $url ) {
		$this->unfiltered_url = $url;

		return $url;
	}

	public function restore_unfiltered_url( $url ) {
		$url                  = $this->unfiltered_url;
		$this->unfiltered_url = null;

		$this->filter_hooks->each(
			function ( $filter_hook ) {
				remove_filter( $filter_hook, [ $this, 'save_unfiltered_url' ], 0 );
				remove_filter( $filter_hook, [ $this, 'restore_unfiltered_url' ], 20 );
			}
		);

		return $url;
	}
}
