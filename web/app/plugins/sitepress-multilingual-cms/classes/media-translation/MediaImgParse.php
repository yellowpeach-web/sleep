<?php

namespace WPML\MediaTranslation;

use WPML\LIB\WP\Attachment;
use WPML\MediaTranslation\MediaCollector\Collector;

class MediaImgParse {
	private $media = [];
	private $collector;

	private static $misses = [];

	public static function forgetMisses() {
		self::$misses = [];
	}

	public function get_imgs( $text ) {
		if ( $this->can_parse_blocks( $text ) ) {
			$blocks = parse_blocks( $text );
			$this->collect_media_in_blocks( $blocks, $this->media );
			Attachment::addToCache( $this->media );
			$images = $this->get_from_css_background_images_in_blocks( $blocks );
		} else {
			$images = $this->get_from_css_background_images( $text );
		}

		$images = array_merge( $this->get_from_img_tags( $text ), $images );
		return $images;
	}

	public function get_imgs_from_blocks( $text ) {
		if ( ! $this->can_parse_blocks( $text ) ) {
			return array();
		}

		$media             = array();
		$media_srcs_to_ids = array();

		$blocks = parse_blocks( $text );
		$this->collect_media_in_blocks( $blocks, $media_srcs_to_ids );
		Attachment::addToCache( $media_srcs_to_ids );

		foreach ( $media_srcs_to_ids as $media_src => $media_id ) {
			$media[] = array(
				'attributes'    => array(
					'src' => $media_src,
					'alt' => '',
				),
				'attachment_id' => $media_id,
			);
		}

		$media = array_merge(
			$media,
			$this->get_from_css_background_images_in_blocks( $blocks )
		);

		return $media;
	}

	public function collect_media_in_blocks( $blocks, &$mediaCollection = [] ) {
		if ( $this->collector == null ) {
			$file = __DIR__ . '/media-collector/block-definitions/all.php';

			if ( ! file_exists( $file ) ) {
				return $mediaCollection;
			}

			$this->collector = new Collector();
			$this->collector->addCollectorBlocks(
				require __DIR__ . '/media-collector/block-definitions/all.php'
			);
		}

		$this->collector->collectMediaFromBlocks( $blocks, $mediaCollection );

		return $mediaCollection;
	}

	public function get_from_img_tags( $text, $get_attachment_ids_from_urls = true ) {
		$media = wpml_collect( [] );

		$media_elements = [
			'/<img ([^>]+)>/s',
			'/<video ([^>]+)>/s',
			'/<audio ([^>]+)>/s',
		];

		foreach ( $media_elements as $element_expression ) {
			if ( preg_match_all( $element_expression, $text, $matches ) ) {
				$media = $media->merge( $this->getAttachments( $matches, $get_attachment_ids_from_urls  ) );
			}
		}

		return $media->toArray();
	}

	private function getAttachments( $matches, $get_attachment_ids_from_urls = true ) {
		$attachments = [];

		foreach ( $matches[1] as $i => $match ) {
			if ( preg_match_all( '/(\S+)\\s*=\\s*["\']?((?:.(?!["\']?\s+(?:\S+)=|[>"\']))+.)["\']?/', $match, $attribute_matches ) ) {
				$attributes = [];
				foreach ( $attribute_matches[1] as $k => $key ) {
					$attributes[ $key ] = $attribute_matches[2][ $k ];
				}
				if ( isset( $attributes['src'] ) ) {
					$attachments[ $i ]['attributes']    = $attributes;
					$attachments[ $i ]['attachment_id'] = null;
				}
			}
		}

		if ( $get_attachment_ids_from_urls && $attachments ) {
			$srcs = [];
			foreach ( $attachments as $attachment ) {
				$srcs[] = $attachment['attributes']['src'];
			}
			$ids = $this->idsFromUrls( $srcs );

			foreach ( $attachments as $i => $attachment ) {
				$src                                = $attachment['attributes']['src'];
				$attachments[ $i ]['attachment_id'] = is_string( $src ) && isset( $ids[ $src ] ) ? $ids[ $src ] : null;
			}
		}

		return $attachments;
	}

