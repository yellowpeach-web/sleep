<?php

namespace ACFML\Field;

use WPML\FP\Fns;
use WPML\LIB\WP\Hooks;

class LinkUrlLoadHooks implements \IWPML_Backend_Action, \IWPML_Frontend_Action, \IWPML_DIC_Action {

	private $fieldResolver;

	private $cache = [];

	private $storedRows = [];

	private $renderedPosts = [];

	public function __construct( Resolver $fieldResolver ) {
		$this->fieldResolver = $fieldResolver;
	}

	public function add_hooks() {
		add_filter( 'acf/load_value/type=url', [ $this, 'convertUrlField' ], 10, 3 );
		add_filter( 'acf/load_value/type=link', [ $this, 'convertLinkField' ], 10, 3 );
		add_filter( 'acf/update_value/type=url', [ $this, 'keepStoredUrlField' ], 5, 3 );
		add_filter( 'acf/update_value/type=link', [ $this, 'keepStoredLinkField' ], 5, 3 );

		foreach ( [ 'repeater', 'flexible_content' ] as $type ) {
			add_filter( 'acf/update_value/type=' . $type, [ $this, 'readStoredRows' ], 5, 3 );
			add_filter( 'acf/update_value/type=' . $type, [ $this, 'forgetStoredRows' ], 15, 3 );
		}

		add_filter( 'the_content', [ $this, 'enterPostContent' ], -PHP_INT_MAX );
		add_filter( 'the_content', [ $this, 'leavePostContent' ], PHP_INT_MAX );
	}

	public function keepStoredUrlField( $value, $postId, $field ) {
		if ( ! is_string( $value ) || $this->isBlock( $postId ) || $this->keepsStoredValue( $postId ) ) {
			return $value;
		}

		$stored = $this->findStoredValue( wp_unslash( $value ), 'url', $postId, $field );

		return is_string( $stored ) ? wp_slash( $stored ) : $value;
	}

	public function keepStoredLinkField( $value, $postId, $field ) {
		if (
			! is_array( $value )
			|| ! isset( $value['url'] )
			|| ! is_string( $value['url'] )
			|| $this->isBlock( $postId )
			|| $this->keepsStoredValue( $postId )
		) {
			return $value;
		}

		$submitted = wp_unslash( $value );
		$stored    = $this->findStoredValue( $submitted['url'], 'link', $postId, $field );
		if ( ! is_array( $stored ) ) {
			return $value;
		}

		$value['url'] = wp_slash( $stored['url'] );
		$converted    = $this->convertLinkField( $stored, $postId, $field );
		if (
			isset( $submitted['title'], $stored['title'], $converted['title'] )
			&& is_string( $submitted['title'] )
			&& $this->sanitize( $submitted['title'] ) === $this->sanitize( $converted['title'] )
		) {
			$value['title'] = wp_slash( $stored['title'] );
		}

		return $value;
	}

	private function findStoredValue( $submitted, $type, $postId, $field ) {
		$submitted = $this->sanitize( $submitted );
		$name      = isset( $field['name'] ) ? (string) $field['name'] : '';
		$rows      = $this->getStoredRows( $postId, $name );

		$own = isset( $rows['values'][ $name ] ) ? $rows['values'][ $name ]['value'] : $this->getStoredValue( $postId, $field );
		if ( in_array( $submitted, $this->getShownUrls( $type, $own, $postId, $field ), true ) ) {
			return $own;
		}

		if ( ! $rows || empty( $field['key'] ) ) {
			return null;
		}

		$nearest = $this->getNearestRow( $name, $this->getRowsShowing( $submitted, $type, $postId, $field ) );

		return null !== $nearest ? $rows['values'][ $nearest ]['value'] : null;
	}

	private function getShownUrls( $type, $stored, $postId, $field ) {
		if ( 'link' === $type ) {
			if ( ! is_array( $stored ) || ! isset( $stored['url'] ) || ! is_string( $stored['url'] ) ) {
				return [];
			}

			$converted = $this->convertLinkField( $stored, $postId, $field );
			$shown     = [ $stored['url'], is_array( $converted ) && isset( $converted['url'] ) ? $converted['url'] : null ];
		} else {
			$shown = is_string( $stored ) ? [ $stored, $this->convertUrlField( $stored, $postId, $field ) ] : [];
		}

		$urls = [];
		foreach ( $shown as $url ) {
			if ( is_string( $url ) ) {
				$urls[] = $this->sanitize( $url );
			}
		}

		return array_values( array_unique( $urls ) );
	}

