<?php

use WPML\PB\TranslationBody\RendersTranslationBody;

class WPML_Page_Builders_Update {

	protected $data_settings;

	public function __construct( IWPML_Page_Builders_Data_Settings $data_settings ) {
		$this->data_settings = $data_settings;
	}

	public function get_converted_data( $post_id ) {
		$data = get_post_meta( $post_id, $this->data_settings->get_meta_field(), true );
		return $this->data_settings->convert_data_to_array( $data );
	}

	public function save( $post_id, $original_post_id, $converted_data ) {
		$fields_to_copy = $this->data_settings->get_fields_to_copy();
		$body_renderer  = $this->data_settings instanceof RendersTranslationBody ? $this->data_settings : null;
		$this->copy_meta_fields(
			$post_id,
			$original_post_id,
			$body_renderer ? array_diff( $fields_to_copy, [ 'post_content' ] ) : $fields_to_copy
		);

		$fields_to_save = $this->data_settings->get_fields_to_save();
		$prepared_data  = $this->data_settings->prepare_data_for_saving( $converted_data );

		$this->save_builder_data( $post_id, $fields_to_save, $prepared_data );

		if ( ! $body_renderer ) {
			return;
		}

		$should_copy_original_body = in_array( 'post_content', $fields_to_copy, true );
		$is_body_written           = $this->update_body( $body_renderer, $post_id, $original_post_id, $should_copy_original_body );

		if ( $is_body_written ) {
			$this->save_builder_data( $post_id, $fields_to_save, $prepared_data );
		}
	}

	private function save_builder_data( $post_id, $fields, $prepared_data ) {
		$this->run_with_unfiltered_html_grant(
			function () use ( $post_id, $fields, $prepared_data ) {
				$this->save_data( $post_id, $fields, $prepared_data );
			}
		);
	}

	protected function run_with_unfiltered_html_grant( callable $write ) {
		$isAteDelivery = apply_filters( \WPML\TM\ATE\Receive\UnfilteredHtmlGrant::FILTER_DELIVERY, false );
		$isKsesActive  = $isAteDelivery && false !== has_filter( 'content_save_pre', 'wp_filter_post_kses' );

		if ( $isAteDelivery ) {
			add_filter( 'user_has_cap', [ __CLASS__, 'grant_unfiltered_html' ] );
			add_filter( 'map_meta_cap', [ __CLASS__, 'allow_unfiltered_html_on_multisite' ], 10, 3 );
			kses_init();
		}

		try {
			return $write();
		} finally {
			if ( $isAteDelivery ) {
				remove_filter( 'user_has_cap', [ __CLASS__, 'grant_unfiltered_html' ] );
				remove_filter( 'map_meta_cap', [ __CLASS__, 'allow_unfiltered_html_on_multisite' ] );
				$isKsesActive ? kses_init_filters() : kses_remove_filters();
			}
		}
	}

	private function update_body( RendersTranslationBody $renderer, $post_id, $original_post_id, $should_copy_original_body ) {
		$original_post = get_post( $original_post_id );
		$body          = $original_post instanceof WP_Post
			? $renderer->render_translation_body( $post_id, $original_post )
			: null;

		if ( is_string( $body ) ) {
			if ( \WPML\PB\Helper\TranslationContent::isStored( $post_id, $this->as_kses_stores_it( $body ) ) ) {
				return false;
			}

			return $this->write_body( $post_id, $body );
		}

		if ( $should_copy_original_body && $original_post instanceof WP_Post ) {
			return $this->write_body( $post_id, $original_post->post_content );
		}

		return false;
	}

	private function as_kses_stores_it( $body ) {
		if ( false === has_filter( 'content_save_pre', 'wp_filter_post_kses' ) ) {
			return $body;
		}

		return wp_unslash( wp_filter_post_kses( wp_slash( $body ) ) );
	}

	private function write_body( $post_id, $body ) {
		$postarr = [
			'ID'           => $post_id,
			'post_content' => $body,
		];
		if ( ! \WPML\PB\Helper\TranslationContent::update( $postarr ) ) {
			return false;
		}

		\WPML\PB\Helper\TranslationContent::followRecord( $post_id );

		return true;
	}

	public static function grant_unfiltered_html( $allcaps ) {
		$allcaps['unfiltered_html'] = true;

		return $allcaps;
	}

	public static function allow_unfiltered_html_on_multisite( $caps, $cap, $user_id ) {
		if ( 'unfiltered_html' !== $cap ) {
			return $caps;
		}

		if ( defined( 'DISALLOW_UNFILTERED_HTML' ) && DISALLOW_UNFILTERED_HTML ) {
			return $caps;
		}

		if ( ! is_multisite() ) {
			return $caps;
		}

		if ( 0 !== (int) $user_id ) {
			return $caps;
		}

		return [ 'unfiltered_html' ];
	}

	private function save_data( $post_id, $fields, $data ) {
		foreach ( $fields as $field ) {
			update_post_meta( $post_id, $field, $data );
		}
	}

	private function copy_meta_fields( $translated_post_id, $original_post_id, $meta_fields ) {
		foreach ( $meta_fields as $meta_key ) {
			if ( 'post_content' === $meta_key ) {
				$original_post = get_post( $original_post_id );
				$this->write_body( $translated_post_id, $original_post->post_content );
			} else {
				$value = get_post_meta( $original_post_id, $meta_key, true );
				update_post_meta(
					$translated_post_id,
					$meta_key,
					apply_filters( 'wpml_pb_copy_meta_field', $value, $translated_post_id, $original_post_id, $meta_key )
				);
			}
		}
	}
}
