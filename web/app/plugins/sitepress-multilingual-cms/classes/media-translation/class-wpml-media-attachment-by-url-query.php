<?php
namespace WPML\Media\Classes;

use WPML\FP\Obj;
use WPML\FP\StrNative;
use WPML\Media\Lookup\MediaLookupService;
use WPML\Media\Lookup\MediaLookupServiceFactory;
use WPML\Media\Lookup\MediaLookupTable;

class WPML_Media_Attachment_By_URL_Query {
	private $wpdb;

	private $was_last_fetch_from_cache = false;

	private $lookup_service = false;

	public function __construct( \wpdb $wpdb ) {
		$this->wpdb = $wpdb;
	}

	private function lookupService() {
		if ( false === $this->lookup_service ) {
			$this->lookup_service = MediaLookupServiceFactory::service();
		}

		return $this->lookup_service;
	}

	public function setLookupService( $service ) {
		$this->lookup_service = $service;
	}

	public function getWasLastFetchFromCache() {
		return $this->was_last_fetch_from_cache;
	}

	private function filterItems( $source_items ) {
		return array_values( array_filter( array_unique( $source_items ) ) );
	}

	private function populateNotFoundItemsInCache( $language, $items, $cache_prop = 'id_from_guid_cache' ) {
		foreach ( $items as $item ) {
			$index = md5( $language . $item );
			if ( WPML_Media_Attachments_Query_Cache::hasCacheItem( $cache_prop, $index ) ) {
				continue;
			}
			WPML_Media_Attachments_Query_Cache::setCacheItem( $cache_prop, $index, null );
		}
	}