	private function sanitize( $value ) {
		return wp_kses_post( $value );
	}

	private function getRowsShowing( $submitted, $type, $postId, $field ) {
		$key = $this->getStoredRowsKey( $postId );
		if ( ! isset( $this->storedRows[ $key ]['shown'][ $field['key'] ] ) ) {
			$shown = [];
			foreach ( $this->storedRows[ $key ]['values'] as $name => $stored ) {
				if ( $stored['key'] === $field['key'] ) {
					foreach ( $this->getShownUrls( $type, $stored['value'], $postId, $field ) as $url ) {
						$shown[ $url ][] = (string) $name;
					}
				}
			}

			$this->storedRows[ $key ]['shown'][ $field['key'] ] = $shown;
		}

		return isset( $this->storedRows[ $key ]['shown'][ $field['key'] ][ $submitted ] )
			? $this->storedRows[ $key ]['shown'][ $field['key'] ][ $submitted ]
			: [];
	}

	private function getNearestRow( $name, array $names ) {
		$path          = explode( '_', $name );
		$nearest       = null;
		$nearestShared = -1;
		foreach ( $names as $other ) {
			if ( $other === $name ) {
				continue;
			}

			$otherPath = explode( '_', $other );
			$shared    = 0;
			while ( isset( $path[ $shared ], $otherPath[ $shared ] ) && $path[ $shared ] === $otherPath[ $shared ] ) {
				++$shared;
			}

			if ( $shared > $nearestShared ) {
				$nearest       = $other;
				$nearestShared = $shared;
			}
		}

		return $nearest;
	}

	public function readStoredRows( $value, $postId, $field ) {
		if ( empty( $field['name'] ) || $this->getStoredRows( $postId, (string) $field['name'] ) ) {
			return $value;
		}

		$key = $this->getStoredRowsKey( $postId );
		unset( $this->storedRows[ $key ] );
		if ( ! function_exists( 'acf_get_meta' ) || $this->isBlock( $postId ) || $this->keepsStoredValue( $postId ) ) {
			return $value;
		}

		$meta     = (array) acf_get_meta( $postId );
		$isOption = $this->isOption( $postId );
		$values   = [];
		foreach ( $meta as $name => $stored ) {
			$reference = isset( $meta[ '_' . $name ] ) ? $meta[ '_' . $name ] : null;
			if ( is_string( $reference ) && $this->isInRows( (string) $name, (string) $field['name'] ) ) {
				$values[ $name ] = [
					'key'   => $reference,
					'value' => $isOption ? $this->decodeOptionValue( $stored ) : $stored,
				];
			}
		}

		$this->storedRows[ $key ] = [
			'parent' => (string) $field['name'],
			'values' => $values,
			'shown'  => [],
		];

		return $value;
	}

	private function isOption( $postId ) {
		$decoded = acf_decode_post_id( $postId );

		return 'option' === $decoded['type'];
	}

	private function decodeOptionValue( $value ) {
		return function_exists( 'acf_maybe_unserialize' ) ? acf_maybe_unserialize( $value ) : $value;
	}

	public function forgetStoredRows( $value, $postId, $field ) {
		$key = $this->getStoredRowsKey( $postId );
		if ( isset( $field['name'], $this->storedRows[ $key ] ) && $this->storedRows[ $key ]['parent'] === (string) $field['name'] ) {
			unset( $this->storedRows[ $key ] );
		}

		return $value;
	}

	private function getStoredRows( $postId, $name ) {
		$key = $this->getStoredRowsKey( $postId );

		return isset( $this->storedRows[ $key ] ) && $this->isInRows( $name, $this->storedRows[ $key ]['parent'] )
			? $this->storedRows[ $key ]
			: null;
	}

	private function isInRows( $name, $parentName ) {
		return (bool) preg_match( '/^' . preg_quote( $parentName, '/' ) . '_\d+_/', $name );
	}

	private function getStoredRowsKey( $postId ) {
		return get_current_blog_id() . '|' . $postId;
	}

	private function getStoredValue( $postId, $field ) {
		if ( function_exists( 'acf_get_metadata_by_field' ) ) {
			return acf_get_metadata_by_field( $postId, $field );
		}

		return isset( $field['name'] ) ? acf_get_metadata( $postId, $field['name'] ) : null;
	}

	public function convertUrlField( $value, $postId, $field ) {
		$ownerId = $this->getOwnerId( $postId );
		if ( $this->keepsStoredValue( $ownerId ) ) {
			return $value;
		}

		return $this->translateUrlValue( $value, $this->resolveTargetLang( $ownerId ) );
	}

