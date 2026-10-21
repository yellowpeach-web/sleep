<?php

class WPML_Media_Translated_Images_Update {

	private $media_parser_factory;

	private $image_translator;
	private $media_sizes;

	public function __construct(
		\WPML\Media\Factories\WPML_Media_Element_Parser_Factory $media_parser_factory,
		WPML_Media_Image_Translate $image_translator,
		WPML_Media_Sizes $media_sizes
	) {
		$this->media_parser_factory = $media_parser_factory;
		$this->image_translator     = $image_translator;
		$this->media_sizes          = $media_sizes;
	}

	public function replace_images_with_translations( $text, $target_language, $source_language = null ) {
		$media_parsers = $this->media_parser_factory->create( $text );

		$attachment_id = $this->get_posted_attachment_id( 'original-attachment-id' );
		$translated_id = $this->get_posted_attachment_id( 'translated-attachment-id' );

		$pre_update_translated_media_guid = isset( $_POST['pre-update-translated-media-guid'] ) ? $_POST['pre-update-translated-media-guid'] : '';

		$pre_update_translated_media_sizes = isset( $_POST['pre-update-translated-media-sizes'] ) && is_array( $_POST['pre-update-translated-media-sizes'] )
			? array_map( 'sanitize_file_name', wp_unslash( $_POST['pre-update-translated-media-sizes'] ) )
			: [];

		$media_src_same_as_pre_update = function ( $media_src ) use ( $pre_update_translated_media_guid ) {
			return $media_src === $pre_update_translated_media_guid;
		};

		$remove_dimension_suffix = function ( $media_src, $media_extension ) {
			$sanitize_pattern = '/-(?:\d+x\d+|scaled|rotated)\.' . preg_quote( $media_extension, '/' ) . '$/';

			return preg_replace( $sanitize_pattern, '.' . $media_extension, $media_src );
		};

		$media_src_contains_pre_update = function ( $media_src ) use ( $remove_dimension_suffix, $pre_update_translated_media_guid ) {
			$thumb_file_name                   = basename( $pre_update_translated_media_guid );
			$pre_update_translated_media_parts = explode( '.', $thumb_file_name );

			if ( count( $pre_update_translated_media_parts ) < 2 || ! $pre_update_translated_media_parts[0] || ! $pre_update_translated_media_parts[1] ) {
				return false;
			}

			$media_src_extension                   = pathinfo( $media_src, PATHINFO_EXTENSION );
			$pre_update_translated_media_extension = is_array( $pre_update_translated_media_parts ) ? end( $pre_update_translated_media_parts ) : null;

			$sanitized_media_src = $remove_dimension_suffix( $media_src, $pre_update_translated_media_parts[1] );

			return false !== str_ends_with( $sanitized_media_src, implode( '.', $pre_update_translated_media_parts ) ) && $media_src_extension === $pre_update_translated_media_extension;
		};

		if ( ! empty( $media_parsers ) ) {
			$items_to_translate = [];
			foreach ( $media_parsers as $media_parser ) {
				$media_items = $media_parser->getMediaElements();

				foreach ( $media_items as $media ) {
					$media_src = $media_parser->getMediaSrcFromAttributes( $media['attributes'] );

					if ( ! $this->isTranslatableMediaSrc( $media_src ) ) {
						continue;
					}

					$original_media_guid = isset( $attachment_id ) ? $this->getSizedOriginalMediaGuid( $attachment_id, $source_language ) : $media_src;

					if (
						( $media_src_same_as_pre_update( $media_src ) || $media_src_contains_pre_update( $media_src ) ) || ( $media_src === $original_media_guid )
					) {
						$items_to_translate[] = [
							'attachment_id'   => $attachment_id,
							'translated_id'   => $translated_id,
							'media'           => $media,
							'mediaParser'     => $media_parser,
							'mediaSrc'        => $media_src,
							'target_language' => $target_language,
						];
					} else {
						if ( empty( $pre_update_translated_media_guid ) && $this->mediaSrcContainsMediaFileName( $translated_id, $media_src ) ) {
							$text = $this->replace_image_src( $text, $media_src, $original_media_guid );
							$text = $this->replace_att_class( $text, $translated_id, $attachment_id );
							$text = $this->replace_att_in_block( $text, $translated_id, $attachment_id );
						}
					}
				}
			}

			$this->image_translator->prefetchDataForFutureGetTranslatedImageCalls(
				$source_language,
				array_map(
					function( $source_item ) {
						$item        = $source_item;
						$item['url'] = $this->getMediaParserImgSrc( $item['mediaParser'], $item['media'] );

						return $item;
					},
					$items_to_translate
				)
			);

			foreach ( $items_to_translate as $item_to_translate ) {
				$attachment_id   = $item_to_translate['attachment_id'];
				$translated_id   = $item_to_translate['translated_id'];
				$media           = $item_to_translate['media'];
				$media_parser    = $item_to_translate['mediaParser'];
				$media_src       = $item_to_translate['mediaSrc'];
				$target_language = $item_to_translate['target_language'];

				if ( isset( $attachment_id ) && $attachment_id ) {
					$size = $this->media_sizes->get_attachment_size( $media );
					if ( ! $size && $pre_update_translated_media_guid ) {
						$size = $this->get_pre_update_size( $media_src, $pre_update_translated_media_sizes );
					}
					if ( ! $size ) {
						$size = $this->media_sizes->get_image_size_from_url( $media_src, $attachment_id );
					}
					$translated_src = $this->image_translator->get_translated_image( $attachment_id, $target_language, $size );
				} else {
					$translated_src = $this->get_translated_image_by_url( $media_parser, $target_language, $source_language, $media );

					if ( $translated_src && $this->get_url_in_form_of( $media_src, $translated_src ) !== $media_src ) {
						$attachment_id = $this->image_translator->get_attachment_id_by_url( $media_src, $source_language );
					}
				}

				if ( $translated_src ) {
					if ( $translated_src !== $media_src ) {
						$text = $this->replace_image_src( $text, $media_src, $translated_src );
					}

					$text = $this->replaceAttributeInHref( $text, $media_src, $translated_src, $source_language );
					$text = $this->replaceLinkedAttachmentHref( $text, $this->get_url_in_form_of( $media_src, $translated_src ), $source_language, $target_language );
				}
				$target_attachment_id = $this->get_attachment_id_for_language( $attachment_id, $translated_id, $target_language );

				if ( $attachment_id && $target_attachment_id && (string) $attachment_id !== (string) $target_attachment_id ) {
					$text = $this->replace_att_class( $text, $attachment_id, $target_attachment_id );
					$text = $this->replace_att_in_block( $text, $attachment_id, $target_attachment_id );
				}
				if ( $attachment_id && $translated_src ) {
					$text = $this->replace_att_in_block_background( $text, $attachment_id, $target_attachment_id, $translated_src );
				}
			}
		}

		return $text;
	}

