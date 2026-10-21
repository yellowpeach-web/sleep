<?php

namespace ACFML\FieldGroup;

class SyncTermMetaFactory {

	public function createForTerm( int $termId ) {
		global $sitepress;

		$term = get_term( $termId );

		if ( ! $term instanceof \WP_Term ) {
			return null;
		}

		return new \WPML_Sync_Term_Meta_Action( $sitepress, (int) $term->term_taxonomy_id );
	}
}
