<?php

namespace WPML\PB\ElementUid;

class FieldDecorator {

	private $nameMap;

	public function __construct( NameMap $nameMap ) {
		$this->nameMap = $nameMap;
	}

	public function addUidToFields( $fields, $job = null ) {
		if ( ! is_array( $fields ) || ! is_object( $job ) || empty( $job->original_doc_id ) ) {
			return $fields;
		}

		$stringIds = [];

		foreach ( $fields as $index => $field ) {
			$fieldType = isset( $field['field_type'] ) ? $field['field_type'] : '';

			if ( preg_match( '/^package-string-\d+-(\d+)$/', $fieldType, $matches ) ) {
				$stringIds[ $index ] = (int) $matches[1];
			}
		}

		if ( ! $stringIds ) {
			return $fields;
		}

		$originalPostId = (int) $job->original_doc_id;

		$map = (array) apply_filters( 'wpml_pb_string_uid_map', $this->nameMap->get( $originalPostId ), $originalPostId );

		if ( ! $map ) {
			return $fields;
		}

		$names = $this->getStringNames( array_values( array_unique( $stringIds ) ) );

		foreach ( $stringIds as $index => $stringId ) {
			if ( ! isset( $names[ $stringId ] ) || ! isset( $map[ $names[ $stringId ] ] ) ) {
				continue;
			}

			$uids = array_map( 'strval', (array) $map[ $names[ $stringId ] ] );
			$uids = array_values(
				array_filter(
					$uids,
					function ( $uid ) {
						return '' !== $uid;
					}
				)
			);

			if ( ! $uids ) {
				continue;
			}

			$fields[ $index ]['uid'] = $uids[0];

			if ( count( $uids ) > 1 ) {
				$fields[ $index ]['uids'] = implode( ' ', $uids );
			}
		}

		return $fields;
	}

	private function getStringNames( array $stringIds ) {
		global $wpdb;

		$placeholders = implode( ',', array_fill( 0, count( $stringIds ), '%d' ) );

		$rows = $wpdb->get_results(
			$wpdb->prepare( "SELECT id, name FROM {$wpdb->prefix}icl_strings WHERE id IN ({$placeholders})", $stringIds ),
			ARRAY_A
		);

		$names = [];

		foreach ( (array) $rows as $row ) {
			if ( isset( $row['id'], $row['name'] ) ) {
				$names[ (int) $row['id'] ] = (string) $row['name'];
			}
		}

		return $names;
	}
}
