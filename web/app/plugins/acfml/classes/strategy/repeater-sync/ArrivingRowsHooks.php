<?php

namespace ACFML\Repeater\Sync;

use ACFML\FieldGroup\Mode;
use ACFML\Helper\Fields;
use ACFML\Repeater\Shuffle\Post;

class ArrivingRowsHooks implements \IWPML_Action {

	const PRIORITY_AFTER_HOLD_BACK = \WPML_ACF_Repeater_Shuffle::PRIORITY_HOLD_BACK + 10;

	private $post;

	public function __construct( Post $post ) {
		$this->post = $post;
	}

	public function add_hooks() {
		add_filter( 'wpml_custom_fields_to_sync_on_post_save', [ $this, 'addKeysOfArrivingRows' ], self::PRIORITY_AFTER_HOLD_BACK, 2 );
	}

	public function addKeysOfArrivingRows( $metaKeys, $postId = 0 ) {
		if ( ! is_array( $metaKeys ) || ! $metaKeys || ! $this->post->isValidId( $postId ) || ! $this->post->isOriginal( $postId ) ) {
			return $metaKeys;
		}

		$translations = $this->post->getTranslations( $postId );
		if ( ! $translations ) {
			return $metaKeys;
		}

		$rowCounts = $this->rowCountsOfWrappers( (int) $postId, $metaKeys );

		if ( ! $rowCounts || Mode::LOCALIZATION === Mode::getForFieldableEntity( Mode::ENTITY_POST, $postId ) ) {
			return $metaKeys;
		}

		$missing = array_fill_keys( array_keys( $rowCounts ), [] );
		foreach ( $translations as $translation ) {
			$translationKeys = array_keys( (array) $this->post->getAllMeta( (int) $translation->element_id ) );

			foreach ( $rowCounts as $field => $rowCount ) {
				$missing[ $field ] += array_fill_keys( array_diff( range( 0, $rowCount - 1 ), self::rowIndexes( (string) $field, $translationKeys ) ), true );
			}
		}

		$missing = array_filter( $missing );
		if ( ! $missing ) {
			return $metaKeys;
		}

		$originalKeys = array_keys( (array) $this->post->getAllMeta( $postId ) );
		$added        = [];

		foreach ( $missing as $field => $rows ) {
			$pattern = \WPML_ACF_Repeater_Shuffle::rowKeyPattern( (string) $field );

			foreach ( $originalKeys as $key ) {
				if ( preg_match( $pattern, (string) $key, $matched ) && isset( $rows[ (int) $matched[2] ] ) ) {
					$added[] = (string) $key;
				}
			}
		}

		return $added ? array_values( array_unique( array_merge( $metaKeys, $added ) ) ) : $metaKeys;
	}

	private function rowCountsOfWrappers( int $postId, array $metaKeys ): array {
		$rowCounts = [];

		foreach ( $metaKeys as $metaKey ) {
			if ( ! is_string( $metaKey ) || '' === $metaKey || '_' === $metaKey[0] || isset( $rowCounts[ $metaKey ] ) ) {
				continue;
			}

			$rowCount = self::rowCount( $this->post->getOneMeta( $postId, $metaKey, true ) );
			$fieldKey = $rowCount ? $this->post->getOneMeta( $postId, '_' . $metaKey, true ) : '';

			if ( is_string( $fieldKey ) && acf_is_field_key( $fieldKey ) && Fields::isWrapper( acf_get_field( $fieldKey ) ) ) {
				$rowCounts[ $metaKey ] = $rowCount;
			}
		}

		return $rowCounts;
	}

	private static function rowCount( $value ): int {
		if ( is_array( $value ) ) {
			return count( $value );
		}

		return is_numeric( $value ) ? (int) $value : 0;
	}

	private static function rowIndexes( string $field, array $metaKeys ): array {
		$pattern = \WPML_ACF_Repeater_Shuffle::rowKeyPattern( $field );
		$indexes = [];

		foreach ( $metaKeys as $key ) {
			if ( preg_match( $pattern, (string) $key, $matched ) ) {
				$indexes[ (int) $matched[2] ] = true;
			}
		}

		return array_keys( $indexes );
	}
}
