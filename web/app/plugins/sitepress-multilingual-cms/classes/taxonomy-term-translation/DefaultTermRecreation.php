<?php

namespace WPML\TaxonomyTermTranslation;

class DefaultTermRecreation {

	protected $sitepress;

	public function __construct( \SitePress $sitepress ) {
		$this->sitepress = $sitepress;
	}

	public function defaultLanguageMember( $termId, $taxonomy, $previousTermId = 0 ) {
		$language    = $this->defaultLanguage();
		$elementType = 'tax_' . $taxonomy;
		$ttid        = $this->ttidOf( (int) $termId, $taxonomy );

		if ( '' === $language || ! $ttid ) {
			return 0;
		}

		$trid    = $this->tridOfTtid( $ttid, $elementType );
		$members = $trid ? $this->members( $trid, $elementType ) : [];

		if ( isset( $members[ $language ] ) ) {
			$memberTermId = $this->termIdOf( $members[ $language ] );
			if ( $memberTermId ) {
				return $memberTermId;
			}
		} elseif ( count( $members ) <= 1 && $this->isNamedAsDefaultTerm( (int) $termId, $taxonomy ) ) {
			$leftBehind = $this->groupOfDeletedDefaultTerm( $taxonomy, (int) $previousTermId );
			if ( $leftBehind && $leftBehind !== $trid ) {
				$this->placeAsOriginal( $ttid, $leftBehind, $taxonomy );
				$this->completeTranslations( $ttid, $taxonomy );

				return (int) $termId;
			}

			$this->sitepress->set_element_language_details( $ttid, $elementType, $trid ? $trid : null, $language, null );
			$this->clearTranslationCaches( $this->tridOfTtid( $ttid, $elementType ), $elementType );
			$this->completeTranslations( $ttid, $taxonomy );

			return (int) $termId;
		}

		$term = $this->writeDefaultLanguageTerm( $taxonomy, $trid );
		if ( ! $term ) {
			return 0;
		}

		$this->completeTranslations( $term['term_taxonomy_id'], $taxonomy );

		return $term['term_id'];
	}

	public function writeDefaultLanguageTerm( $taxonomy, $trid, $term = null ) {
		$term     = $term ? $term : $this->defaultTermOf( $taxonomy );
		$language = $this->defaultLanguage();

		if ( ! $term || '' === $language ) {
			return null;
		}

		$slug     = sanitize_title( '' !== $term['slug'] ? $term['slug'] : $term['name'] );
		$inserted = wp_insert_term( $term['name'], $taxonomy, [ 'slug' => $slug, 'description' => $term['description'] ] );

		if ( is_wp_error( $inserted ) && 'term_exists' === $inserted->get_error_code() ) {
			$inserted = wp_insert_term( $term['name'], $taxonomy, [ 'slug' => $slug . '-' . $language, 'description' => $term['description'] ] );
		}

		if ( is_wp_error( $inserted ) || ! is_array( $inserted ) || empty( $inserted['term_taxonomy_id'] ) ) {
			return null;
		}

		$written = [
			'term_id'          => (int) $inserted['term_id'],
			'term_taxonomy_id' => (int) $inserted['term_taxonomy_id'],
		];

		$this->placeAsOriginal( $written['term_taxonomy_id'], (int) $trid, $taxonomy );

		return $written;
	}

	public function completeTranslations( $ttid, $taxonomy ) {
		$elementType = 'tax_' . $taxonomy;
		$default     = $this->defaultLanguage();
		$trid        = $this->tridOfTtid( (int) $ttid, $elementType );

		if ( ! $trid ) {
			return;
		}

		$members = $this->members( $trid, $elementType );
		foreach ( array_keys( (array) $this->sitepress->get_active_languages() ) as $language ) {
			$language = (string) $language;
			if ( $language !== $default && ! isset( $members[ $language ] ) ) {
				$this->createTranslation( $trid, $language, $default, $taxonomy );
			}
		}
	}

	public function tridOf( $termId, $taxonomy ) {
		$ttid = $this->ttidOf( (int) $termId, $taxonomy );

		return $ttid ? $this->tridOfTtid( $ttid, 'tax_' . $taxonomy ) : 0;
	}

