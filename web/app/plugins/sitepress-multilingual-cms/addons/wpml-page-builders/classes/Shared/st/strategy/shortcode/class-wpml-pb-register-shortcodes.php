<?php

use WPML\PB\Shortcode\RegistrationPass;
use WPML\PB\Shortcode\StringCleanUp;

class WPML_PB_Register_Shortcodes {

	private $handle_strings;
	private $shortcode_strategy;
	private $encoding;
	private $reuse_translations;

	private $existingStrings;

	private $pass;

	private $attribute_names = [];

	public function __construct(
		WPML_PB_String_Registration $handle_strings,
		WPML_PB_Shortcode_Strategy $shortcode_strategy,
		WPML_PB_Shortcode_Encoding $encoding,
		?WPML_PB_Reuse_Translations_By_Strategy $reuse_translations = null
	) {
		$this->handle_strings     = $handle_strings;
		$this->shortcode_strategy = $shortcode_strategy;
		$this->encoding           = $encoding;
		$this->reuse_translations = $reuse_translations;
	}

	public function register_shortcode_strings(
		$post_id,
		$content,
		?StringCleanUp $externalStringCleanUp = null
	) {

		$any_registered = false;

		$this->attribute_names = [];

		$content = apply_filters( 'wpml_pb_shortcode_content_for_translation', $content, $post_id );
		$content = WPML_PB_Shortcode_Content_Wrapper::maybeWrap( $content, $this->shortcode_strategy->get_shortcodes() );

		$this->existingStrings = $externalStringCleanUp ?: new StringCleanUp( $post_id, $this->shortcode_strategy );
		$this->pass            = $this->existingStrings->getPass();

		$shortcode_parser = $this->pass->getParser( [ $this->shortcode_strategy, 'get_shortcode_parser' ] );
		$shortcodes       = $shortcode_parser->get_shortcodes( $content );

		if ( $this->reuse_translations ) {
			$this->reuse_translations->set_original_strings( $this->existingStrings->get() );
		}

		foreach ( $shortcodes as $shortcode ) {
			$strings = [];

			if ( $this->should_handle_content( $shortcode ) ) {
				$shortcode_content  = $shortcode['content'];
				$encoding           = $this->shortcode_strategy->get_shortcode_tag_encoding( $shortcode['tag'] );
				$encoding_condition = $this->shortcode_strategy->get_shortcode_tag_encoding_condition( $shortcode['tag'] );
				$type               = $this->shortcode_strategy->get_shortcode_tag_type( $shortcode['tag'] );
				$shortcode_content  = $this->encoding->decode( $shortcode_content, $encoding, $encoding_condition );
				$strings[]          = [ $shortcode_content, 'content', $type ];
			}

			$attributes              = (array) shortcode_parse_atts( $shortcode['attributes'] );
			$translatable_attributes = $this->shortcode_strategy->get_shortcode_attributes( $shortcode['tag'] );
			if ( ! empty( $attributes ) ) {
				foreach ( $attributes as $attr => $attr_value ) {
					if ( in_array( $attr, $translatable_attributes, true ) ) {
						$encoding   = $this->shortcode_strategy->get_shortcode_attribute_encoding( $shortcode['tag'], $attr );
						$type       = $this->shortcode_strategy->get_shortcode_attribute_type( $shortcode['tag'], $attr );
						$attr_value = $this->encoding->decode( $attr_value, $encoding );
						$strings[]  = [ $attr_value, $attr, $type ];
					}
				}
			}

			$this->set_attribute_names( $shortcode, $strings );

			foreach ( $strings as list( $value, $attribute, $type ) ) {
				$any_registered = $this->register_string( $post_id, $value, $shortcode, $attribute, $type ) || $any_registered;
			}
		}

		$this->attribute_names = [];

		if ( $this->reuse_translations ) {
			$this->reuse_translations->find_and_reuse( $post_id, $this->existingStrings->get() );
		}

		if ( ! $externalStringCleanUp ) {
			$this->existingStrings->cleanUp();
			$this->mark_post_as_migrate_location_done( $post_id );
		}

		do_action( 'wpml_pb_after_register_shortcode_strings', $post_id );

		return $any_registered;
	}

	private function should_handle_content( $shortcode ) {
		$tag = $shortcode['tag'];

		$handle_content = ! (
			$this->shortcode_strategy->get_shortcode_ignore_content( $tag )
			|| in_array(
				$this->shortcode_strategy->get_shortcode_tag_type( $tag ),
				array(
					'media-url',
					'media-ids',
				),
				true
			)
		);

		return apply_filters( 'wpml_pb_should_handle_content', $handle_content, $shortcode );
	}

