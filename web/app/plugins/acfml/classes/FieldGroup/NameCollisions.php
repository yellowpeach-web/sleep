<?php

namespace ACFML\FieldGroup;

use ACFML\Helper\Fields;
use WPML\FP\Obj;

class NameCollisions {

	private $fieldNamePatterns;

	public function __construct( FieldNamePatterns $fieldNamePatterns ) {
		$this->fieldNamePatterns = $fieldNamePatterns;
	}

	public function find( $fieldGroup ) {
		$names = $this->getTopLevelFieldNames( $fieldGroup );

		if ( ! $names ) {
			return [];
		}

		$collisions = $this->findInStoredGroups( $names, (int) Obj::propOr( 0, 'ID', $fieldGroup ) );

		return array_merge(
			$collisions,
			$this->findInNamePatterns( $names, (string) Obj::propOr( '', 'key', $fieldGroup ), $collisions )
		);
	}

	private function getTopLevelFieldNames( $fieldGroup ) {
		$names = [];

		foreach ( Fields::getFresh( $fieldGroup ) as $field ) {
			$name = Obj::propOr( '', 'name', $field );

			if ( is_string( $name ) && '' !== $name ) {
				$names[] = $name;
			}
		}

		return array_values( array_unique( $names ) );
	}

	private function findInStoredGroups( $names, $fieldGroupId ) {
		global $wpdb;

		$placeholders = implode( ', ', array_fill( 0, count( $names ), '%s' ) );

		$rows = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT f.post_excerpt AS field_name, g.ID AS group_id, g.post_name AS group_key, g.post_title AS group_title
				 FROM {$wpdb->posts} f
				 INNER JOIN {$wpdb->posts} g ON g.ID = f.post_parent
				 WHERE f.post_type = 'acf-field' AND f.post_status = 'publish'
				   AND g.post_type = 'acf-field-group' AND g.post_status = 'publish'
				   AND g.ID != %d
				   AND f.post_excerpt IN ($placeholders)
				 GROUP BY f.post_excerpt, g.ID, g.post_name, g.post_title",
				array_merge( [ $fieldGroupId ], $names )
			)
		);

		$collisions = [];

		foreach ( (array) $rows as $row ) {
			$collisions[] = [
				'field_name'  => (string) $row->field_name,
				'group_id'    => (int) $row->group_id,
				'group_key'   => (string) $row->group_key,
				'group_title' => (string) $row->group_title,
			];
		}

		return $collisions;
	}

	private function findInNamePatterns( $names, $fieldGroupKey, array $alreadyFound ) {
		$exactNamePatterns = [];

		foreach ( $this->fieldNamePatterns->getAllPatterns() as $groupKey => $patterns ) {
			if ( $groupKey === $fieldGroupKey ) {
				continue;
			}

			foreach ( (array) $patterns as $pattern ) {
				$exactNamePatterns[ $pattern ][ $groupKey ] = $groupKey;
			}
		}

		$seen = [];
		foreach ( $alreadyFound as $collision ) {
			$seen[ $collision['group_key'] . '|' . $collision['field_name'] ] = true;
		}

		$collisions = [];
		$missing    = [];

		foreach ( $names as $name ) {
			$pattern = preg_quote( $name, '/' );

			if ( ! isset( $exactNamePatterns[ $pattern ] ) ) {
				continue;
			}

			foreach ( $exactNamePatterns[ $pattern ] as $groupKey ) {
				if ( isset( $seen[ $groupKey . '|' . $name ] ) || isset( $missing[ $groupKey ] ) ) {
					continue;
				}

				$seen[ $groupKey . '|' . $name ] = true;

				$group = acf_get_field_group( $groupKey );

				if ( ! is_array( $group ) ) {
					$missing[ $groupKey ] = true;
					$this->fieldNamePatterns->removeGroup( $groupKey );

					continue;
				}

				$collisions[] = $this->describeGroup( $groupKey, $name, $group );
			}
		}

		return $collisions;
	}

	private function describeGroup( $groupKey, $fieldName, array $group ) {
		return [
			'field_name'  => $fieldName,
			'group_id'    => (int) Obj::propOr( 0, 'ID', $group ),
			'group_key'   => $groupKey,
			'group_title' => (string) Obj::propOr( $groupKey, 'title', $group ),
		];
	}
}
