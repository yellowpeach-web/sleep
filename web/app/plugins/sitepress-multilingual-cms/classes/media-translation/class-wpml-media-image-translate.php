<?php

use WPML\Media\Classes\WPML_Media_Element_Translation_Factory;

class WPML_Media_Image_Translate {

	const URLS_TO_IDS_CACHE_KEY = 'urls-to-ids-cache-key';

	private $sitepress;

	private $attachment_by_url_factory;

	private $media_attachment_by_url_query;

	public function __construct(
		SitePress $sitepress,
		WPML_Media_Attachment_By_URL_Factory $attachment_by_url_factory,
		\WPML\Media\Factories\WPML_Media_Attachment_By_URL_Query_Factory $media_attachment_by_url_query_factory
	) {
		$this->sitepress                     = $sitepress;
		$this->attachment_by_url_factory     = $attachment_by_url_factory;
		$this->media_attachment_by_url_query = $media_attachment_by_url_query_factory->create();
		wp_cache_add_non_persistent_groups( self::URLS_TO_IDS_CACHE_KEY );
	}

	public function prefetchDataForFutureGetTranslatedImageCalls( $source_language, $items_to_translate ) {
		$items_to_translate = array_map(
			function ( $item ) {
				$item['url'] = WPML_Media_Attachment_By_URL::normalizeUrl( $item['url'] );

				return $item;
			},
			$items_to_translate
		);

		$languages = $this->getPrefetchLanguages( $source_language, $items_to_translate );

		if ( ! $languages ) {
			return;
		}

		$found_ids = (array) $this->media_attachment_by_url_query->prefetchAllIdsFromGuids(
			$languages,
			array_merge(
				array_map(
					function ( $item ) {
						return $item['url'];
					},
					$items_to_translate
				),
				array_map(
					function( $item ) {
						return WPML_Media_Attachment_By_URL::getUrl( $item['url'] );
					},
					$items_to_translate
				),
				array_map(
					function( $item ) {
						return WPML_Media_Attachment_By_URL::getUrlNotScaled( $item['url'] );
					},
					$items_to_translate
				)
			)
		);
		$found_ids = array_merge(
			$found_ids,
			(array) $this->media_attachment_by_url_query->prefetchAllIdsFromMetas(
				$languages,
				array_merge(
					array_map(
						function ( $item ) {
							return WPML_Media_Attachment_By_URL::getUrlRelativePath( $item['url'] );
						},
						$items_to_translate
					),
					array_map(
						function ( $item ) {
							return WPML_Media_Attachment_By_URL::getUrlRelativePathOriginal(
								WPML_Media_Attachment_By_URL::getUrlRelativePath( $item['url'] )
							);
						},
						$items_to_translate
					),
					array_map(
						function ( $item ) {
							return WPML_Media_Attachment_By_URL::getUrlRelativePathScaled( $item['url'] );
						},
						$items_to_translate
					)
				)
			)
		);

		$this->primeAttachmentHydrationCaches( $found_ids );
	}

	private function getPrefetchLanguages( $source_language, $items_to_translate ) {
		if ( $source_language ) {
			return [ $source_language ];
		}

		$languages = [];
		foreach ( $items_to_translate as $item ) {
			$language = $this->getLanguageByUrl( $item['url'] );
			if ( $language ) {
				$languages[ $language ] = $language;
			}
		}

		return array_values( $languages );
	}

	private function primeAttachmentHydrationCaches( $attachment_ids ) {
		global $wpml_post_translations;

		$attachment_ids = array_values( array_unique( array_map( 'intval', $attachment_ids ) ) );

		if ( ! $attachment_ids || ! $wpml_post_translations instanceof WPML_Element_Translation ) {
			return;
		}

		$wpml_post_translations->prefetch_ids( $attachment_ids );

		$trids   = [];
		$all_ids = $attachment_ids;
		foreach ( $attachment_ids as $attachment_id ) {
			$trids[] = $wpml_post_translations->get_element_trid( $attachment_id );
			foreach ( (array) $wpml_post_translations->get_element_translations( $attachment_id ) as $translation ) {
				if ( isset( $translation->element_id ) && $translation->element_id ) {
					$all_ids[] = (int) $translation->element_id;
				}
			}
		}

		$translations = new WPML_Translations( $this->sitepress );
		$translations->prime_cache_for_trids( array_filter( $trids ), 'post_attachment' );

		_prime_post_caches( array_values( array_unique( $all_ids ) ), false, true );
	}

