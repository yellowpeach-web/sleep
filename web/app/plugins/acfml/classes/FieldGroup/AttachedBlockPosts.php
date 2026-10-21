<?php

namespace ACFML\FieldGroup;

use ACFML\Helper\Fields;
use WPML\FP\Fns;
use WPML\FP\Obj;

class AttachedBlockPosts {

	public static function blockNamesForFieldNames( array $fieldNames ) {
		if ( ! $fieldNames || ! function_exists( 'acf_get_field_groups' ) || ! function_exists( 'acf_get_fields' ) ) {
			return [];
		}

		$wanted     = array_flip( $fieldNames );
		$blockNames = [];

		foreach ( (array) acf_get_field_groups() as $fieldGroup ) {
			$blocks = self::blockNamesForGroup( $fieldGroup );

			if ( ! $blocks || ! self::definesAnyOf( $fieldGroup, $wanted ) ) {
				continue;
			}

			$blockNames = array_merge( $blockNames, $blocks );
		}

		return array_values( array_unique( $blockNames ) );
	}

	public static function blockNamesForGroup( $fieldGroup ) {
		$locations = Obj::propOr( [], 'location', (array) $fieldGroup );

		$blockNames = [];

		foreach ( (array) $locations as $ruleGroup ) {
			foreach ( (array) $ruleGroup as $rule ) {
				$isBlockRule = 'block' === Obj::propOr( '', 'param', (array) $rule )
					&& '==' === Obj::propOr( '', 'operator', (array) $rule );

				$blockName = (string) Obj::propOr( '', 'value', (array) $rule );

				if ( $isBlockRule && '' !== $blockName ) {
					$blockNames[] = $blockName;
				}
			}
		}

		return array_values( array_unique( $blockNames ) );
	}

	public static function idsForFieldNames( array $fieldNames ) {
		$blockNames = self::blockNamesForFieldNames( $fieldNames );

		if ( ! $blockNames ) {
			return [];
		}

		global $wpdb;

		$translations = $wpdb->prefix . 'icl_translations';

		$ids = $wpdb->get_col(
			"SELECT DISTINCT p.ID
			FROM {$wpdb->posts} p
			INNER JOIN {$translations} orig
				ON orig.element_id = p.ID
				AND orig.element_type = CONCAT('post_', p.post_type)
				AND orig.source_language_code IS NULL
			WHERE " . self::blockClause( $blockNames ) . '
				AND ' . self::publishedClause() . '
				AND ' . self::notInMetaClause( $fieldNames ) . "
				AND EXISTS (
					SELECT 1 FROM {$translations} tr
					WHERE tr.trid = orig.trid
						AND tr.source_language_code IS NOT NULL
				)
			ORDER BY p.ID ASC"
		);

		return array_map( 'intval', (array) $ids );
	}

	public static function idsWithin( array $fieldNames, array $postIds ) {
		$postIds = array_values( array_unique( array_filter( array_map( 'intval', $postIds ) ) ) );

		if ( ! $postIds ) {
			return [];
		}

		$blockNames = self::blockNamesForFieldNames( $fieldNames );

		if ( ! $blockNames ) {
			return [];
		}

		global $wpdb;

		$ids = $wpdb->get_col(
			"SELECT p.ID
			FROM {$wpdb->posts} p
			WHERE p.ID IN (" . implode( ',', $postIds ) . ')
				AND ' . self::blockClause( $blockNames ) . '
			ORDER BY p.ID ASC'
		);

		return array_map( 'intval', (array) $ids );
	}

	public static function countForGroup( $fieldGroupId ) {
		if ( ! function_exists( 'acf_get_field_group' ) || ! function_exists( 'acf_get_fields' ) ) {
			return 0;
		}

		$fieldGroup = acf_get_field_group( $fieldGroupId );
		$blockNames = is_array( $fieldGroup ) ? self::blockNamesForGroup( $fieldGroup ) : [];

		if ( ! $blockNames ) {
			return 0;
		}

		$fieldNames = wpml_collect( acf_get_fields( $fieldGroupId ) )
			->map( Obj::prop( 'name' ) )
			->filter()
			->toArray();

		global $wpdb;

		$translations = $wpdb->prefix . 'icl_translations';

		$count = (int) $wpdb->get_var(
			"SELECT COUNT(DISTINCT(p.ID))
			FROM {$wpdb->posts} p
			LEFT JOIN {$translations} t
				ON t.element_id = p.ID AND t.element_type = CONCAT('post_', p.post_type)
			WHERE " . self::blockClause( $blockNames ) . '
				AND ' . self::publishedClause() . '
				AND ' . self::notInMetaClause( $fieldNames ) . '
				AND ( t.translation_id IS NULL OR t.source_language_code IS NULL )'
		);

		return $count;
	}

	private static function definesAnyOf( $fieldGroup, array $wanted ) {
		$found = false;

		$check = function ( $field ) use ( $wanted, &$found ) {
			$name = Obj::propOr( '', 'name', (array) $field );

			if ( is_string( $name ) && '' !== $name && isset( $wanted[ $name ] ) ) {
				$found = true;
			}

			return $field;
		};

		Fields::iterate( (array) acf_get_fields( $fieldGroup ), $check, Fns::identity() );

		return $found;
	}

	private static function blockClause( array $blockNames ) {
		global $wpdb;

		$clauses = [];

		foreach ( $blockNames as $blockName ) {
			$clauses[] = $wpdb->prepare(
				'p.post_content LIKE %s',
				'%<!-- wp:' . $wpdb->esc_like( $blockName ) . ' %'
			);
		}

		return '( ' . implode( ' OR ', $clauses ) . ' )';
	}

	private static function publishedClause() {
		return "p.post_type != 'revision'
				AND p.post_status NOT IN ( 'auto-draft', 'inherit', 'trash' )";
	}

	private static function notInMetaClause( array $fieldNames ) {
		if ( ! $fieldNames ) {
			return '1 = 1';
		}

		global $wpdb;

		return "NOT EXISTS (
					SELECT 1 FROM {$wpdb->postmeta} pm
					WHERE pm.post_id = p.ID
						AND pm.meta_key IN (" . wpml_prepare_in( $fieldNames ) . ')
				)';
	}
}
