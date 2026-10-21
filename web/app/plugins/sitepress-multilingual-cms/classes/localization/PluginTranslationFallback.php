<?php

namespace WPML\Localization;

class PluginTranslationFallback {

	private $resolver;

	private $script_requested_locales = [];

	public function __construct( CatalogFallbackResolver $resolver ) {
		$this->resolver = $resolver;
	}

	public static function register() {
		add_action( 'plugins_loaded', [ __CLASS__, 'register_hooks' ], 1 );
	}

	public static function register_hooks() {
		self::create()->add_hooks();
	}

	public static function create() {
		$resolver = new CatalogFallbackResolver(
			self::packaged_domain_roots(),
			new CatalogLocaleInventory()
		);

		return new self( $resolver );
	}

	public function add_hooks() {
		add_filter( 'load_textdomain_mofile', [ $this, 'filter_mofile' ], 10, 2 );
		add_filter( 'load_script_translation_file', [ $this, 'filter_script_translation_file' ], 10, 3 );
		add_filter(
			'wpml_st_script_translation_file_locale',
			[ $this, 'filter_script_translation_file_locale' ],
			10,
			2
		);
	}

	public function filter_mofile( $file, $domain ) {
		return $this->resolver->resolve_php_catalog( $file, $domain )->file();
	}

	public function filter_script_translation_file( $file, $handle, $domain ) {
		if ( ! is_string( $file ) ) {
			return $file;
		}

		$resolution = $this->resolver->resolve_script_catalog( $file, $handle, $domain );

		if ( $resolution->uses_fallback() ) {
			$this->remember_requested_locale( $resolution );
		}

		return $resolution->file();
	}

	public function filter_script_translation_file_locale( $locale, $file ) {
		$file = wp_normalize_path( $file );

		return isset( $this->script_requested_locales[ $file ] )
			? $this->script_requested_locales[ $file ]
			: $locale;
	}

	private function remember_requested_locale( CatalogResolution $resolution ) {
		$file             = wp_normalize_path( $resolution->file() );
		$requested_locale = $resolution->requested_locale();

		if ( $requested_locale ) {
			$this->script_requested_locales[ $file ] = $requested_locale;
		}
	}

	private static function packaged_domain_roots() {
		$core_locale      = WPML_PLUGIN_PATH . '/locale';
		$installer_locale = WPML_PLUGIN_PATH . '/vendor/otgs/installer/locale';
		$roots            = [
			'installer' => [ $installer_locale ],
			'sitepress' => [ $core_locale ],
			'wpml'      => [ WPML_PLUGIN_PATH . '/wpml/languages' ],
		];

		if ( defined( 'WPML_ST_PATH' ) ) {
			$roots['wpml-string-translation'] = [ WPML_ST_PATH . '/locale' ];
		}

		if ( defined( 'WPML_MEDIA_PATH' ) ) {
			$roots['wpml-media'] = [ WPML_MEDIA_PATH . '/locale' ];
		}

		return $roots;
	}
}
