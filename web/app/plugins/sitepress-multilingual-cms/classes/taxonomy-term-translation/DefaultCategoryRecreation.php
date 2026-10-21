<?php

namespace WPML\TaxonomyTermTranslation;

class DefaultCategoryRecreation implements \IWPML_Backend_Action, \IWPML_Frontend_Action, \IWPML_DIC_Action {

	const TAXONOMY = 'category';

	private $sitepress;

	private $recreation;

	private $deleting;

	private $restoring = false;

	private $checked = false;

	public function __construct( \SitePress $sitepress, DefaultTermRecreation $recreation ) {
		$this->sitepress  = $sitepress;
		$this->recreation = $recreation;
	}

	public function add_hooks() {
		add_action( 'pre_delete_term', [ $this, 'noteDefaultCategoryDelete' ], 10, 2 );
		add_action( 'delete_term', [ $this, 'recreateDeletedDefaultCategory' ], 10, 3 );
		add_filter( 'pre_option_default_category', [ $this, 'healMissingDefaultCategory' ], 20 );
	}

	public function noteDefaultCategoryDelete( $termId, $taxonomy ) {
		if ( self::TAXONOMY !== $taxonomy || $this->restoring ) {
			return;
		}

		$ttid     = $this->recreation->ttidOf( (int) $termId, self::TAXONOMY );
		$language = $ttid ? array_search( $ttid, $this->map(), true ) : false;

		if ( false === $language ) {
			return;
		}

		$this->deleting = [
			'language' => (string) $language,
			'trid'     => $this->recreation->tridOfTtid( $ttid, 'tax_' . self::TAXONOMY ),
			'term'     => $this->termOf( (int) $termId ),
		];
	}

	public function recreateDeletedDefaultCategory( $termId, $ttId, $taxonomy ) {
		if ( self::TAXONOMY !== $taxonomy || ! $this->deleting ) {
			return;
		}

		$deleting       = $this->deleting;
		$this->deleting = null;

		$this->restore( $deleting['language'], $deleting['trid'], $deleting['term'] );
	}

	public function healMissingDefaultCategory( $value ) {
		if ( null !== $value || $this->checked || $this->restoring ) {
			return $value;
		}

		$this->checked = true;

		$map     = $this->map();
		$live    = $this->liveTtids( array_values( $map ) );
		$missing = array_filter(
			$map,
			function ( $ttid ) use ( $live ) {
				return $ttid > 0 && ! in_array( $ttid, $live, true );
			}
		);

		if ( ! $missing ) {
			return $value;
		}

		$default = (string) $this->sitepress->get_default_language();
		if ( isset( $missing[ $default ] ) ) {
			$this->restore( $default, $this->recreation->tridOfTtid( $missing[ $default ], 'tax_' . self::TAXONOMY ) );
		} else {
			foreach ( array_keys( $missing ) as $language ) {
				$this->restore( (string) $language, 0 );
			}
		}

		return $this->sitepress->pre_option_default_category( $value );
	}

	private function restore( $language, $trid, $term = null ) {
		$default = (string) $this->sitepress->get_default_language();

		$this->restoring = true;
		try {
			if ( $language !== $default ) {
				if ( $this->isActive( $language ) ) {
					$this->insertDefaultCategory( $language );
				}

				return;
			}

			$term = $this->recreation->writeDefaultLanguageTerm(
				self::TAXONOMY,
				$trid,
				$term ? $term : [
					'name'        => $this->defaultCategoryName(),
					'slug'        => 'uncategorized',
					'description' => '',
				]
			);
			if ( ! $term ) {
				return;
			}

			$map = $this->map();

			update_option( 'default_category', $term['term_id'] );

			$map[ $default ] = $term['term_taxonomy_id'];
			$this->sitepress->set_default_categories( $map );

			foreach ( array_keys( (array) $this->sitepress->get_active_languages() ) as $other ) {
				if ( (string) $other !== $default ) {
					$this->insertDefaultCategory( (string) $other );
				}
			}
		} finally {
			$this->restoring = false;
		}
	}

	private function isActive( $language ) {
		return array_key_exists( $language, (array) $this->sitepress->get_active_languages( true ) );
	}

	private function map() {
		return array_map( 'intval', (array) $this->sitepress->get_setting( 'default_categories', [] ) );
	}

	private function liveTtids( array $ttids ) {
		global $wpdb;

		$live = [];
		foreach ( array_unique( array_filter( array_map( 'intval', $ttids ) ) ) as $ttid ) {
			$found = (int) $wpdb->get_var(
				$wpdb->prepare(
					"SELECT term_taxonomy_id FROM {$wpdb->term_taxonomy} WHERE term_taxonomy_id = %d AND taxonomy = 'category'",
					$ttid
				)
			);
			if ( $found ) {
				$live[] = $found;
			}
		}

		return $live;
	}

	protected function insertDefaultCategory( $language ) {
		\SitePress_Setup::insert_default_category( $language );
	}

	private function termOf( $termId ) {
		global $wpdb;

		$row = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT t.name, t.slug, tt.description FROM {$wpdb->terms} t JOIN {$wpdb->term_taxonomy} tt ON tt.term_id = t.term_id WHERE t.term_id = %d AND tt.taxonomy = 'category'",
				$termId
			)
		);

		return is_object( $row ) && '' !== (string) $row->name
			? [
				'name'        => (string) $row->name,
				'slug'        => (string) $row->slug,
				'description' => (string) $row->description,
			]
			: null;
	}

	protected function defaultCategoryName() {
		$locale   = (string) $this->sitepress->get_locale( (string) $this->sitepress->get_default_language() );
		$switched = '' !== $locale && switch_to_locale( $locale );
		/* translators: The name WordPress gives its default category. WPML uses it when it makes that category in a new language, so use the same wording WordPress itself uses in your language. */
		$name = __( 'Uncategorized', 'sitepress' );
		if ( $switched ) {
			restore_previous_locale();
		}

		return $name;
	}
}
