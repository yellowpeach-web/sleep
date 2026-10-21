<?php

namespace WPML\TaxonomyTermTranslation;

class DefaultCategoryDeleteProtection implements \IWPML_Backend_Action, \IWPML_AJAX_Action, \IWPML_REST_Action, \IWPML_DIC_Action {

	private static $protected_term_ids;

	private static $protected_default_term_ids = array();

	private $sitepress;

	public function __construct( \SitePress $sitepress ) {
		$this->sitepress = $sitepress;
	}

	public static function resetProtectedTermIds() {
		self::$protected_term_ids         = null;
		self::$protected_default_term_ids = array();
	}

	private function hasTranslatableDefaultTermTaxonomy() {
		foreach ( (array) get_taxonomies( array(), 'objects' ) as $name => $taxonomy ) {
			if ( ! empty( $taxonomy->default_term ) && $this->sitepress->is_translated_taxonomy( $name ) ) {
				return true;
			}
		}

		return false;
	}

	private function isTranslatableDefaultTermTaxonomy( $taxonomy ) {
		$object = get_taxonomy( $taxonomy );

		return $object && ! empty( $object->default_term ) && $this->sitepress->is_translated_taxonomy( $taxonomy );
	}

	private static function protectedDefaultTermIds( $taxonomy ) {
		$default = (int) get_option( 'default_term_' . $taxonomy );
		if ( $default <= 0 ) {
			return array();
		}

		if ( isset( self::$protected_default_term_ids[ $taxonomy ][ $default ] ) ) {
			return self::$protected_default_term_ids[ $taxonomy ][ $default ];
		}

		global $wpdb;

		$element_type = 'tax_' . $taxonomy;
		$group        = $wpdb->get_col(
			$wpdb->prepare(
				"SELECT tt.term_id
				 FROM {$wpdb->term_taxonomy} tt
				 JOIN {$wpdb->prefix}icl_translations t
				   ON t.element_id = tt.term_taxonomy_id AND t.element_type = %s
				 WHERE tt.taxonomy = %s
				 AND t.trid IN (
				   SELECT d.trid
				   FROM {$wpdb->prefix}icl_translations d
				   JOIN {$wpdb->term_taxonomy} dtt ON dtt.term_taxonomy_id = d.element_id
				   WHERE d.element_type = %s AND dtt.taxonomy = %s AND dtt.term_id = %d
				 )",
				$element_type,
				$taxonomy,
				$element_type,
				$taxonomy,
				$default
			)
		);

		self::$protected_default_term_ids[ $taxonomy ][ $default ] = array_values(
			array_unique( array_merge( array( $default ), array_map( 'intval', (array) $group ) ) )
		);

		return self::$protected_default_term_ids[ $taxonomy ][ $default ];
	}

	public function add_hooks() {
		add_filter( 'map_meta_cap', array( $this, 'protectDefaultCategories' ), 10, 4 );
		add_action( 'load-edit-tags.php', array( $this, 'dropProtectedTermsFromBulkDelete' ) );
	}

	public function protectDefaultCategories( $caps, $cap, $user_id, $args ) {
		if ( 'delete_term' !== $cap ) {
			return $caps;
		}

		$default_categories = (array) $this->sitepress->get_setting( 'default_categories', array() );
		if ( ! $default_categories && ! $this->hasTranslatableDefaultTermTaxonomy() ) {
			return $caps;
		}

		$term_id = isset( $args[0] ) ? (int) $args[0] : 0;
		if ( ! $term_id ) {
			return $caps;
		}

		$term = get_term( $term_id );
		if ( ! $term || is_wp_error( $term ) || ! isset( $term->taxonomy ) ) {
			return $caps;
		}

		if ( 'category' !== $term->taxonomy ) {
			if (
				$this->isTranslatableDefaultTermTaxonomy( $term->taxonomy )
				&& in_array( $term_id, self::protectedDefaultTermIds( $term->taxonomy ), true )
			) {
				$caps[] = 'do_not_allow';
			}

			return $caps;
		}

		if ( ! $default_categories ) {
			return $caps;
		}

		if ( in_array( $term_id, self::protectedTermIds( $default_categories ), true ) ) {
			$caps[] = 'do_not_allow';
		}

		return $caps;
	}

	private static function protectedTermIds( array $default_categories ) {
		if ( null !== self::$protected_term_ids ) {
			return self::$protected_term_ids;
		}

		global $wpdb;

		$ttids = array_filter( array_map( 'intval', $default_categories ) );

		self::$protected_term_ids = $ttids
			? array_map(
				'intval',
				$wpdb->get_col(
					"SELECT term_id
					 FROM {$wpdb->term_taxonomy}
					 WHERE taxonomy='category'
					 AND term_taxonomy_id IN (" . wpml_prepare_in( $ttids, '%d' ) . ')'
				)
			)
			: array();

		return self::$protected_term_ids;
	}

	public function dropProtectedTermsFromBulkDelete() {
		if (
			! isset( $_REQUEST['_wpnonce'] )
			|| ! wp_verify_nonce( sanitize_key( wp_unslash( $_REQUEST['_wpnonce'] ) ), 'bulk-tags' )
		) {
			return;
		}

		$taxonomy = isset( $_REQUEST['taxonomy'] )
			? sanitize_text_field( wp_unslash( $_REQUEST['taxonomy'] ) ) : 'post_tag';
		if ( ! isset( $_REQUEST['delete_tags'] ) ) {
			return;
		}

		if ( 'category' !== $taxonomy ) {
			if ( $this->isTranslatableDefaultTermTaxonomy( $taxonomy ) ) {
				$protected = self::protectedDefaultTermIds( $taxonomy );
				$kept      = array_values(
					array_filter(
						array_map( 'intval', (array) wp_unslash( $_REQUEST['delete_tags'] ) ),
						function ( $term_id ) use ( $protected ) {
							return ! in_array( $term_id, $protected, true );
						}
					)
				);

				$_REQUEST['delete_tags'] = $kept;
				$_POST['delete_tags']    = $kept;
			}

			return;
		}

		$default_categories = (array) $this->sitepress->get_setting( 'default_categories', array() );
		if ( ! $default_categories ) {
			return;
		}

		$protected = array();
		foreach ( $default_categories as $default_category ) {
			$protected[] = (int) $default_category;
		}

		$kept          = array();
		$submitted_ids = array_map( 'intval', (array) wp_unslash( $_REQUEST['delete_tags'] ) );
		foreach ( $submitted_ids as $term_id ) {
			$term_taxonomy_id = $this->category_ttid_of_term_id( $term_id );
			if ( $term_taxonomy_id && in_array( $term_taxonomy_id, $protected, true ) ) {
				continue;
			}

			$kept[] = $term_id;
		}

		$_REQUEST['delete_tags'] = $kept;
		$_POST['delete_tags']    = $kept;
	}

	private function category_ttid_of_term_id( $term_id ) {
		global $wpdb;

		$term_id = (int) $term_id;
		if ( $term_id <= 0 ) {
			return 0;
		}

		return (int) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT term_taxonomy_id
				     FROM {$wpdb->term_taxonomy}
				     WHERE term_id = %d
				     AND taxonomy = 'category'",
				$term_id
			)
		);
	}
}
