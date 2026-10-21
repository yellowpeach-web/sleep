<?php

use WPML\UrlHandling\Resolver\EndpointSlugs;

class WPML_Single_Url_Cache_Services {

	private static $instance;

	private $repository;

	private $generation;

	private $scheduler;

	private $cache;

	private $resolver;

	private $inline_resolver;

	private $worker;

	private $admin;

	private $hooks;

	private $invalidator;

	public static function get_instance(
		?SitePress $sitepress_instance = null,
		?WPML_URL_Converter $url_converter_instance = null,
		?WPML_Translate_Link_Targets $translate_link_targets = null
	) {
		if ( ! self::$instance ) {
			global $wpdb, $sitepress, $wpml_url_converter;

			$sitepress_instance     = $sitepress_instance ?: $sitepress;
			$url_converter_instance = $url_converter_instance ?: $wpml_url_converter;

			if ( ! $translate_link_targets ) {
				$translate_link_targets = new WPML_Translate_Link_Targets(
					new AbsoluteLinks(),
					new WPML_Absolute_To_Permalinks( $sitepress_instance )
				);
			}

			self::$instance = new self(
				$wpdb,
				$sitepress_instance,
				$url_converter_instance,
				$translate_link_targets
			);
		}

		return self::$instance;
	}

	public function __construct(
		wpdb $wpdb,
		SitePress $sitepress,
		WPML_URL_Converter $url_converter,
		WPML_Translate_Link_Targets $translate_link_targets
	) {
		$this->repository  = new WPML_Single_Url_Cache_Repository( $wpdb );
		$this->generation  = new WPML_Single_Url_Cache_Generation( $wpdb );
		$this->scheduler   = new WPML_Single_Url_Cache_Scheduler();
		$this->cache       = new WPML_Single_Url_Resolution_Cache(
			$this->repository,
			$this->generation,
			$this->scheduler
		);
		$this->resolver    = new WPML_Resolve_Single_Url(
			$sitepress,
			$url_converter,
			$translate_link_targets,
			$this->cache,
			new EndpointSlugs( $sitepress, $wpdb )
		);

		$this->inline_resolver = new WPML_Single_Url_Inline_Resolver(
			$this->repository,
			$this->cache
		);
		$this->inline_resolver->set_resolver( $this->resolver );
		$this->resolver->set_inline_resolver( $this->inline_resolver );

		$this->worker      = new WPML_Single_Url_Cache_Worker(
			$this->repository,
			$this->generation,
			$this->scheduler,
			$this->cache,
			$this->resolver
		);
		$this->admin       = new WPML_Single_Url_Cache_Admin_Service(
			$this->repository,
			$this->generation,
			$this->scheduler,
			$this->worker
		);
		$this->hooks       = new WPML_Single_Url_Cache_Hooks(
			$this->scheduler,
			$this->worker,
			$this->admin,
			$this->generation
		);
		$this->invalidator = new WPML_Single_Url_Cache_Invalidator(
			$wpdb,
			$this->repository,
			$this->generation,
			$this->cache
		);
	}

	public function repository() {
		return $this->repository;
	}

	public function generation() {
		return $this->generation;
	}

	public function scheduler() {
		return $this->scheduler;
	}

	public function cache() {
		return $this->cache;
	}

	public function resolver() {
		return $this->resolver;
	}

	public function inline_resolver() {
		return $this->inline_resolver;
	}

	public function worker() {
		return $this->worker;
	}

	public function admin() {
		return $this->admin;
	}

	public function hooks() {
		return $this->hooks;
	}

	public function invalidator() {
		return $this->invalidator;
	}
}