	public function idsFromUrls( array $urls ) {
		$this->primeAttachmentIdCache( $urls );

		$ids = [];
		foreach ( $urls as $url ) {
			if ( ! is_string( $url ) || array_key_exists( $url, $ids ) ) {
				continue;
			}
			$ids[ $url ] = $this->idFromUrl( $url );
		}

		return $ids;
	}

	private function idFromUrl( $url ) {
		$cached = Attachment::idFromUrlCache( $url );
		if ( is_numeric( $cached ) ) {
			return $cached;
		}
		if ( '' === $url || isset( self::$misses[ $url ] ) || self::isDataUri( $url ) ) {
			return null;
		}

		$id = Attachment::idFromUrl( $url );
		if ( ! $id ) {
			self::$misses[ $url ] = true;

			return null;
		}

		return $id;
	}

	private static function isDataUri( $url ) {
		return 0 === strncasecmp( ltrim( $url ), 'data:', 5 );
	}

	private function primeAttachmentIdCache( array $srcs ) {
		if ( ! self::canBatchResolve() ) {
			return;
		}

		$pending    = [];
		$candidates = [];
		foreach ( $srcs as $src ) {
			if (
				! is_string( $src ) || '' === $src || isset( $pending[ $src ] ) || isset( self::$misses[ $src ] )
				|| self::isDataUri( $src ) || null !== Attachment::idFromUrlCache( $src )
			) {
				continue;
			}
			$pending[ $src ]                = $this->srcWithoutSize( $src );
			$candidates[ $src ]             = true;
			$candidates[ $pending[ $src ] ] = true;
		}

		if ( ! $pending ) {
			return;
		}

		$metaMap = Attachment::attachmentUrlsToPostIds( array_keys( $candidates ) );

		$guidTargets = [];
		foreach ( $pending as $src => $srcWithoutSize ) {
			if ( empty( $metaMap[ $src ] ) && empty( $metaMap[ $srcWithoutSize ] ) ) {
				$guidTargets[ $src ]            = true;
				$guidTargets[ $srcWithoutSize ] = true;
			}
		}
		$guidMap = $this->attachmentIdsByGuids( array_keys( $guidTargets ) );

		$primed = [];
		foreach ( $pending as $src => $srcWithoutSize ) {
			$id = self::pickIdWithIdFromUrlPrecedence( $src, $srcWithoutSize, $metaMap, $guidMap );
			if ( ! $id ) {
				$id = $this->idByAttachedFileFilters( $src, $srcWithoutSize );
			}
			if ( $id ) {
				$primed[ $src ]            = $id;
				$primed[ $srcWithoutSize ] = $id;
			} else {
				self::$misses[ $src ] = true;
			}
		}

		if ( $primed ) {
			Attachment::addToCache( $primed );
		}
	}

	private function idByAttachedFileFilters( $src, $srcWithoutSize ) {
		if ( $src !== $srcWithoutSize ) {
			$id = (int) attachment_url_to_postid( $srcWithoutSize );
			if ( $id ) {
				return $id;
			}
		}

		return (int) attachment_url_to_postid( $src );
	}

	public static function pickIdWithIdFromUrlPrecedence( $src, $srcWithoutSize, array $metaMap, array $guidMap ) {
		$meta = function ( $url ) use ( $metaMap ) {
			return empty( $metaMap[ $url ] ) ? null : (int) $metaMap[ $url ];
		};
		$guid = function ( $url ) use ( $guidMap ) {
			$key = strtolower( $url );
			return empty( $guidMap[ $key ] ) ? null : (int) $guidMap[ $key ];
		};

		if ( $src !== $srcWithoutSize && $meta( $srcWithoutSize ) ) {
			return $meta( $srcWithoutSize );
		}
		if ( $meta( $src ) ) {
			return $meta( $src );
		}
		if ( $guid( $src ) ) {
			return $guid( $src );
		}
		if ( $src !== $srcWithoutSize && $guid( $srcWithoutSize ) ) {
			return $guid( $srcWithoutSize );
		}

		return null;
	}

