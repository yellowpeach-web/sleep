<?php

namespace WPML\TM\XLIFF;

use WPML\FP\Obj;
use WPML\TM\OwnedPackages\OwnerJobService;
use WPML\TM\Taxonomy\TranslatableTermMeta;

class TaxonomyTermXliffBuilder {

	public function build( \WP_Term $term, $sourceLang, $targetLang, array $options = [] ) {
		$forTp = ! empty( $options['for_translation_proxy'] );

		$fileAttributes = [
			'original'        => 'term-' . $term->term_id,
			'source-language' => $sourceLang,
			'target-language' => $targetLang,
			'datatype'        => 'plaintext',
		];

		$bodyGroups = [
			[
				'id'    => 'term-' . $term->term_id,
				'units' => $this->unitsFor( $term, $options ),
			],
		];

		$xliff = new \WPML_TM_XLIFF( '1.2' );
		$xliff->setFileAttributes( $fileAttributes );
		if ( $forTp ) {
			$xliff->setReferences( [ 'external-file' => $this->termLink( $term ) ] );
			$xliff->setPhaseGroup( ( new \WPML_TM_XLIFF_Post_Type( 'tax_' . $term->taxonomy ) )->get() );
		}
		$xliff->setBodyGroups( $bodyGroups );

		return $xliff->toString();
	}

	public function unitsFor( \WP_Term $term, array $options = [] ) {
		$note = ! empty( $options['for_translation_proxy'] )
			? $this->parentNote( $term )
			: $this->buildContextNote( $this->buildAncestorNames( $term ), $term );

		return $this->buildUnits( $term, $note, $options );
	}

	public function termLink( \WP_Term $term ): string {
		$link = get_term_link( $term );

		return is_string( $link ) && '' !== $link
			? $link
			: admin_url( 'edit-tags.php?taxonomy=' . $term->taxonomy . '&tag_ID=' . $term->term_id );
	}

	public function parentNote( \WP_Term $term ): string {
		return implode( ' > ', $this->buildAncestorNames( $term ) );
	}

	public function sourceChecksum( \WP_Term $term ): string {
		$meta = TranslatableTermMeta::values( $term );
		ksort( $meta, SORT_STRING );

		$parts = [ (string) $term->name, (string) $term->slug, (string) $term->description ];
		foreach ( $meta as $metaKey => $value ) {
			$parts[] = (string) $metaKey;
			$parts[] = (string) $value;
		}
		foreach ( (array) OwnerJobService::ownedStrings( 'term', (int) $term->term_taxonomy_id ) as $owned ) {
			$parts[] = (string) $owned[1]->value;
		}

		return md5( implode( "\0", $parts ) );
	}

	private function buildContextNote( array $ancestors, \WP_Term $term ) {
		$taxonomy = [];
		foreach ( $ancestors as $name ) {
			$taxonomy[] = [ 'name' => (string) $name ];
		}

		$leaf = [ 'name' => (string) $term->name ];
		if ( ! empty( $term->description ) ) {
			$leaf['description'] = (string) $term->description;
		}
		$taxonomy[] = $leaf;

		return (string) wp_json_encode(
			[ 'taxonomy' => $taxonomy ],
			JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
		);
	}

	private function buildAncestorNames( \WP_Term $term ) {
		if ( ! $term->parent ) {
			return [];
		}

		$chain    = [];
		$parentId = $term->parent;

		$disable = '__return_true';
		add_filter( 'wpml_disable_term_adjust_id', $disable, 999 );
		try {
			while ( $parentId ) {
				$parent = get_term( $parentId, $term->taxonomy );
				if ( ! $parent || is_wp_error( $parent ) ) {
					break;
				}
				array_unshift( $chain, $parent->name );
				$parentId = $parent->parent;
			}
		} finally {
			remove_filter( 'wpml_disable_term_adjust_id', $disable, 999 );
		}

		return $chain;
	}

	private function buildUnits( \WP_Term $term, $note, array $options ) {
		$units = [
			[
				'id'     => 'term-name',
				'source' => $term->name,
				'note'   => $note,
			],
		];

		if ( ! empty( $options['translate_slug'] ) ) {
			$units[] = [
				'id'     => 'term-slug',
				'source' => $term->slug,
				'note'   => $note,
			];
		}

		if ( ! empty( $term->description ) ) {
			$units[] = [
				'id'     => 'term-description',
				'source' => $term->description,
				'note'   => $note,
			];
		}

		$termMetaValues  = TranslatableTermMeta::values( $term );
		$fieldsByMetaKey = $this->buildTermMetaFieldsByMetaKey( $term, array_keys( $termMetaValues ) );
		foreach ( $termMetaValues as $metaKey => $value ) {
			$units[] = [
				'id'        => TranslatableTermMeta::UNIT_ID_PREFIX . $metaKey,
				'source'    => $value,
				'note'      => $note,
				'extradata' => \WPML_TM_Xliff_Writer::buildFieldExtradata( $fieldsByMetaKey[ $metaKey ], true ),
			];
		}

		foreach ( (array) OwnerJobService::ownedStrings( 'term', (int) $term->term_taxonomy_id ) as $owned ) {
			list( $package, $string ) = $owned;
			$slug  = \WPML_TM_Page_Builders_Field_Wrapper::generate_field_slug( $package->ID, $string->id );
			$title = apply_filters( 'wpml_string_title_from_id', false, $string->id );
			$title = ! empty( $title ) ? $title : ( $string->title ?? '' );
			$extraData = \WPML_TM_Xliff_Writer::buildFieldExtradata(
				[
					'field_type' => $slug,
					'title'      => $title,
					'group'      => [ 'package-' . $package->ID => $package->title ],
				],
				true
			);
			$extraData['is_xliff_for_ate'] = true;
			$patterns = apply_filters( 'wpml_tm_xliff_unit_placeholders', [], (object) [ 'field_type' => $slug ], $extraData );
			$units[] = [
				'id'        => $slug,
				'source'    => DeclaredPlaceholders::encode( (string) $string->value, array_values( array_unique( array_filter( (array) $patterns, 'is_string' ) ) ) ),
				'note'      => $note,
				'extradata' => $extraData,
			];
		}

		return $units;
	}

	private function buildTermMetaFieldsByMetaKey( \WP_Term $term, array $metaKeys ) {
		$defaultFields = [];
		foreach ( $metaKeys as $metaKey ) {
			$defaultFields[] = [
				'field_type' => TranslatableTermMeta::UNIT_ID_PREFIX . $metaKey,
				'meta_key'   => $metaKey,
				'title'      => $metaKey,
			];
		}

		$adjustedFields = apply_filters( 'wpml_tm_adjust_term_translation_fields', $defaultFields, $term );

		$fieldsByMetaKey = array_column( $defaultFields, null, 'meta_key' );
		foreach ( is_array( $adjustedFields ) ? $adjustedFields : [] as $adjustedField ) {
			$metaKey = is_array( $adjustedField ) ? Obj::prop( 'meta_key', $adjustedField ) : null;
			if ( is_string( $metaKey ) && isset( $fieldsByMetaKey[ $metaKey ] ) ) {
				$fieldsByMetaKey[ $metaKey ] = $adjustedField;
			}
		}

		return $fieldsByMetaKey;
	}
}
