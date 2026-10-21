<?php

use WPML\FP\Obj;

class WPML_TM_Rest_Jobs_Element_Info {
	private $package_helper_factory;

	private $post_types;

	public function __construct( WPML_TM_Rest_Jobs_Package_Helper_Factory $package_helper_factory ) {
		$this->package_helper_factory = $package_helper_factory;
	}


	public function get( WPML_TM_Job_Entity $job ) {
		$type   = $job->get_type();
		$id     = $job->get_original_element_id();
		$result = [];

		switch ( $type ) {
			case WPML_TM_Job_Entity::POST_TYPE:
				$result = $this->get_for_post( $id, $job->get_element_id() );
				break;
			case WPML_TM_Job_Entity::STRING_TYPE:
			case WPML_TM_Job_Entity::STRING_BATCH:
				$result = $this->get_for_title( $job->get_title() );
				break;
			case WPML_TM_Job_Entity::PACKAGE_TYPE:
				$result = $this->get_for_package( $id );
				break;
			case WPML_TM_Job_Entity::TAXONOMY_TYPE:
				$result = $this->get_for_term( $id, $job->get_element_type() );
				break;
		}

		if ( empty( $result ) ) {
			$result = array(
				'name' => '',
				'url'  => null,
			);
			do_action( 'wpml_tm_jobs_log', 'WPML_TM_Rest_Jobs_Element_Info::get', array( $id, $type ), 'Empty result' );
		}

		$result['url'] = apply_filters( 'wpml_tm_job_list_element_url', $result['url'], $id, $type );

		if ( $job instanceof WPML_TM_Post_Job_Entity ) {
			$result['type'] = $this->get_type_info( $job );
		}

		return $result;
	}

	private function get_for_post( $originalPostId, $translatedPostId ) {
		$result = array();

		$post = get_post( $originalPostId );
		if ( $post ) {
			$permalink = get_permalink( $post );

			$result = [
				'name'   => $post->post_title,
				'url'    => $permalink,
				'status' => Obj::propOr( 'draft', 'post_status', get_post( $translatedPostId ) ),
			];
		}

		return $result;
	}

	private function get_for_package( $id ) {
		$result = array();

		$helper = $this->package_helper_factory->create();
		if ( ! $helper ) {
			return array(
				'name' => __( 'String package job', 'sitepress' ),
				'url'  => null,
			);
		}

		$package = $helper->get_translatable_item( null, $id );
		if ( $package ) {
			$result = array(
				'name' => $package->title,
				'url'  => $package->edit_link,
			);
		}

		return $result;
	}

	private function get_for_term( $termTaxonomyId, $elementType ) {
		$taxonomy = (string) substr( (string) $elementType, strlen( WPML_TM_Job_Entity::TAXONOMY_TYPE ) + 1 );
		$term     = $taxonomy ? get_term_by( 'term_taxonomy_id', (int) $termTaxonomyId, $taxonomy ) : null;

		if ( ! $term || ! isset( $term->term_id ) ) {
			return array();
		}

		$editLink = get_edit_term_link( (int) $term->term_id, $taxonomy );

		return [
			'name' => (string) $term->name,
			'url'  => is_string( $editLink ) ? $editLink : null,
		];
	}

	private function get_for_title( $title ) {
		return [
			'name' => $title,
			'url'  => null,
		];
	}

	private function get_type_info( WPML_TM_Post_Job_Entity $job ) {
		$generalType = substr(
			$job->get_element_type(),
			0,
			strpos( $job->get_element_type(), '_' ) ?: 0
		);

		switch ( $generalType ) {
			case 'post':
			case 'package':
				$specificType = substr( $job->get_element_type(), strlen( $generalType ) + 1 );
				$label        = Obj::pathOr(
					$job->get_element_type(),
					[ $specificType, 'labels', 'singular_name' ],
					$this->get_post_types()
				);
				break;
			case 'st-batch':
				/* translators: Name of a kind of content in the translation screens: the single texts of the site, as opposed to posts and pages. Plural noun. */
				$label = __( 'Strings', 'sitepress' );
				break;
			case WPML_TM_Job_Entity::TAXONOMY_TYPE:
				$specificType = substr( $job->get_element_type(), strlen( $generalType ) + 1 );
				$taxonomy     = get_taxonomy( $specificType );
				$label        = $taxonomy && isset( $taxonomy->labels->singular_name )
					? (string) $taxonomy->labels->singular_name
					: $job->get_element_type();
				break;
			default:
				$label = $job->get_element_type();
		}

		return [
			'value' => $job->get_element_type(),
			'label' => apply_filters( 'wpml_tm_job_list_element_label_filter', $label, $job->get_element_type() ),
		];
	}

	private function get_post_types() {
		if ( $this->post_types === null ) {
			$this->post_types = apply_filters(
				'wpml_tm_job_list_post_types_filter',
				\WPML\API\PostTypes::getTranslatableWithInfo()
			);
		}

		return $this->post_types;
	}
}