	public function convertLinkField( $value, $postId, $field ) {
		$ownerId = $this->getOwnerId( $postId );
		if ( $this->keepsStoredValue( $ownerId ) ) {
			return $value;
		}

		if ( is_array( $value ) && isset( $value['url'] ) ) {
			return $this->translateLinkValue( $value, $this->resolveTargetLang( $ownerId ) );
		}
		return $value;
	}

	public function enterPostContent( $content ) {
		$this->renderedPosts[] = get_the_ID();

		return $content;
	}

	public function leavePostContent( $content ) {
		array_pop( $this->renderedPosts );

		return $content;
	}

	private function getOwnerId( $postId ) {
		if ( ! $this->isBlock( $postId ) ) {
			return $postId;
		}

		if ( $this->renderedPosts ) {
			$ownerId = end( $this->renderedPosts );
		} else {
			$ownerId = is_admin() || wpml_is_rest_request() ? get_the_ID() : false;
		}

		return $ownerId ?: null;
	}

	private function isBlock( $postId ) {
		return is_string( $postId ) && 0 === strpos( $postId, 'block_' );
	}

	private function keepsStoredValue( $postId ) {
		$element = $this->getElement( $postId );
		if ( ! $element ) {
			return false;
		}

		$details = apply_filters( 'wpml_element_language_details', null, $element );

		return is_object( $details )
			&& ! empty( $details->language_code )
			&& empty( $details->source_language_code );
	}

	private function getElement( $postId ) {
		if ( is_numeric( $postId ) ) {
			$postType = get_post_type( (int) $postId );

			return $postType
				? [
					'element_id'   => (int) $postId,
					'element_type' => 'post_' . $postType,
				]
				: null;
		}

		$term = is_string( $postId ) ? $this->getTerm( $postId ) : null;

		return $term
			? [
				'element_id'   => (int) $term->term_taxonomy_id,
				'element_type' => 'tax_' . $term->taxonomy,
			]
			: null;
	}

	private function getTerm( $postId ) {
		if ( preg_match( '/^term_(\d+)$/', $postId, $matches ) ) {
			$term = $this->getTermWithoutLanguageAdjustment( (int) $matches[1], '' );
		} elseif ( preg_match( '/^(.+)_(\d+)$/', $postId, $matches ) && taxonomy_exists( $matches[1] ) ) {
			$term = $this->getTermWithoutLanguageAdjustment( (int) $matches[2], $matches[1] );
		} else {
			return null;
		}

		return $term instanceof \WP_Term ? $term : null;
	}

	private function getTermWithoutLanguageAdjustment( int $termId, string $taxonomy ) {
		return Hooks::callWithFilter(
			fn() => get_term( $termId, $taxonomy ),
			'wpml_disable_term_adjust_id',
			Fns::always( true )
		);
	}

	private function resolveTargetLang( $postId ) {
		$element = $this->getElement( $postId );
		$lang    = $element ? apply_filters( 'wpml_element_language_code', null, $element ) : null;

		return $lang ?: apply_filters( 'wpml_current_language', null );
	}

	private function translateUrlValue( $url, $targetLang ) {
		if ( ! is_string( $url ) || '' === $url || ! $targetLang ) {
			return $url;
		}

		$cacheKey = get_current_blog_id() . '|' . $targetLang . '|' . $url;
		if ( ! array_key_exists( $cacheKey, $this->cache ) ) {
			$processedData            = new \WPML_ACF_Processed_Data( $url, $targetLang, [ 'type' => 'url' ] );
			$this->cache[ $cacheKey ] = $this->fieldResolver->run( $processedData )->convert_ids();
		}

		return $this->cache[ $cacheKey ];
	}

	private function translateLinkValue( array $value, $targetLang ) {
		if ( ! is_string( $value['url'] ) || '' === $value['url'] || ! $targetLang ) {
			return $value;
		}

		$cacheKey = get_current_blog_id() . '|' . $targetLang . '|link|' . md5( serialize( $value ) );
		if ( ! array_key_exists( $cacheKey, $this->cache ) ) {
			$processedData            = new \WPML_ACF_Processed_Data( $value, $targetLang, [ 'type' => 'link' ] );
			$this->cache[ $cacheKey ] = $this->fieldResolver->run( $processedData )->convert_ids();
		}

		return $this->cache[ $cacheKey ];
	}
}
