<?php

use WPML\FP\Fns;

class WPML_WPSEO_Categories implements IWPML_Action {

	const CATEGORY_SLUG = 'category';

	const DEFAULT_CATEGORY_BASE = 'category';

	const BASELESS_CATEGORY_REDIRECT_PATTERN = '(.+)$';

	const CATEGORY_REDIRECT_QUERY = 'index.php?wpseo_category_redirect=$matches[1]';

	private $slugTranslationSettingsFactory;

	public function __construct( ?WPML_ST_Slug_Translation_Settings_Factory $slugTranslationSettingsFactory = null ) {
		$this->slugTranslationSettingsFactory = $slugTranslationSettingsFactory;
	}

	public function add_hooks() {
		if ( $this->is_stripping_category_base() ) {
			add_filter( 'category_rewrite_rules', array( $this, 'append_categories_hook' ), -PHP_INT_MAX );
			add_filter( 'category_rewrite_rules', array( $this, 'turn_off_get_terms_filter' ), PHP_INT_MAX );
			add_filter( 'category_rewrite_rules', [ $this, 'scopeCategoryRedirectToCategoryBase' ], PHP_INT_MAX );
			add_action( 'admin_init', [ $this, 'ensureCategoryBaseIsNotTranslated' ] );
		}
	}

	private function is_stripping_category_base() {
		$option = (array) get_option( 'wpseo_titles' );

		return array_key_exists( 'stripcategorybase', $option ) && $option['stripcategorybase'];
	}

	public function append_categories_hook( $rules ) {
		add_filter( 'get_terms', array( $this, 'append_categories_translations' ), 10, 2 );

		return $rules;
	}

	public function turn_off_get_terms_filter( $rules ) {
		remove_filter( 'get_terms', array( $this, 'append_categories_translations' ) );

		return $rules;
	}

	public function append_categories_translations( $categories, $taxonomy ) {
		if ( ! in_array( self::CATEGORY_SLUG, $taxonomy, true ) || ! $this->is_array_of_wp_term( $categories ) ) {
			return $categories;
		}

		global $wpdb;

		$sql = "
			SELECT t.term_id FROM {$wpdb->terms} t
			INNER JOIN {$wpdb->term_taxonomy} tt ON tt.term_id = t.term_id
			WHERE tt.taxonomy = '" . self::CATEGORY_SLUG . "'
		";

		return array_filter( array_map( array( $this, 'map_to_term' ), $wpdb->get_col( $sql ) ) );
	}

	private function is_array_of_wp_term( array $terms ) {
		return current( $terms ) instanceof WP_Term;
	}

	protected function map_to_term( $term_id ) {
		$disableAdjustId = [ 'wpml_disable_term_adjust_id', Fns::always( true ) ];

		add_filter( ...$disableAdjustId );
		$term = get_term( $term_id, self::CATEGORY_SLUG );
		remove_filter( ...$disableAdjustId );

		return $term;
	}

	public function scopeCategoryRedirectToCategoryBase( $rules ) {
		if ( ! is_array( $rules ) || ! $this->isBaselessCategoryRedirect( $rules ) ) {
			return $rules;
		}

		unset( $rules[ self::BASELESS_CATEGORY_REDIRECT_PATTERN ] );
		$rules[ $this->getCategoryBase() . '/' . self::BASELESS_CATEGORY_REDIRECT_PATTERN ] = self::CATEGORY_REDIRECT_QUERY;

		return $rules;
	}

	private function isBaselessCategoryRedirect( array $rules ): bool {
		return self::CATEGORY_REDIRECT_QUERY === ( $rules[ self::BASELESS_CATEGORY_REDIRECT_PATTERN ] ?? null );
	}

	private function getCategoryBase(): string {
		return trim( get_option( 'category_base' ) ?: self::DEFAULT_CATEGORY_BASE, '/' );
	}

	public function ensureCategoryBaseIsNotTranslated() {
		if ( $this->slugTranslationSettingsFactory ) {
			$taxSettings = $this->slugTranslationSettingsFactory->createTaxSettings();

			if ( $taxSettings->is_translated( self::CATEGORY_SLUG ) ) {
				$taxSettings->set_type( self::CATEGORY_SLUG, false );
				$taxSettings->save();
			}
		}
	}
}
