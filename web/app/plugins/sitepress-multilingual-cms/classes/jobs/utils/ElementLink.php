<?php

namespace WPML\TM\Jobs\Utils;

use WPML\TM\Menu\PostLinkUrl;
use WPML_Post_Translation;

class ElementLink {

	private $postLinkUrl;

	private $postTranslation;

	private $termTranslation;

	public function __construct( PostLinkUrl $postLinkUrl, WPML_Post_Translation $postTranslation, ?\WPML_Term_Translation $termTranslation = null ) {
		$this->postLinkUrl     = $postLinkUrl;
		$this->postTranslation = $postTranslation;
		$this->termTranslation = $termTranslation;
	}

	public function getOriginal( \WPML_TM_Post_Job_Entity $job ) {
		if ( $this->isTermJob( $job ) ) {
			return $this->getTermLink( $job, (int) $job->get_original_element_id() );
		}

		return $this->get( $job, $job->get_original_element_id() );
	}

	public function getTranslation( \WPML_TM_Post_Job_Entity $job ) {
		if ( $this->isExternalType( $job->get_element_type_prefix() ) ) {
			return '';
		}

		if ( $this->isTermJob( $job ) ) {
			$termTranslation = $this->termTranslation ?: \WPML_Term_Translation::getGlobalInstance();
			$translatedTermTaxonomyId = (int) $termTranslation->element_id_in( (int) $job->get_original_element_id(), $job->get_target_language() );

			return $translatedTermTaxonomyId ? $this->getTermLink( $job, $translatedTermTaxonomyId ) : '';
		}

		$translatedId = $this->postTranslation->element_id_in( $job->get_original_element_id(), $job->get_target_language() );

		if ( $translatedId ) {
			return $this->get( $job, $translatedId );
		}

		return '';
	}

	private function get( \WPML_TM_Post_Job_Entity $job, $elementId = null ) {
		$elementId   = $elementId ?: $job->get_target_language();
		$elementType = preg_replace( '/^' . preg_quote( $job->get_element_type_prefix(), '/' ) . '_/', '', $job->get_element_type() );

		if ( $this->isExternalType( $job->get_element_type_prefix() ) ) {
			$tmPostLink = apply_filters( 'wpml_external_item_url', '', $elementId );
		} else {
			$tmPostLink = $this->postLinkUrl->viewLinkUrl( $elementId );
		}
		$tmPostLink = apply_filters(
			'wpml_document_view_item_link',
			$tmPostLink,
			/* translators: Button label in the job log table that opens the details of a request, and link text that opens the translated item. Verb, imperative: to look at it. */
			__( 'View', 'sitepress' ),
			$job,
			$job->get_element_type_prefix(),
			$elementType
		);

		return $tmPostLink;
	}

	private function isTermJob( \WPML_TM_Post_Job_Entity $job ) {
		return \WPML_TM_Job_Entity::TAXONOMY_TYPE === $job->get_element_type_prefix();
	}

	private function getTermLink( \WPML_TM_Post_Job_Entity $job, $termTaxonomyId ) {
		$taxonomy = preg_replace( '/^' . $job->get_element_type_prefix() . '_/', '', $job->get_element_type() );
		$term     = $taxonomy ? get_term_by( 'term_taxonomy_id', (int) $termTaxonomyId, $taxonomy ) : null;
		$link     = $term && isset( $term->term_id ) ? get_edit_term_link( (int) $term->term_id, $taxonomy ) : '';

		return (string) apply_filters(
			'wpml_document_view_item_link',
			is_string( $link ) ? $link : '',
			__( 'View', 'sitepress' ),
			$job,
			$job->get_element_type_prefix(),
			$taxonomy
		);
	}

	private function isExternalType( $elementTypePrefix ) {
		return apply_filters( 'wpml_is_external', false, $elementTypePrefix );
	}
}
