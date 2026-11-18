<?php

namespace WPML\TM\OwnedPackages;

use WPML\PB\Gutenberg\NativeEditorStringTranslations;

class PreviousTranslations {

	const BATCH_FIELD_PREFIX = 'batch-string-';

	private $stringTranslations;

	private $wpdb;

	public function __construct( ?NativeEditorStringTranslations $stringTranslations = null, $wpdb = null ) {
		$this->stringTranslations = $stringTranslations;
		$this->wpdb               = $wpdb;
	}

	public function markCompleteStringTranslationsFinished( array $previousTranslations, array $package, $language ) {
		$contents               = (array) ( $package['contents'] ?? [] );
		$unfinishedStringFields = $this->getUnfinishedStringFields( $contents )
			+ $this->getUnfinishedStandalonePackageFields( $package, $contents );
		if ( ! $unfinishedStringFields ) {
			return $previousTranslations;
		}

		$completeTranslations = $this->getCompleteStringTranslations( array_values( $unfinishedStringFields ), $language );
		foreach ( $unfinishedStringFields as $fieldName => $stringId ) {
			if ( isset( $completeTranslations[ $stringId ] ) ) {
				$translation = base64_encode( $completeTranslations[ $stringId ] );
				if (
					isset( $previousTranslations[ $fieldName ] ) &&
					$previousTranslations[ $fieldName ]->is_finished( $contents[ $fieldName ]['data'] ) &&
					$previousTranslations[ $fieldName ]->get_translation() === $translation
				) {
					continue;
				}
				$previousTranslations[ $fieldName ] = new \WPML_TM_Translated_Field(
					$contents[ $fieldName ]['data'],
					$translation,
					true
				);
			}
		}

		return $previousTranslations;
	}

	private function getUnfinishedStringFields( array $contents ) {
		$unfinishedStringFields = [];
		foreach ( $contents as $fieldName => $field ) {
			$original = (string) ( $field['data'] ?? '' );
			$wrapper  = new \WPML_TM_Page_Builders_Field_Wrapper( (string) $fieldName );
			if ( '' === $original || ! $wrapper->is_valid() ) {
				continue;
			}
			$unfinishedStringFields[ $fieldName ] = (int) $wrapper->get_string_id();
		}

		return $unfinishedStringFields;
	}

	private function getUnfinishedStandalonePackageFields( array $package, array $contents ) {
		$packageId = (int) ( $contents['original_id']['data'] ?? 0 );
		if ( OwnerJobService::PACKAGE_TYPE_EXTERNAL !== ( $package['type'] ?? null ) || ! $packageId ) {
			return [];
		}

		foreach ( array_keys( $contents ) as $fieldName ) {
			if ( 0 === strpos( (string) $fieldName, self::BATCH_FIELD_PREFIX ) ) {
				return [];
			}
		}

		$candidates = [];
		foreach ( $contents as $fieldName => $field ) {
			$original = (string) ( $field['data'] ?? '' );
			if ( 'original_id' === $fieldName || 1 !== (int) ( $field['translate'] ?? 0 ) || '' === $original ) {
				continue;
			}
			$candidates[ (string) $fieldName ] = true;
		}

		if ( ! $candidates ) {
			return [];
		}

		return array_intersect_key( $this->getPackageStringIds( $packageId ), $candidates );
	}

	private function getPackageStringIds( $packageId ) {
		$wpdb = $this->wpdb ?: $GLOBALS['wpdb'];

		$rows = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT id, name FROM {$wpdb->prefix}icl_strings WHERE string_package_id = %d",
				$packageId
			)
		);

		$stringIds = [];
		foreach ( (array) $rows as $row ) {
			$stringIds[ (string) $row->name ] = (int) $row->id;
		}

		return $stringIds;
	}

	private function getCompleteStringTranslations( array $stringIds, $language ) {
		$completeTranslations = [];
		foreach ( $this->getStringTranslations()->get( $stringIds, $language ) as $stringId => $translation ) {
			if ( OwnerJobService::TRANSLATION_COMPLETE === $translation['status'] && '' !== (string) $translation['value'] ) {
				$completeTranslations[ $stringId ] = (string) $translation['value'];
			}
		}

		return $completeTranslations;
	}

	private function getStringTranslations() {
		if ( null === $this->stringTranslations ) {
			$this->stringTranslations = new NativeEditorStringTranslations();
		}

		return $this->stringTranslations;
	}
}
