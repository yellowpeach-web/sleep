<?php

namespace WPML\ContentDeletion;

class Rows {

	const COVERED = [
		'post_post',
		'post_page',
		'post_attachment',
		'post_product',
		'tax_category',
		'tax_post_tag',
		'tax_nav_menu',
	];

	public static function all() {
		$rows = [];

		$rows[] = self::row(
			'posts-and-pages',
			__( 'Pages and posts', 'sitepress' ),
			[ Settings::postKey( 'post' ), Settings::postKey( 'page' ) ]
		);

		if ( self::postTypeExists( 'product' ) ) {
			$rows[] = self::row(
				'products',
				/* translators: Name of a content type in the table of the dialog shown before content is deleted. Plural. */
				self::postTypeLabel( 'product', __( 'Products', 'sitepress' ) ),
				[ Settings::postKey( 'product' ) ]
			);
		}

		$rows[] = self::row(
			'images',
			/* translators: Name of a content type in the table of the dialog shown before content is deleted. Plural. */
			__( 'Images', 'sitepress' ),
			[ Settings::postKey( 'attachment' ) ],
			false,
			__( 'Image files on disk are kept when other copies share them.', 'sitepress' )
		);

		$rows[] = self::row(
			'categories-and-tags',
			__( 'Categories and tags', 'sitepress' ),
			[ Settings::termKey( 'category' ), Settings::termKey( 'post_tag' ) ]
		);

		$rows[] = self::row(
			'menus',
			/* translators: Name of a kind of term in the table of the dialog shown before content is deleted. Plural. */
			__( 'Menus', 'sitepress' ),
			[ Settings::termKey( 'nav_menu' ) ]
		);

		foreach ( self::customPostTypes() as $type => $label ) {
			$rows[] = self::row( 'post-' . $type, $label, [ Settings::postKey( $type ) ] );
		}

		foreach ( self::customTaxonomies() as $taxonomy => $label ) {
			$rows[] = self::row( 'tax-' . $taxonomy, $label, [ Settings::termKey( $taxonomy ) ] );
		}

		$templates = self::templateMembers();
		if ( $templates ) {
			$rows[] = self::row(
				'templates',
				/* translators: Name of a content type in the table of the dialog shown before content is deleted: the page layouts of the site. Plural. */
				__( 'Templates', 'sitepress' ),
				$templates,
				true,
				__( 'WordPress keeps a block theme\'s templates in step across languages, so deleting one always deletes it in every language.', 'sitepress' )
			);
		}

		return $rows;
	}

	public static function withValues( ?Settings $settings = null ) {
		$settings = $settings ? $settings : new Settings();

		return array_map(
			function ( array $row ) use ( $settings ) {
				$effective = $settings->effectiveFor( $row['members'][0] );

				return array_merge(
					$row,
					[
						'original'    => $effective['original'],
						'translation' => $effective['translation'],
					]
				);
			},
			self::all()
		);
	}

	private static function customPostTypes() {
		$out = [];

		foreach ( self::translatableDocuments() as $type => $object ) {
			$type = (string) $type;
			if ( in_array( Settings::postKey( $type ), self::COVERED, true )
				|| Settings::isForcedCascade( $type ) ) {
				continue;
			}
			$out[ $type ] = self::labelOf( $object, $type );
		}

		return $out;
	}

	private static function customTaxonomies() {
		$out = [];

		foreach ( self::translatableTaxonomies() as $taxonomy ) {
			$taxonomy = (string) $taxonomy;
			if ( in_array( Settings::termKey( $taxonomy ), self::COVERED, true ) ) {
				continue;
			}
			$out[ $taxonomy ] = self::taxonomyLabel( $taxonomy );
		}

		return $out;
	}

	private static function templateMembers() {
		$members = [];

		foreach ( Settings::FORCED_CASCADE_POST_TYPES as $type ) {
			if ( self::postTypeExists( $type ) ) {
				$members[] = Settings::postKey( $type );
			}
		}

		return $members;
	}

	private static function row( $id, $label, array $members, $locked = false, $note = '' ) {
		return [
			'id'      => $id,
			'label'   => $label,
			'members' => $members,
			'locked'  => (bool) $locked,
			'note'    => (string) $note,
		];
	}

	private static function translatableDocuments() {
		global $sitepress;

		if ( ! is_object( $sitepress ) || ! method_exists( $sitepress, 'get_translatable_documents' ) ) {
			return [];
		}

		$documents = $sitepress->get_translatable_documents();

		return is_array( $documents ) ? $documents : [];
	}

	private static function translatableTaxonomies() {
		global $sitepress;

		if ( ! is_object( $sitepress ) || ! method_exists( $sitepress, 'get_translatable_taxonomies' ) ) {
			return [];
		}

		$taxonomies = $sitepress->get_translatable_taxonomies();

		return is_array( $taxonomies ) ? $taxonomies : [];
	}

	private static function labelOf( $object, $fallback ) {
		if ( is_object( $object ) && isset( $object->labels->name ) && '' !== (string) $object->labels->name ) {
			return (string) $object->labels->name;
		}
		if ( is_object( $object ) && isset( $object->label ) && '' !== (string) $object->label ) {
			return (string) $object->label;
		}

		return $fallback;
	}

	private static function postTypeLabel( $type, $fallback ) {
		if ( ! function_exists( 'get_post_type_object' ) ) {
			return $fallback;
		}

		return self::labelOf( get_post_type_object( $type ), $fallback );
	}

	private static function taxonomyLabel( $taxonomy ) {
		if ( ! function_exists( 'get_taxonomy' ) ) {
			return $taxonomy;
		}

		return self::labelOf( get_taxonomy( $taxonomy ), $taxonomy );
	}

	private static function postTypeExists( $type ) {
		return function_exists( 'post_type_exists' ) && post_type_exists( $type );
	}
}
