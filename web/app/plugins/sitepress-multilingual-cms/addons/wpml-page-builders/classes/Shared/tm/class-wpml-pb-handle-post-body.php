<?php

class WPML_PB_Handle_Post_Body implements IWPML_Backend_Action, IWPML_Frontend_Action, IWPML_DIC_Action {

	private $page_builders_built;

	private $element_factory;

	public function __construct(
		WPML_Page_Builders_Page_Built $page_builders_built,
		WPML_Translation_Element_Factory $element_factory
	) {
		$this->page_builders_built = $page_builders_built;
		$this->element_factory     = $element_factory;
	}

	public function add_hooks() {
		add_filter( 'wpml_pb_should_body_be_translated', array( $this, 'should_translate' ), 10, 2 );
		add_action( 'wpml_pb_finished_adding_string_translations', array( $this, 'copy' ), 10, 3 );
	}

	public function should_translate( $translate, WP_Post $post ) {
		return $this->page_builders_built->is_page_builder_page( $post ) ? 0 : $translate;
	}

	public function copy( $new_post_id, $original_post_id, array $fields ) {
		if ( ! $new_post_id ) {
			return;
		}

		$original_post = get_post( $original_post_id );
		if ( $original_post && $this->page_builders_built->is_page_builder_page( $original_post ) && ! $this->job_has_packages( $fields ) ) {
			$element = $this->element_factory->create_post( $new_post_id );
			\WPML\PB\Helper\TranslationContent::update(
				[
					'ID'           => $new_post_id,
					'post_content' => $original_post->post_content,
				],
				$element->get_language_code()
			);
			do_action( 'wpml_pb_after_page_without_elements_post_content_copy', $new_post_id, $original_post_id );
		}
	}

	private function job_has_packages( array $fields ) {
		foreach ( $fields as $key => $field ) {
			if ( 0 === strpos( $key, 'package' ) ) {
				return true;
			}
		}

		return false;
	}
}