	public function tridOfTtid( $ttid, $elementType ) {
		global $wpdb;

		return (int) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT trid FROM {$wpdb->prefix}icl_translations WHERE element_id = %d AND element_type = %s",
				$ttid,
				$elementType
			)
		);
	}

	protected function groupOfDeletedDefaultTerm( $taxonomy, $previousTermId ) {
		global $wpdb;

		$elementType = 'tax_' . $taxonomy;
		$rows        = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT i.element_id, i.trid
				 FROM {$wpdb->prefix}icl_translations i
				 LEFT JOIN {$wpdb->term_taxonomy} tt ON tt.term_taxonomy_id = i.element_id
				 WHERE i.element_type = %s AND i.language_code = %s
				   AND i.element_id IS NOT NULL AND tt.term_taxonomy_id IS NULL",
				$elementType,
				$this->defaultLanguage()
			)
		);

		$named = [];
		foreach ( (array) $rows as $row ) {
			if ( $previousTermId > 0 && (int) $row->element_id === $previousTermId ) {
				return (int) $row->trid;
			}
			foreach ( $this->members( (int) $row->trid, $elementType ) as $memberTtid ) {
				$memberTermId = $this->termIdOf( $memberTtid );
				if ( $memberTermId && $this->isNamedAsDefaultTerm( $memberTermId, $taxonomy ) ) {
					$named[ (int) $row->trid ] = true;
				}
			}
		}

		return 1 === count( $named ) ? (int) key( $named ) : 0;
	}

	public function ttidOf( $termId, $taxonomy ) {
		global $wpdb;

		if ( $termId <= 0 ) {
			return 0;
		}

		return (int) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT term_taxonomy_id FROM {$wpdb->term_taxonomy} WHERE term_id = %d AND taxonomy = %s",
				$termId,
				$taxonomy
			)
		);
	}

	protected function placeAsOriginal( $ttid, $trid, $taxonomy ) {
		global $wpdb;

		$elementType = 'tax_' . $taxonomy;
		$language    = $this->defaultLanguage();

		if ( ! $trid ) {
			$this->sitepress->set_element_language_details( $ttid, $elementType, null, $language, null );

			return;
		}

		$this->dropTranslationRows( $this->rowsNamingNoTerm( $trid, $elementType, $language ) );
		$this->sitepress->set_element_language_details( $ttid, $elementType, $trid, $language, null );

		$wpdb->query(
			$wpdb->prepare(
				"UPDATE {$wpdb->prefix}icl_translations
				 SET source_language_code = IF( element_id = %d, NULL, %s )
				 WHERE trid = %d AND element_type = %s",
				$ttid,
				$language,
				$trid,
				$elementType
			)
		);

		$this->clearTranslationCaches( $trid, $elementType );
	}

	protected function rowsNamingNoTerm( $trid, $elementType, $language ) {
		global $wpdb;

		return array_map(
			'intval',
			$wpdb->get_col(
				$wpdb->prepare(
					"SELECT i.translation_id
					 FROM {$wpdb->prefix}icl_translations i
					 LEFT JOIN {$wpdb->term_taxonomy} tt ON tt.term_taxonomy_id = i.element_id
					 WHERE i.trid = %d AND i.element_type = %s AND i.language_code = %s
					   AND i.element_id IS NOT NULL AND tt.term_taxonomy_id IS NULL",
					$trid,
					$elementType,
					$language
				)
			)
		);
	}

	protected function dropTranslationRows( array $translationIds ) {
		if ( $translationIds ) {
			\WPML_Translation_Records_Delete::translations_by_ids( $translationIds );
		}
	}

	protected function clearTranslationCaches( $trid, $elementType ) {
		global $wpml_term_translations;

		$this->sitepress->get_translations_cache()->clear();
		$this->sitepress->clear_elements_cache( [], '' );
		if ( $wpml_term_translations ) {
			$wpml_term_translations->reload();
		}
		foreach ( $this->members( $trid, $elementType ) as $ttid ) {
			wp_cache_delete( ...\WPML_Set_Language::get_cache_ref( $ttid, $elementType ) );
		}

		do_action( 'wpml_translation_update', [ 'type' => 'update', 'trid' => $trid, 'element_type' => $elementType, 'context' => 'tax' ] );
	}

	protected function createTranslation( $trid, $language, $sourceLanguage, $taxonomy ) {
		( new \WPML_Terms_Translations() )->create_automatic_translation(
			[
				'lang_code'       => $language,
				'taxonomy'        => $taxonomy,
				'trid'            => $trid,
				'source_language' => $sourceLanguage,
			]
		);
	}

	protected function members( $trid, $elementType ) {
		global $wpdb;

		$rows = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT language_code, element_id FROM {$wpdb->prefix}icl_translations WHERE trid = %d AND element_type = %s",
				$trid,
				$elementType
			)
		);

		$members = [];
		foreach ( (array) $rows as $row ) {
			if ( ! empty( $row->element_id ) ) {
				$members[ (string) $row->language_code ] = (int) $row->element_id;
			}
		}

		return $members;
	}

	private function termIdOf( $ttid ) {
		global $wpdb;

		return (int) $wpdb->get_var(
			$wpdb->prepare( "SELECT term_id FROM {$wpdb->term_taxonomy} WHERE term_taxonomy_id = %d", $ttid )
		);
	}

	private function isNamedAsDefaultTerm( $termId, $taxonomy ) {
		global $wpdb;

		$defaultTerm = $this->defaultTermOf( $taxonomy );
		if ( ! $defaultTerm ) {
			return false;
		}

		$name = (string) $wpdb->get_var(
			$wpdb->prepare( "SELECT name FROM {$wpdb->terms} WHERE term_id = %d", $termId )
		);

		return $name === $defaultTerm['name']
			|| $name === wp_unslash( sanitize_term_field( 'name', $defaultTerm['name'], 0, $taxonomy, 'db' ) );
	}

	private function defaultTermOf( $taxonomy ) {
		$object = get_taxonomy( $taxonomy );
		$term   = $object && isset( $object->default_term ) ? $object->default_term : null;

		if ( is_string( $term ) ) {
			$term = [ 'name' => $term ];
		}

		if ( ! is_array( $term ) || ! isset( $term['name'] ) || '' === (string) $term['name'] ) {
			return null;
		}

		return [
			'name'        => (string) $term['name'],
			'slug'        => isset( $term['slug'] ) ? (string) $term['slug'] : '',
			'description' => isset( $term['description'] ) ? (string) $term['description'] : '',
		];
	}

	protected function defaultLanguage() {
		return (string) $this->sitepress->get_default_language();
	}
}
