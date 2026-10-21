<?php

namespace ACFML\FieldPreferences;

class FieldReferences {

	const QUERY_CHUNK = 500;

	public static function fieldKeysByMetaKey( $referenceMetaKeys, $elementType ) {
		global $wpdb;

		$table = 'term' === $elementType ? $wpdb->termmeta : $wpdb->postmeta;

		$fieldKeysByMetaKey = [];
		foreach ( array_chunk( $referenceMetaKeys, self::QUERY_CHUNK ) as $chunk ) {
			$placeholders = implode( ',', array_fill( 0, count( $chunk ), '%s' ) );
			$rows         = $wpdb->get_results(
				$wpdb->prepare( "SELECT DISTINCT meta_key, meta_value FROM {$table} WHERE meta_key IN ({$placeholders}) AND meta_value LIKE %s", array_merge( $chunk, [ $wpdb->esc_like( 'field_' ) . '%' ] ) )
			);
			foreach ( (array) $rows as $row ) {
				$fieldKeysByMetaKey[ $row->meta_key ][] = $row->meta_value;
			}
		}

		return $fieldKeysByMetaKey;
	}

	public static function pointsToAnExistingField( $fieldReference ) {
		foreach ( preg_split( '/_(?=field_)/', $fieldReference ) as $fieldKey ) {
			if ( acf_get_field( $fieldKey ) || self::isInTrash( $fieldKey ) ) {
				return true;
			}
		}

		return false;
	}

	private static function isInTrash( $fieldKey ) {
		return (bool) get_posts(
			[
				'post_type'        => 'acf-field',
				'post_status'      => 'trash',
				'name'             => $fieldKey . '__trashed',
				'fields'           => 'ids',
				'posts_per_page'   => 1,
				'suppress_filters' => true,
			]
		);
	}
}