	public function get_translated_image( $attachment_id, $language = null, $size = null ) {
		if ( ! $language ) {
			$language = $this->sitepress->get_current_language();
		}

		$image_url              = '';
		$attachment             = WPML_Media_Element_Translation_Factory::create( $attachment_id );
		$attachment_translation = $attachment->get_translation( $language );

		if ( $attachment_translation ) {
			$uploads_dir            = wp_get_upload_dir();
			$uploads_dir['baseurl'] = $this->get_uploads_baseurl( $uploads_dir['baseurl'], $language );
			$attachment_id          = $attachment_translation->get_id();
			if ( null === $size ) {
				$image_url = $uploads_dir['baseurl'] . '/' . get_post_meta( $attachment_id, '_wp_attached_file', true );
			} else {
				$image_url = $this->get_sized_image_url( $attachment_id, $size, $uploads_dir );
			}
		}

		return $image_url;
	}

	private function get_uploads_baseurl( $baseurl, $language ) {
		if (
			! $language
			|| WPML_LANGUAGE_NEGOTIATION_TYPE_DOMAIN !== (int) $this->sitepress->get_setting( 'language_negotiation_type' )
		) {
			return $baseurl;
		}

		$converted = $this->sitepress->convert_url( $baseurl, $language );

		return '/' === substr( $baseurl, -1 ) ? trailingslashit( $converted ) : untrailingslashit( $converted );
	}

	public function get_translated_image_by_url( $img_src, $source_language, $target_language ) {

		$attachment_id = $this->get_attachment_id_by_url( $img_src, $source_language );

		if ( $attachment_id ) {
			$size = $this->get_image_size_from_url( $img_src, $attachment_id );
			try {
				$img_src = $this->get_translated_image( $attachment_id, $target_language, $size );
			} catch ( Exception $e ) {
				$img_src = false;
			}
		} else {
			$img_src = false;
		}

		return $img_src;
	}

	public function get_attachment_id_by_url( $img_src, $source_language = null ) {
		$img_src = WPML_Media_Attachment_By_URL::normalizeUrl( $img_src );

		if ( ! is_string( $img_src ) || '' === $img_src ) {
			return 0;
		}

		if ( ! $source_language ) {
			$source_language = $this->getLanguageByUrl( $img_src ) ?: $this->sitepress->get_current_language();
		}

		$attachment_by_url = $this->attachment_by_url_factory->create( $img_src, $source_language, $this->media_attachment_by_url_query );

		return (int) $attachment_by_url->get_id();
	}

	private function getLanguageByUrl( $url ) {
		$image_url = is_string( $url ) ? WPML_Media_Attachment_By_URL::getUrl( $url ) : '';

		if ( ! is_string( $image_url ) || '' === $image_url ) {
			return null;
		}

		$cache    = new WPML_WP_Cache( self::URLS_TO_IDS_CACHE_KEY );
		$image_id = $cache->get( $image_url );
		if ( ! $image_id ) {
			$image_id = attachment_url_to_postid( $image_url );
			if ( ! $image_id && $image_url !== $url ) {
				$image_id = $this->getBigImageAttachmentId( $image_url );
			}
			$cache->set( $image_url, $image_id, HOUR_IN_SECONDS );
		}

		return $this->sitepress->get_language_for_element( $image_id, 'post_attachment' );
	}

	private function getBigImageAttachmentId( $image_url ) {
		foreach ( [ '-scaled', '-rotated' ] as $suffix ) {
			$image_id = attachment_url_to_postid( preg_replace( '/(\.[a-z]{3,4})$/i', $suffix . '$1', $image_url ) );
			if ( $image_id ) {
				return $image_id;
			}
		}

		return 0;
	}

	private function get_image_size_from_url( $url, $attachment_id ) {
		$media_sizes = new WPML_Media_Sizes();

		return $media_sizes->get_image_size_from_url( $url, $attachment_id );
	}

	private function get_sized_image_url( $attachment_id, $size, $uploads_dir ) {
		$image_url       = '';
		$meta_data       = wp_get_attachment_metadata( $attachment_id );
		$image_url_parts = array( $uploads_dir['baseurl'] );

		if ( is_array( $meta_data ) && array_key_exists( 'file', $meta_data ) ) {
			$file_subdirectory       = $meta_data['file'];
			$file_subdirectory_parts = explode( '/', $file_subdirectory );

			$filename = array_pop( $file_subdirectory_parts );

			if ( ! empty( $file_subdirectory_parts ) ) {
				$image_url_parts[] = implode( '/', $file_subdirectory_parts );
			}

			if ( array_key_exists( $size, $meta_data['sizes'] ) ) {
				$image_url_parts[] = $meta_data['sizes'][ $size ]['file'];
			} else {
				$image_url_parts[] = $filename;
			}

			$image_url = implode( '/', $image_url_parts );
		}

		return $image_url;
	}
}
