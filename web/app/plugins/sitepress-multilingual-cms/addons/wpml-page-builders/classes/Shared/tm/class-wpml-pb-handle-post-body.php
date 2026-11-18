<?php

use WPML\TM\Jobs\JobLog;

class WPML_PB_Handle_Post_Body implements IWPML_Backend_Action, IWPML_Frontend_Action, IWPML_DIC_Action {

	private $page_builders_built;

	private $element_factory;

	private $job_elements;

	public function __construct(
		WPML_Page_Builders_Page_Built $page_builders_built,
		WPML_Translation_Element_Factory $element_factory,
		WPML_TM_Job_Elements_Repository $job_elements
	) {
		$this->page_builders_built = $page_builders_built;
		$this->element_factory     = $element_factory;
		$this->job_elements        = $job_elements;
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
			$own_body_job_id = $this->own_translated_body_job_id( (int) $new_post_id, (string) $original_post->post_content, $fields );
			if ( false !== $own_body_job_id ) {
				JobLog::add(
					'pb_body_copy_skipped_own_body',
					[
						'post_id' => (int) $new_post_id,
						'job_id'  => $own_body_job_id,
					]
				);

				return;
			}

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

	private function own_translated_body_job_id( $new_post_id, $original_body, array $fields ) {
		$latest     = $this->job_elements->get_latest_translated_job_body( $new_post_id );
		$job_id     = $latest ? $latest['job_id'] : null;
		$translated = $latest && $latest['has_body'] && 1 === $latest['field_translate'];

		if ( $this->field_body_is_translated( $fields, $translated ) && $this->is_own_body( $this->field_body( $fields ), $original_body ) ) {
			return $job_id;
		}

		if (
			$translated
			&& 1 === $latest['field_finished']
			&& $this->is_own_body( $latest['data'], $original_body )
		) {
			return $job_id;
		}

		return false;
	}

	private function field_body_is_translated( array $fields, $stored_row_translated ) {
		if ( ! isset( $fields['body'] ) || ! is_array( $fields['body'] ) ) {
			return false;
		}

		$field = $fields['body'];

		if ( array_key_exists( 'finished', $field ) && ! $field['finished'] ) {
			return false;
		}

		foreach ( [ 'field_translate', 'translate' ] as $key ) {
			if ( array_key_exists( $key, $field ) ) {
				return 1 === (int) $field[ $key ];
			}
		}

		return $stored_row_translated;
	}

	private function field_body( array $fields ) {
		$body = isset( $fields['body']['data'] ) ? $fields['body']['data'] : '';

		return is_string( $body ) ? $body : '';
	}

	private function is_own_body( $body, $original_body ) {
		return '' !== trim( $body ) && $body !== $original_body;
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