	private function get_posted_attachment_id( $key ) {
		if ( ! isset( $_POST[ $key ] ) || ! is_numeric( $_POST[ $key ] ) ) {
			return null;
		}

		$id = (int) $_POST[ $key ];

		return $id > 0 ? $id : null;
	}

	private function get_attachment_id_for_language( $attachment_id, $translated_id, $target_language ) {
		if ( ! $attachment_id || ! $target_language ) {
			return $translated_id;
		}

		$resolved = apply_filters( 'wpml_object_id', $attachment_id, 'attachment', false, $target_language );

		return $resolved ? $resolved : null;
	}

	private function get_pre_update_size( $media_src, array $sizes ) {
		$size = array_search( basename( (string) wp_parse_url( $media_src, PHP_URL_PATH ) ), $sizes, true );

		return is_string( $size ) ? $size : null;
	}

	private function getMediaGuid( $id ) {
		return get_post_field( 'guid', $id );
	}

	private function mediaSrcContainsMediaFileName( $attachment_id, $media_src ) {
		$guid            = $this->getMediaGuid( $attachment_id );
		$media_extension = substr( $guid, - 4 );
		$media_filename  = explode( $media_extension, basename( $guid ) )[0];

		return \WPML\FP\Str::includes( $media_filename, $media_src );
	}

	private function getSizedOriginalMediaGuid( $attachment_id, $source_lang ) {
		$original_media_guid = $this->getMediaGuid( $attachment_id );
		$original_media_size = $this->media_sizes->get_image_size_from_url( $original_media_guid, $attachment_id );

		return $this->image_translator->get_translated_image( $attachment_id, $source_lang, $original_media_size );
	}

	private function replace_image_src( $text, $from, $to ) {
		$to = $this->get_url_in_form_of( $from, $to );

		if ( '' === (string) $from || $to === $from ) {
			return $text;
		}

		$pattern = '#(?<![\w.:/-])' . preg_quote( $from, '#' ) . '(?![\w-]|\.\w)#';

		$replaced = preg_replace_callback(
			$pattern,
			function () use ( $to ) {
				return $to;
			},
			$text
		);

		return null === $replaced ? $text : $replaced;
	}

