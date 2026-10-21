<?php

namespace ACFML\Field;

trait IdsInLanguageTrait {

	private function getIdsInLanguage( array $ids, $objectType, $languageCode ) {
		$idsInLanguage = [];

		foreach ( $ids as $id ) {
			$id = is_numeric( $id ) ? (int) $id : 0;
			if ( $id > 0 && $languageCode === $this->getLanguageCode( $id, $objectType ) ) {
				$idsInLanguage[ $id ] = $id;
			}
		}

		return array_values( $idsInLanguage );
	}

	private function getLanguageCode( $id, $objectType ) {
		if ( ReferenceRepository::META_TYPE_TERM === $objectType ) {
			$term = get_term( $id );
			if ( ! $term instanceof \WP_Term ) {
				return null;
			}

			return apply_filters( 'wpml_element_language_code', null, [ 'element_id' => $term->term_taxonomy_id, 'element_type' => $term->taxonomy ] );
		}

		return apply_filters( 'wpml_element_language_code', null, [ 'element_id' => $id, 'element_type' => get_post_type( $id ) ] );
	}
}