	public function get_updated_shortcode_string_title( $string_id, $shortcode, $attribute ) {
		$title = $this->shortcode_strategy->get_shortcode_attribute_label( $shortcode['tag'], $attribute );
		if ( $title ) {
			return $title;
		}

		$shortcode_attributes = [ $attribute ];
		if (
			isset( $this->attribute_names['shortcode'], $this->attribute_names['names'][ $attribute ] )
			&& $this->attribute_names['shortcode'] === $this->get_shortcode_key( $shortcode )
		) {
			$shortcode_attributes = $this->attribute_names['names'][ $attribute ];
		}

		return implode( '/', $shortcode['crumbs'] ) . ': ' . implode( ', ', $shortcode_attributes );
	}

	private function set_attribute_names( $shortcode, array $strings ) {
		$names_by_text     = [];
		$text_by_attribute = [];

		foreach ( $strings as list( $value, $attribute ) ) {
			$this->collect_attribute_names( $value, $attribute, $names_by_text, $text_by_attribute );
		}

		$names = [];
		foreach ( $text_by_attribute as $attribute => $text ) {
			$names[ $attribute ] = $names_by_text[ $text ];
		}

		$this->attribute_names = [
			'shortcode' => $this->get_shortcode_key( $shortcode ),
			'names'     => $names,
		];
	}

	private function collect_attribute_names( $value, $attribute, array &$names_by_text, array &$text_by_attribute ) {
		if ( is_array( $value ) ) {
			foreach ( $value as $key => $data ) {
				if ( $data['translate'] ) {
					$this->collect_attribute_names( $data['value'], $attribute . ' ' . $key, $names_by_text, $text_by_attribute );
				}
			}

			return;
		}

		$text = md5( (string) $value );
		if ( ! isset( $names_by_text[ $text ] ) || ! in_array( $attribute, $names_by_text[ $text ], true ) ) {
			$names_by_text[ $text ][] = $attribute;
		}
		$text_by_attribute[ $attribute ] = $text;
	}

	public function get_shortcode_string_title( $string_id ) {
		return $this->handle_strings->get_string_title( $string_id );
	}

	public function register_string( $post_id, $content, $shortcode, $attribute, $editor_type ) {
		$string_id = 0;

		if ( is_array( $content ) ) {
			foreach ( $content as $key => $data ) {
				if ( $data['translate'] ) {
					$this->register_string( $post_id, $data['value'], $shortcode, $attribute . ' ' . $key, $editor_type );
				}
			}
		} else {
			if ( $this->existingStrings ) {
				$this->existingStrings->remove( $content );
			}
			try {
				$pass          = $this->get_pass();
				$string_name   = md5( (string) $content );
				$shortcode_key = $this->get_shortcode_key( $shortcode );
				$first         = $pass->getRegistered( $string_name );

				if ( $first && $first['shortcode'] !== $shortcode_key ) {
					$string_id = $first['string_id'];
				} else {
					$string_id = $this->handle_strings->get_string_id_from_package( $post_id, $content );

					if ( $first ) {
						$string_title = $first['title'];
						$location     = $first['location'];
					} else {
						$string_title = apply_filters( 'wpml_pb_shortcode_string_title', $this->get_updated_shortcode_string_title( $string_id, $shortcode, $attribute ), $shortcode );
						$location     = $pass->getLocation();
					}

					$string_id = $this->handle_strings->register_string( $post_id, $content, $editor_type, $string_title, '', $location, '' );
					if ( $string_id && ! $first ) {
						$pass->advanceLocation();
						$pass->setRegistered(
							$string_name,
							[
								'shortcode' => $shortcode_key,
								'string_id' => (int) $string_id,
								'title'     => (string) $string_title,
								'location'  => $location,
							]
						);
					}
				}

				if ( $string_id ) {
					do_action( 'wpml_pb_register_shortcode_string', $post_id, $content, $shortcode );
				}
			} catch ( Exception $exception ) {
				$string_id = 0;
			}
		}

		return (bool) $string_id;
	}

	private function get_pass() {
		if ( ! $this->pass ) {
			$this->pass = new RegistrationPass();
		}

		return $this->pass;
	}

	private function get_shortcode_key( $shortcode ) {
		return isset( $shortcode['crumbs'] ) ? implode( '/', (array) $shortcode['crumbs'] ) : '';
	}

	private function mark_post_as_migrate_location_done( $post_id ) {
		update_post_meta( $post_id, WPML_PB_Integration::MIGRATION_DONE_POST_META, true );
	}
}