	public static function canBatchResolve() {
		if ( ! function_exists( 'wp_get_upload_dir' ) || ! function_exists( 'wpml_prepare_in' ) ) {
			return false;
		}
		$dir = wp_get_upload_dir();

		return is_array( $dir ) && ! empty( $dir['url'] ) && ! empty( $dir['baseurl'] );
	}

	private function srcWithoutSize( $src ) {
		return Attachment::extractSrcFromAttributes( [ 'attributes' => [ 'src' => $src ] ] );
	}

	private function attachmentIdsByGuids( array $urls ) {
		if ( ! $urls ) {
			return [];
		}

		global $wpdb;
		$map  = [];
		$rows = $wpdb->get_results(
			"SELECT ID, guid FROM {$wpdb->posts} WHERE post_type = 'attachment' AND guid IN (" . wpml_prepare_in( $urls, '%s' ) . ') ORDER BY ID'
		);
		foreach ( $rows as $row ) {
			$key = strtolower( $row->guid );
			if ( ! isset( $map[ $key ] ) ) {
				$map[ $key ] = (int) $row->ID;
			}
		}

		return $map;
	}

	private function get_from_css_background_images( $text ) {
		$images = [];

		if ( preg_match_all( '/<\w+[^>]+style\s*=\s*(["\'])(?:(?!\1).)*?background-image\s*:\s*url\(\s*(?:["\']|&quot;|&apos;|&#0?39;)?(.+?)(?:["\']|&quot;|&apos;|&#0?39;)?\s*\)/', $text, $matches ) ) {
			foreach ( $matches[2] as $src ) {
				$images[] = [
					'attributes'    => [ 'src' => $src ],
					'attachment_id' => null,
				];
			}
		}

		return $images;
	}

	private function get_from_css_background_images_in_blocks( $blocks ) {
		$images = [];

		foreach ( $blocks as $block ) {
			$block = $this->sanitize_block( $block );

			$attribute_background = $this->get_background_from_block_attributes( $block );
			if ( $attribute_background ) {
				$images[] = [
					'attributes'    => [ 'src' => $attribute_background['url'] ],
					'attachment_id' => $attribute_background['id'],
				];
			}

			if ( ! empty( $block->innerBlocks ) ) {
				$inner_images = $this->get_from_css_background_images_in_blocks( $block->innerBlocks );
				$images       = array_merge( $images, $inner_images );
				continue;
			}

			if ( ! isset( $block->innerHTML ) ) {
				continue;
			}

			if ( ! isset( $block->attrs->id ) ) {
				$images = array_merge( $images, $this->get_from_css_background_images( $block->innerHTML ) );
				continue;
			}

			$background_images = $this->get_from_css_background_images( $block->innerHTML );
			$image             = reset( $background_images );

			if ( $image ) {
				$image['attachment_id'] = $block->attrs->id;
				$images[]               = $image;
			}
		}

		return $images;
	}

	private function get_background_from_block_attributes( $block ) {
		if ( ! isset( $block->attrs->style ) ) {
			return null;
		}

		$style = json_decode( (string) json_encode( $block->attrs->style ), true );
		$image = isset( $style['background']['backgroundImage'] ) && is_array( $style['background']['backgroundImage'] )
			? $style['background']['backgroundImage']
			: [];

		if ( ! isset( $image['url'] ) || ! is_string( $image['url'] ) || '' === $image['url'] ) {
			return null;
		}

		return [
			'url' => $image['url'],
			'id'  => isset( $image['id'] ) && is_numeric( $image['id'] ) ? (int) $image['id'] : null,
		];
	}

	private function sanitize_block( $block ) {
		$block = (object) $block;

		if ( isset( $block->attrs ) && ! is_object( $block->attrs ) ) {
			$block->attrs = (object) $block->attrs;
		}

		return $block;
	}

	function can_parse_blocks( $string ) {
		return false !== strpos( $string, '<!-- wp:' ) && function_exists( 'parse_blocks' );
	}
}