	public function prefetchAllIdsFromGuids( $languages, $urls ) {
		$urls = $this->filterItems( $urls );

		$cache_languages = apply_filters( 'wpml_prefetch_languages_for_mt_attachments', $languages );

		if ( ! empty( $cache_languages ) ) {
			$languages = $cache_languages;
		}

		list( $urls_with_ext, $urls_without_ext ) = $this->partItemsWithExtAndWithout( $urls );
		$urls                                     = $urls_with_ext;

		foreach ( $languages as $language ) {
			$urls = array_filter(
				$urls, function( $url ) use ( $language ) {
					$index = md5( $language . $url );
					return ! WPML_Media_Attachments_Query_Cache::hasCacheItem( 'id_from_guid_cache', $index );
				}
			);
			$this->populateNotFoundItemsInCache( $language, $urls_without_ext, 'id_from_guid_cache' );
		}

		$found     = [];
		$undecided = $this->decideFromLookupTable( $languages, $urls, 'id_from_guid_cache', MediaLookupTable::VARIANT_GUID, $found );
		$urls      = $this->itemsWithUndecidedPairs( $urls, $undecided );

		if ( 0 === count( $urls ) ) {
			return array_values( array_unique( $found ) );
		}

		$sql = "SELECT p.ID AS post_id, p.guid, t.language_code
        FROM {$this->wpdb->posts} p
        JOIN {$this->wpdb->prefix}icl_translations t ON t.element_id = p.ID
        WHERE t.element_type = %s
        AND t.language_code IN (" . wpml_prepare_in( $languages ) . ')
        AND p.guid IN (' . wpml_prepare_in( $urls ) . ')';

		$results = $this->wpdb->get_results( $this->wpdb->prepare( $sql, 'post_attachment' ), ARRAY_A );
		foreach ( $results as $result ) {
			$index   = md5( $result['language_code'] . $result['guid'] );
			$found[] = (int) $result['post_id'];
			WPML_Media_Attachments_Query_Cache::setCacheItem( 'id_from_guid_cache', $index, $result );
		}

		foreach ( $languages as $language ) {
			$this->populateNotFoundItemsInCache( $language, $urls, 'id_from_guid_cache' );
		}

		$this->recordScanOutcomes( $undecided, 'id_from_guid_cache', MediaLookupTable::VARIANT_GUID );

		return array_values( array_unique( $found ) );
	}

	private function decideFromLookupTable( $languages, $items, $cache_prop, $variant, &$found = [] ) {
		$pairs = [];
		foreach ( $languages as $language ) {
			foreach ( $items as $item ) {
				if ( ! WPML_Media_Attachments_Query_Cache::hasCacheItem( $cache_prop, md5( $language . $item ) ) ) {
					$pairs[] = [
						'language' => $language,
						'value'    => $item,
						'variant'  => $variant,
					];
				}
			}
		}

		$service = $this->lookupService();
		if ( ! $service || ! $pairs ) {
			return $pairs;
		}

		$decisions = $service->decideMany( $pairs );
		$undecided = [];

		foreach ( $pairs as $key => $pair ) {
			$decision = $decisions[ $key ];
			$index    = md5( $pair['language'] . $pair['value'] );

			if ( MediaLookupService::STATUS_FOUND === $decision['status'] ) {
				$found[] = (int) $decision['id'];
				WPML_Media_Attachments_Query_Cache::setCacheItem( $cache_prop, $index, [ 'post_id' => $decision['id'] ] );
			} elseif ( MediaLookupService::STATUS_NOT_FOUND === $decision['status'] ) {
				WPML_Media_Attachments_Query_Cache::setCacheItem( $cache_prop, $index, null );
			} else {
				$undecided[] = $pair;
			}
		}

		return $undecided;
	}

	private function itemsWithUndecidedPairs( $items, $undecided_pairs ) {
		if ( ! $this->lookupService() ) {
			return $items;
		}

		$keep = [];
		foreach ( $undecided_pairs as $pair ) {
			$keep[ $pair['value'] ] = true;
		}

		return array_values( array_filter( $items, function( $item ) use ( $keep ) {
			return isset( $keep[ $item ] );
		} ) );
	}

	private function recordScanOutcomes( $undecided_pairs, $cache_prop, $variant ) {
		$service = $this->lookupService();
		if ( ! $service || ! $undecided_pairs ) {
			return;
		}

		$results = [];
		foreach ( $undecided_pairs as $pair ) {
			$cache_item = WPML_Media_Attachments_Query_Cache::getCacheItem( $cache_prop, md5( $pair['language'] . $pair['value'] ) );

			$results[] = [
				'language' => $pair['language'],
				'value'    => $pair['value'],
				'variant'  => $variant,
				'id'       => $cache_item ? (int) $cache_item['post_id'] : 0,
			];
		}

		$service->recordMany( $results );
	}

	public function getIdFromGuid( $language, $url ) {
		$wpdb = $this->wpdb;

		$this->was_last_fetch_from_cache = false;
		$index                           = md5( $language . $url );

		if ( WPML_Media_Attachments_Query_Cache::hasCacheItem( 'id_from_guid_cache', $index ) ) {
			$this->was_last_fetch_from_cache = true;
			$cache_item                      = WPML_Media_Attachments_Query_Cache::getCacheItem( 'id_from_guid_cache', $index );
			return $cache_item ? $cache_item['post_id'] : null;
		}

		$decided = $this->decideSingleFromLookupTable( $language, $url, 'id_from_guid_cache', MediaLookupTable::VARIANT_GUID );
		if ( null !== $decided ) {
			return $decided['id'];
		}

		$sql = $this->wpdb->prepare(
			"SELECT ID FROM {$wpdb->posts} p
			JOIN {$wpdb->prefix}icl_translations t ON t.element_id = p.ID
			WHERE t.element_type = %s AND t.language_code = %s AND p.guid = %s",
			'post_attachment',
			$language,
			$url
		);

		$attachment_id = $this->wpdb->get_var( $sql );

		$cache_item = $attachment_id ? [ 'post_id' => $attachment_id ] : null;
		WPML_Media_Attachments_Query_Cache::setCacheItem( 'id_from_guid_cache', $index, $cache_item );

		$this->recordSingleScanOutcome( $language, $url, MediaLookupTable::VARIANT_GUID, $attachment_id );

		return $attachment_id;
	}

	private function decideSingleFromLookupTable( $language, $value, $cache_prop, $variant ) {
		$service = $this->lookupService();
		if ( ! $service ) {
			return null;
		}

		$decisions = $service->decideMany( [ [ 'language' => $language, 'value' => $value, 'variant' => $variant ] ] );
		$decision  = $decisions[0];
		$index     = md5( $language . $value );

		if ( MediaLookupService::STATUS_FOUND === $decision['status'] ) {
			WPML_Media_Attachments_Query_Cache::setCacheItem( $cache_prop, $index, [ 'post_id' => $decision['id'] ] );

			return [ 'id' => $decision['id'] ];
		}

		if ( MediaLookupService::STATUS_NOT_FOUND === $decision['status'] ) {
			WPML_Media_Attachments_Query_Cache::setCacheItem( $cache_prop, $index, null );

			return [ 'id' => null ];
		}

		return null;
	}

	private function recordSingleScanOutcome( $language, $value, $variant, $attachment_id ) {
		$service = $this->lookupService();
		if ( ! $service ) {
			return;
		}

		$service->recordMany( [
			[
				'language' => $language,
				'value'    => $value,
				'variant'  => $variant,
				'id'       => (int) $attachment_id,
			],
		] );
	}

	public function prefetchAllIdsFromMetas( $languages, $paths ) {
		$paths = $this->filterItems( $paths );

		$cache_languages = apply_filters( 'wpml_prefetch_languages_for_mt_attachments', $languages );

		if ( ! empty( $cache_languages ) ) {
			$languages = $cache_languages;
		}

		list( $paths_with_ext, $paths_without_ext ) = $this->partItemsWithExtAndWithout( $paths );
		$paths                                      = $paths_with_ext;

		foreach ( $languages as $language ) {
			$paths = array_filter(
				$paths, function( $path ) use ( $language ) {
					$index = md5( $language . $path );
					return ! WPML_Media_Attachments_Query_Cache::hasCacheItem( 'id_from_meta_cache', $index );
				}
			);
			$this->populateNotFoundItemsInCache( $language, $paths_without_ext, 'id_from_meta_cache' );
		}

		$found     = [];
		$undecided = $this->decideFromLookupTable( $languages, $paths, 'id_from_meta_cache', MediaLookupTable::VARIANT_ATTACHED_FILE, $found );
		$paths     = $this->itemsWithUndecidedPairs( $paths, $undecided );

		if ( 0 === count( $paths ) ) {
			return array_values( array_unique( $found ) );
		}

		$sql = "SELECT p.post_id, t.language_code, p.meta_value
            FROM {$this->wpdb->postmeta} p
            JOIN {$this->wpdb->prefix}icl_translations t ON t.element_id = p.post_id
            WHERE p.meta_key = %s
            AND t.element_type = %s
            AND t.language_code IN (" . wpml_prepare_in( $languages ) . ')
            AND p.meta_value IN (' . wpml_prepare_in( $paths ) . ')';

		$results = $this->wpdb->get_results( $this->wpdb->prepare( $sql, '_wp_attached_file', 'post_attachment' ), ARRAY_A );
		foreach ( $results as $result ) {
			$index   = md5( $result['language_code'] . $result['meta_value'] );
			$found[] = (int) $result['post_id'];
			WPML_Media_Attachments_Query_Cache::setCacheItem( 'id_from_meta_cache', $index, $result );
		}

		foreach ( $languages as $language ) {
			$this->populateNotFoundItemsInCache( $language, $paths, 'id_from_meta_cache' );
		}

		$this->recordScanOutcomes( $undecided, 'id_from_meta_cache', MediaLookupTable::VARIANT_ATTACHED_FILE );

		return array_values( array_unique( $found ) );
	}

	public function getIdFromMeta( $relative_path, $language ) {
		$wpdb = $this->wpdb;

		$this->was_last_fetch_from_cache = false;
		$index                           = md5( $language . $relative_path );

		if ( WPML_Media_Attachments_Query_Cache::hasCacheItem( 'id_from_meta_cache', $index ) ) {
			$this->was_last_fetch_from_cache = true;
			$cache_item                      = WPML_Media_Attachments_Query_Cache::getCacheItem( 'id_from_meta_cache', $index );
			return $cache_item ? $cache_item['post_id'] : null;
		}

		$decided = $this->decideSingleFromLookupTable( $language, $relative_path, 'id_from_meta_cache', MediaLookupTable::VARIANT_ATTACHED_FILE );
		if ( null !== $decided ) {
			return $decided['id'];
		}

		$sql = $this->wpdb->prepare(
			"SELECT post_id
			FROM {$wpdb->postmeta} p
			JOIN {$wpdb->prefix}icl_translations t ON t.element_id = p.post_id
			WHERE p.meta_key = %s
			AND p.meta_value = %s
			AND t.element_type = 'post_attachment'
			AND t.language_code = %s",
			'_wp_attached_file',
			$relative_path,
			$language
		);

		$attachment_id = $this->wpdb->get_var( $sql );

		$cache_item = $attachment_id ? [ 'post_id' => $attachment_id ] : null;
		WPML_Media_Attachments_Query_Cache::setCacheItem( 'id_from_meta_cache', $index, $cache_item );

		$this->recordSingleScanOutcome( $language, $relative_path, MediaLookupTable::VARIANT_ATTACHED_FILE, $attachment_id );

		return $attachment_id;
	}

	private function getAllowedExtensionsForFilename() {
		$extensions = [
			'jpg', 'jpeg', 'jpe', 'gif', 'png', 'bmp', 'tiff', 'tif', 'webp', 'ico', 'heic',
			'asf', 'asx', 'wmv', 'wmx', 'wm', 'avi', 'divx', 'flv', 'mov', 'qt', 'mpeg', 'mpg',
			'mpe', 'mp4', 'm4v', 'ogv', 'webm', 'mkv', '3gp', '3gpp', '3g2', '3gp2', 'txt', 'asc',
			'srt', 'csv', 'tsv', 'ics', 'rtx', 'vtt', 'dfxp',
			'mp3', 'm4a', 'm4b', 'aac', 'ra', 'ram', 'wav', 'ogg', 'oga', 'flac', 'mid', 'midi', 'wma',
			'wax', 'mka', 'rtf', 'pdf', 'swf', 'tar', 'zip', 'gz', 'gzip', 'rar', '7z',
			'exe', 'psd', 'xcf', 'doc', 'pot', 'pps', 'ppt', 'wri', 'xla', 'xls', 'xlt', 'xlw', 'mdb', 'mpp',
			'docx', 'docm', 'dotx', 'dotm', 'xlsx', 'xlsm', 'xlsb', 'xltx', 'xltm', 'xlam',
			'pptx', 'pptm', 'ppsx', 'ppsm', 'potx', 'potm', 'ppam', 'sldx', 'sldm',
			'onetoc', 'onetoc2', 'onetmp', 'onepkg', 'oxps', 'xps', 'odt', 'odp', 'ods', 'odg', 'odc', 'odb', 'odf',
			'wp', 'wpd', 'key', 'numbers', 'pages', 'svg', 'avif', 'apng',
		];

		return apply_filters( 'wpml_media_allowed_filename_extensions', $extensions );
	}

	private function hasAllowedExtension( $ext ) {
		$exts = $this->getAllowedExtensionsForFilename();
		return in_array( $ext, $exts );
	}

	private function partItemsWithExtAndWithout( $source_items ) {
		$with_ext    = [];
		$without_ext = [];

		foreach ( $source_items as $source_item ) {
			$url_parts = wpml_parse_url( $source_item );
			if ( isset( $url_parts['host'] ) && is_string( $url_parts['host'] ) ) {
				$domain            = $url_parts['host'];
				$domain_with_slash = $url_parts['host'] . '/';

				if ( StrNative::endsWith( $domain, $source_item ) || StrNative::endsWith( $domain_with_slash, $source_item ) ) {
					$without_ext[] = $source_item;
					continue;
				}
			}

			$maybe_ext = pathinfo( $source_item, PATHINFO_EXTENSION );

			if ( is_string( $maybe_ext ) && StrNative::len( $maybe_ext ) > 0 ) {
				if ( $this->hasAllowedExtension( $maybe_ext ) ) {
					$with_ext[] = $source_item;
				} else {
					$without_ext[] = $source_item;
				}
			} else {
				$without_ext[] = $source_item;
			}
		}

		return [ $with_ext, $without_ext ];
	}
}