	private function get_url_in_form_of( $source, $url ) {
		if ( ! is_string( $source ) || ! is_string( $url ) || '' === $source || '/' !== $source[0] ) {
			return $url;
		}

		if ( ! preg_match( '#^(https?:)(//[^/?\#]+)(/.*)$#i', $url, $url_parts ) ) {
			return $url;
		}

		$uploads_dir = wp_get_upload_dir();
		$baseurl     = wp_parse_url( $uploads_dir['baseurl'] );

		if ( empty( $baseurl['scheme'] ) || empty( $baseurl['host'] ) ) {
			return $url;
		}

		$port   = isset( $baseurl['port'] ) ? ':' . $baseurl['port'] : '';
		$origin = $baseurl['scheme'] . '://' . $baseurl['host'] . $port;

		if ( 0 !== strcasecmp( $url_parts[1] . $url_parts[2], $origin ) ) {
			return $url;
		}

		return 0 === strpos( $source, '//' ) ? $url_parts[2] . $url_parts[3] : $url_parts[3];
	}

	private function replace_att_class( $text, $from, $to ) {
		$pattern     = '/\bwp-image-' . $from . '\b/u';
		$replacement = 'wp-image-' . $to;

		return preg_replace( $pattern, $replacement, $text );
	}

	private function replace_att_in_block( $text, $from, $to ) {
		$from = (string) $from;

		if ( '' === $from || ! ctype_digit( $from ) || false === strpos( (string) $text, '<!-- wp:' ) ) {
			return $text;
		}

		$pattern = '/<!-- wp:(?:image|file|audio|video) \{(?:(?!-->).)*?"id":' . $from . '(?=[,}])(?:(?!-->).)*?-->/su';

		$replacement = function ( $matches ) use ( $from, $to ) {
			return (string) preg_replace( '/"id":' . $from . '(?=[,}])/', '"id":' . $to, $matches[0] );
		};

		$replaced = preg_replace_callback( $pattern, $replacement, $text );

		return null === $replaced ? $text : $replaced;
	}

	private function replace_att_in_block_background( $text, $from, $to, $translated_src ) {
		if ( ! $translated_src || ! $to || (string) $from === (string) $to ) {
			return $text;
		}

		$src  = preg_quote( $translated_src, '/' );
		$from = preg_quote( (string) $from, '/' );

		$patterns = [
			'/("backgroundImage":\{[^{}]*?"url":"' . $src . '"[^{}]*?"id":)' . $from . '(?=[,}])/u',
			'/("backgroundImage":\{[^{}]*?"id":)' . $from . '(?=[,}][^{}]*?"url":"' . $src . '")/u',
		];

		return (string) preg_replace( $patterns, '${1}' . $to, $text );
	}

	private function replaceAttributeInHref( $text, $from, $to, $source_lang ) {
		$pattern = '/<a.*?href="(.*?)".*?>/u';

		$attach_id = $this->image_translator->get_attachment_id_by_url( $from, $source_lang );
		$from      = get_post_field( 'guid', $attach_id );

		$replacement = function ( $matches ) use ( $from, $to ) {
			return str_replace( 'href="' . $from . '"', 'href="' . $to . '"', $matches[0] );
		};

		return preg_replace_callback( $pattern, $replacement, $text );
	}

	private function replaceLinkedAttachmentHref( $text, $img_src, $source_lang, $target_lang ) {
		if ( ! $target_lang || false === strpos( $text, '<a' ) ) {
			return $text;
		}

		$pattern = '/(<a\b[^>]*?\bhref=")([^"]+)("[^>]*>\s*<img\b[^>]*?\bsrc="' . preg_quote( $img_src, '/' ) . '")/u';

		$replacement = function ( $matches ) use ( $img_src, $source_lang, $target_lang ) {
			$href = $matches[2];

			if ( $href === $img_src || ! $this->isUploadsUrl( $href ) ) {
				return $matches[0];
			}

			$translated_href = $this->image_translator->get_translated_image_by_url( $href, $source_lang, $target_lang );

			return $translated_href && $translated_href !== $href
				? $matches[1] . $translated_href . $matches[3]
				: $matches[0];
		};

		return (string) preg_replace_callback( $pattern, $replacement, $text );
	}

	private function isUploadsUrl( $url ) {
		$uploads_dir = wp_get_upload_dir();

		return 0 === strpos(
			(string) preg_replace( '#^https?:#', '', WPML_Media_Attachment_By_URL::normalizeUrl( $url ) ),
			(string) preg_replace( '#^https?:#', '', $uploads_dir['baseurl'] )
		);
	}

	private function isTranslatableMediaSrc( $media_src ) {
		return is_string( $media_src ) && 1 === preg_match( '#^(?:https?://|/)#i', $media_src );
	}

	private function getMediaParserImgSrc( $media_parser, $img ) {
		return $media_parser->getMediaSrcFromAttributes( $img['attributes'] );
	}

	private function get_translated_image_by_url( $media_parser, $target_language, $source_language, $img ) {
		$translated_src = $this->image_translator->get_translated_image_by_url(
			$this->getMediaParserImgSrc( $media_parser, $img ),
			$source_language,
			$target_language
		);

		return $translated_src;
	}

}
